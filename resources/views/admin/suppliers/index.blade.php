@extends('admin.layouts.app')

@section('title', __('الموردين'))
@section('subtitle', __('حسابات الموردين وأقساطهم وتقييمهم'))

@php
    $money = fn ($v) => number_format((float) $v, (int) config('laroville.decimals', 3));
    $currency = config('laroville.currency');
    $statusStyle = [
        'active'      => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'inactive'    => 'bg-slate-100 text-slate-500 ring-slate-200',
        'suspended'   => 'bg-amber-50 text-amber-700 ring-amber-200',
        'blacklisted' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
    $toneStyle = [
        'emerald' => 'text-emerald-600', 'blue' => 'text-blue-600',
        'amber' => 'text-amber-500', 'rose' => 'text-rose-600', 'slate' => 'text-slate-300',
    ];
@endphp

@section('actions')
    <a href="{{ route('admin.suppliers.create') }}"
       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('إضافة مورد') }}
    </a>
@endsection

@section('content')

    {{-- ═══════ الأرقام ═══════ --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('عدد الموردين') }}</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $totals['suppliers'] }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('إجمالي المستحق للموردين') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">{{ $money($totals['balance']) }}</div>
            <div class="text-xs text-slate-400">{{ $currency }}</div>
        </div>
        <a href="{{ route('admin.suppliers.index', ['overdue' => 1]) }}"
           class="rounded-2xl p-5 shadow-sm ring-1 transition hover:-translate-y-0.5 {{ $totals['overdue'] > 0 ? 'bg-rose-50 ring-rose-200' : 'bg-white ring-slate-200' }}">
            <div class="text-sm {{ $totals['overdue'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">{{ __('أقساط متأخرة') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold {{ $totals['overdue'] > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $money($totals['overdue']) }}</div>
            <div class="text-xs {{ $totals['overdue'] > 0 ? 'text-rose-500' : 'text-slate-400' }}">{{ __('اضغط للعرض') }}</div>
        </a>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('مستحق خلال 7 أيام') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-amber-600">{{ $money($totals['due_week']) }}</div>
            <div class="text-xs text-slate-400">{{ $currency }}</div>
        </div>
    </div>

    {{-- ═══════ الفلاتر ═══════ --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="relative min-w-[220px] flex-1">
            <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('الاسم، الكود، التليفون، البطاقة...') }}"
                   class="w-full rounded-xl border border-slate-200 py-2.5 pe-3 ps-9 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        </div>
        <select name="status" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400">
            <option value="">{{ __('كل الحالات') }}</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-600 has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50 has-[:checked]:text-rose-700">
            <input type="checkbox" name="overdue" value="1" class="accent-rose-600" @checked($filters['overdue']) onchange="this.form.submit()">
            {{ __('عليهم متأخرات') }}
        </label>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        @if ($filters['q'] !== '' || $filters['status'] || $filters['overdue'])
            <a href="{{ route('admin.suppliers.index') }}" class="text-sm text-slate-500 hover:text-slate-900">{{ __('مسح الفلاتر') }}</a>
        @endif
    </form>

    {{-- ═══════ الجدول ═══════ --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($suppliers->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-400">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/></svg>
                </div>
                <p class="mt-4 font-bold text-slate-700">{{ __('مفيش موردين هنا') }}</p>
                <a href="{{ route('admin.suppliers.create') }}" class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:underline">{{ __('أضف أول مورد') }}</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('المورد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العربيات') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الرصيد المستحق') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('أقرب قسط') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('التقييم') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3 text-end font-semibold">{{ __('إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($suppliers as $supplier)
                            @php
                                $rating = \App\Support\SupplierAccount::rating((int) $supplier->vehicles_count, (int) $supplier->returns_count);
                                $balance = (float) $supplier->current_balance;
                                $overdue = (float) $supplier->overdue_amount;
                                $wa = preg_replace('/\D/', '', (string) $supplier->phone);
                            @endphp
                            <tr class="group transition hover:bg-slate-50/70">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('admin.suppliers.show', $supplier) }}" class="font-bold text-slate-900 hover:text-blue-700">{{ \App\Support\Lang::pick($supplier, 'name') }}</a>
                                    <div class="mt-0.5 text-xs text-slate-400">
                                        {{ $supplier->supplier_code }}
                                        @if ($supplier->phone) · <a href="tel:{{ $supplier->phone }}" class="hover:text-blue-600" dir="ltr">{{ $supplier->phone }}</a> @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="font-bold text-slate-800">{{ (int) $supplier->vehicles_count }}</span>
                                    @if ($supplier->returns_count > 0)
                                        <span class="ms-1 rounded bg-rose-50 px-1.5 text-[11px] font-bold text-rose-600">{{ $supplier->returns_count }} {{ __('مرتجع') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    @if ($balance > 0)
                                        <div class="font-bold text-slate-900">{{ $money($balance) }}</div>
                                        @if ($overdue > 0)
                                            <div class="text-[11px] font-semibold text-rose-600">{{ __('متأخر') }} {{ $money($overdue) }}</div>
                                        @endif
                                    @elseif ($balance < 0)
                                        <div class="font-bold text-emerald-600">{{ $money(abs($balance)) }}</div>
                                        <div class="text-[11px] text-emerald-600">{{ __('لكم عند المورد') }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">{{ __('خالص') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs">
                                    @if ($supplier->next_due_date)
                                        @php $days = (int) today()->diffInDays(\Illuminate\Support\Carbon::parse($supplier->next_due_date), false); @endphp
                                        <div class="font-semibold text-slate-700">{{ \Illuminate\Support\Carbon::parse($supplier->next_due_date)->format('Y-m-d') }}</div>
                                        <div class="{{ $days <= 7 ? 'text-amber-600' : 'text-slate-400' }}">{{ $days === 0 ? __('النهارده') : __('بعد ').$days.__(' يوم') }}</div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-1 {{ $toneStyle[$rating['tone']] }}">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <svg class="h-3.5 w-3.5 {{ $rating['score'] !== null && $i <= round($rating['score']) ? '' : 'opacity-25' }}" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>
                                        @endfor
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-slate-500">{{ $rating['label'] }}</div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $statusStyle[$supplier->status] ?? '' }}">
                                        {{ $statuses[$supplier->status] ?? $supplier->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($wa)
                                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" title="{{ __('واتساب') }}"
                                               class="grid h-8 w-8 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20l1.3-3.9A8 8 0 1 1 8 19z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1c-1-.5-1.5-1-2-2l1-1-1-2z"/></svg>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.suppliers.edit', $supplier) }}" title="{{ __('تعديل') }}"
                                           class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                                        </a>
                                        <a href="{{ route('admin.suppliers.show', $supplier) }}"
                                           class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">{{ __('الحساب') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="border-t border-slate-100 p-4">{{ $suppliers->links() }}</div>
            @endif
        @endif
    </div>
@endsection
