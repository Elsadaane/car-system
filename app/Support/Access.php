<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * الصلاحيات (Spatie) — كل حاجة مجمّعة هنا:
 *   - MODULES: الأقسام وصلاحيات كل قسم (بتظهر في صفحة الأدوار بنفس الترتيب)
 *   - كل صفحة في لوحة الإدارة بتتربط بصلاحية أوتوماتيك من اسم الـ route
 *     (index/show = عرض، create/store = إضافة، edit/update = تعديل، destroy = مسح)
 *   - OVERRIDES: للصفحات اللي ليها صلاحية خاصة
 *
 * أي صفحة جديدة: ضيف الـ prefix بتاعها في MODULES وخلاص.
 */
class Access
{
    public const SUPER_ROLE = 'مدير عام';

    public const ACTIONS = [
        'view' => 'عرض', 'create' => 'إضافة', 'edit' => 'تعديل', 'delete' => 'مسح',
        'cost' => 'يشوف تكلفة الشراء والربح', 'approve' => 'الموافقة / الرفض', 'pay' => 'الصرف', 'financial' => 'التقارير المالية (الأرباح)',
    ];

    /** key => [الاسم, المجموعة, الصلاحيات, prefixes الـ routes (بعد admin.)] */
    public const MODULES = [
        'inquiries'        => ['طلبات الموقع', 'المبيعات', ['view', 'edit'], ['inquiries.']],
        'sales'            => ['عقود البيع', 'المبيعات', ['view', 'create', 'edit', 'delete'], ['sales-contracts.']],
        'payments'         => ['المدفوعات والأقساط', 'المبيعات', ['view', 'create', 'edit', 'delete'], ['sale-payments.', 'dealer-payments.', 'installments.', 'cheques.']],
        'deliveries'       => ['التسليم', 'المبيعات', ['view', 'create', 'delete'], ['deliveries.']],
        'cancellations'    => ['طلبات الإلغاء', 'المبيعات', ['view', 'approve'], ['cancellations.']],
        'finance'          => ['التمويل (البنوك)', 'المبيعات', ['view', 'create', 'edit', 'delete', 'approve'], ['finance-requests.', 'finance-companies.']],

        'vehicles'         => ['المركبات', 'المخزون', ['view', 'create', 'edit', 'delete', 'cost'], ['vehicles.', 'inspections.']],
        'catalog'          => ['الماركات والطرازات', 'المخزون', ['view', 'create', 'edit', 'delete'], ['brands.', 'car-models.', 'import-sources.']],
        'suppliers'        => ['الموردين', 'المخزون', ['view', 'create', 'edit', 'delete'], ['suppliers.']],
        'dealer_contracts' => ['عقود التفويض', 'المخزون', ['view', 'create', 'edit', 'delete'], ['dealer-contracts.']],

        'treasury'         => ['الخزائن وطرق الدفع', 'المالية', ['view', 'create', 'edit', 'delete'], ['accounts.', 'payment-methods.']],
        'cash'             => ['المصروفات والإيرادات', 'المالية', ['view', 'create', 'edit', 'delete'], ['expenses.', 'revenues.', 'cash-entries.', 'finance-categories.', 'shared-expenses.']],
        'partners'         => ['الشركاء في العربيات', 'المالية', ['view', 'create', 'edit', 'delete'], ['partners.']],
        'assets'           => ['الأصول وأقساطها', 'المالية', ['view', 'create', 'edit', 'delete', 'pay'], ['assets.', 'asset-installments.']],

        'customers'        => ['العملاء', 'العملاء', ['view', 'create', 'edit', 'delete'], ['customers.']],
        'test_drives'      => ['تجارب القيادة', 'العملاء', ['view', 'create', 'edit', 'delete'], ['test-drives.']],

        'hr'               => ['الموظفين والحضور والإجازات', 'الموارد البشرية', ['view', 'create', 'edit', 'delete', 'approve'], ['hr.']],
        'payroll'          => ['المرتبات', 'الموارد البشرية', ['view', 'create', 'approve', 'pay'], ['hr.payroll.']],

        'reports'          => ['التقارير', 'التقارير', ['view', 'financial'], ['reports.']],

        'website'          => ['إعدادات الموقع والبانرات', 'الموقع الإلكتروني', ['view', 'edit'], ['site-settings.', 'site-blocks.']],
        'blog'             => ['المدونة', 'الموقع الإلكتروني', ['view', 'create', 'edit', 'delete'], ['blog.', 'blog-categories.']],

        'branches'         => ['الفروع', 'النظام', ['view', 'create', 'edit', 'delete'], ['branches.']],
        'settings'         => ['إعدادات النظام', 'النظام', ['view', 'edit'], ['settings.']],
        'users'            => ['المستخدمين', 'النظام', ['view', 'create', 'edit', 'delete'], ['users.']],
        'roles'            => ['الأدوار والصلاحيات', 'النظام', ['view', 'create', 'edit', 'delete'], ['roles.']],
        'audit'            => ['سجل النشاط', 'النظام', ['view'], ['audit.']],
        'backups'          => ['النسخ الاحتياطي', 'النظام', ['view', 'create', 'delete'], ['backups.']],
        'data'             => ['استيراد وتصدير Excel', 'النظام', ['view', 'create'], ['data.']],
    ];

    /** صفحات ليها صلاحية مختلفة عن القاعدة العامة */
    public const OVERRIDES = [
        'sales-contracts.payments.store'       => 'payments.create',
        'sales-contracts.deliver'              => 'deliveries.create',
        'sales-contracts.cancellations.decide' => 'cancellations.approve',
        'sales-contracts.quick-customer'       => 'customers.create',
        'finance-requests.decide'              => 'finance.approve',
        'finance-requests.receive'             => 'finance.approve',
        'finance-requests.store'               => 'finance.create',
        'dealer-contracts.payments.store'      => 'payments.create',
        'sales-contracts.cheques.store'        => 'payments.create',
        'cheques.destroy'                      => 'payments.delete',
        'vehicles.partners.store'              => 'partners.edit',
        'vehicles.partners.destroy'            => 'partners.edit',
        'partners.transactions.store'          => 'partners.edit',
        'partners.transactions.destroy'        => 'partners.edit',
        'inspections.create'                   => 'vehicles.edit',
        'inspections.store'                    => 'vehicles.edit',
        'inspections.destroy'                  => 'vehicles.edit',
        'branches.vehicles.transfer'           => 'vehicles.edit',
        'asset-installments.pay'               => 'assets.pay',
        'asset-installments.unpay'             => 'assets.pay',
        'assets.payoff'                        => 'assets.pay',
        'hr.leaves.decide'                     => 'hr.approve',
        'backups.google.connect'               => 'backups.create',
        'backups.google.callback'              => 'backups.create',
        'backups.google.disconnect'            => 'backups.delete',
        'data.map'                             => 'data.create',
        'data.run'                             => 'data.create',
        'data.errors'                          => 'data.create',
        'customers.statement'                  => 'customers.view',
        'hr.payroll.approve'                   => 'payroll.approve',
        'hr.payroll.unapprove'                 => 'payroll.approve',
        'hr.payroll.pay'                       => 'payroll.pay',
        'hr.payroll.unpay'                     => 'payroll.pay',
        'hr.payroll.bank-file'                 => 'payroll.pay',
        'hr.payroll.generate'                  => 'payroll.create',
        'hr.payroll.row'                       => 'payroll.create',
        'hr.payroll.destroy'                   => 'payroll.create',
        'reports.overview'                     => 'reports.financial',
        'reports.profits'                      => 'reports.financial',
        'reports.pnl'                          => 'reports.financial',
        'reports.targets'                      => 'reports.financial',
        'settings.index'                       => 'settings.view',
    ];

    /** صفحات مفتوحة لأي موظف داخل اللوحة */
    public const FREE = ['dashboard', 'dashboard.', 'attachments.', 'notifications.', 'locale.'];

    private const VERB = [
        'view'   => ['index', 'show', 'print', 'receipt', 'download', 'payslip', 'sheet', 'monthly', 'eos-print', 'category'],
        'create' => ['create', 'store', 'upload', 'generate'],
        'edit'   => ['edit', 'update'],
        'delete' => ['destroy'],
    ];

    /** الصلاحية المطلوبة لـ route (null = مفتوح) */
    public static function permissionFor(string $routeName, string $method = 'GET'): ?string
    {
        $name = Str::after($routeName, 'admin.');
        if ($name === $routeName && ! str_starts_with($routeName, 'admin.')) {
            return null; // مش صفحة لوحة
        }
        foreach (self::FREE as $free) {
            if ($name === $free || (str_ends_with($free, '.') && str_starts_with($name, $free))) {
                return null;
            }
        }
        if (isset(self::OVERRIDES[$name])) {
            return self::OVERRIDES[$name];
        }

        // أطول prefix يكسب (hr.payroll. قبل hr.)
        $module = null;
        $len = 0;
        foreach (self::MODULES as $key => [, , , $prefixes]) {
            foreach ($prefixes as $p) {
                if (str_starts_with($name, $p) && strlen($p) > $len) {
                    [$module, $len] = [$key, strlen($p)];
                }
            }
        }
        if (! $module) {
            return 'settings.edit'; // صفحة مش متصنّفة: للمدير العام ومسؤول الإعدادات بس
        }

        $action = Str::afterLast($name, '.');
        $verb = collect(self::VERB)->search(fn ($list) => in_array($action, $list, true))
            ?: (strtoupper($method) === 'GET' ? 'view' : 'edit');
        $allowed = self::MODULES[$module][2];
        if (! in_array($verb, $allowed, true)) {
            $verb = in_array('edit', $allowed, true) ? 'edit' : $allowed[0];
        }

        return $module.'.'.$verb;
    }

    public static function isSuper($user): bool
    {
        if (! $user) {
            return false;
        }
        if (($user->role ?? null) === 'admin') {
            return true;
        }

        return method_exists($user, 'hasRole') && self::ready() && $user->hasRole(self::SUPER_ROLE);
    }

    /** اليوزر عنده الصلاحية؟ (المدير العام عنده كله) */
    public static function can(string $permission, $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user) {
            return false;
        }
        if (self::isSuper($user)) {
            return true;
        }

        return self::ready() && method_exists($user, 'checkPermissionTo') && $user->checkPermissionTo($permission);
    }

    /** يقدر يفتح الـ route ده؟ (بيستخدمه السايدبار والـ middleware) */
    public static function allows($user, string $routeName, string $method = 'GET'): bool
    {
        $perm = self::permissionFor($routeName, $method);

        return $perm === null || self::can($perm, $user);
    }

    /** null = كل الفروع، [] أو [id] = فرع معيّن */
    public static function branchIds($user = null): ?array
    {
        $user ??= auth()->user();
        if (! $user || self::isSuper($user) || ($user->all_branches ?? false)) {
            return null;
        }

        return $user->branch_id ? [(int) $user->branch_id] : [0];
    }

    /** بيفلتر أي query بفرع اليوزر (لو مقفول على فرعه) */
    public static function scope($query, string $column)
    {
        $ids = self::branchIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /** فلترة جدول مالوش branch_id عن طريق جدول مربوط بيه (زي المدفوعات عن طريق العقد) */
    public static function scopeVia($query, string $column, string $table)
    {
        $ids = self::branchIds();

        return $ids === null ? $query : $query->whereIn($column, fn ($s) => $s->select('id')->from($table)->whereIn('branch_id', $ids));
    }

    /** كل الصلاحيات [name => label] مجمّعة بالقسم */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::MODULES as $key => [$label, $group, $actions]) {
            foreach ($actions as $a) {
                $out[$group][$key]['label'] = $label;
                $out[$group][$key]['perms'][$key.'.'.$a] = self::ACTIONS[$a];
            }
        }

        return $out;
    }

    public static function names(): array
    {
        return collect(self::MODULES)->flatMap(fn ($m, $key) => array_map(fn ($a) => $key.'.'.$a, $m[2]))->values()->all();
    }

    /** بيعمل الصلاحيات الناقصة ويمسح القديمة اللي اتشالت من الكود */
    public static function sync(): void
    {
        $model = \Spatie\Permission\Models\Permission::class;
        $names = self::names();
        foreach ($names as $n) {
            $model::findOrCreate($n, 'web');
        }
        $model::where('guard_name', 'web')->whereNotIn('name', $names)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** الأدوار الافتراضية اللي بتتعمل أول مرة */
    public static function defaultRoles(): array
    {
        $all = fn (string ...$modules) => collect($modules)->flatMap(fn ($m) => array_map(fn ($a) => $m.'.'.$a, self::MODULES[$m][2]))->all();
        $only = fn (string $m, string ...$actions) => array_map(fn ($a) => $m.'.'.$a, $actions);

        return [
            self::SUPER_ROLE => self::names(),
            'مدير فرع'       => array_merge($all('inquiries', 'sales', 'payments', 'deliveries', 'cancellations', 'finance', 'customers', 'test_drives', 'dealer_contracts'),
                $only('vehicles', 'view', 'create', 'edit', 'cost'), $only('catalog', 'view', 'create', 'edit'), $only('cash', 'view', 'create'),
                $only('hr', 'view', 'approve'), $only('reports', 'view'), $all('data')),
            'مندوب مبيعات'   => array_merge($only('inquiries', 'view', 'edit'), $only('sales', 'view', 'create', 'edit'), $only('payments', 'view', 'create'),
                $only('deliveries', 'view', 'create'), $only('cancellations', 'view'), $only('finance', 'view', 'create'),
                $only('vehicles', 'view'), $only('catalog', 'view'), $only('customers', 'view', 'create', 'edit'), $only('test_drives', 'view', 'create', 'edit')),
            'محاسب'          => array_merge($all('payments', 'treasury', 'cash', 'assets', 'finance', 'partners'), $only('sales', 'view'), $only('deliveries', 'view'),
                $only('cancellations', 'view'), $only('vehicles', 'view', 'cost'), $only('suppliers', 'view', 'edit'), $only('dealer_contracts', 'view'),
                $only('customers', 'view'), $only('payroll', 'view', 'pay'), $all('reports'), $only('data', 'view')),
            'موظف HR'        => array_merge($all('hr'), $only('payroll', 'view', 'create'), $only('branches', 'view')),
            'مسؤول الموقع'   => array_merge($all('website', 'blog'), $only('inquiries', 'view', 'edit'), $only('vehicles', 'view', 'edit'), $only('catalog', 'view'), $only('reports', 'view')),
            'كاشير'          => array_merge($only('payments', 'view', 'create'), $only('sales', 'view'), $only('customers', 'view'),
                $only('cash', 'view', 'create'), $only('treasury', 'view'), $only('assets', 'view', 'pay')),
        ];
    }

    private static ?bool $ready = null;

    /** جداول Spatie موجودة؟ (عشان السيستم ميقعش قبل الـ migrate) */
    public static function ready(): bool
    {
        return self::$ready ??= class_exists(\Spatie\Permission\Models\Permission::class) && Schema::hasTable('model_has_roles');
    }
}
