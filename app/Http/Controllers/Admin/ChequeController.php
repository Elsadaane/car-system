<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesContract;
use App\Support\Cheques;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** الشيكات المؤجلة: القايمة + تسجيلها على العقد + متابعة حالتها */
class ChequeController extends Controller
{
    public const TABS = [
        'due'       => 'مستحقة الإيداع (7 أيام)',
        'received'  => 'في الخزنة',
        'deposited' => 'في البنك',
        'bounced'   => 'الشيكات المرتجعة',
        'cleared'   => 'اتصرفت',
        'all'       => 'الكل',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'due';
        $q = trim((string) $request->query('q'));
        $today = today()->toDateString();

        $base = fn () => DB::table('cheques')->whereNull('cheques.deleted_at')
            ->join('sales_contracts', 'sales_contracts.id', '=', 'cheques.sales_contract_id')
            ->tap(fn ($w) => \App\Support\Access::scope($w, 'sales_contracts.branch_id'));

        $rows = $base()
            ->leftJoin('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->leftJoin('installments', 'installments.id', '=', 'cheques.installment_id')
            ->select('cheques.*', 'sales_contracts.contract_number', \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone',
                'installments.installment_sequence')
            ->when($tab === 'due', fn ($w) => $w->where('cheques.status', 'received')->where('cheques.due_date', '<=', today()->addDays(7)->toDateString()))
            ->when(! in_array($tab, ['due', 'all'], true), fn ($w) => $w->where('cheques.status', $tab))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('cheques.cheque_number', 'like', "%{$q}%")
                ->orWhere('sales_contracts.contract_number', 'like', "%{$q}%")->orWhere('customers.name_ar', 'like', "%{$q}%")
                ->orWhere('customers.phone', 'like', "%{$q}%")->orWhere('cheques.bank_name', 'like', "%{$q}%")))
            ->orderBy($tab === 'cleared' ? 'cheques.cleared_at' : 'cheques.due_date', $tab === 'cleared' ? 'desc' : 'asc')
            ->paginate(25)->withQueryString();

        $sum = fn ($status) => (float) $base()->where('cheques.status', $status)->sum('cheques.amount');

        return view('admin.cheques.index', [
            'rows'    => $rows,
            'tab'     => $tab,
            'q'       => $q,
            'totals'  => [
                'received'  => $sum('received'),
                'deposited' => $sum('deposited'),
                'bounced'   => $sum('bounced'),
                'due'       => (int) $base()->where('cheques.status', 'received')->where('cheques.due_date', '<=', today()->addDays(7)->toDateString())->count(),
                'overdue'   => (int) $base()->where('cheques.status', 'received')->where('cheques.due_date', '<', $today)->count(),
            ],
            'counts'  => $base()->whereIn('cheques.status', ['received', 'deposited', 'bounced'])->groupBy('cheques.status')
                ->selectRaw('cheques.status, count(*) n')->pluck('n', 'status'),
            'methods' => \App\Models\PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluckLocal('name', 'id'),
        ]);
    }

    /** تسجيل شيك أو أكتر على العقد (شيك لكل قسط لسه مش مدفوع) */
    public function store(Request $request, SalesContract $salesContract): RedirectResponse
    {
        $data = $request->validate([
            'bank_name'          => ['nullable', 'string', 'max:100'],
            'cheques'            => ['required', 'array', 'min:1', 'max:120'],
            'cheques.*.number'   => ['nullable', 'string', 'max:50'],
            'cheques.*.amount'   => ['nullable', 'numeric', 'min:0'],
            'cheques.*.due_date' => ['nullable', 'date'],
            'cheques.*.installment_id' => ['nullable', 'integer'],
        ], ['cheques.required' => 'ضيف شيك واحد على الأقل.']);

        $rows = collect($data['cheques'])->filter(fn ($c) => filled($c['number'] ?? null) && (float) ($c['amount'] ?? 0) > 0 && filled($c['due_date'] ?? null));
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['cheques' => 'اكتب رقم الشيك والمبلغ والتاريخ لشيك واحد على الأقل.']);
        }

        $open = (float) DB::table('cheques')->where('sales_contract_id', $salesContract->id)->whereNull('deleted_at')
            ->whereIn('status', ['received', 'deposited'])->sum('amount');
        $total = $rows->sum(fn ($c) => (float) $c['amount']);
        if ($total + $open > (float) $salesContract->remaining_amount + 0.0005) {
            throw ValidationException::withMessages(['cheques' => 'إجمالي الشيكات ('.Money::format($total + $open).') أكبر من المتبقي على العميل ('.Money::format($salesContract->remaining_amount).').']);
        }

        $valid = DB::table('installments')->where('sales_contract_id', $salesContract->id)->pluck('id')->all();
        $now = now();
        DB::table('cheques')->insert($rows->map(fn ($c) => [
            'sales_contract_id' => $salesContract->id,
            'installment_id'    => in_array((int) ($c['installment_id'] ?? 0), $valid, true) ? (int) $c['installment_id'] : null,
            'cheque_number'     => trim($c['number']),
            'bank_name'         => $data['bank_name'] ?? null,
            'amount'            => Money::round($c['amount']),
            'due_date'          => $c['due_date'],
            'status'            => 'received',
            'user_id'           => $request->user()->id,
            'created_at'        => $now,
            'updated_at'        => $now,
        ])->values()->all());

        return redirect()->to(route('admin.sales-contracts.show', $salesContract).'#cheques')
            ->with('status', 'اتسجل '.$rows->count().' شيك بإجمالي '.Money::format($total).'.');
    }

    public function action(Request $request, int $cheque): RedirectResponse
    {
        $data = $request->validate([
            'action'            => ['required', 'in:deposit,clear,bounce,return,cancel'],
            'date'              => ['nullable', 'date', 'before_or_equal:today'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ]);
        $row = DB::table('cheques')->where('id', $cheque)->whereNull('deleted_at')->first() ?? abort(404);
        $ids = \App\Support\Access::branchIds();
        abort_if($ids !== null && ! in_array((int) DB::table('sales_contracts')->where('id', $row->sales_contract_id)->value('branch_id'), $ids, true), 403);

        return back()->with('status', Cheques::apply($row, $data['action'], $data, $request->user()->id));
    }

    public function destroy(int $cheque): RedirectResponse
    {
        $row = DB::table('cheques')->where('id', $cheque)->whereNull('deleted_at')->first() ?? abort(404);
        if ($row->status === 'cleared') {
            return back()->with('error', 'الشيك ده اتصرف واتسجل دفعة — سجّله مرتجع بدل المسح.');
        }
        DB::table('cheques')->where('id', $cheque)->update(['deleted_at' => now()]);

        return back()->with('status', 'الشيك اتمسح.');
    }
}
