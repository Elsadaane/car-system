@extends('admin.layouts.app')

@section('title', __('تقرير المبيعات'))
@section('subtitle', __('اتجاه البيع آخر 12 شهر + تفصيل بالماركة والبياع وطريقة الدفع'))

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $maxM = max(1, $months->max('total'));
@endphp

@section('content')
    @include('admin.reports.partials.header')

    <section class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold text-slate-900">{{ __('آخر 12 شهر') }}</h2><span class="text-xs text-slate-400">{{ __('العمود = المبيعات · الأخضر = الربح') }}</span></div>
        <div class="flex h-56 items-end gap-2">
            @foreach ($months as $m)
                <div class="group relative flex h-full flex-1 flex-col items-center justify-end">
                    <div class="pointer-events-none absolute -top-2 z-10 hidden -translate-y-full whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[11px] text-white group-hover:block">{{ $m['count'] }} {{ __('عربية ·') }} {{ Money::format($m['total'], false) }} {{ __('· ربح') }} {{ Money::format($m['profit'], false) }}</div>
                    <div class="relative w-full overflow-hidden rounded-t-lg bg-blue-500/90 transition group-hover:bg-blue-600" style="height: {{ max(2, $m['total'] / $maxM * 100) }}%">
                        <div class="absolute inset-x-0 bottom-0 bg-emerald-400/80" style="height: {{ $m['total'] > 0 ? max(0, min(100, $m['profit'] / $m['total'] * 100)) : 0 }}%"></div>
                    </div>
                    <div class="mt-1.5 text-[10px] font-bold text-slate-500">{{ $m['label'] }}</div>
                    <div class="text-[10px] text-slate-400">{{ $m['count'] ?: '' }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mb-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        @include('admin.reports.partials.bars', ['title' => __('بالماركة'), 'items' => $brands->take(8), 'color' => 'bg-blue-500'])
        @include('admin.reports.partials.bars', ['title' => __('بالبياع'), 'items' => $sellers, 'color' => 'bg-violet-500'])
        @include('admin.reports.partials.bars', ['title' => __('كاش ولا تقسيط'), 'items' => $types, 'color' => 'bg-emerald-500'])
        @include('admin.reports.partials.bars', ['title' => __('بسنة الصنع'), 'items' => $years->take(8), 'color' => 'bg-amber-500'])
    </div>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-100 px-6 py-4 font-extrabold text-slate-900">{{ __('كل البيعات في المدة (') }}{{ $sold->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('العقد') }}</th><th class="px-3 py-3 text-start">{{ __('العربية') }}</th><th class="px-3 py-3 text-start">{{ __('العميل') }}</th><th class="px-3 py-3 text-start">{{ __('البياع / الفرع') }}</th><th class="px-3 py-3 text-start">{{ __('الإجمالي') }}</th><th class="px-3 py-3 text-start">{{ __('اتحصّل') }}</th><th class="px-3 py-3 text-start">{{ __('الربح') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sold->sortByDesc('contract_date') as $s)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-2.5"><a href="{{ route('admin.sales-contracts.show', $s->id) }}" class="font-mono text-xs font-bold text-blue-700 hover:underline">{{ $s->contract_number }}</a><div class="text-[11px] text-slate-400">{{ Carbon::parse($s->contract_date)->format('Y-m-d') }} · {{ $s->payment_label }}</div></td>
                            <td class="px-3 py-2.5">{{ $s->brand_name }} {{ $s->model_name }} {{ $s->manufacturing_year }}</td>
                            <td class="px-3 py-2.5">{{ $s->customer_name }}</td>
                            <td class="px-3 py-2.5 text-xs">{{ $s->user_name }}<div class="text-slate-400">{{ $s->branch_name }}</div></td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-bold">{{ Money::format($s->total_amount, false) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-xs">{{ Money::format($s->paid_amount, false) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-bold {{ $s->profit < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ Money::format($s->profit, false) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">{{ __('مفيش مبيعات في المدة دي') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($sold->isNotEmpty())
                    <tfoot class="bg-slate-50 font-extrabold"><tr><td colspan="4" class="px-5 py-3">{{ __('الإجمالي') }}</td><td class="px-3 py-3">{{ Money::format($sold->sum('total_amount'), false) }}</td><td class="px-3 py-3">{{ Money::format($sold->sum('paid_amount'), false) }}</td><td class="px-3 py-3 text-emerald-700">{{ Money::format($sold->sum('profit'), false) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </section>
@endsection
