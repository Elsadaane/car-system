<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Ledger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * الخزائن والحسابات البنكية: الرصيد اللحظي، كشف الحساب، التحويل بين الخزائن.
 */
class FinancialAccountController extends Controller
{
    public const TYPES = ['cash' => 'خزنة كاش', 'bank' => 'حساب بنكي', 'wallet' => 'محفظة إلكترونية', 'other' => 'أخرى'];

    private const MESSAGES = ['required' => 'الحقل ده مطلوب.', 'numeric' => 'لازم يكون رقم.', 'min' => 'أقل من المسموح.', 'different' => 'اختار خزنتين مختلفتين.', 'exists' => 'الاختيار مش صحيح.'];

    public function index(): View
    {
        $balances = Ledger::balances();
        $accounts = DB::table('financial_accounts')->whereNull('financial_accounts.deleted_at')
            ->leftJoin('branches', 'branches.id', '=', 'financial_accounts.branch_id')
            ->select('financial_accounts.*', \App\Support\Lang::sel('branches.name_ar as branch_name'))
            ->orderByDesc('is_active')->orderBy('financial_accounts.id')->get()
            ->map(function ($a) use ($balances) {
                $a->total_in = $balances[$a->id]['in'] ?? 0;
                $a->total_out = $balances[$a->id]['out'] ?? 0;
                $a->balance = $balances[$a->id]['balance'] ?? (float) $a->opening_balance;
                $a->methods = DB::table('payment_methods')->where('financial_account_id', $a->id)->pluckLocal('name');

                return $a;
            });

        $today = today()->toDateString();
        $todayMove = Ledger::entries()->where('entry_date', $today)->selectRaw('coalesce(sum(amount_in),0) as i, coalesce(sum(amount_out),0) as o')->first();

        return view('admin.accounts.index', [
            'accounts'  => $accounts,
            'unlinked'  => $balances['unlinked'],
            'todayIn'   => (float) $todayMove->i,
            'todayOut'  => (float) $todayMove->o,
            'transfers' => DB::table('account_transfers')->whereNull('account_transfers.deleted_at')
                ->join('financial_accounts as f', 'f.id', '=', 'account_transfers.from_account_id')
                ->join('financial_accounts as t', 't.id', '=', 'account_transfers.to_account_id')
                ->leftJoin('users', 'users.id', '=', 'account_transfers.user_id')
                ->select('account_transfers.*', \App\Support\Lang::sel('f.name_ar as from_name'), \App\Support\Lang::sel('t.name_ar as to_name'), 'users.name as user_name')
                ->latest('transfer_date')->latest('account_transfers.id')->limit(10)->get(),
            'branches'  => DB::table('branches')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'methods'   => DB::table('payment_methods')->whereNull('financial_account_id')->get(['id', 'name_ar']),
        ]);
    }

    /** كشف حساب خزنة: رصيد أول المدة + الحركات + الرصيد الجاري */
    public function show(Request $request, int $account): View
    {
        $acc = DB::table('financial_accounts')->where('id', $account)->first();
        abort_unless($acc, 404);

        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();
        $kind = array_key_exists($request->query('kind'), Ledger::KINDS) ? $request->query('kind') : null;

        $before = Ledger::entries()->where('account_id', $acc->id)->where('entry_date', '<', $from)
            ->selectRaw('coalesce(sum(amount_in),0) - coalesce(sum(amount_out),0) as net')->value('net');
        $opening = Money::round((float) $acc->opening_balance + (float) $before);

        $rows = Ledger::entries()->where('account_id', $acc->id)
            ->whereBetween('entry_date', [$from, $to])
            ->when($kind, fn ($q, $v) => $q->where('kind', $v))
            ->orderBy('entry_date')->orderBy('source_id')->get();

        $running = $opening;
        foreach ($rows as $r) {
            $running = Money::round($running + (float) $r->amount_in - (float) $r->amount_out);
            $r->running = $running;
        }

        return view('admin.accounts.show', [
            'account' => $acc,
            'rows'    => $rows,
            'opening' => $opening,
            'closing' => $kind ? null : $running,
            'from'    => $from,
            'to'      => $to,
            'kind'    => $kind,
            'byKind'  => $rows->groupBy('kind')->map(fn ($g) => ['in' => $g->sum('amount_in'), 'out' => $g->sum('amount_out'), 'count' => $g->count()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::table('financial_accounts')->insert($this->data($request) + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تم إضافة الحساب');
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        DB::table('financial_accounts')->where('id', $account)->update($this->data($request) + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(int $account): RedirectResponse
    {
        if (Ledger::entries()->where('account_id', $account)->exists()) {
            return back()->with('error', 'الحساب عليه حركات. اقفله بدل المسح.');
        }

        DB::table('payment_methods')->where('financial_account_id', $account)->update(['financial_account_id' => null]);
        DB::table('financial_accounts')->where('id', $account)->update(['deleted_at' => now()]);

        return back()->with('status', 'تم المسح');
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_account_id' => ['required', Rule::exists('financial_accounts', 'id')->whereNull('deleted_at')],
            'to_account_id'   => ['required', 'different:from_account_id', Rule::exists('financial_accounts', 'id')->whereNull('deleted_at')],
            'amount'          => ['required', 'numeric', 'min:0.001'],
            'transfer_date'   => ['required', 'date', 'before_or_equal:today'],
            'notes'           => ['nullable', 'string', 'max:255'],
        ], self::MESSAGES);

        $balance = Ledger::balances()[$data['from_account_id']]['balance'] ?? 0;
        if (Money::round($data['amount']) > $balance + 0.0005) {
            throw ValidationException::withMessages(['amount' => 'رصيد الخزنة مايكفيش ('.Money::format($balance).').']);
        }

        DB::table('account_transfers')->insert([
            'code' => 'TRF-'.now()->format('ymd').'-'.Str::upper(Str::random(4)),
            'from_account_id' => $data['from_account_id'], 'to_account_id' => $data['to_account_id'],
            'amount' => Money::round($data['amount']), 'transfer_date' => $data['transfer_date'],
            'notes' => $data['notes'] ?? null, 'user_id' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'تم التحويل');
    }

    public function destroyTransfer(int $transfer): RedirectResponse
    {
        DB::table('account_transfers')->where('id', $transfer)->update(['deleted_at' => now()]);

        return back()->with('status', 'تم إلغاء التحويل');
    }

    public function linkMethods(Request $request): RedirectResponse
    {
        foreach ((array) $request->input('methods', []) as $methodId => $accountId) {
            DB::table('payment_methods')->where('id', (int) $methodId)->update(['financial_account_id' => $accountId ? (int) $accountId : null]);
        }

        return back()->with('status', 'تم ربط طرق الدفع بالخزائن');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name_ar'         => ['required', 'string', 'max:150'],
            'name_en'         => ['nullable', 'string', 'max:150'],
            'type'            => ['required', Rule::in(array_keys(self::TYPES))],
            'branch_id'       => ['nullable', Rule::exists('branches', 'id')],
            'bank_name'       => ['nullable', 'string', 'max:150'],
            'account_number'  => ['nullable', 'string', 'max:60'],
            'iban'            => ['nullable', 'string', 'max:60'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_date'    => ['nullable', 'date'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES);

        return array_merge($data, [
            'opening_balance' => Money::round($data['opening_balance'] ?? 0),
            'is_active'       => $request->boolean('is_active'),
        ]);
    }
}
