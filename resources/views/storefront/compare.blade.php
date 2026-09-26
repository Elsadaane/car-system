@extends('storefront.layouts.app')

@php
    use App\Support\Showroom;
    $money = fn ($v) => Showroom::money($v);
    $cards = $cars->map(fn ($p) => $p[0]);
    $best = [
        'price'   => $cards->where('on_request', false)->min('price'),
        'mileage' => $cards->min('mileage'),
        'year'    => $cards->max('year'),
    ];
    $rows = [
        [__('السعر'), fn ($c) => $c->on_request ? __('عند الطلب') : $money($c->price), fn ($c) => ! $c->on_request && $c->price == $best['price']],
        [__('القسط الشهري من'), fn ($c) => $c->monthly > 0 ? number_format($c->monthly, 0).__(' / شهر') : '—', fn ($c) => ! $c->on_request && $c->price == $best['price']],
        [__('سنة الصنع'), fn ($c) => $c->year, fn ($c) => $c->year == $best['year']],
        [__('الكيلومترات'), fn ($c) => number_format($c->mileage).__(' كم'), fn ($c) => $c->mileage == $best['mileage']],
        [__('اللون'), fn ($c) => $c->color ?: '—', null],
        [__('النوع'), fn ($c) => $c->body ? Showroom::bodyLabel($c->body) : '—', null],
        [__('الوقود'), fn ($c) => $c->fuel ? Showroom::fuelLabel($c->fuel) : '—', null],
        [__('السلندرات'), fn ($c) => $c->cylinders ?: '—', null],
        [__('الفرع'), fn ($c) => $c->branch ?: '—', null],
        [__('الخصم'), fn ($c) => $c->discount_pct ? $c->discount_pct.'% ('.$money($c->save).')' : '—', fn ($c) => $c->discount_pct > 0 && $c->save == $cards->max('save')],
    ];
@endphp

@section('title', __('مقارنة السيارات — ').\App\Support\Site::name())

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-black text-slate-900">{{ __('🔀 مقارنة السيارات') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('حطّ لحد 3 عربيات جنب بعض — الأفضل في كل بند متعلّم عليه بالأخضر.') }}</p>

    @if ($cars->isEmpty())
        <div class="mt-8 rounded-3xl bg-white px-6 py-20 text-center ring-1 ring-slate-200">
            <div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-3xl bg-slate-100 text-3xl">🔀</div>
            <h2 class="text-lg font-black text-slate-900">{{ __('لسه مختارتش عربيات للمقارنة') }}</h2>
            <p class="mt-2 text-sm text-slate-500">{{ __('دوس على زرار ⇅ في كارت أي عربية عشان تضيفها هنا.') }}</p>
            <a href="{{ lroute('storefront.vehicles.index') }}" class="mt-6 inline-block rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white">{{ __('تصفّح السيارات') }}</a>
        </div>
    @else
        <div class="mt-8 overflow-x-auto rounded-3xl bg-white ring-1 ring-slate-200">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr>
                        <th class="w-40 p-4"></th>
                        @foreach ($cards as $c)
                            <th class="p-4 align-top">
                                <div class="relative overflow-hidden rounded-2xl bg-slate-100">
                                    <div class="aspect-[4/3]">@if ($c->image)<img src="{{ $c->image }}" alt="" class="h-full w-full object-cover">@else<div class="grid h-full place-items-center text-5xl">{{ Showroom::bodyIcon($c->body) }}</div>@endif</div>
                                    <button type="button" data-remove="{{ $c->id }}" class="absolute top-2 end-2 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-xs font-black text-slate-600 shadow hover:bg-rose-500 hover:text-white" title="{{ __('شيل') }}">✕</button>
                                </div>
                                <a href="{{ $c->url }}" class="mt-3 block text-start text-base font-black text-slate-900 hover:underline">{{ $c->title }} {{ $c->year }}</a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as [$label, $val, $isBest])
                        <tr class="border-t border-slate-100 odd:bg-slate-50/60">
                            <th class="p-4 text-start text-xs font-extrabold text-slate-500">{{ $label }}</th>
                            @foreach ($cards as $c)
                                @php $win = $isBest && $cards->count() > 1 && $isBest($c); @endphp
                                <td class="p-4 font-bold {{ $win ? 'text-emerald-700' : 'text-slate-800' }}">{{ $val($c) }} @if ($win)<span class="ms-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px]">{{ __('الأفضل') }}</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="border-t border-slate-100">
                        <th></th>
                        @foreach ($cards as $c)
                            <td class="p-4"><a href="{{ $c->url }}#leadForm" class="block rounded-xl bg-[var(--accent)] px-4 py-3 text-center text-sm font-black text-[var(--accent-ink)]">{{ __('اطلبها ←') }}</a></td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const ids = LV.store.get('compare');
    const inUrl = new URLSearchParams(location.search).get('ids');
    if (!inUrl && ids.length) { location.replace('?ids=' + ids.join(',')); return; }
    document.querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', () => {
        const left = LV.store.get('compare').filter((x) => x !== +b.dataset.remove);
        LV.store.set('compare', left);
        location.replace('?ids=' + left.join(','));
    }));
})();
</script>
@endpush
