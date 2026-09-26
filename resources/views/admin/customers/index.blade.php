@extends('admin.layouts.app')

@section('title', __('العملاء'))
@section('subtitle', __('كل العملاء ومشترياتهم وأقساطهم'))

@php
    use App\Support\Money;
    $statusStyle = ['active' => 'bg-emerald-50 text-emerald-700', 'inactive' => 'bg-slate-100 text-slate-500', 'blocked' => 'bg-rose-50 text-rose-700'];
    $chips = ['' => __('الكل'), 'buyers' => __('اشتروا'), 'installments' => __('عليهم أقساط'), 'portal' => __('عندهم حساب'), 'leads' => __('لسه مااشتروش')];
@endphp

@section('actions')
    <a href="{{ route('admin.customers.create') }}"
       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('إضافة عميل') }}
    </a>
@endsection

@section('content')
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('كل العملاء') }}</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($totals['all']) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('اشتروا من المعرض') }}</div>
            <div class="mt-2 text-2xl font-extrabold text-blue-600">{{ number_format($totals['buyers']) }}</div>
            <div class="text-xs text-slate-400">{{ $totals['all'] ? round($totals['buyers'] / $totals['all'] * 100) : 0 }}{{ __('% نسبة التحويل') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('المتبقي على العملاء') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">{{ Money::format($totals['remaining'], false) }}</div>
            <div class="text-xs text-slate-400">{{ Money::currency() }}</div>
        </div>
        <a href="{{ route('admin.customers.index', ['filter' => 'installments']) }}"
           class="rounded-2xl p-5 shadow-sm ring-1 transition hover:-translate-y-0.5 {{ $totals['overdue'] ? 'bg-rose-50 ring-rose-200' : 'bg-white ring-slate-200' }}">
            <div class="text-sm {{ $totals['overdue'] ? 'text-rose-700' : 'text-slate-500' }}">{{ __('عملاء عليهم أقساط متأخرة') }}</div>
            <div class="mt-2 text-2xl font-extrabold {{ $totals['overdue'] ? 'text-rose-700' : 'text-slate-900' }}">{{ $totals['overdue'] }}</div>
        </a>
    </div>

    <form method="GET" class="mb-5 space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap gap-3">
            <div class="relative min-w-[240px] flex-1">
                <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/></svg>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('الاسم، التليفون، الرقم المدني، الكود...') }}"
                       class="w-full rounded-xl border border-slate-200 py-2.5 pe-3 ps-9 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
            </div>
            <select name="status" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach ($statuses as $k => $l) <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option> @endforeach
            </select>
            @if ($filters['filter']) <input type="hidden" name="filter" value="{{ $filters['filter'] }}"> @endif
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($chips as $k => $l)
                <a href="{{ route('admin.customers.index', array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'filter' => $k])) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-bold transition {{ (string) $filters['filter'] === $k ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $l }}</a>
            @endforeach
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($customers->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-700">{{ __('مفيش عملاء مطابقين') }}</p>
                <a href="{{ route('admin.customers.create') }}" class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:underline">{{ __('أضف عميل') }}</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العميل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('التواصل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المشتريات') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المتبقي عليه') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('التقييم') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($customers as $c)
                            @php
                                $wa = preg_replace('/\D/', '', (string) $c->phone);
                                $sc = \App\Support\CustomerScore::calculate((int) $c->inst_paid, (int) $c->inst_on_time, (int) $c->overdue_count, (float) $c->avg_days_late, (int) $c->contracts_count);
                                $scTone = ['emerald' => 'bg-emerald-50 text-emerald-700', 'blue' => 'bg-blue-50 text-blue-700', 'amber' => 'bg-amber-50 text-amber-700', 'rose' => 'bg-rose-50 text-rose-700', 'slate' => 'bg-slate-100 text-slate-500'][$sc['tone']];
                            @endphp
                            <tr class="group transition hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.customers.show', $c) }}" class="flex items-center gap-3">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-bold text-white">{{ mb_substr(\App\Support\Lang::pick($c, 'name'), 0, 1) }}</span>
                                        <span>
                                            <span class="block font-bold text-slate-900 group-hover:text-blue-700">{{ \App\Support\Lang::pick($c, 'name') }}</span>
                                            <span class="flex items-center gap-1.5 text-xs text-slate-400">
                                                {{ $c->customer_code }}
                                                @if ($c->portal_user_id) <span class="rounded bg-violet-50 px-1 text-[10px] font-bold text-violet-700">{{ __('بوابة') }}</span> @endif
                                                @if ($c->nationality) · {{ $c->nationality }} @endif
                                            </span>
                                        </span>
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-xs">
                                    <a href="tel:{{ $c->phone }}" class="block font-semibold text-slate-700 hover:text-blue-700" dir="ltr" data-page-align>{{ $c->phone ?: '—' }}</a>
                                    <span class="text-slate-400">{{ $c->email }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($c->contracts_count)
                                        <span class="font-bold text-slate-800">{{ $c->contracts_count }} {{ __('عربية') }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">{{ __('لسه') }}</span>
                                    @endif
                                    @if ($c->drives_count) <div class="text-[11px] text-slate-400">{{ $c->drives_count }} {{ __('تجربة قيادة') }}</div> @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    @if ((float) $c->remaining_total > 0)
                                        <div class="font-bold text-slate-900">{{ Money::format($c->remaining_total, false) }}</div>
                                        @if ($c->overdue_count) <div class="text-[11px] font-bold text-rose-600">{{ $c->overdue_count }} {{ __('قسط متأخر') }}</div> @endif
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $scTone }}" title="{{ $sc['hint'] }}">
                                        @if ($sc['score'] !== null) <span class="font-extrabold">{{ $sc['score'] }}</span> · @endif{{ $sc['label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusStyle[$c->status] ?? '' }}">{{ $statuses[$c->status] ?? $c->status }}</span></td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1">
                                        @if ($wa)
                                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" title="{{ __('واتساب') }}" class="grid h-8 w-8 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20l1.3-3.9A8 8 0 1 1 8 19z"/></svg>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.test-drives.create', ['customer' => $c->id]) }}" title="{{ __('تجربة قيادة') }}" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2"/><path d="M12 14v6.5M10 12H3.5M14 12h6.5"/></svg>
                                        </a>
                                        <a href="{{ route('admin.customers.show', $c) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">{{ __('الملف') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages()) <div class="border-t border-slate-100 p-4">{{ $customers->links() }}</div> @endif
        @endif
    </div>
@endsection
