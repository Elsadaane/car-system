@extends('admin.layouts.app')

@section('title', __('ربح كل عربية'))
@section('subtitle', __('اشتريناها بكام، صرفنا عليها كام، بعناها بكام، وقعدت عندنا قد إيه'))

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $sorts = ['date' => __('الأحدث'), 'profit' => __('الأعلى ربح'), 'margin' => __('أعلى هامش'), 'days' => __('الأسرع بيع')];
    $losers = $sold->where('profit', '<', 0)->count();
@endphp

@section('content')
    @include('admin.reports.partials.header')

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('عربيات اتباعت') }}</div><div class="mt-1 text-2xl font-extrabold">{{ $sold->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('إجمالي الربح') }}</div><div class="mt-1 truncate text-2xl font-extrabold text-emerald-600">{{ Money::format($sold->sum('profit'), false) }}</div>@if ($sold->sum('partner_share') != 0)<div class="text-xs font-semibold text-violet-600">{{ __('منها للشركاء') }} {{ Money::format($sold->sum('partner_share'), false) }}</div>@endif</div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('متوسط ربح العربية') }}</div><div class="mt-1 truncate text-2xl font-extrabold">{{ Money::format($sold->count() ? $sold->avg('profit') : 0, false) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('متوسط أيام البيع') }}</div><div class="mt-1 text-2xl font-extrabold">{{ $sold->count() ? round($sold->avg('days_to_sell')) : '—' }} <span class="text-sm text-slate-400">{{ __('يوم') }}</span></div></div>
        <div class="rounded-2xl p-5 shadow-sm ring-1 {{ $losers ? 'bg-rose-50 ring-rose-200' : 'bg-white ring-slate-200' }}"><div class="text-sm {{ $losers ? 'text-rose-700' : 'text-slate-500' }}">{{ __('اتباعت بخسارة') }}</div><div class="mt-1 text-2xl font-extrabold {{ $losers ? 'text-rose-700' : '' }}">{{ $losers }}</div></div>
    </div>

    <div class="mb-3 flex flex-wrap gap-2 print:hidden">
        @foreach ($sorts as $k => $l)
            <a href="{{ route('admin.reports.profits', array_filter(['from' => $from, 'to' => $to, 'branch' => $branch, 'sort' => $k])) }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $sort === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $l }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('العربية') }}</th><th class="px-3 py-3 text-start">{{ __('اتباعت بـ (بدون ضريبة)') }}</th><th class="px-3 py-3 text-start">{{ __('التكلفة') }}</th><th class="px-3 py-3 text-start">{{ __('مصروفات عليها') }}</th><th class="px-3 py-3 text-start">{{ __('الربح') }}</th><th class="px-3 py-3 text-start">{{ __('الهامش') }}</th><th class="px-3 py-3 text-start">{{ __('قعدت') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sold as $s)
                        <tr class="{{ $s->profit < 0 ? 'bg-rose-50/50' : '' }} hover:bg-slate-50/70">
                            <td class="px-5 py-2.5">
                                <a href="{{ route('admin.vehicles.show', $s->vehicle_id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $s->brand_name }} {{ $s->model_name }} {{ $s->manufacturing_year }}</a>
                                <div class="text-[11px] text-slate-400"><span class="font-mono">{{ $s->vehicle_code }}</span> · <a href="{{ route('admin.sales-contracts.show', $s->id) }}" class="hover:underline">{{ $s->contract_number }}</a> · {{ Carbon::parse($s->contract_date)->format('Y-m-d') }}
                                    @if ($s->is_dealer) · <span class="rounded bg-violet-50 px-1 font-bold text-violet-700">{{ __('تفويض') }}</span> @endif</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-bold">{{ Money::format($s->net_sale, false) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ Money::format($s->cost, false) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-xs text-slate-500">{{ $s->extra_cost ? Money::format($s->extra_cost, false) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-extrabold {{ $s->profit < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ Money::format($s->profit, false) }}@if (($s->partner_share ?? 0) != 0)<div class="text-[11px] font-semibold text-violet-600">{{ __('للشركاء') }} {{ Money::format($s->partner_share, false) }} · {{ __('للمعرض') }} {{ Money::format($s->showroom_profit, false) }}</div>@endif</td>
                            <td class="px-3 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $s->margin >= 10 ? 'bg-emerald-50 text-emerald-700' : ($s->margin >= 0 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">{{ $s->margin }}%</span></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-xs">{{ $s->days_to_sell }} {{ __('يوم') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">{{ __('مفيش مبيعات في المدة دي') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-slate-400">{{ __('عربيات التفويض: التكلفة = المستحق لصاحب العربية (يعني الربح = عمولة المعرض + أي فرق سعر). المصروفات = سندات صرف متربطة بالعربية.') }}</p>
@endsection
