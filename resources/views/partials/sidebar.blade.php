@php
    $modules = \App\Support\Module::class;

    $newInquiries = 0;
    if ($modules::enabled('storefront') && class_exists(\App\Models\VehicleInquiry::class)) {
        $newInquiries = \App\Models\VehicleInquiry::where('status', 'new')->count();
    }

    $icons = [
        'home'     => '<path d="M3 10.5 12 4l9 6.5V20H3z"/><path d="M9.5 20v-5h5v5"/>',
        'sales'    => '<path d="M4 5h2l2 10h10l2-7H7"/><circle cx="9.5" cy="19" r="1.3"/><circle cx="16.5" cy="19" r="1.3"/>',
        'car'      => '<path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v2H3z"/><circle cx="7.5" cy="13" r="1"/><circle cx="16.5" cy="13" r="1"/>',
        'money'    => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.3-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.8c1.6.9 2.6 2.6 3 5.2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>',
    ];

    $menu = [
        ['label' => 'المبيعات', 'icon' => 'sales', 'items' => [
            ['label' => 'الاستفسارات',   'route' => 'admin.inquiries.index', 'module' => 'storefront', 'badge' => $newInquiries],
            ['label' => 'عقود البيع',    'route' => 'admin.sales-contracts.index'],
            ['label' => 'عقود التسليم',  'route' => 'admin.deliveries.index', 'module' => 'delivery_contracts'],
            ['label' => 'طلبات الإلغاء', 'route' => 'admin.cancellations.index'],
        ]],
        ['label' => 'المخزون', 'icon' => 'car', 'items' => [
            ['label' => 'المركبات',           'route' => 'admin.vehicles.index'],
            ['label' => 'الماركات والموديلات', 'route' => 'admin.brands.index'],
            ['label' => 'الموردين',           'route' => 'admin.suppliers.index', 'admin' => true],
            ['label' => 'عقود التفويض',       'route' => 'admin.dealer-contracts.index'],
        ]],
        ['label' => 'المالية', 'icon' => 'money', 'items' => [
            ['label' => 'المدفوعات', 'route' => 'admin.payments.index'],
            ['label' => 'الأقساط',   'route' => 'admin.installments.index', 'module' => 'installments'],
            ['label' => 'التمويل',   'route' => 'admin.finance.index',      'module' => 'finance'],
            ['label' => 'المصروفات', 'route' => 'admin.expenses.index'],
        ]],
        ['label' => 'العملاء', 'icon' => 'users', 'items' => [
            ['label' => 'العملاء',        'route' => 'admin.customers.index'],
            ['label' => 'تجارب القيادة', 'route' => 'admin.test-drives.index', 'module' => 'test_drives'],
        ]],
        ['label' => 'النظام', 'icon' => 'settings', 'items' => [
            ['label' => 'الفروع',    'route' => 'admin.branches.index'],
            ['label' => 'التقارير',  'route' => 'admin.reports.index'],
            ['label' => 'الإعدادات', 'route' => 'admin.settings.index'],
        ]],
    ];

    foreach ($menu as $g => $group) {
        $menu[$g]['items'] = array_values(array_filter(
            $group['items'],
            fn ($item) => (! isset($item['module']) || $modules::enabled($item['module']))
                && (empty($item['admin']) || auth()->user()?->isAdmin())
        ));
        $menu[$g]['active'] = collect($menu[$g]['items'])->contains(
            fn ($item) => request()->routeIs(\Illuminate\Support\Str::beforeLast($item['route'], '.').'.*')
        );
        $menu[$g]['badge'] = collect($menu[$g]['items'])->sum(fn ($item) => $item['badge'] ?? 0);
    }
    $menu = array_filter($menu, fn ($group) => count($group['items']) > 0);
@endphp

<aside id="sidebar"
       class="fixed inset-y-0 right-0 z-40 flex w-64 translate-x-full flex-col border-l border-slate-200 bg-white transition-all duration-200 lg:translate-x-0">

    {{-- الشعار --}}
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-5">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-blue-600 text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['car'] !!}</svg>
        </span>
        <span class="nav-text truncate text-base font-extrabold text-slate-900">{{ config('app.name') }}</span>
    </div>

    {{-- بحث في القائمة --}}
    <div class="nav-text px-4 pt-4">
        <div class="relative">
            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
            <input id="navSearch" type="search" placeholder="ابحث في القائمة..."
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-3 pr-9 text-sm outline-none transition focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-50">
        </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">

        {{-- الرئيسية --}}
        <a href="{{ route('admin.dashboard') }}" title="الرئيسية"
           class="nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition
                  {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $icons['home'] !!}</svg>
            <span class="nav-text">الرئيسية</span>
        </a>

        @foreach ($menu as $g => $group)
            <details class="nav-group group" data-key="nav-{{ $g }}" @if ($group['active']) open data-active @endif>
                <summary title="{{ $group['label'] }}"
                         class="flex cursor-pointer select-none items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition
                                {{ $group['active'] ? 'text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <span class="relative shrink-0">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$group['icon']] !!}</svg>
                        @if ($group['badge'] > 0)
                            <span class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white group-open:hidden"></span>
                        @endif
                    </span>
                    <span class="nav-text flex-1">{{ $group['label'] }}</span>
                    <svg class="nav-text h-4 w-4 text-slate-400 transition-transform duration-200 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>

                <div class="nav-sub mt-1 space-y-0.5 border-r-2 border-slate-100 pr-3 mr-5">
                    @foreach ($group['items'] as $item)
                        @php
                            $exists = \Illuminate\Support\Facades\Route::has($item['route']);
                            $active = $exists && request()->routeIs(\Illuminate\Support\Str::beforeLast($item['route'], '.').'.*');
                        @endphp

                        @if ($exists)
                            <a href="{{ route($item['route']) }}"
                               class="nav-link flex items-center justify-between rounded-lg px-3 py-2 text-sm transition
                                      {{ $active ? 'bg-blue-50 font-bold text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                <span>{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] font-bold leading-5 text-white">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @else
                            <span class="nav-link flex cursor-not-allowed items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-400">
                                <span>{{ $item['label'] }}</span>
                                <span class="rounded-md bg-slate-100 px-1.5 text-[10px] font-semibold leading-5 text-slate-400">قريباً</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </details>
        @endforeach
    </nav>

    <div class="nav-text border-t border-slate-100 px-5 py-3 text-[11px] text-slate-400">
        @if (\App\Models\Branch::isMultiBranch() && auth()->user()?->branch)
            <div class="mb-1 flex items-center gap-1.5 font-semibold text-slate-500">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ auth()->user()->branch->name_ar }}
            </div>
        @endif
        {{ config('laroville.license.client_name') }}
    </div>
</aside>
