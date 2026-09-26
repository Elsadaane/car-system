<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesContract;
use App\Support\ContractStatus;
use App\Support\Ledger;
use App\Support\Money;
use App\Support\SalesStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * التقارير. كلها بتاخد نفس الفلتر: من / إلى / الفرع.
 *
 * ربح العربية = سعر البيع بدون ضريبة − تكلفتها − مصروفاتها (صيانة/تجهيز)
 *   تكلفتها = سعر الشراء، ولو عربية تفويض = المستحق لصاحبها
 * صافي الربح = مجمل ربح العربيات + الإيرادات الأخرى − المصروفات العامة − إهلاك الأصول
 */
class ReportController extends Controller
{
    // =========================================================
    //  التقرير الإجمالي
    // =========================================================
    public function overview(Request $request): View
    {
        $p = $this->period($request);
        $sold = $this->sold($p);
        $pnl = $this->pnlData($p, $sold);

        $collected = $this->collected($p);
        $vehicles = DB::table('vehicles')->whereNull('deleted_at')->when($p['branch'], fn ($q, $v) => $q->where('branch_id', $v));

        $drives = DB::table('test_drives')->join('vehicles', 'vehicles.id', '=', 'test_drives.vehicle_id')
            ->whereBetween(DB::raw('date(test_drives.start_time)'), [$p['from'], $p['to']])
            ->when($p['branch'], fn ($q, $v) => $q->where('vehicles.branch_id', $v));
        $driveCustomers = (clone $drives)->distinct()->pluck('test_drives.customer_id');
        $converted = $driveCustomers->isEmpty() ? 0 : DB::table('sales_contracts')->whereIn('customer_id', $driveCustomers)
            ->where('contract_date', '>=', $p['from'])->whereNull('deleted_at')->distinct()->count('customer_id');

        $today = today()->toDateString();
        $recv = $this->contracts()->when($p['branch'], fn ($q, $v) => $q->where('branch_id', $v));

        return view('admin.reports.overview', $p + [
            'sold'      => $sold,
            'pnl'       => $pnl,
            'prev'      => $this->pnlData($this->previous($p)),
            'collected' => $collected,
            'kpi'       => [
                'vehicles_added' => (clone $vehicles)->whereBetween(DB::raw('date(created_at)'), [$p['from'], $p['to']])->count(),
                'available'      => (clone $vehicles)->whereIn('status', ['available', 'reserved'])->count(),
                'stock_value'    => (float) (clone $vehicles)->whereIn('status', ['available', 'reserved', 'maintenance'])->sum('total_cost'),
                'drives'         => (clone $drives)->count(),
                'drive_customers'=> $driveCustomers->count(),
                'converted'      => $converted,
                'receivables'    => (float) (clone $recv)->sum('remaining_amount'),
                'overdue'        => (float) DB::table('installments')->join('sales_contracts', 'sales_contracts.id', '=', 'installments.sales_contract_id')
                    ->whereNull('installments.deleted_at')->where('installments.is_paid', false)->where('installments.due_date', '<', $today)
                    ->when($p['branch'], fn ($q, $v) => $q->where('sales_contracts.branch_id', $v))
                    ->sum(DB::raw('installments.amount - installments.paid_amount')),
                'dealer_due'     => (float) DB::table('dealer_contracts')->whereNull('deleted_at')->where('status_id', ContractStatus::id('active'))->sum('remaining_amount'),
                'cancellations'  => DB::table('cancellation_requests')->whereNull('deleted_at')->whereBetween('cancellation_date', [$p['from'], $p['to']])->count(),
                'cash'           => collect(Ledger::balances())->except('unlinked')->sum('balance'),
            ],
            'topBrands'  => $sold->groupBy('brand_name')->map(fn ($g, $k) => ['name' => $k ?: '—', 'count' => $g->count(), 'total' => $g->sum('total_amount'), 'profit' => $g->sum('profit')])->sortByDesc('count')->take(5)->values(),
            'topSellers' => $sold->groupBy('user_name')->map(fn ($g, $k) => ['name' => $k ?: '—', 'count' => $g->count(), 'total' => $g->sum('total_amount'), 'profit' => $g->sum('profit')])->sortByDesc('total')->take(5)->values(),
            'finance'    => DB::table('finance_requests')->whereNull('finance_requests.deleted_at')
                ->whereBetween('request_date', [$p['from'], $p['to']])
                ->join('finance_companies', 'finance_companies.id', '=', 'finance_requests.finance_company_id')
                ->groupBy('finance_companies.name_ar', 'finance_companies.name_en')
                ->select(\App\Support\Lang::sel('finance_companies.name_ar as name'), DB::raw('count(*) as cnt'),
                    DB::raw("sum(case when status in ('approved','paid_off') then 1 else 0 end) as ok"),
                    DB::raw("coalesce(sum(case when status in ('approved','paid_off') then approved_amount end), 0) as amount"))
                ->orderByDesc('cnt')->get(),
        ]);
    }

    // =========================================================
    //  منافسة الفروع + لوحة شرف الموظفين
    // =========================================================
    public function branches(Request $request): View
    {
        $p = $this->period($request);
        $p['branch'] = null;
        $sold = $this->sold($p);
        $prevSold = $this->sold($this->previous($p));
        $monthSold = $this->sold(['from' => now()->startOfMonth()->toDateString(), 'to' => today()->toDateString(), 'branch' => null]);

        $expenses = DB::table('cash_entries')->whereNull('deleted_at')->where('kind', 'expense')->whereNull('vehicle_id')
            ->whereBetween('entry_date', [$p['from'], $p['to']])->groupBy('branch_id')->select('branch_id', DB::raw('sum(total_amount) as s'))->pluck('s', 'branch_id');
        $salaries = DB::table('hr_payrolls')->join('hr_payroll_runs as r', 'r.id', '=', 'hr_payrolls.payroll_run_id')->where('r.status', '!=', 'draft')
            ->whereRaw("str_to_date(concat(r.year, '-', r.month, '-01'), '%Y-%m-%d') between ? and ?", [Carbon::parse($p['from'])->startOfMonth()->toDateString(), $p['to']])
            ->groupBy('hr_payrolls.branch_id')->select('hr_payrolls.branch_id', DB::raw('sum(hr_payrolls.gross - hr_payrolls.absence_deduction - hr_payrolls.fixed_deductions - hr_payrolls.adjustment_deductions - hr_payrolls.other_deductions) as s'))
            ->pluck('s', 'branch_id');
        $collected = $this->collected($p, true);
        $drives = DB::table('test_drives')->join('vehicles', 'vehicles.id', '=', 'test_drives.vehicle_id')
            ->whereBetween(DB::raw('date(test_drives.start_time)'), [$p['from'], $p['to']])
            ->groupBy('vehicles.branch_id')->select('vehicles.branch_id', DB::raw('count(*) as c'))->pluck('c', 'branch_id');
        $stock = DB::table('vehicles')->whereNull('deleted_at')->whereIn('status', ['available', 'reserved'])
            ->groupBy('branch_id')->select('branch_id', DB::raw('count(*) as c'), DB::raw('sum(total_cost) as v'))->get()->keyBy('branch_id');

        $rows = DB::table('branches')->orderBy('id')->get()->map(function ($b) use ($sold, $prevSold, $monthSold, $expenses, $salaries, $collected, $drives, $stock) {
            $s = $sold->where('branch_id', $b->id);
            $total = (float) $s->sum('total_amount');
            $prev = (float) $prevSold->where('branch_id', $b->id)->sum('total_amount');
            $month = (float) $monthSold->where('branch_id', $b->id)->sum('total_amount');
            $exp = (float) ($expenses[$b->id] ?? 0) + (float) ($salaries[$b->id] ?? 0);
            $d = (int) ($drives[$b->id] ?? 0);

            return (object) [
                'id' => $b->id, 'name' => $b->name_ar, 'target' => (float) ($b->sales_target ?? 0),
                'count' => $s->count(), 'total' => $total, 'profit' => (float) $s->sum('profit') - $exp,
                'gross' => (float) $s->sum('profit'), 'expenses' => $exp,
                'collected' => (float) ($collected[$b->id] ?? 0),
                'avg' => $s->count() ? $total / $s->count() : 0,
                'days' => $s->count() ? round($s->avg('days_to_sell')) : null,
                'drives' => $d, 'conversion' => $d ? round($s->count() / $d * 100) : null,
                'growth' => $prev > 0 ? round(($total - $prev) / $prev * 100) : null,
                'month' => $month,
                'stock' => (int) ($stock[$b->id]->c ?? 0), 'stock_value' => (float) ($stock[$b->id]->v ?? 0),
            ];
        })->sortByDesc('total')->values();

        // نقاط المنافسة: مبيعات 40% + ربح 30% + تحصيل 20% + تحويل 10% (نسبة من الأعلى)
        $max = fn ($k) => max(1, (float) $rows->max($k));
        $rows = $rows->map(function ($r) use ($max) {
            $r->score = (int) round(40 * $r->total / $max('total') + 30 * max(0, $r->profit) / $max('profit') + 20 * $r->collected / $max('collected') + 10 * (float) $r->conversion / $max('conversion'));

            return $r;
        })->sortByDesc('score')->values();

        $sellers = $sold->groupBy('user_id')->map(fn ($g) => (object) [
            'name' => $g->first()->user_name ?? '—', 'branch' => $g->first()->branch_name,
            'count' => $g->count(), 'total' => $g->sum('total_amount'), 'profit' => $g->sum('profit'),
        ])->sortByDesc('total')->values();

        return view('admin.reports.branches', $p + ['rows' => $rows, 'sellers' => $sellers]);
    }

    public function saveTargets(Request $request): RedirectResponse
    {
        foreach ((array) $request->input('targets', []) as $id => $target) {
            DB::table('branches')->where('id', (int) $id)->update(['sales_target' => $target === null || $target === '' ? null : Money::round($target)]);
        }

        return back()->with('status', 'تم حفظ التارجت الشهري للفروع');
    }

    // =========================================================
    //  المبيعات
    // =========================================================
    public function sales(Request $request): View
    {
        $p = $this->period($request);
        $sold = $this->sold($p);

        // آخر 12 شهر
        $start = now()->startOfMonth()->subMonths(11);
        $trend = $this->sold(['from' => $start->toDateString(), 'to' => today()->toDateString(), 'branch' => $p['branch']])
            ->groupBy(fn ($r) => substr($r->contract_date, 0, 7));
        $months = collect(range(0, 11))->map(function ($i) use ($start, $trend) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $g = $trend[$key] ?? collect();

            return ['key' => $key, 'label' => $start->copy()->addMonths($i)->translatedFormat('M y'), 'count' => $g->count(), 'total' => (float) $g->sum('total_amount'), 'profit' => (float) $g->sum('profit')];
        });

        $group = fn (string $k) => $sold->groupBy($k)->map(fn ($g, $key) => ['name' => $key ?: '—', 'count' => $g->count(), 'total' => $g->sum('total_amount'), 'profit' => $g->sum('profit')])->sortByDesc('total')->values();

        return view('admin.reports.sales', $p + [
            'sold'    => $sold,
            'months'  => $months,
            'brands'  => $group('brand_name'),
            'types'   => $group('payment_label'),
            'sellers' => $group('user_name'),
            'years'   => $group('manufacturing_year'),
        ]);
    }

    // =========================================================
    //  ربح كل عربية
    // =========================================================
    public function profits(Request $request): View
    {
        $p = $this->period($request);
        $sort = in_array($request->query('sort'), ['profit', 'margin', 'days', 'date'], true) ? $request->query('sort') : 'date';
        $sold = $this->sold($p);
        $sorted = match ($sort) {
            'profit' => $sold->sortByDesc('profit'),
            'margin' => $sold->sortByDesc('margin'),
            'days'   => $sold->sortBy('days_to_sell'),
            default  => $sold->sortByDesc('contract_date'),
        };

        return view('admin.reports.profits', $p + ['sold' => $sorted->values(), 'sort' => $sort]);
    }

    // =========================================================
    //  المخزون وأعماره (العربيات الراكدة)
    // =========================================================
    public function inventory(Request $request): View
    {
        $p = $this->period($request);
        $rows = DB::table('vehicles')->whereNull('vehicles.deleted_at')->whereIn('vehicles.status', ['available', 'reserved', 'maintenance'])
            ->when($p['branch'], fn ($q, $v) => $q->where('vehicles.branch_id', $v))
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('branches', 'branches.id', '=', 'vehicles.branch_id')
            ->select('vehicles.id', 'vehicles.vehicle_code', 'vehicles.manufacturing_year', 'vehicles.status', 'vehicles.total_cost', 'vehicles.selling_price',
                'vehicles.created_at', 'vehicles.is_published', 'vehicles.supplier_id', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'), \App\Support\Lang::sel('branches.name_ar as branch_name'),
                DB::raw('(select count(*) from test_drives td where td.vehicle_id = vehicles.id) as drives'),
                DB::raw("(select coalesce(sum(total_amount),0) from cash_entries ce where ce.vehicle_id = vehicles.id and ce.kind = 'expense' and ce.deleted_at is null) as extra_cost"),
                DB::raw('(select count(*) from dealer_contracts dc where dc.vehicle_id = vehicles.id and dc.deleted_at is null) as is_dealer'))
            ->get()->map(function ($v) {
                $v->age = (int) Carbon::parse($v->created_at)->diffInDays(today());
                $v->cost = (float) $v->total_cost + (float) $v->extra_cost;
                $v->expected = $v->selling_price ? (float) $v->selling_price - $v->cost : null;

                return $v;
            })->sortByDesc('age')->values();

        $buckets = collect(['0-30' => [0, 30], '31-60' => [31, 60], '61-90' => [61, 90], '+90' => [91, 99999]])
            ->map(fn ($r) => ['count' => $rows->whereBetween('age', $r)->count(), 'value' => $rows->whereBetween('age', $r)->sum('cost')]);

        return view('admin.reports.inventory', $p + ['rows' => $rows, 'buckets' => $buckets]);
    }

    // =========================================================
    //  المديونيات (أعمار الديون)
    // =========================================================
    public function receivables(Request $request): View
    {
        $p = $this->period($request);
        $today = today();

        $inst = DB::table('installments')->join('sales_contracts', 'sales_contracts.id', '=', 'installments.sales_contract_id')
            ->whereNull('installments.deleted_at')->whereNull('sales_contracts.deleted_at')
            ->where('installments.is_paid', false)->where('installments.status', '!=', 'cancelled')
            ->when($p['branch'], fn ($q, $v) => $q->where('sales_contracts.branch_id', $v))
            ->select('installments.customer_id', 'installments.due_date', DB::raw('installments.amount - installments.paid_amount as left_amount'))
            ->get();

        $bucket = function ($due) use ($today) {
            $d = Carbon::parse($due);
            if ($d->gte($today)) {
                return 'current';
            }
            $late = (int) $d->diffInDays($today);

            return $late <= 30 ? '1-30' : ($late <= 60 ? '31-60' : ($late <= 90 ? '61-90' : '+90'));
        };
        $aging = collect(['current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '+90' => 0]);
        foreach ($inst as $i) {
            $aging[$bucket($i->due_date)] += (float) $i->left_amount;
        }

        $customers = $this->contracts()->when($p['branch'], fn ($q, $v) => $q->where('sales_contracts.branch_id', $v))
            ->where('sales_contracts.remaining_amount', '>', 0)
            ->join('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->groupBy('customers.id', 'customers.name_ar', 'customers.name_en', 'customers.phone')
            ->select('customers.id', 'customers.name_ar', 'customers.name_en', 'customers.phone', DB::raw('count(*) as contracts'), DB::raw('sum(sales_contracts.remaining_amount) as remaining'))
            ->orderByDesc('remaining')->get()
            ->map(function ($c) use ($inst, $today) {
                $mine = $inst->where('customer_id', $c->id);
                $late = $mine->filter(fn ($i) => Carbon::parse($i->due_date)->lt($today));
                $c->overdue = (float) $late->sum('left_amount');
                $c->overdue_count = $late->count();
                $c->oldest = $late->min('due_date');
                $c->next = $mine->filter(fn ($i) => Carbon::parse($i->due_date)->gte($today))->min('due_date');

                return $c;
            });

        return view('admin.reports.receivables', $p + ['aging' => $aging, 'customers' => $customers]);
    }

    // =========================================================
    //  قائمة الأرباح والخسائر
    // =========================================================
    public function pnl(Request $request): View
    {
        $p = $this->period($request);
        $prevP = $this->previous($p);

        return view('admin.reports.pnl', $p + [
            'cur'  => $this->pnlData($p),
            'prev' => $this->pnlData($prevP),
            'prevFrom' => $prevP['from'], 'prevTo' => $prevP['to'],
        ]);
    }

    // =========================================================
    //  أدوات الحساب
    // =========================================================
    private function period(Request $request): array
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from, 'to' => $to,
            'branch' => $request->integer('branch') ?: null,
            'branchesList' => DB::table('branches')->orderBy('name_ar')->pluckLocal('name', 'id'),
        ];
    }

    private function previous(array $p): array
    {
        $from = Carbon::parse($p['from']);
        $days = (int) $from->diffInDays(Carbon::parse($p['to'])) + 1;

        return ['from' => $from->copy()->subDays($days)->toDateString(), 'to' => $from->copy()->subDay()->toDateString(), 'branch' => $p['branch'] ?? null];
    }

    private function contracts()
    {
        return SalesStats::contracts();
    }

    private function sold(array $p): Collection
    {
        return SalesStats::sold($p);
    }

    /** المحصّل من العملاء في المدة (إجمالي، أو لكل فرع) */
    private function collected(array $p, bool $byBranch = false)
    {
        $q = DB::table('payments')->where('payments.payable_type', (new SalesContract)->getMorphClass())
            ->whereNull('payments.deleted_at')->where('payments.status', 'completed')
            ->whereBetween('payments.payment_date', [$p['from'], $p['to']])
            ->join('sales_contracts', 'sales_contracts.id', '=', 'payments.payable_id')
            ->when($p['branch'] ?? null, fn ($w, $v) => $w->where('sales_contracts.branch_id', $v));

        return $byBranch
            ? $q->groupBy('sales_contracts.branch_id')->select('sales_contracts.branch_id', DB::raw('sum(payments.amount) as s'))->pluck('s', 'branch_id')
            : (float) $q->sum('payments.amount');
    }

    /** أرقام قائمة الدخل لمدة */
    public function pnlData(array $p, ?Collection $sold = null): array
    {
        $sold ??= $this->sold($p);

        $entries = fn (string $kind) => DB::table('cash_entries')->whereNull('cash_entries.deleted_at')->where('cash_entries.kind', $kind)
            ->whereBetween('cash_entries.entry_date', [$p['from'], $p['to']])
            ->when($p['branch'] ?? null, fn ($q, $v) => $q->where('cash_entries.branch_id', $v))
            ->when($kind === 'expense', fn ($q) => $q->whereNull('cash_entries.vehicle_id'))
            ->leftJoin('finance_categories as c', 'c.id', '=', 'cash_entries.category_id')
            ->groupBy('c.name_ar', 'c.name_en')->select(\App\Support\Lang::sel('c.name_ar as name'), DB::raw('sum(cash_entries.amount) as total'))
            ->orderByDesc('total')->get();

        // الإهلاك في المدة = المتراكم في آخرها − المتراكم قبل أولها
        $from = Carbon::parse($p['from'])->subDay();
        $to = Carbon::parse($p['to']);
        $depreciation = DB::table('assets')->whereNull('deleted_at')
            ->when($p['branch'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->where('purchase_date', '<=', $p['to'])->get()
            ->sum(function ($a) use ($from, $to) {
                $end = $a->disposal_date && Carbon::parse($a->disposal_date)->lt($to) ? Carbon::parse($a->disposal_date) : $to;

                return max(0, AssetController::depreciation($a, $end)['accumulated'] - AssetController::depreciation($a, $from->copy()->max(Carbon::parse($a->purchase_date)))['accumulated']);
            });

        // المرتبات بتتحسب على شهرها (مش يوم صرفها)
        $salaries = (float) DB::table('hr_payrolls')->join('hr_payroll_runs as r', 'r.id', '=', 'hr_payrolls.payroll_run_id')
            ->where('r.status', '!=', 'draft')
            ->whereRaw("str_to_date(concat(r.year, '-', r.month, '-01'), '%Y-%m-%d') between ? and ?", [Carbon::parse($p['from'])->startOfMonth()->toDateString(), $p['to']])
            ->when($p['branch'] ?? null, fn ($q, $v) => $q->where('hr_payrolls.branch_id', $v))
            ->sum(DB::raw('hr_payrolls.gross - hr_payrolls.absence_deduction - hr_payrolls.fixed_deductions - hr_payrolls.adjustment_deductions - hr_payrolls.other_deductions'));
        $eos = (float) DB::table('hr_end_of_services')->join('hr_employees as e', 'e.id', '=', 'hr_end_of_services.employee_id')
            ->whereBetween('hr_end_of_services.termination_date', [$p['from'], $p['to']])
            ->when($p['branch'] ?? null, fn ($q, $v) => $q->where('e.branch_id', $v))
            ->sum(DB::raw('gratuity_amount + leave_encashment + hr_end_of_services.other_additions - hr_end_of_services.other_deductions'));

        // فوايد تمويل الأصول: مصروف تمويلي بيتحسب يوم دفع القسط
        $financeCost = (float) DB::table('asset_installments as ai')->join('assets as a', 'a.id', '=', 'ai.asset_id')
            ->whereNull('a.deleted_at')->whereBetween('ai.paid_date', [$p['from'], $p['to']])
            ->when($p['branch'] ?? null, fn ($q, $v) => $q->where('a.branch_id', $v))
            ->sum('ai.interest');

        $revenues = $entries('revenue');
        $expenses = $entries('expense');
        $sales = (float) $sold->sum('net_sale');
        $cogs = (float) $sold->sum('cost');
        $carExp = (float) $sold->sum('extra_cost');
        $gross = $sales - $cogs - $carExp;
        $net = $gross + (float) $revenues->sum('total') - (float) $expenses->sum('total') - $depreciation - $salaries - $eos - $financeCost;

        return [
            'count' => $sold->count(), 'sales' => $sales, 'tax' => (float) $sold->sum('tax_amount'), 'discounts' => (float) $sold->sum('discount_amount'),
            'cogs' => $cogs, 'car_expenses' => $carExp, 'gross' => $gross,
            'revenues' => $revenues, 'revenues_total' => (float) $revenues->sum('total'),
            'expenses' => $expenses, 'expenses_total' => (float) $expenses->sum('total'),
            'depreciation' => Money::round($depreciation), 'salaries' => Money::round($salaries), 'eos' => Money::round($eos), 'finance_cost' => Money::round($financeCost),
            'net' => Money::round($net),
            'margin' => $sales > 0 ? round($net / $sales * 100, 1) : 0,
        ];
    }
}
