@extends('admin.layouts.app')

@section('title', __('المركبات'))
@section('subtitle', __('كل عربيات المعرض ومكانها وحالتها'))

@php
    $user = auth()->user();
    $statusStyle = [
        'available' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'reserved' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'sold' => 'bg-blue-50 text-blue-700 ring-blue-200', 'maintenance' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'damaged' => 'bg-rose-50 text-rose-700 ring-rose-200', 'returned' => 'bg-slate-100 text-slate-500 ring-slate-200',
    ];
    $total = $statusCounts->sum();
    $activeFilters = collect($filters)->except(['status'])->filter(fn ($v) => $v !== null && $v !== '')->count();
    $url = fn (array $merge) => route('admin.vehicles.index', array_filter(array_merge($filters, $merge), fn ($v) => $v !== null && $v !== ''));
@endphp

@section('actions')
    <a href="{{ route('admin.vehicles.create') }}"
       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('إضافة مركبة') }}
    </a>
@endsection

@section('content')

    {{-- ═══════ تبويبات الحالة ═══════ --}}
    <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
        <a href="{{ $url(['status' => null]) }}"
           class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition {{ ! $filters['status'] ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
            {{ __('الكل') }} <span class="rounded-full px-1.5 text-xs {{ ! $filters['status'] ? 'bg-white/20' : 'bg-slate-100' }}">{{ $total }}</span>
        </a>
        @foreach ($statuses as $key => $label)
            @php $n = (int) ($statusCounts[$key] ?? 0); @endphp
            @continue($n === 0 && $filters['status'] !== $key)
            <a href="{{ $url(['status' => $key]) }}"
               class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition {{ $filters['status'] === $key ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                {{ $label }} <span class="rounded-full px-1.5 text-xs {{ $filters['status'] === $key ? 'bg-white/20' : 'bg-slate-100' }}">{{ $n }}</span>
            </a>
        @endforeach
    </div>

    {{-- ═══════ الفلاتر ═══════ --}}
    <form method="GET" class="mb-5 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
        <div class="flex flex-wrap gap-3">
            <div class="relative min-w-[240px] flex-1">
                <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('الكود، اللوحة، الشاسيه، الماركة، الطراز...') }}"
                       class="w-full rounded-xl border border-slate-200 py-2.5 pe-3 ps-9 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
            </div>
            <button type="button" onclick="document.getElementById('moreFilters').classList.toggle('hidden')"
                    class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                {{ __('فلاتر') }} @if ($activeFilters - ($filters['q'] !== '' ? 1 : 0) > 0)<span class="rounded-full bg-blue-600 px-1.5 text-xs text-white">{{ $activeFilters - ($filters['q'] !== '' ? 1 : 0) }}</span>@endif
            </button>
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        </div>

        <div id="moreFilters" class="mt-3 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-6 {{ $activeFilters - ($filters['q'] !== '' ? 1 : 0) > 0 ? '' : 'hidden' }}">
            <select name="brand" id="fBrand" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                <option value="">{{ __('كل الماركات') }}</option>
                @foreach ($brands as $id => $name) <option value="{{ $id }}" @selected($filters['brand'] === $id)>{{ $name }}</option> @endforeach
            </select>
            <select name="model" id="fModel" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                <option value="">{{ __('كل الطرازات') }}</option>
                @foreach ($models as $m) <option value="{{ $m->id }}" data-brand="{{ $m->brand_id }}" @selected($filters['model'] === $m->id)>{{ \App\Support\Lang::pick($m, 'name') }} {{ $m->year }}</option> @endforeach
            </select>
            @if (\App\Support\Access::can('suppliers.view'))
                <select name="supplier" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                    <option value="">{{ __('كل الموردين') }}</option>
                    @foreach ($suppliers as $id => $name) <option value="{{ $id }}" @selected($filters['supplier'] === $id)>{{ $name }}</option> @endforeach
                </select>
            @endif
            <select name="source" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                <option value="">{{ __('كل جهات الاستيراد') }}</option>
                @foreach ($sources as $id => $name) <option value="{{ $id }}" @selected($filters['source'] === $id)>{{ $name }}</option> @endforeach
            </select>
            @if ($branches->count() > 1 && $user->canSeeAllBranches())
                <select name="branch" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                    <option value="">{{ __('كل الفروع') }}</option>
                    @foreach ($branches as $id => $name) <option value="{{ $id }}" @selected($filters['branch'] === $id)>{{ $name }}</option> @endforeach
                </select>
            @endif
            <select name="published" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                <option value="">{{ __('المتجر: الكل') }}</option>
                <option value="1" @selected($filters['published'] === '1')>{{ __('منشورة') }}</option>
                <option value="0" @selected($filters['published'] === '0')>{{ __('مش منشورة') }}</option>
            </select>
            @if ($activeFilters > 0)
                <a href="{{ route('admin.vehicles.index', array_filter(['status' => $filters['status']])) }}" class="self-center text-sm text-slate-500 hover:text-slate-900">{{ __('مسح الفلاتر') }}</a>
            @endif
        </div>
    </form>

    {{-- ═══════ الجدول ═══════ --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($vehicles->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17V12l1.8-4.6A2 2 0 0 1 8.7 6h6.6a2 2 0 0 1 1.9 1.4L19 12v5"/><path d="M3 17h18v2.5H3z"/></svg>
                </div>
                <p class="mt-4 font-bold text-slate-700">{{ __('مفيش عربيات مطابقة') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العربية') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('اللوحة / الشاسيه') }}</th>
                            @if (\App\Support\Access::can('suppliers.view')) <th class="px-3 py-3 text-start font-semibold">{{ __('المورد') }}</th> @endif
                            <th class="px-3 py-3 text-start font-semibold">{{ __('سعر البيع') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العقود') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ __('المتجر') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($vehicles as $v)
                            @php $cover = \App\Support\Media::url(\App\Support\Media::list($v->images)[0] ?? null); @endphp
                            <tr class="group transition hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.vehicles.show', $v) }}" class="flex items-center gap-3">
                                        <div class="h-14 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200">
                                            @if ($cover)
                                                <img src="{{ $cover }}" alt="" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                                            @else
                                                <div class="grid h-full place-items-center text-slate-300">
                                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17V12l1.8-4.6A2 2 0 0 1 8.7 6h6.6a2 2 0 0 1 1.9 1.4L19 12v5"/><path d="M3 17h18v2.5H3z"/></svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="truncate font-bold text-slate-900 group-hover:text-blue-700">
                                                {{ $v->brand_name }} {{ $v->model_name }} <span class="font-normal text-slate-500">{{ $v->manufacturing_year }}</span>
                                            </div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-400">
                                                <span class="font-mono">{{ $v->vehicle_code }}</span>
                                                <span>·</span><span>{{ $v->color }}</span>
                                                @if ($v->source_name) <span>·</span><span>{{ $v->source_name }}</span> @endif
                                                @if ($branches->count() > 1) <span>·</span><span class="text-slate-500">{{ $v->branch_name }}</span> @endif
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-xs" dir="ltr" data-page-align>
                                    <div class="font-semibold text-slate-700">{{ $v->plate_number ?: __('بدون لوحة') }}</div>
                                    <div class="font-mono text-slate-400">{{ $v->chassis_number ?: '—' }}</div>
                                </td>
                                @if (\App\Support\Access::can('suppliers.view'))
                                    <td class="px-3 py-3 text-slate-600">
                                        @if ($v->supplier_id)
                                            <a href="{{ route('admin.suppliers.show', $v->supplier_id) }}" class="hover:text-blue-700">{{ $v->supplier_name }}</a>
                                        @else — @endif
                                    </td>
                                @endif
                                <td class="whitespace-nowrap px-3 py-3">
                                    @if ($v->price_on_request)
                                        <span class="text-xs text-slate-500">{{ __('عند الطلب') }}</span>
                                    @elseif ($v->discount_price)
                                        <div class="font-bold text-emerald-700">{{ number_format((float) $v->discount_price) }}</div>
                                        <div class="text-xs text-slate-400 line-through">{{ number_format((float) $v->selling_price) }}</div>
                                    @elseif ($v->selling_price)
                                        <div class="font-bold text-slate-900">{{ number_format((float) $v->selling_price) }}</div>
                                    @else
                                        <span class="text-xs text-amber-600">{{ __('بدون سعر') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $statusStyle[$v->status] ?? '' }}">{{ $statuses[$v->status] ?? $v->status }}</span>
                                </td>
                                <td class="px-3 py-3 text-xs">
                                    @if ($v->dealer_contract_number)
                                        <div class="text-slate-500">{{ __('تفويض:') }} <span class="font-mono text-slate-700">{{ $v->dealer_contract_number }}</span></div>
                                    @endif
                                    @if ($v->sales_contract_number)
                                        <div class="text-slate-500">{{ __('بيع:') }} <span class="font-mono text-blue-700">{{ $v->sales_contract_number }}</span></div>
                                    @endif
                                    @if (! $v->dealer_contract_number && ! $v->sales_contract_number) <span class="text-slate-300">—</span> @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <form method="POST" action="{{ route('admin.vehicles.publish', $v) }}">
                                        @csrf @method('PATCH')
                                        <button title="{{ $v->is_published ? __('اضغط للإخفاء من المتجر') : __('اضغط للنشر في المتجر') }}"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $v->is_published ? 'bg-emerald-500' : 'bg-slate-200' }}"
                                                @disabled(in_array($v->status, ['sold', 'returned'], true))>
                                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition {{ $v->is_published ? '-translate-x-5' : '-translate-x-0.5' }}"></span>
                                        </button>
                                    </form>
                                    @if ($v->is_published && $v->views_count)
                                        <div class="mt-1 text-[10px] text-slate-400">{{ number_format($v->views_count) }} {{ __('مشاهدة') }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1">
                                        <a href="{{ route('admin.vehicles.edit', $v) }}" title="{{ __('تعديل') }}" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                                        </a>
                                        <a href="{{ route('admin.vehicles.show', $v) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">{{ __('التفاصيل') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($vehicles->hasPages())
                <div class="border-t border-slate-100 p-4">{{ $vehicles->links() }}</div>
            @endif
        @endif
    </div>

    <script>
        (function () {
            const brand = document.getElementById('fBrand');
            const model = document.getElementById('fModel');
            const sync = () => {
                [...model.options].forEach((o) => { if (o.dataset.brand) o.hidden = brand.value && o.dataset.brand !== brand.value; });
                if (model.selectedOptions[0]?.hidden) model.value = '';
            };
            brand.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
