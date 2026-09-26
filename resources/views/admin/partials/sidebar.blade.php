@php
    use App\Support\Module;
    use Illuminate\Support\Facades\Route as Router;
    use Illuminate\Support\Str;

    $user = auth()->user();

    $newInquiries = 0;
    if (Module::enabled('storefront') && class_exists(\App\Models\VehicleInquiry::class)) {
        $newInquiries = \App\Models\VehicleInquiry::where('status', 'new')->count();
    }

    $assetOverdue = 0;
    try {
        $assetOverdue = \Illuminate\Support\Facades\Schema::hasTable('asset_installments') && \App\Support\Access::can('assets.view', $user)
            ? \Illuminate\Support\Facades\DB::table('asset_installments')->join('assets', 'assets.id', '=', 'asset_installments.asset_id')->whereNull('assets.deleted_at')
                ->whereNull('asset_installments.paid_date')->where('asset_installments.due_date', '<=', today()->addDays(3)->toDateString())->count()
            : 0;
    } catch (\Throwable $e) {
        $assetOverdue = 0;
    }

    $icons = [
        'home'      => '<path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
        'sales'     => '<path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/>',
        'inventory' => '<path d="M5 17V12l1.8-4.6A2 2 0 0 1 8.7 6h6.6a2 2 0 0 1 1.9 1.4L19 12v5"/><path d="M3 17h18v2.5H3z"/><path d="M5 12h14"/><circle cx="8" cy="14.5" r=".8"/><circle cx="16" cy="14.5" r=".8"/>',
        'finance'   => '<rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M2.5 10h19"/><path d="M6.5 15h3"/>',
        'customers' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.3-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.8c1.6.9 2.6 2.6 3 5.2"/>',
        'system'    => '<path d="M12 3.5 13.6 6l2.9-.4.9 2.8 2.6 1.3-.9 2.8 1.4 2.5-2.4 1.7-.2 2.9-2.9.1L12 21.5l-2-1.8-2.9-.1-.2-2.9-2.4-1.7 1.4-2.5-.9-2.8 2.6-1.3.9-2.8 2.9.4z"/><circle cx="12" cy="12.5" r="2.8"/>',
        'hr'        => '<circle cx="12" cy="7.5" r="3.5"/><path d="M5 21v-1.5A5.5 5.5 0 0 1 10.5 14h3a5.5 5.5 0 0 1 5.5 5.5V21"/><path d="M12 14v3l-1.5 2h3L12 17"/>',
        'reports'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'store'     => '<path d="M3.5 9 5 4h14l1.5 5"/><path d="M3.5 9a2.8 2.8 0 0 0 5.7 0 2.8 2.8 0 0 0 5.6 0 2.8 2.8 0 0 0 5.7 0"/><path d="M5 11.5V20h14v-8.5"/><path d="M10 20v-5h4v5"/>',
        'search'    => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/>',
        'chevron'   => '<path d="m6 9 6 6 6-6"/>',
    ];

    $tones = [
        'blue'    => ['soft' => 'bg-blue-50 text-blue-600',       'solid' => 'bg-blue-600 text-white'],
        'amber'   => ['soft' => 'bg-amber-50 text-amber-600',     'solid' => 'bg-amber-500 text-white'],
        'emerald' => ['soft' => 'bg-emerald-50 text-emerald-600', 'solid' => 'bg-emerald-600 text-white'],
        'violet'  => ['soft' => 'bg-violet-50 text-violet-600',   'solid' => 'bg-violet-600 text-white'],
        'slate'   => ['soft' => 'bg-slate-100 text-slate-600',    'solid' => 'bg-slate-800 text-white'],
    ];

    /*
    | القائمة
    | module => بيختفي لو الموديول مقفول من .env
    | admin  => بيظهر للأدمن بس
    | badge  => رقم أحمر جنب البند
    */
    // الشيكات اللي ميعاد إيداعها خلال 3 أيام أو فات
    $chequesDue = 0;
    try {
        $chequesDue = \App\Support\Cheques::table() && \App\Support\Access::can('payments.view', $user)
            ? \Illuminate\Support\Facades\DB::table('cheques')->whereNull('deleted_at')->where('status', 'received')->where('due_date', '<=', today()->addDays(3)->toDateString())->count()
            : 0;
    } catch (\Throwable $e) {
        $chequesDue = 0;
    }

    $menu = [
        ['section' => __('التشغيل'), 'label' => __('المبيعات'), 'icon' => 'sales', 'tone' => 'blue', 'items' => [
            ['label' => __('الاستفسارات'),   'route' => 'admin.inquiries.index', 'module' => 'storefront', 'badge' => $newInquiries],
            ['label' => __('عقود البيع'),    'route' => 'admin.sales-contracts.index'],
            ['label' => __('عقود التسليم'),  'route' => 'admin.deliveries.index', 'module' => 'delivery_contracts'],
            ['label' => __('طلبات الإلغاء'), 'route' => 'admin.cancellations.index'],
        ]],
        ['section' => __('التشغيل'), 'label' => __('المخزون'), 'icon' => 'inventory', 'tone' => 'amber', 'items' => [
            ['label' => __('المركبات'),        'route' => 'admin.vehicles.index'],
            ['label' => __('الماركات'),        'route' => 'admin.brands.index'],
            ['label' => __('الطرازات'),        'route' => 'admin.car-models.index'],
            ['label' => __('جهات الاستيراد'),  'route' => 'admin.import-sources.index'],
            ['label' => __('الموردين'),        'route' => 'admin.suppliers.index', 'admin' => true],
            ['label' => __('عقود التفويض'),       'route' => 'admin.dealer-contracts.index'],
            ['label' => __('الفحوصات'),        'route' => 'admin.inspections.index'],
        ]],
        ['section' => __('المال والعملاء'), 'label' => __('المالية'), 'icon' => 'finance', 'tone' => 'emerald', 'items' => [
            ['label' => __('مدفوعات التفويض'), 'route' => 'admin.dealer-payments.index', 'admin' => true],
            ['label' => __('مدفوعات المبيعات'), 'route' => 'admin.sale-payments.index'],
            ['label' => __('الأقساط'),   'route' => 'admin.installments.index', 'module' => 'installments'],
            ['label' => __('الشيكات'),   'route' => 'admin.cheques.index', 'badge' => $chequesDue],
            ['label' => __('الشركاء'),   'route' => 'admin.partners.index'],
            ['label' => __('طلبات التمويل'), 'route' => 'admin.finance-requests.index', 'module' => 'finance'],
            ['label' => __('شركات التمويل'), 'route' => 'admin.finance-companies.index', 'module' => 'finance'],
            ['label' => __('طرق الدفع'), 'route' => 'admin.payment-methods.index', 'admin' => true],
            ['label' => __('الخزائن والحسابات'), 'route' => 'admin.accounts.index', 'admin' => true],
            ['label' => __('المصروفات'), 'route' => 'admin.expenses.index', 'admin' => true],
            ['label' => __('＋ مصروف جديد'), 'route' => 'admin.expenses.index', 'query' => 'new=1', 'admin' => true],
            ['label' => __('المصاريف المشتركة'), 'route' => 'admin.shared-expenses.index', 'admin' => true],
            ['label' => __('الإيرادات'), 'route' => 'admin.revenues.index', 'admin' => true],
            ['label' => __('＋ إيراد جديد'), 'route' => 'admin.revenues.index', 'query' => 'new=1', 'admin' => true],
            ['label' => __('الأصول'), 'route' => 'admin.assets.index', 'admin' => true],
            ['label' => __('＋ أصل جديد'), 'route' => 'admin.assets.index', 'query' => 'new=1', 'admin' => true],
            ['label' => __('أقساط الأصول'), 'route' => 'admin.asset-installments.index', 'admin' => true, 'badge' => $assetOverdue],
        ]],
        ['section' => __('المال والعملاء'), 'label' => __('العملاء'), 'icon' => 'customers', 'tone' => 'violet', 'items' => [
            ['label' => __('العملاء'),        'route' => 'admin.customers.index'],
            ['label' => __('تجارب القيادة'), 'route' => 'admin.test-drives.index', 'module' => 'test_drives'],
        ]],
        ['section' => __('الإدارة'), 'label' => __('الموارد البشرية'), 'icon' => 'hr', 'tone' => 'violet', 'items' => [
            ['label' => __('الموظفين'),          'route' => 'admin.hr.employees.index', 'admin' => true],
            ['label' => __('الحضور والانصراف'), 'route' => 'admin.hr.attendance.index', 'admin' => true],
            ['label' => __('الإجازات'),          'route' => 'admin.hr.leaves.index', 'admin' => true],
            ['label' => __('السلف والحوافز'),   'route' => 'admin.hr.finance.index', 'admin' => true],
            ['label' => __('المرتبات'),          'route' => 'admin.hr.payroll.index', 'admin' => true],
            ['label' => __('إعدادات HR'),        'route' => 'admin.hr.settings.index', 'admin' => true],
        ]],
        ['section' => __('الإدارة'), 'label' => __('التقارير'), 'icon' => 'reports', 'tone' => 'blue', 'items' => [
            ['label' => __('التقرير الإجمالي'), 'route' => 'admin.reports.overview', 'admin' => true],
            ['label' => __('منافسة الفروع'),   'route' => 'admin.reports.branches', 'admin' => true],
            ['label' => __('المبيعات'),        'route' => 'admin.reports.sales', 'admin' => true],
            ['label' => __('ربح كل عربية'),    'route' => 'admin.reports.profits', 'admin' => true],
            ['label' => __('عمولات المندوبين'), 'route' => 'admin.reports.commissions', 'admin' => true],
            ['label' => __('المخزون وأعماره'), 'route' => 'admin.reports.inventory', 'admin' => true],
            ['label' => __('المديونيات'),      'route' => 'admin.reports.receivables', 'admin' => true],
            ['label' => __('الأرباح والخسائر'), 'route' => 'admin.reports.pnl', 'admin' => true],
        ]],
        ['section' => __('الإدارة'), 'label' => __('الموقع الإلكتروني'), 'icon' => 'store', 'tone' => 'amber', 'items' => [
            ['label' => __('طلبات الموقع'),    'route' => 'admin.inquiries.index', 'module' => 'storefront', 'badge' => $newInquiries],
            ['label' => __('المدونة'),          'route' => 'admin.blog.index', 'module' => 'storefront', 'admin' => true],
            ['label' => __('البانرات والمحتوى'), 'route' => 'admin.site-blocks.index', 'module' => 'storefront', 'admin' => true],
            ['label' => __('إعدادات الموقع'),  'route' => 'admin.site-settings.index', 'module' => 'storefront', 'admin' => true],
            ['label' => __('فتح الموقع ↗'),    'route' => 'storefront.home', 'module' => 'storefront'],
        ]],
        ['section' => __('الإدارة'), 'label' => __('النظام'), 'icon' => 'system', 'tone' => 'slate', 'items' => [
            ['label' => __('الفروع'),    'route' => 'admin.branches.index'],
            ['label' => __('المستخدمين'), 'route' => 'admin.users.index'],
            ['label' => __('الأدوار والصلاحيات'), 'route' => 'admin.roles.index'],
            ['label' => __('سجل النشاط'), 'route' => 'admin.audit.index'],
            ['label' => __('النسخ الاحتياطي'), 'route' => 'admin.backups.index'],
            ['label' => __('استيراد وتصدير Excel'), 'route' => 'admin.data.index'],
            ['label' => __('الإعدادات'), 'route' => 'admin.settings.index', 'admin' => true],
        ]],
    ];

    // بنود ".index" بتنور على كل صفحات القسم، وباقي البنود (زي التقارير) على صفحتها بس
    $isActive = fn (string $route) => str_ends_with($route, '.index')
        ? request()->routeIs(Str::beforeLast($route, '.').'.*')
        : request()->routeIs($route);

    foreach ($menu as $g => $group) {
        $items = array_values(array_filter($group['items'], fn ($item) =>
            (! isset($item['module']) || Module::enabled($item['module']))
            && \App\Support\Access::allows($user, $item['route'])
        ));

        foreach ($items as $i => $item) {
            $items[$i]['exists'] = Router::has($item['route']);
            $items[$i]['active'] = $items[$i]['exists'] && empty($item['query']) && $isActive($item['route']);
        }

        $menu[$g]['items']  = $items;
        $menu[$g]['active'] = collect($items)->contains('active', true);
        $menu[$g]['badge']  = collect($items)->sum(fn ($item) => $item['badge'] ?? 0);
    }
    $menu = array_values(array_filter($menu, fn ($group) => count($group['items']) > 0));

    $plans = ['basic' => __('الباقة الأساسية'), 'pro' => __('الباقة المتقدمة'), 'full' => __('الباقة الكاملة')];
    $plan = $plans[config('laroville.license.plan')] ?? null;

    $roleLabel = match ($user?->role) { 'admin' => __('مدير النظام'), 'staff' => __('موظف'), default => __('مستخدم') };
    $branchName = \App\Models\Branch::isMultiBranch() ? $user?->branch?->name_ar : null;
@endphp

<aside id="sidebar"
       class="fixed inset-y-0 start-0 z-40 flex w-64 {{ app()->getLocale() === 'en' ? '-translate-x-full' : 'translate-x-full' }} flex-col border-e border-slate-200/80 bg-white transition-all duration-200 lg:translate-x-0">

    {{-- ═══════ الشعار ═══════ --}}
    <div class="flex h-16 shrink-0 items-center gap-3 px-5">
        @if ($logo = \App\Support\Company::logo())
            <img src="{{ $logo }}" alt="" class="h-10 w-10 shrink-0 rounded-2xl bg-white object-contain p-0.5 ring-1 ring-slate-200">
        @else
            <span class="relative grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-lg shadow-blue-600/25">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['inventory'] !!}</svg>
            </span>
        @endif
        <div class="nav-text min-w-0">
            <div class="truncate text-[15px] font-extrabold leading-tight text-slate-900">{{ \App\Support\Company::name() }}</div>
            @if ($plan)
                <div class="mt-0.5 text-[11px] font-semibold text-blue-600">{{ $plan }}</div>
            @endif
        </div>
    </div>

    {{-- ═══════ البحث ═══════ --}}
    <div class="nav-text px-4 pb-2 pt-1">
        <label class="group/search relative block">
            <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 transition group-focus-within/search:text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">{!! $icons['search'] !!}</svg>
            <input id="navSearch" type="search" placeholder="{{ __('ابحث في القائمة') }}" autocomplete="off"
                   class="w-full rounded-xl border border-transparent bg-slate-100/80 py-2.5 pe-10 ps-9 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-200 focus:bg-white focus:ring-4 focus:ring-blue-50">
            <kbd class="pointer-events-none absolute end-2.5 top-1/2 -translate-y-1/2 rounded-md border border-slate-200 bg-white px-1.5 font-sans text-[10px] font-bold text-slate-400 shadow-sm">/</kbd>
        </label>
    </div>

    {{-- ═══════ القائمة ═══════ --}}
    <nav class="flex-1 overflow-y-auto px-3 pb-4 pt-2 [scrollbar-width:thin]">

        <a href="{{ route('admin.dashboard') }}" title="{{ __('الرئيسية') }}"
           class="nav-link group/link relative mb-1 flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-bold transition
                  {{ request()->routeIs('admin.dashboard') ? 'bg-slate-900 text-white shadow-md shadow-slate-900/10' : 'text-slate-700 hover:bg-slate-50' }}">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg transition
                         {{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white' : 'bg-slate-100 text-slate-600 group-hover/link:bg-white group-hover/link:shadow-sm' }}">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['home'] !!}</svg>
            </span>
            <span class="nav-text">{{ __('نظرة عامة') }}</span>
        </a>

        @php $lastSection = null; @endphp
        @foreach ($menu as $g => $group)
            @if ($group['section'] !== $lastSection)
                <div class="nav-text nav-section mb-1.5 mt-5 px-3 text-[11px] font-bold tracking-wide text-slate-400">{{ $group['section'] }}</div>
                @php $lastSection = $group['section']; @endphp
            @endif

            <details class="nav-group group mb-0.5" data-key="nav-{{ Str::slug($group['icon']) }}" @if ($group['active']) open data-active @endif>
                <summary title="{{ $group['label'] }}"
                         class="flex cursor-pointer select-none items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-bold transition
                                {{ $group['active'] ? 'text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <span class="relative grid h-8 w-8 shrink-0 place-items-center rounded-lg transition
                                 {{ $group['active'] ? $tones[$group['tone']]['solid'].' shadow-md' : $tones[$group['tone']]['soft'] }}">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$group['icon']] !!}</svg>
                        @if ($group['badge'] > 0)
                            <span class="absolute -start-1 -top-1 flex h-3 w-3 group-open:hidden">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-rose-500 ring-2 ring-white"></span>
                            </span>
                        @endif
                    </span>
                    <span class="nav-text flex-1">{{ $group['label'] }}</span>
                    <svg class="nav-text h-4 w-4 text-slate-300 transition-transform duration-200 group-open:rotate-180 group-open:text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">{!! $icons['chevron'] !!}</svg>
                </summary>

                <div class="nav-sub mb-2 ms-[26px] mt-0.5 space-y-0.5 border-s-2 border-slate-100 ps-3">
                    @foreach ($group['items'] as $item)
                        @if ($item['exists'])
                            <a href="{{ route($item['route']).(! empty($item['query']) ? '?'.$item['query'] : '') }}" @if (str_starts_with($item['route'], 'storefront.')) target="_blank" @endif
                               class="nav-link relative flex items-center justify-between rounded-lg px-3 py-2 text-[13px] transition
                                      {{ $item['active'] ? 'bg-blue-50 font-bold text-blue-700' : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                                @if ($item['active'])
                                    <span class="absolute -start-[14px] top-1/2 h-5 w-[2px] -translate-y-1/2 rounded-full bg-blue-600"></span>
                                @endif
                                <span>{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="min-w-[20px] rounded-full bg-rose-500 px-1.5 text-center text-[11px] font-bold leading-5 text-white shadow-sm shadow-rose-500/30">
                                        {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                                    </span>
                                @endif
                            </a>
                        @else
                            <span class="nav-link flex cursor-not-allowed items-center justify-between rounded-lg px-3 py-2 text-[13px] font-medium text-slate-300">
                                <span>{{ $item['label'] }}</span>
                                <span class="rounded-md border border-dashed border-slate-200 px-1.5 text-[10px] font-semibold leading-4 text-slate-400">{{ __('قريباً') }}</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </details>
        @endforeach

        <p id="navEmpty" class="hidden px-3 py-6 text-center text-xs text-slate-400">{{ __('مفيش بند بالاسم ده') }}</p>
    </nav>

    {{-- ═══════ كارت المستخدم ═══════ --}}
    <div class="shrink-0 border-t border-slate-100 p-3">
        <div class="flex items-center gap-3 rounded-2xl bg-slate-50 p-2.5">
            <span class="relative grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 text-sm font-bold text-white">
                {{ mb_substr($user?->name ?? __('؟'), 0, 1) }}
                <span class="absolute -bottom-0.5 -end-0.5 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-slate-50"></span>
            </span>
            <div class="nav-text min-w-0 flex-1">
                <div class="truncate text-[13px] font-bold text-slate-900">{{ $user?->name }}</div>
                <div class="truncate text-[11px] text-slate-500">{{ $roleLabel }}@if ($branchName) · {{ $branchName }}@endif</div>
            </div>
        </div>
    </div>
</aside>

<script>
    (function () {
        const search = document.getElementById('navSearch');
        const empty = document.getElementById('navEmpty');

        // "/" يفتح البحث من أي مكان
        document.addEventListener('keydown', (e) => {
            if (e.key !== '/' || ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
            e.preventDefault();
            document.body.classList.remove('sidebar-mini');
            search?.focus();
        });

        // لما البحث مايلاقيش حاجة
        search?.addEventListener('input', () => {
            requestAnimationFrame(() => {
                const visible = [...document.querySelectorAll('#sidebar .nav-group')].some((g) => g.style.display !== 'none');
                const sections = document.querySelectorAll('#sidebar .nav-section');
                sections.forEach((s) => { s.style.display = search.value.trim() ? 'none' : ''; });
                empty.classList.toggle('hidden', visible || !search.value.trim());
            });
        });

        // Enter يفتح أول نتيجة
        search?.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { search.value = ''; search.dispatchEvent(new Event('input')); search.blur(); }
            if (e.key !== 'Enter') return;
            const first = [...document.querySelectorAll('#sidebar .nav-sub a.nav-link')].find((a) => a.offsetParent !== null);
            if (first) window.location = first.href;
        });
    })();
</script>
