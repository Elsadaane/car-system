@extends('admin.layouts.app')

@section('title', __('الفروع'))
@section('subtitle', __('كل فروع المعرض ومخزونها ومبيعاتها'))

@php
    $money = fn ($v) => number_format((float) $v, (int) config('laroville.decimals', 3)).' '.config('laroville.currency');
    $canManage = auth()->user()->canManageSettings();
@endphp

@section('actions')
    @if ($canManage)
        <a href="{{ route('admin.branches.create') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            {{ __('إضافة فرع') }}
        </a>
    @endif
@endsection

@section('content')

    {{-- الأرقام الإجمالية --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['label' => __('عدد الفروع'),         'value' => $totals['branches'],           'bar' => 'bg-blue-500'],
            ['label' => __('فروع نشطة'),          'value' => $totals['active'],             'bar' => 'bg-emerald-500'],
            ['label' => __('إجمالي العربيات'),    'value' => $totals['vehicles'],           'bar' => 'bg-amber-400'],
            ['label' => __('مبيعات الشهر'),       'value' => $money($totals['month_sales']), 'bar' => 'bg-violet-500'],
        ] as $card)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-sm text-slate-500">{{ $card['label'] }}</div>
                <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">{{ $card['value'] }}</div>
                <div class="mt-3 h-1 w-10 rounded-full {{ $card['bar'] }}"></div>
            </div>
        @endforeach
    </div>

    {{-- البحث والفلترة --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="relative min-w-[220px] flex-1">
            <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ابحث بالاسم أو الكود أو المدينة...') }}"
                   class="w-full rounded-xl border border-slate-200 py-2.5 pe-3 ps-9 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        </div>
        <select name="status" onchange="this.form.submit()"
                class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400">
            <option value="">{{ __('كل الحالات') }}</option>
            <option value="active" @selected(request('status') === 'active')>{{ __('نشط') }}</option>
            <option value="inactive" @selected(request('status') === 'inactive')>{{ __('غير نشط') }}</option>
        </select>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        @if ($search !== '' || request('status'))
            <a href="{{ route('admin.branches.index') }}" class="text-sm text-slate-500 hover:text-slate-900">{{ __('مسح الفلاتر') }}</a>
        @endif
    </form>

    {{-- الفروع --}}
    @if ($branches->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 4l9 6.5V20H3z"/><path d="M9.5 20v-5h5v5"/></svg>
            </div>
            <p class="mt-4 font-bold text-slate-700">{{ __('مفيش فروع مطابقة للبحث') }}</p>
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($branches as $branch)
                @php
                    $total = max(1, (int) $branch->vehicles_count);
                    $bars = [
                        ['n' => (int) $branch->available_count, 'c' => 'bg-emerald-500', 'l' => __('متاحة')],
                        ['n' => (int) $branch->reserved_count,  'c' => 'bg-amber-400',   'l' => __('محجوزة')],
                        ['n' => (int) $branch->sold_count,      'c' => 'bg-blue-500',    'l' => __('مباعة')],
                    ];
                @endphp

                <a href="{{ route('admin.branches.show', $branch) }}"
                   class="group flex flex-col rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-blue-200">

                    <div class="flex items-start gap-3 p-5 pb-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $branch->is_main ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 4l9 6.5V20H3z"/><path d="M9.5 20v-5h5v5"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-base font-extrabold text-slate-900 group-hover:text-blue-700">{{ \App\Support\Lang::pick($branch, 'name') }}</h3>
                                @if ($branch->is_main)
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700">{{ __('رئيسي') }}</span>
                                @endif
                            </div>
                            <div class="mt-0.5 text-xs text-slate-500">
                                {{ $branch->branch_code }}@if ($branch->city) · {{ $branch->city }}@endif
                            </div>
                        </div>
                        <span class="flex items-center gap-1.5 text-xs font-semibold {{ $branch->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">
                            <span class="h-2 w-2 rounded-full {{ $branch->status === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            {{ $branch->status === 'active' ? __('نشط') : __('غير نشط') }}
                        </span>
                    </div>

                    {{-- توزيع العربيات --}}
                    <div class="px-5">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-slate-500">{{ __('العربيات') }}</span>
                            <span class="text-lg font-extrabold text-slate-900">{{ (int) $branch->vehicles_count }}</span>
                        </div>
                        <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-slate-100">
                            @foreach ($bars as $bar)
                                @if ($bar['n'] > 0)
                                    <div class="{{ $bar['c'] }}" style="width: {{ round($bar['n'] / $total * 100, 1) }}%"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="mt-2 flex gap-4 text-xs text-slate-500">
                            @foreach ($bars as $bar)
                                <span class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full {{ $bar['c'] }}"></span>{{ $bar['l'] }} {{ $bar['n'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-3 divide-x divide-x-reverse divide-slate-100 border-t border-slate-100 text-center">
                        <div class="px-2 py-3">
                            <div class="text-[11px] text-slate-400">{{ __('مبيعات الشهر') }}</div>
                            <div class="mt-0.5 truncate text-sm font-bold text-slate-800">{{ number_format((float) $branch->month_sales, 0) }}</div>
                        </div>
                        <div class="px-2 py-3">
                            <div class="text-[11px] text-slate-400">{{ __('الموظفين') }}</div>
                            <div class="mt-0.5 text-sm font-bold text-slate-800">{{ (int) $branch->staff_count }}</div>
                        </div>
                        <div class="px-2 py-3">
                            <div class="text-[11px] text-slate-400">{{ __('المدير') }}</div>
                            <div class="mt-0.5 truncate text-sm font-bold text-slate-800">{{ $branch->manager_name ?? '—' }}</div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

@endsection
