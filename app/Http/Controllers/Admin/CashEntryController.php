<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Company;
use App\Support\Ledger;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * سندات الصرف (مصروفات) والقبض (إيرادات أخرى غير بيع العربيات).
 * نفس الصفحة ونفس الكود — الفرق في $kind.
 *
 * الرصيد بيتحسب لحظياً من الدفتر، فتعديل المبلغ أو المسح بيظبط الخزنة لوحده.
 * المصروف ممكن يتربط بعربية (صيانة/تجهيز) ← بيدخل في حساب ربح العربية.
 */
class CashEntryController extends Controller
{
    public const LABELS = [
        'expense' => ['title' => 'المصروفات', 'single' => 'مصروف', 'voucher' => 'سند صرف', 'prefix' => 'EXP', 'tone' => 'rose', 'party' => 'المورد / الجهة', 'account' => 'اتصرف من'],
        'revenue' => ['title' => 'الإيرادات', 'single' => 'إيراد', 'voucher' => 'سند قبض', 'prefix' => 'REV', 'tone' => 'emerald', 'party' => 'العميل / الجهة', 'account' => 'اتقبض في'],
    ];

    public function index(Request $request, string $kind): View
    {
        $f = [
            'q'        => trim((string) $request->query('q')),
            'branch'   => $request->integer('branch') ?: null,
            'category' => $request->integer('category') ?: null,
            'account'  => $request->integer('account') ?: null,
            'from'     => $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString(),
            'to'       => $request->date('to')?->toDateString() ?? today()->toDateString(),
        ];

        $query = DB::table('cash_entries')->where('cash_entries.kind', $kind)->whereNull('cash_entries.deleted_at')
            ->whereBetween('cash_entries.entry_date', [$f['from'], $f['to']])
            ->when($f['q'] !== '', fn ($w) => $w->where(fn ($s) => $s->where('cash_entries.details', 'like', "%{$f['q']}%")
                ->orWhere('cash_entries.code', 'like', "%{$f['q']}%")->orWhere('cash_entries.invoice_no', 'like', "%{$f['q']}%")))
            ->when($f['branch'], fn ($w, $v) => $w->where('cash_entries.branch_id', $v))
            ->when($f['category'], fn ($w, $v) => $w->where(fn ($s) => $s->where('cash_entries.category_id', $v)->orWhere('cash_entries.sub_category_id', $v)))
            ->when($f['account'], fn ($w, $v) => $w->where('cash_entries.financial_account_id', $v));

        $entries = (clone $query)
            ->leftJoin('branches', 'branches.id', '=', 'cash_entries.branch_id')
            ->leftJoin('finance_categories as c', 'c.id', '=', 'cash_entries.category_id')
            ->leftJoin('finance_categories as sc', 'sc.id', '=', 'cash_entries.sub_category_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'cash_entries.supplier_id')
            ->leftJoin('customers', 'customers.id', '=', 'cash_entries.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'cash_entries.vehicle_id')
            ->leftJoin('financial_accounts as fa', 'fa.id', '=', 'cash_entries.financial_account_id')
            ->leftJoin('users', 'users.id', '=', 'cash_entries.user_id')
            ->select('cash_entries.*', \App\Support\Lang::sel('branches.name_ar as branch_name'), \App\Support\Lang::sel('c.name_ar as category_name'), \App\Support\Lang::sel('sc.name_ar as sub_category_name'),
                \App\Support\Lang::sel('suppliers.name_ar as supplier_name'), \App\Support\Lang::sel('customers.name_ar as customer_name'), 'vehicles.vehicle_code', \App\Support\Lang::sel('fa.name_ar as account_name'), 'users.name as user_name')
            ->latest('cash_entries.entry_date')->latest('cash_entries.id')
            ->paginate(25)->withQueryString();

        $byCategory = (clone $query)->leftJoin('finance_categories as c', 'c.id', '=', 'cash_entries.category_id')
            ->groupBy('c.name_ar', 'c.name_en')->select(\App\Support\Lang::sel('c.name_ar as name'), DB::raw('sum(total_amount) as total'), DB::raw('count(*) as cnt'))
            ->orderByDesc('total')->get();

        // مقارنة بنفس المدة اللي قبلها
        $days = max(1, (int) \Illuminate\Support\Carbon::parse($f['from'])->diffInDays(\Illuminate\Support\Carbon::parse($f['to'])) + 1);
        $prevTo = \Illuminate\Support\Carbon::parse($f['from'])->subDay();
        $prev = (float) DB::table('cash_entries')->where('kind', $kind)->whereNull('deleted_at')
            ->whereBetween('entry_date', [$prevTo->copy()->subDays($days - 1)->toDateString(), $prevTo->toDateString()])->sum('total_amount');

        $categories = DB::table('finance_categories')->where('kind', $kind)->where('is_active', true)->orderBy('name_ar')->get(['id', 'parent_id', 'name_ar']);

        return view('admin.cash-entries.index', [
            'kind'       => $kind,
            'L'          => self::LABELS[$kind],
            'entries'    => $entries,
            'total'      => (float) $byCategory->sum('total'),
            'count'      => (int) $byCategory->sum('cnt'),
            'prev'       => $prev,
            'byCategory' => $byCategory,
            'filters'    => $f,
            'mainCats'   => $categories->whereNull('parent_id')->pluckLocal('name', 'id'),
            'subCats'    => $categories->whereNotNull('parent_id')->values(),
            'branches'   => DB::table('branches')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'accounts'   => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->pluckLocal('name', 'id'),
            'balances'   => Ledger::balances(),
            'suppliers'  => DB::table('suppliers')->whereNull('deleted_at')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'customers'  => $kind === 'revenue' ? DB::table('customers')->whereNull('deleted_at')->orderBy('name_ar')->pluckLocal('name', 'id') : collect(),
            'vehicles'   => $kind === 'expense' ? DB::table('vehicles')->whereNull('vehicles.deleted_at')->whereNotIn('vehicles.status', ['returned'])
                ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
                ->selectRaw("vehicles.id, concat(vehicles.vehicle_code, ' — ', coalesce(car_brands.name_ar,''), ' ', coalesce(car_models.name_ar,''), ' ', vehicles.manufacturing_year) as label")
                ->orderByDesc('vehicles.id')->pluck('label', 'id') : collect(),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $data = $this->validated($request, $kind);
        $data['code'] = self::LABELS[$kind]['prefix'].'-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        $data['kind'] = $kind;
        $data['user_id'] = $request->user()->id;
        if ($request->hasFile('attachment')) {
            $data['attachment'] = Media::store($request->file('attachment'), 'cash-entries');
        }
        $id = DB::table('cash_entries')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تم تسجيل '.self::LABELS[$kind]['voucher'].' '.$data['code'])
            ->with('receipt_url', route('admin.cash-entries.receipt', $id))
            ->with('warning', $this->balanceWarning($kind, $data['financial_account_id'] ?? null));
    }

    public function update(Request $request, int $entry): RedirectResponse
    {
        $row = DB::table('cash_entries')->where('id', $entry)->whereNull('deleted_at')->first();
        abort_unless($row, 404);
        if (! empty($row->shared_expense_id)) {
            return back()->with('error', 'السند ده جزء من مصروف مشترك — عدّله من صفحة المصاريف المشتركة.');
        }

        $data = $this->validated($request, $row->kind);
        if ($request->hasFile('attachment')) {
            Media::delete($row->attachment);
            $data['attachment'] = Media::store($request->file('attachment'), 'cash-entries');
        }
        DB::table('cash_entries')->where('id', $entry)->update($data + ['updated_at' => now()]);

        return back()->with('status', 'تم حفظ التعديلات — رصيد الخزنة اتظبط لوحده')
            ->with('warning', $this->balanceWarning($row->kind, $data['financial_account_id'] ?? null));
    }

    public function destroy(int $entry): RedirectResponse
    {
        if (DB::table('cash_entries')->where('id', $entry)->value('shared_expense_id')) {
            return back()->with('error', 'السند ده جزء من مصروف مشترك — امسحه من صفحة المصاريف المشتركة.');
        }
        DB::table('cash_entries')->where('id', $entry)->update(['deleted_at' => now()]);

        return back()->with('status', 'تم إلغاء السند والمبلغ رجع للخزنة');
    }

    public function receipt(int $entry): View
    {
        $e = DB::table('cash_entries')->where('cash_entries.id', $entry)
            ->leftJoin('branches', 'branches.id', '=', 'cash_entries.branch_id')
            ->leftJoin('finance_categories as c', 'c.id', '=', 'cash_entries.category_id')
            ->leftJoin('finance_categories as sc', 'sc.id', '=', 'cash_entries.sub_category_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'cash_entries.supplier_id')
            ->leftJoin('customers', 'customers.id', '=', 'cash_entries.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'cash_entries.vehicle_id')
            ->leftJoin('financial_accounts as fa', 'fa.id', '=', 'cash_entries.financial_account_id')
            ->leftJoin('users', 'users.id', '=', 'cash_entries.user_id')
            ->select('cash_entries.*', \App\Support\Lang::sel('branches.name_ar as branch_name'), \App\Support\Lang::sel('c.name_ar as category_name'), \App\Support\Lang::sel('sc.name_ar as sub_category_name'),
                \App\Support\Lang::sel('suppliers.name_ar as supplier_name'), \App\Support\Lang::sel('customers.name_ar as customer_name'), 'vehicles.vehicle_code', \App\Support\Lang::sel('fa.name_ar as account_name'), 'users.name as user_name')
            ->first();
        abort_unless($e, 404);

        return view('admin.cash-entries.receipt', ['e' => $e, 'L' => self::LABELS[$e->kind], 'company' => Company::info()]);
    }

    private function validated(Request $request, string $kind): array
    {
        $data = $request->validate([
            'branch_id'       => ['nullable', Rule::exists('branches', 'id')],
            'category_id'     => ['required', Rule::exists('finance_categories', 'id')->where('kind', $kind)->whereNull('parent_id')],
            'sub_category_id' => ['nullable', Rule::exists('finance_categories', 'id')->where('kind', $kind)->where('parent_id', $request->input('category_id'))],
            'supplier_id'     => ['nullable', Rule::exists('suppliers', 'id')],
            'customer_id'     => ['nullable', Rule::exists('customers', 'id')],
            'vehicle_id'      => ['nullable', Rule::exists('vehicles', 'id')],
            'invoice_no'      => ['nullable', 'string', 'max:60'],
            'amount'          => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'tax_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'entry_date'      => ['required', 'date', 'before_or_equal:today'],
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')->whereNull('deleted_at')],
            'check_number'    => ['nullable', 'string', 'max:60'],
            'details'         => ['required', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'attachment'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'required' => 'الحقل ده مطلوب.', 'exists' => 'الاختيار مش صحيح.', 'numeric' => 'لازم يكون رقم.', 'min' => 'أقل من المسموح.',
            'before_or_equal' => 'مينفعش تاريخ في المستقبل.', 'mimes' => 'المرفق لازم صورة أو PDF.', 'attachment.max' => 'المرفق أكبر من 5 ميجا.',
        ]);

        $amount = Money::round($data['amount']);
        $tax = Money::round($amount * (float) ($data['tax_percent'] ?? 0) / 100);
        unset($data['attachment']);

        return array_merge($data, [
            'amount'       => $amount,
            'tax_percent'  => (float) ($data['tax_percent'] ?? 0),
            'tax_amount'   => $tax,
            'total_amount' => Money::round($amount + $tax),
            'supplier_id'  => $kind === 'expense' ? ($data['supplier_id'] ?? null) : null,
            'vehicle_id'   => $kind === 'expense' ? ($data['vehicle_id'] ?? null) : null,
            'customer_id'  => $kind === 'revenue' ? ($data['customer_id'] ?? null) : null,
        ]);
    }

    private function balanceWarning(string $kind, ?int $account): ?string
    {
        if ($kind !== 'expense' || ! $account) {
            return null;
        }
        $bal = Ledger::balances()[$account]['balance'] ?? 0;

        return $bal < 0 ? 'تنبيه: رصيد الخزنة بقى بالسالب ('.Money::format($bal).'). راجع الرصيد الافتتاحي أو اعمل تحويل.' : null;
    }
}
