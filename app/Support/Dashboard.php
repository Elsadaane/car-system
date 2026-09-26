<?php

namespace App\Support;

use App\Http\Controllers\Admin\ReportController;
use App\Models\SalesContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * ويدجتس الرئيسية: كل موظف بيشوف اللي صلاحياته تسمح بيه بس،
 * وبيرتّبها ويكبّرها ويصغّرها ويضيف ويشيل براحته (بتتحفظ في users.dashboard_layout).
 *
 * ويدجت جديد؟ ضيفه في WIDGETS + دالة data_{key} + view في admin/dashboard/widgets/{key}.blade.php
 */
class Dashboard
{
    /** key => [العنوان, الأيقونة, الصلاحية (null = للكل), الحجم الافتراضي 1-4, الوصف] */
    public const WIDGETS = [
        'quick_actions'  => ['إجراءات سريعة', '⚡', null, 4, 'أزرار لأكتر حاجات بتعملها كل يوم'],
        'alerts'         => ['محتاج تدخّلك', '🔔', null, 2, 'الأقساط المتأخرة والطلبات اللي محدش رد عليها والمتابعات — كله في مكان واحد'],
        'sales_kpi'      => ['المبيعات', '💰', 'sales.view', 2, 'مبيعات النهارده والشهر ومقارنة بالشهر اللي فات'],
        'profit_month'   => ['صافي ربح الشهر', '📈', 'reports.financial', 2, 'الأرباح بعد التكلفة والمصروفات والمرتبات'],
        'sales_trend'    => ['المبيعات آخر 30 يوم', '📊', 'sales.view', 2, 'رسم بياني يومي لعدد وقيمة العقود'],
        'leads'          => ['طلبات الموقع', '📥', 'inquiries.view', 2, 'الطلبات الجديدة والمتأخرة في الرد'],
        'lead_sources'   => ['مصادر العملاء', '🎯', 'inquiries.view', 2, 'الطلبات جاية منين ونسبة تحويل كل مصدر'],
        'installments'   => ['أقساط العملاء', '📅', 'payments.view', 2, 'المتأخر والمستحق الأسبوع ده'],
        'collections'    => ['التحصيل', '🧾', 'payments.view', 1, 'المحصّل النهارده والشهر'],
        'stock'          => ['المخزون', '🚗', 'vehicles.view', 2, 'المتاح والمحجوز والعربيات الراكدة'],
        'top_viewed'     => ['الأكثر مشاهدة', '👁️', 'vehicles.view', 1, 'العربيات اللي الزوار بيتفرجوا عليها'],
        'treasury'       => ['أرصدة الخزائن', '🏦', 'treasury.view', 2, 'الرصيد الحالي في كل خزنة'],
        'cash_month'     => ['مصروفات الشهر', '💸', 'cash.view', 2, 'المصروفات والإيرادات بالتصنيف'],
        'branches'       => ['منافسة الفروع', '🏆', 'reports.view', 2, 'ترتيب الفروع بمبيعات الشهر'],
        'sellers'        => ['أفضل المندوبين', '🥇', 'reports.view', 2, 'مين باع أكتر الشهر ده'],
        'test_drives'    => ['تجارب القيادة النهارده', '🛞', 'test_drives.view', 2, 'مواعيد النهارده'],
        'hr_today'       => ['الموظفين النهارده', '🧑‍💼', 'hr.view', 1, 'الحضور والإجازات المعلّقة'],
        'asset_installments' => ['أقساط الأصول', '🏢', 'assets.view', 1, 'أقساط على المعرض مستحقة'],
        'activity'       => ['آخر النشاط', '📜', 'audit.view', 2, 'آخر العمليات في السيستم'],
        'backup'         => ['النسخ الاحتياطي', '💾', 'backups.view', 1, 'حالة نسخة النهارده'],
    ];

    /** الترتيب الافتراضي لأول مرة (بيتفلتر بالصلاحيات) */
    private const DEFAULT = ['quick_actions', 'alerts', 'sales_kpi', 'leads', 'profit_month', 'installments', 'stock', 'sales_trend', 'treasury',
        'branches', 'sellers', 'lead_sources', 'test_drives', 'collections', 'hr_today', 'asset_installments', 'top_viewed', 'backup'];

    public static function allowed($user): array
    {
        return array_filter(self::WIDGETS, fn ($w) => $w[2] === null || Access::can($w[2], $user));
    }

    /** [['k' => key, 's' => size], ...] */
    public static function layout($user): array
    {
        $allowed = self::allowed($user);
        $saved = is_string($user->dashboard_layout ?? null) ? json_decode($user->dashboard_layout, true) : ($user->dashboard_layout ?? null);
        if (is_array($saved)) {
            return array_values(array_filter(array_map(fn ($w) => ['k' => (string) ($w['k'] ?? ''), 's' => max(1, min(4, (int) ($w['s'] ?? 2)))], $saved),
                fn ($w) => isset($allowed[$w['k']])));
        }

        return array_values(array_map(fn ($k) => ['k' => $k, 's' => self::WIDGETS[$k][3]], array_filter(self::DEFAULT, fn ($k) => isset($allowed[$k]))));
    }

    public static function data(string $key, $user): array
    {
        $method = 'data_'.$key;
        try {
            return method_exists(self::class, $method) ? self::$method($user) : [];
        } catch (\Throwable $e) {
            report($e);

            return ['_error' => 'البيانات مش متاحة حالياً'];
        }
    }

    // ───────────────────────── مساعدات ─────────────────────────

    private static function ids($user): ?array
    {
        return Access::branchIds($user);
    }

    private static function contracts($user)
    {
        $ids = self::ids($user);

        return SalesStats::contracts()->when($ids, fn ($q) => $q->whereIn('sales_contracts.branch_id', $ids));
    }

    private static function pct(float $now, float $before): ?int
    {
        return $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    }

    // ───────────────────────── الويدجتس ─────────────────────────

    private static function data_quick_actions($user): array
    {
        $all = [
            ['admin.sales-contracts.create', '📝', 'عقد بيع جديد'],
            ['admin.vehicles.create', '🚗', 'عربية جديدة'],
            ['admin.customers.create', '👤', 'عميل جديد'],
            ['admin.test-drives.create', '🛞', 'تجربة قيادة'],
            ['admin.expenses.index', '💸', 'مصروف', ['new' => 1]],
            ['admin.revenues.index', '💵', 'إيراد', ['new' => 1]],
            ['admin.installments.index', '📅', 'الأقساط'],
            ['admin.inquiries.index', '📥', 'طلبات الموقع'],
            ['admin.reports.overview', '📊', 'التقارير'],
        ];

        return ['actions' => array_values(array_filter(array_map(function ($a) use ($user) {
            if (! Route::has($a[0]) || ! Access::allows($user, $a[0])) {
                return null;
            }

            return ['url' => route($a[0], $a[3] ?? []), 'icon' => $a[1], 'label' => $a[2]];
        }, $all)))];
    }

    private static function data_sales_kpi($user): array
    {
        $today = today()->toDateString();
        $m0 = now()->startOfMonth()->toDateString();
        $lm0 = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lmSame = now()->subMonthNoOverflow()->toDateString();
        $sum = fn ($from, $to) => (clone self::contracts($user))->whereBetween('sales_contracts.contract_date', [$from, $to])
            ->selectRaw('count(*) c, coalesce(sum(sales_contracts.total_amount),0) v')->first();
        $t = $sum($today, $today);
        $m = $sum($m0, $today);
        $lm = $sum($lm0, $lmSame);

        return [
            'today_count' => (int) $t->c, 'today_value' => (float) $t->v,
            'month_count' => (int) $m->c, 'month_value' => (float) $m->v,
            'change' => self::pct((float) $m->v, (float) $lm->v), 'count_change' => self::pct((float) $m->c, (float) $lm->c),
        ];
    }

    private static function data_profit_month($user): array
    {
        $p = ['from' => now()->startOfMonth()->toDateString(), 'to' => today()->toDateString(), 'branch' => ($ids = self::ids($user)) ? $ids[0] : null];
        $prev = ['from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => now()->subMonthNoOverflow()->toDateString(), 'branch' => $p['branch']];
        $r = app(ReportController::class);
        $cur = $r->pnlData($p);
        $old = $r->pnlData($prev);

        return ['net' => $cur['net'], 'sales' => $cur['sales'], 'gross' => $cur['gross'], 'margin' => $cur['margin'], 'change' => self::pct($cur['net'], $old['net']), 'count' => $cur['count']];
    }

    private static function data_sales_trend($user): array
    {
        $from = today()->subDays(29);
        $rows = (clone self::contracts($user))->where('sales_contracts.contract_date', '>=', $from->toDateString())
            ->groupBy('sales_contracts.contract_date')
            ->selectRaw('sales_contracts.contract_date d, count(*) c, coalesce(sum(sales_contracts.total_amount),0) v')->get()
            ->keyBy(fn ($r) => substr((string) $r->d, 0, 10));
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $days[] = ['d' => $d, 'c' => (int) ($rows[$d]->c ?? 0), 'v' => (float) ($rows[$d]->v ?? 0)];
        }

        return ['days' => $days, 'max' => max(1, max(array_column($days, 'v'))), 'total' => array_sum(array_column($days, 'v')), 'count' => array_sum(array_column($days, 'c'))];
    }

    private static function inquiries($user)
    {
        $ids = self::ids($user);

        return DB::table('vehicle_inquiries')->whereNull('vehicle_inquiries.deleted_at')
            ->when($ids, fn ($q) => $q->whereIn('vehicle_inquiries.branch_id', $ids));
    }

    private static function data_leads($user): array
    {
        $q = self::inquiries($user);

        return [
            'new' => (clone $q)->where('status', 'new')->count(),
            'late' => (clone $q)->where('status', 'new')->where('created_at', '<', now()->subDay())->count(),
            'today' => (clone $q)->where('created_at', '>=', today())->count(),
            'mine' => (clone $q)->where('assigned_to', $user->id)->whereIn('status', ['new', 'contacted', 'qualified'])->count(),
            'latest' => (clone $q)->whereIn('status', ['new', 'contacted'])->orderByDesc('id')->limit(5)->get(['id', 'name', 'phone', 'type', 'status', 'created_at', 'source']),
        ];
    }

    private static function data_lead_sources($user): array
    {
        $rows = (clone self::inquiries($user))->where('created_at', '>=', now()->subDays(30))->where('status', '!=', 'spam')
            ->groupBy('source')->selectRaw("coalesce(nullif(source,''),'website') s, count(*) c, sum(case when status='converted' then 1 else 0 end) w")
            ->orderByDesc('c')->limit(6)->get();

        return ['rows' => $rows, 'total' => (int) $rows->sum('c')];
    }

    private static function installmentsQ($user)
    {
        return DB::table('installments')->join('sales_contracts', 'sales_contracts.id', '=', 'installments.sales_contract_id')
            ->whereNull('installments.deleted_at')->whereNull('sales_contracts.deleted_at')->where('installments.is_paid', false)
            ->where('installments.status', '!=', 'cancelled')
            ->when(self::ids($user), fn ($q, $ids) => $q->whereIn('sales_contracts.branch_id', $ids));
    }

    private static function data_installments($user): array
    {
        $today = today()->toDateString();
        $due = 'installments.amount - installments.paid_amount';
        $over = (clone self::installmentsQ($user))->where('installments.due_date', '<', $today);
        $week = (clone self::installmentsQ($user))->whereBetween('installments.due_date', [$today, today()->addDays(7)->toDateString()]);

        return [
            'over_count' => (clone $over)->count(), 'over_amount' => (float) (clone $over)->sum(DB::raw($due)),
            'week_count' => (clone $week)->count(), 'week_amount' => (float) (clone $week)->sum(DB::raw($due)),
            'list' => (clone $over)->leftJoin('customers', 'customers.id', '=', 'installments.customer_id')
                ->orderBy('installments.due_date')->limit(5)
                ->get(['installments.id', 'installments.due_date', DB::raw("($due) as due"), \App\Support\Lang::sel('customers.name_ar as customer'), 'customers.phone']),
        ];
    }

    private static function data_collections($user): array
    {
        $q = fn ($from) => DB::table('payments')->where('payments.payable_type', (new SalesContract)->getMorphClass())
            ->whereNull('payments.deleted_at')->where('payments.status', 'completed')->where('payments.payment_date', '>=', $from)
            ->join('sales_contracts', 'sales_contracts.id', '=', 'payments.payable_id')
            ->when(self::ids($user), fn ($w, $ids) => $w->whereIn('sales_contracts.branch_id', $ids))->sum('payments.amount');

        return ['today' => (float) $q(today()->toDateString()), 'month' => (float) $q(now()->startOfMonth()->toDateString())];
    }

    private static function vehiclesQ($user)
    {
        return DB::table('vehicles')->whereNull('vehicles.deleted_at')->when(self::ids($user), fn ($q, $ids) => $q->whereIn('vehicles.branch_id', $ids));
    }

    private static function data_stock($user): array
    {
        $q = self::vehiclesQ($user);
        $avail = (clone $q)->where('status', 'available');

        return [
            'available' => (clone $avail)->count(),
            'reserved' => (clone $q)->where('status', 'reserved')->count(),
            'published' => (clone $avail)->where('is_published', true)->count(),
            'value' => Access::can('vehicles.cost', $user) ? (float) (clone $avail)->sum('total_cost') : null,
            'stale' => (clone $avail)->where('vehicles.created_at', '<', now()->subDays(60))
                ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
                ->orderBy('vehicles.created_at')->limit(4)
                ->get(['vehicles.id', 'vehicles.created_at', 'vehicles.manufacturing_year', \App\Support\Lang::sel('car_brands.name_ar as brand'), \App\Support\Lang::sel('car_models.name_ar as model')]),
            'stale_count' => (clone $avail)->where('vehicles.created_at', '<', now()->subDays(60))->count(),
        ];
    }

    private static function data_top_viewed($user): array
    {
        return ['rows' => (clone self::vehiclesQ($user))->where('vehicles.is_published', true)->whereIn('vehicles.status', ['available', 'reserved'])
            ->where('vehicles.views_count', '>', 0)
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->orderByDesc('vehicles.views_count')->limit(5)
            ->get(['vehicles.id', 'vehicles.views_count', 'vehicles.manufacturing_year', \App\Support\Lang::sel('car_brands.name_ar as brand'), \App\Support\Lang::sel('car_models.name_ar as model')])];
    }

    private static function data_treasury($user): array
    {
        $bal = Ledger::balances();
        $accounts = DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->orderBy('id')->get(['id', 'name_ar']);

        return ['rows' => $accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name_ar, 'balance' => (float) ($bal[$a->id]['balance'] ?? 0)])->all(),
            'total' => (float) collect($bal)->sum('balance')];
    }

    private static function data_cash_month($user): array
    {
        $q = fn ($kind) => DB::table('cash_entries')->whereNull('cash_entries.deleted_at')->where('cash_entries.kind', $kind)
            ->where('cash_entries.entry_date', '>=', now()->startOfMonth()->toDateString())
            ->when(self::ids($user), fn ($w, $ids) => $w->whereIn('cash_entries.branch_id', $ids));

        return [
            'expenses' => (float) $q('expense')->sum('total_amount'),
            'revenues' => (float) $q('revenue')->sum('total_amount'),
            'top' => $q('expense')->leftJoin('finance_categories as c', 'c.id', '=', 'cash_entries.category_id')
                ->groupBy('c.name_ar', 'c.name_en')->selectRaw('coalesce('.Lang::sqlName('c').", ?) n, sum(cash_entries.total_amount) t", [__('بدون تصنيف')])->orderByDesc('t')->limit(5)->get(),
        ];
    }

    private static function data_branches($user): array
    {
        $rows = SalesStats::contracts()->where('sales_contracts.contract_date', '>=', now()->startOfMonth()->toDateString())
            ->join('branches', 'branches.id', '=', 'sales_contracts.branch_id')
            ->groupBy('branches.id', 'branches.name_ar', 'branches.name_en', 'branches.sales_target')
            ->selectRaw('branches.id, '.Lang::sqlName('branches').' n, branches.sales_target target, count(*) c, sum(sales_contracts.total_amount) v')
            ->orderByDesc('v')->get();

        return ['rows' => $rows, 'max' => max(1, (float) $rows->max('v'))];
    }

    private static function data_sellers($user): array
    {
        return ['rows' => (clone self::contracts($user))->where('sales_contracts.contract_date', '>=', now()->startOfMonth()->toDateString())
            ->join('users', 'users.id', '=', DB::raw(SalesStats::repColumn()))
            ->groupBy('users.id', 'users.name')->selectRaw('users.id, users.name n, count(*) c, sum(sales_contracts.total_amount) v')
            ->orderByDesc('v')->limit(5)->get()];
    }

    private static function data_test_drives($user): array
    {
        return ['rows' => DB::table('test_drives')->whereDate('test_drives.start_time', today())
            ->when(Schema::hasColumn('test_drives', 'deleted_at'), fn ($q) => $q->whereNull('test_drives.deleted_at'))
            ->join('vehicles', 'vehicles.id', '=', 'test_drives.vehicle_id')
            ->when(self::ids($user), fn ($q, $ids) => $q->whereIn('vehicles.branch_id', $ids))
            ->leftJoin('customers', 'customers.id', '=', 'test_drives.customer_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->orderBy('test_drives.start_time')->limit(6)
            ->get(['test_drives.id', 'test_drives.start_time', 'test_drives.status', \App\Support\Lang::sel('customers.name_ar as customer'), \App\Support\Lang::sel('car_brands.name_ar as brand'), \App\Support\Lang::sel('car_models.name_ar as model')])];
    }

    private static function data_hr_today($user): array
    {
        $emp = fn () => DB::table('hr_employees')->when(self::ids($user), fn ($q, $ids) => $q->whereIn('hr_employees.branch_id', $ids));
        $att = DB::table('hr_attendances')->whereDate('date', today())->whereIn('employee_id', $emp()->select('id'))
            ->groupBy('status')->selectRaw('status, count(*) c')->pluck('c', 'status');

        return [
            'present' => (int) (($att['present'] ?? 0) + ($att['late'] ?? 0)), 'late' => (int) ($att['late'] ?? 0),
            'absent' => (int) ($att['absent'] ?? 0), 'leave' => (int) ($att['leave'] ?? 0),
            'pending_leaves' => DB::table('hr_leave_requests')->where('status', 'pending')->whereIn('employee_id', $emp()->select('id'))->count(),
        ];
    }

    private static function data_asset_installments($user): array
    {
        return AssetFinance::alerts();
    }

    private static function data_activity($user): array
    {
        return ['rows' => DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->whereIn('audit_logs.event', ['create', 'update', 'delete', 'action', 'denied'])
            ->orderByDesc('audit_logs.id')->limit(7)->get(['audit_logs.event', 'audit_logs.description', 'audit_logs.route', 'audit_logs.method', 'audit_logs.created_at', 'users.name'])];
    }

    private static function data_alerts($user): array
    {
        return ['list' => array_slice(Alerts::for($user), 0, 6), 'total' => count(Alerts::for($user))];
    }

    private static function data_backup($user): array
    {
        $list = Backup::list();

        return ['due' => Backup::dueToday(), 'last' => $list[0]['time'] ?? null, 'count' => count($list)];
    }
}
