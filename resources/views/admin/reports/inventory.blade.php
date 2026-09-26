@extends('admin.layouts.app')

@section('title', __('المخزون وأعماره'))
@section('subtitle', __('العربيات اللي عندك دلوقتي — وأنهي عربية بقالها كتير ومحتاجة حركة'))

@php
    use App\Support\Money;
    $colors = ['0-30' => 'from-emerald-500 to-emerald-600', '31-60' => 'from-blue-500 to-blue-600', '61-90' => 'from-amber-500 to-amber-600', '+90' => 'from-rose-500 to-rose-600'];
@endphp

@section('content')
    @include('admin.reports.partials.header', ['noDates' => true])

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($buckets as $k => $b)
            <div class="rounded-2xl bg-gradient-to-br {{ $colors[$k] }} p-5 text-white shadow-lg">
                <div class="text-sm text-white/80">{{ __('قاعدة') }} {{ $k }} {{ __('يوم') }}</div>
                <div class="mt-1 text-3xl font-extrabold">{{ $b['count'] }}</div>
                <div class="text-xs text-white/80">{{ Money::format($b['value'], false) }}</div>
            </div>
        @endforeach
    </div>

    @php $stale = $rows->where('age', '>', 90); @endphp
    @if ($stale->isNotEmpty())
        <div class="mb-6 rounded-2xl bg-rose-50 p-5 text-sm text-rose-900 ring-1 ring-rose-200">
            <b>⚠️ {{ $stale->count() }} {{ __('عربية بقالها أكتر من 3 شهور') }}</b> {{ __('— فلوس متجمّدة') }} {{ Money::format($stale->sum('cost')) }}{{ __('. اقتراحات: نزّل السعر شوية، اعمل عليها إعلان ممول، أو اعرضها في الواجهة.') }}
            @php $noAds = $stale->where('is_published', false)->count(); @endphp
            @if ($noAds) <div class="mt-1 font-bold">{{ __('منهم') }} {{ $noAds }} {{ __('مش منشورين في المتجر أصلاً!') }}</div> @endif
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('العربية') }}</th><th class="px-3 py-3 text-start">{{ __('الفرع') }}</th><th class="px-3 py-3 text-start">{{ __('بقالها') }}</th><th class="px-3 py-3 text-start">{{ __('التكلفة') }}</th><th class="px-3 py-3 text-start">{{ __('سعر العرض') }}</th><th class="px-3 py-3 text-start">{{ __('الربح المتوقع') }}</th><th class="px-3 py-3 text-start">{{ __('الاهتمام') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $v)
                        <tr class="{{ $v->age > 90 ? 'bg-rose-50/40' : '' }} hover:bg-slate-50/70">
                            <td class="px-5 py-2.5">
                                <a href="{{ route('admin.vehicles.show', $v->id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $v->brand_name }} {{ $v->model_name }} {{ $v->manufacturing_year }}</a>
                                <div class="text-[11px] text-slate-400"><span class="font-mono">{{ $v->vehicle_code }}</span>
                                    @if ($v->is_dealer) · <span class="font-bold text-violet-600">{{ __('تفويض') }}</span> @endif
                                    @if (! $v->is_published) · <span class="text-amber-600">{{ __('مش منشورة') }}</span> @endif
                                    @if ($v->status !== 'available') · {{ \App\Http\Controllers\Admin\VehicleController::STATUSES[$v->status] ?? $v->status }} @endif
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-xs">{{ $v->branch_name }}</td>
                            <td class="px-3 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $v->age > 90 ? 'bg-rose-100 text-rose-700' : ($v->age > 60 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $v->age }} {{ __('يوم') }}</span></td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ Money::format($v->cost, false) }}@if ($v->extra_cost > 0) <div class="text-[11px] text-slate-400">{{ __('شامل') }} {{ Money::format($v->extra_cost, false) }} {{ __('مصروفات') }}</div> @endif</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ $v->selling_price ? Money::format($v->selling_price, false) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-bold {{ ($v->expected ?? 0) < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $v->expected !== null ? Money::format($v->expected, false) : '—' }}</td>
                            <td class="px-3 py-2.5 text-xs">{{ $v->drives ? '🚗 '.$v->drives.__(' تجربة') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">{{ __('مفيش عربيات في المخزون') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="bg-slate-50 font-extrabold"><tr><td class="px-5 py-3" colspan="3">{{ $rows->count() }} {{ __('عربية') }}</td><td class="px-3 py-3">{{ Money::format($rows->sum('cost'), false) }}</td><td class="px-3 py-3">{{ Money::format($rows->sum('selling_price'), false) }}</td><td class="px-3 py-3 text-emerald-700">{{ Money::format($rows->sum('expected'), false) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
