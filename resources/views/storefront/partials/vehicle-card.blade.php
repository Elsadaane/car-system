{{-- كارت العربية: @include('storefront.partials.vehicle-card', ['car' => Showroom::card($v)]) --}}
@php $money = fn ($v) => \App\Support\Showroom::money($v); @endphp
<article class="group relative flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200/80 transition duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-900/10 {{ ($list ?? false) ? 'sm:flex-row' : '' }}">
    <a href="{{ $car->url }}" class="relative block shrink-0 overflow-hidden bg-slate-100 {{ ($list ?? false) ? 'aspect-[4/3] sm:aspect-auto sm:w-80' : 'aspect-[4/3]' }}">
        @if ($car->image)
            <img src="{{ $car->image }}" alt="{{ $car->title }} {{ $car->year }}" loading="lazy" class="h-full w-full object-cover transition duration-700 group-hover:scale-110">
            @if (count($car->images) > 1)
                <span class="absolute bottom-3 end-3 rounded-full bg-slate-900/70 px-2 py-0.5 text-[11px] font-bold text-white backdrop-blur">📷 {{ count($car->images) }}</span>
            @endif
        @else
            <div class="grid h-full w-full place-items-center bg-gradient-to-br from-slate-100 to-slate-200 text-6xl">{{ \App\Support\Showroom::bodyIcon($car->body) }}</div>
        @endif

        <div class="absolute top-3 start-3 flex flex-col items-start gap-1.5">
            @if ($car->offer_label)
                <span class="rounded-full bg-[var(--accent)] px-2.5 py-1 text-[11px] font-extrabold text-[var(--accent-ink)] shadow">{{ $car->offer_label }}</span>
            @endif
            @if ($car->discount_pct)
                <span class="rounded-full bg-rose-500 px-2.5 py-1 text-[11px] font-extrabold text-white shadow">{{ __('خصم') }} {{ $car->discount_pct }}%</span>
            @endif
            @if ($car->is_new)
                <span class="rounded-full bg-emerald-500 px-2.5 py-1 text-[11px] font-extrabold text-white shadow">{{ __('وصلت جديد') }}</span>
            @endif
            @if ($car->reserved)
                <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-extrabold text-white shadow">{{ __('محجوزة') }}</span>
            @endif
        </div>

        @if ($car->offer_ends)
            <div class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-2 bg-gradient-to-t from-rose-600 to-rose-600/80 px-3 py-1.5 text-xs font-bold text-white">
                <span>{{ __('⏳ العرض ينتهي خلال') }}</span><span data-countdown="{{ $car->offer_ends }}" dir="ltr" class="tabular-nums">--</span>
            </div>
        @endif
    </a>

    <div class="absolute top-3 end-3 flex flex-col gap-1.5">
        <button type="button" data-fav="{{ $car->id }}" aria-label="{{ __('المفضلة') }}" class="grid h-9 w-9 place-items-center rounded-full bg-white/90 text-slate-700 shadow backdrop-blur transition hover:scale-110">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20s-7-4.5-9-9a4.8 4.8 0 0 1 9-3 4.8 4.8 0 0 1 9 3c-2 4.5-9 9-9 9z" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" data-compare="{{ $car->id }}" aria-label="{{ __('مقارنة') }}" title="{{ __('قارن') }}" class="grid h-9 w-9 place-items-center rounded-full bg-white/90 text-slate-700 shadow backdrop-blur transition hover:scale-110">
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 4v16M17 4v16M3 8l4-4 4 4M13 16l4 4 4-4"/></svg>
        </button>
    </div>

    <a href="{{ $car->url }}" class="flex flex-1 flex-col p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $car->brand }}</div>
                <h3 class="truncate text-base font-extrabold text-slate-900">{{ $car->model ?: $car->title }} <span class="font-bold text-slate-400">{{ $car->year }}</span></h3>
            </div>
            @if ($car->brand_logo)
                <img src="{{ $car->brand_logo }}" alt="" loading="lazy" class="h-8 w-8 shrink-0 object-contain opacity-80">
            @endif
        </div>

        <div class="mb-4 mt-3 flex flex-wrap gap-1.5 text-[11px] font-semibold text-slate-600">
            <span class="rounded-lg bg-slate-100 px-2 py-1">🛣️ {{ $car->mileage ? number_format($car->mileage).__(' كم') : __('زيرو') }}</span>
            @if ($car->color)<span class="rounded-lg bg-slate-100 px-2 py-1">🎨 {{ $car->color }}</span>@endif
            @if ($car->body)<span class="rounded-lg bg-slate-100 px-2 py-1">{{ \App\Support\Showroom::bodyIcon($car->body) }} {{ \App\Support\Showroom::bodyLabel($car->body) }}</span>@endif
            @if ($car->fuel)<span class="rounded-lg bg-slate-100 px-2 py-1">⛽ {{ \App\Support\Showroom::fuelLabel($car->fuel) }}</span>@endif
        </div>

        <div class="mt-auto flex items-end justify-between gap-2 border-t border-dashed border-slate-200 pt-3">
            <div>
                @if ($car->on_request)
                    <div class="text-lg font-black text-slate-900">{{ __('السعر عند الطلب') }}</div>
                @else
                    @if ($car->old_price)
                        <div class="text-xs font-semibold text-slate-400 line-through">{{ $money($car->old_price) }}</div>
                    @endif
                    <div class="text-xl font-black text-slate-900">{{ $money($car->price) }}</div>
                @endif
            </div>
            @if (! $car->on_request && $car->monthly > 0)
                <div class="rounded-xl bg-[var(--accent)]/15 px-2.5 py-1.5 text-end">
                    <div class="text-[10px] font-bold text-slate-500">{{ __('قسط يبدأ من') }}</div>
                    <div class="text-sm font-black text-slate-900">{{ number_format($car->monthly, 0) }}<span class="text-[10px] font-bold text-slate-500"> {{ __('/شهر') }}</span></div>
                </div>
            @endif
        </div>

        @if ($list ?? false)
            <div class="mt-3 flex items-center gap-3 text-xs text-slate-400">
                @if ($car->branch)<span>📍 {{ $car->branch }}</span>@endif
                @if ($car->views)<span>👁️ {{ number_format($car->views) }} {{ __('مشاهدة') }}</span>@endif
            </div>
        @endif
    </a>
</article>
