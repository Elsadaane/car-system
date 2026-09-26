@extends('storefront.layouts.app')

@php
    use App\Support\Site;
    use App\Support\Showroom;
    $money = fn ($v) => Showroom::money($v);
    $wa = Site::whatsapp();
    $phone = Site::phone();
    $fullTitle = $car->title.' '.$car->year;
    $waText = __('السلام عليكم، مهتم بـ ').$fullTitle.($car->code ? __(' (كود ').$car->code.')' : '').' — '.$car->url;
    $types = ['inquiry' => ['💬', __('استفسار')], 'test_drive' => ['🚗', __('تجربة قيادة')], 'reservation' => ['📌', __('احجزها')], 'finance' => ['💳', __('تقسيط')]];
    $selectedType = old('type', request('type', 'inquiry'));
    $in = 'w-full rounded-xl border-0 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-[var(--accent)]';
@endphp

@section('title', ($vehicle->meta_title ?: $fullTitle).' — '.Site::name())
@section('meta_description', $vehicle->meta_description ?: \Illuminate\Support\Str::limit(trim($fullTitle.' · '.number_format($car->mileage).__(' كم · ').($car->on_request ? '' : $money($car->price)).' · '.strip_tags((string) \App\Support\Lang::pick($vehicle, 'description'))), 155))
@if ($car->image) @section('og_image', url($car->image)) @endif
@section('wa_text', $waText)

@push('head')
<script type="application/ld+json">{!! json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Car', 'name' => $fullTitle, 'image' => array_map('url', $car->images) ?: null,
    'brand' => ['@type' => 'Brand', 'name' => $car->brand], 'model' => $car->model, 'vehicleModelDate' => (string) $car->year, 'color' => $car->color,
    'mileageFromOdometer' => ['@type' => 'QuantitativeValue', 'value' => $car->mileage, 'unitCode' => 'KMT'], 'sku' => $car->code,
    'offers' => $car->on_request ? null : ['@type' => 'Offer', 'price' => round($car->price, 3), 'priceCurrency' => 'OMR', 'availability' => $car->reserved ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock', 'url' => $car->url],
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <nav class="mb-5 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-400">
        <a href="{{ lroute('storefront.home') }}" class="hover:text-slate-900">{{ __('الرئيسية') }}</a> /
        <a href="{{ lroute('storefront.vehicles.index') }}" class="hover:text-slate-900">{{ __('السيارات') }}</a> /
        @if ($vehicle->brand_id)<a href="{{ lroute('storefront.vehicles.index', ['brand' => $vehicle->brand_id]) }}" class="hover:text-slate-900">{{ $car->brand }}</a> /@endif
        <span class="text-slate-700">{{ $car->model }} {{ $car->year }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[1.45fr_1fr]">
        {{-- ═══════ الصور ═══════ --}}
        <div class="min-w-0">
            <div class="relative overflow-hidden rounded-[2rem] bg-slate-900">
                <div class="aspect-[16/10]">
                    @if ($car->image)
                        <img id="mainImage" src="{{ $car->image }}" alt="{{ $fullTitle }}" class="h-full w-full cursor-zoom-in object-cover" data-open="0">
                    @else
                        <div class="grid h-full place-items-center text-8xl">{{ Showroom::bodyIcon($car->body) }}</div>
                    @endif
                </div>
                <div class="absolute top-4 start-4 flex flex-wrap gap-1.5">
                    @if ($car->offer_label)<span class="rounded-full bg-[var(--accent)] px-3 py-1 text-xs font-extrabold text-[var(--accent-ink)]">{{ $car->offer_label }}</span>@endif
                    @if ($car->discount_pct)<span class="rounded-full bg-rose-500 px-3 py-1 text-xs font-extrabold text-white">{{ __('وفّر') }} {{ $car->discount_pct }}%</span>@endif
                    @if ($car->is_new)<span class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-extrabold text-white">{{ __('وصلت جديد') }}</span>@endif
                </div>
                <div class="absolute top-4 end-4 flex gap-2">
                    <button type="button" data-fav="{{ $car->id }}" class="grid h-11 w-11 place-items-center rounded-full bg-white/90 shadow backdrop-blur" aria-label="{{ __('المفضلة') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20s-7-4.5-9-9a4.8 4.8 0 0 1 9-3 4.8 4.8 0 0 1 9 3c-2 4.5-9 9-9 9z" stroke-linejoin="round"/></svg></button>
                    <button type="button" id="shareBtn" class="grid h-11 w-11 place-items-center rounded-full bg-white/90 shadow backdrop-blur" aria-label="{{ __('مشاركة') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/></svg></button>
                </div>
                @if (count($car->images) > 1)
                    <button type="button" data-step="1" class="absolute start-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/80 text-xl font-black shadow backdrop-blur hover:bg-white">›</button>
                    <button type="button" data-step="-1" class="absolute end-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/80 text-xl font-black shadow backdrop-blur hover:bg-white">‹</button>
                    <span class="absolute bottom-4 end-4 rounded-full bg-slate-900/70 px-3 py-1 text-xs font-bold text-white backdrop-blur"><span id="imgIndex">1</span> / {{ count($car->images) }}</span>
                @endif
            </div>
            @if (count($car->images) > 1)
                <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1">
                    @foreach ($car->images as $i => $img)
                        <button type="button" data-thumb="{{ $i }}" class="h-20 w-28 shrink-0 overflow-hidden rounded-2xl ring-2 transition {{ $i === 0 ? 'ring-[var(--accent)]' : 'ring-transparent opacity-70 hover:opacity-100' }}"><img src="{{ $img }}" alt="" loading="lazy" class="h-full w-full object-cover"></button>
                    @endforeach
                </div>
            @endif

            {{-- لمحة سريعة --}}
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (array_filter([
                    ['📅', __('سنة الصنع'), $car->year],
                    ['🛣️', __('الكيلومترات'), $car->mileage ? number_format($car->mileage).__(' كم') : __('زيرو')],
                    $car->body ? [Showroom::bodyIcon($car->body), __('النوع'), Showroom::bodyLabel($car->body)] : null,
                    $car->fuel ? ['⛽', __('الوقود'), Showroom::fuelLabel($car->fuel)] : ($car->color ? ['🎨', __('اللون'), $car->color] : null),
                ]) as [$ic, $lb, $val])
                    <div class="rounded-2xl bg-white p-4 ring-1 ring-slate-200"><div class="text-2xl">{{ $ic }}</div><div class="mt-1 text-xs font-bold text-slate-400">{{ $lb }}</div><div class="font-extrabold text-slate-900">{{ $val }}</div></div>
                @endforeach
            </div>

            {{-- المواصفات --}}
            <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-slate-200">
                <h2 class="mb-4 text-lg font-black text-slate-900">{{ __('📋 المواصفات') }}</h2>
                <dl class="grid gap-x-8 sm:grid-cols-2">
                    @foreach ($specs as $label => $value)
                        <div class="flex items-center justify-between gap-4 border-b border-dashed border-slate-100 py-3 text-sm"><dt class="font-semibold text-slate-500">{{ $label }}</dt><dd class="font-extrabold text-slate-900">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if (\App\Support\Lang::pick($vehicle, 'description'))
                    <h3 class="mb-2 mt-6 font-black text-slate-900">{{ __('الوصف') }}</h3>
                    <p class="whitespace-pre-line text-sm leading-loose text-slate-600">{{ \App\Support\Lang::pick($vehicle, 'description') }}</p>
                @endif
            </div>

            {{-- الحاسبة --}}
            @if (! $car->on_request && $car->price > 0)
                <div class="mt-6">
                    <h2 class="mb-4 text-lg font-black text-slate-900">{{ __('💳 احسب قسط العربية دي') }}</h2>
                    @include('storefront.partials.calculator', ['price' => $car->price, 'lockPrice' => true])
                </div>
            @endif
        </div>

        {{-- ═══════ السعر والطلب ═══════ --}}
        <div class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <div class="rounded-[2rem] bg-white p-6 ring-1 ring-slate-200">
                <div class="text-xs font-extrabold uppercase tracking-wide text-slate-400">{{ $car->brand }}</div>
                <h1 class="text-2xl font-black leading-snug text-slate-900 sm:text-3xl">{{ $car->model }} <span class="text-slate-400">{{ $car->year }}</span></h1>
                <div class="mt-2 flex flex-wrap gap-3 text-xs font-bold text-slate-400">
                    @if ($car->code)<span>{{ __('كود:') }} {{ $car->code }}</span>@endif
                    @if ($car->views > 5)<span>👁️ {{ number_format($car->views) }} {{ __('شافوها') }}</span>@endif
                </div>

                <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                    @if ($car->on_request)
                        <div class="text-2xl font-black text-slate-900">{{ __('السعر عند الطلب') }}</div>
                    @else
                        @if ($car->old_price)
                            <div class="flex items-center gap-2 text-sm"><span class="font-bold text-slate-400 line-through">{{ $money($car->old_price) }}</span><span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-extrabold text-rose-600">{{ __('وفّر') }} {{ $money($car->save) }}</span></div>
                        @endif
                        <div class="text-4xl font-black text-slate-900">{{ $money($car->price) }}</div>
                        @if ($car->monthly > 0)<div class="mt-1 text-sm font-bold text-slate-500">{{ __('أو قسط يبدأ من') }} <span class="font-black text-slate-900">{{ number_format($car->monthly, 0) }} {{ __('ر.ع') }}</span> {{ __('/ شهر') }}</div>@endif
                    @endif
                    @if ($car->offer_ends)
                        <div class="mt-3 flex items-center justify-between rounded-xl bg-rose-600 px-3 py-2 text-sm font-bold text-white"><span>{{ __('⏳ العرض ينتهي خلال') }}</span><span data-countdown="{{ $car->offer_ends }}" dir="ltr" class="tabular-nums">--</span></div>
                    @endif
                </div>

                @if ($car->reserved)
                    <div class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 ring-1 ring-amber-200">{{ __('📌 العربية دي محجوزة حالياً — سجّل اهتمامك ولو الحجز اتلغى هنكلمك أول واحد.') }}</div>
                @endif

                <div class="mt-5 grid grid-cols-2 gap-2">
                    @if ($wa)
                        <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener" data-track="Contact" class="col-span-2 flex items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-4 py-3.5 font-extrabold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-600">{{ __('💬 كلّمنا واتساب عن العربية دي') }}</a>
                    @endif
                    @if ($phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" data-track="Contact" class="flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-extrabold text-white hover:bg-slate-700">{{ __('📞 اتصل') }}</a>
                    @endif
                    <a href="#leadForm" data-pick="test_drive" class="flex items-center justify-center gap-2 rounded-2xl bg-slate-100 px-4 py-3 text-sm font-extrabold text-slate-800 hover:bg-slate-200 {{ $phone ? '' : 'col-span-2' }}">{{ __('🚗 جرّبها') }}</a>
                </div>
                <button type="button" data-compare="{{ $car->id }}" class="mt-2 w-full rounded-2xl px-4 py-2.5 text-sm font-bold text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50">{{ __('🔀 أضفها للمقارنة') }}</button>

                @if ($vehicle->branch)
                    <div class="mt-5 flex items-center gap-3 rounded-2xl bg-slate-50 p-3 text-sm">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-lg ring-1 ring-slate-200">📍</span>
                        <div class="min-w-0"><div class="font-extrabold text-slate-900">{{ __('معروضة في') }} {{ \App\Support\Lang::pick($vehicle->branch, 'name') }}</div><div class="truncate text-xs text-slate-500">{{ $vehicle->branch->city ?? $vehicle->branch->address ?? Site::t('working_hours') }}</div></div>
                        @if (Site::get('map_url'))<a href="{{ Site::get('map_url') }}" target="_blank" rel="noopener" class="ms-auto shrink-0 text-xs font-extrabold text-blue-600">{{ __('الخريطة ←') }}</a>@endif
                    </div>
                @endif
            </div>

            {{-- فورم الطلب --}}
            <div id="leadForm" class="scroll-mt-24 rounded-[2rem] bg-white p-6 ring-1 ring-slate-200">
                <h2 class="text-lg font-black text-slate-900">{{ __('مهتم بالعربية دي؟ 🙋') }}</h2>
                <p class="mb-4 mt-1 text-sm text-slate-500">{{ __('سيب رقمك وهنكلمك في خلال ساعات العمل.') }}</p>
                @include('storefront.partials.form-alerts')
                <form action="{{ lroute('storefront.inquiries.store', $vehicle) }}" method="POST" data-lead>
                    <div class="mb-4 grid grid-cols-4 gap-1.5">
                        @foreach ($types as $k => [$ic, $lb])
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $k }}" class="peer sr-only" @checked($selectedType === $k)>
                                <span class="block rounded-xl bg-slate-100 py-2 text-center text-[11px] font-extrabold text-slate-600 transition peer-checked:bg-slate-900 peer-checked:text-white"><span class="block text-lg">{{ $ic }}</span>{{ $lb }}</span>
                            </label>
                        @endforeach
                    </div>
                    @include('storefront.partials.contact-fields', ['email' => false])
                    <div class="mt-3" id="dateWrap">
                        <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('ميعاد يناسبك') }}</label>
                        <input type="date" name="preferred_date" value="{{ old('preferred_date') }}" min="{{ date('Y-m-d') }}" class="{{ $in }}">
                    </div>
                    <div class="mt-3">
                        <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('رسالتك') }} <span class="font-normal text-slate-400">{{ __('(اختياري)') }}</span></label>
                        <textarea name="message" rows="2" maxlength="1000" class="{{ $in }}">{{ old('message') }}</textarea>
                    </div>
                    <button type="submit" class="mt-4 w-full rounded-2xl bg-[var(--accent)] px-5 py-4 font-black text-[var(--accent-ink)] shadow-lg transition hover:brightness-95">{{ __('ابعت الطلب ←') }}</button>
                    <p class="mt-2 text-center text-[11px] text-slate-400">{{ __('🔒 بياناتك مش هتتشارك مع أي حد.') }}</p>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══════ مشابهة ═══════ --}}
    @if ($similar->isNotEmpty())
        <section class="mt-20">
            @include('storefront.partials.section-head', ['title' => __('ممكن تعجبك كمان'), 'link' => lroute('storefront.vehicles.index', ['brand' => $vehicle->brand_id])])
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($similar as $s)@include('storefront.partials.vehicle-card', ['car' => $s])@endforeach
            </div>
        </section>
    @endif

    {{-- ═══════ شفتها مؤخراً ═══════ --}}
    <section id="recentBox" class="mt-16 hidden">
        <h2 class="mb-4 text-lg font-black text-slate-900">{{ __('👀 شفتها مؤخراً') }}</h2>
        <div id="recentList" class="no-scrollbar flex gap-3 overflow-x-auto pb-2"></div>
    </section>
</div>

{{-- ═══════ عارض الصور ═══════ --}}
<dialog id="lightbox" class="m-0 h-full max-h-none w-full max-w-none bg-slate-950/95 p-0 backdrop:bg-transparent">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between p-4 text-white"><span class="font-bold"><span id="lbIndex">1</span> / {{ count($car->images) }}</span><button type="button" data-lb-close class="grid h-11 w-11 place-items-center rounded-full bg-white/10 text-xl">✕</button></div>
        <div class="relative flex flex-1 items-center justify-center overflow-hidden px-2">
            <img id="lbImage" src="" alt="{{ $fullTitle }}" class="max-h-full max-w-full select-none object-contain">
            <button type="button" data-step="1" class="absolute start-3 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-2xl text-white">›</button>
            <button type="button" data-step="-1" class="absolute end-3 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-2xl text-white">‹</button>
        </div>
    </div>
</dialog>
@endsection

@section('sticky_cta')
<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 p-3 backdrop-blur lg:hidden" style="padding-bottom:max(.75rem,env(safe-area-inset-bottom))">
    <div class="flex items-center gap-2">
        <div class="min-w-0 flex-1">
            <div class="truncate text-xs font-bold text-slate-500">{{ $fullTitle }}</div>
            <div class="font-black text-slate-900">{{ $car->on_request ? __('السعر عند الطلب') : $money($car->price) }}</div>
        </div>
        @if ($wa)<a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener" data-track="Contact" class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500 text-xl text-white">💬</a>@endif
        @if ($phone)<a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" data-track="Contact" class="grid h-12 w-12 place-items-center rounded-2xl bg-slate-900 text-xl text-white">📞</a>@endif
        <a href="#leadForm" class="rounded-2xl bg-[var(--accent)] px-5 py-3 text-sm font-black text-[var(--accent-ink)]">{{ __('اطلبها') }}</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const images = {{ \Illuminate\Support\Js::from($car->images) }};
    let idx = 0;
    const main = document.getElementById('mainImage'), lb = document.getElementById('lightbox'), lbImg = document.getElementById('lbImage');
    const show = (n) => {
        if (!images.length) return;
        idx = (n + images.length) % images.length;
        if (main) main.src = images[idx];
        lbImg.src = images[idx];
        const a = document.getElementById('imgIndex'); if (a) a.textContent = idx + 1;
        document.getElementById('lbIndex').textContent = idx + 1;
        document.querySelectorAll('[data-thumb]').forEach((t) => {
            const on = +t.dataset.thumb === idx;
            t.classList.toggle('ring-[var(--accent)]', on); t.classList.toggle('ring-transparent', !on); t.classList.toggle('opacity-70', !on);
            if (on) t.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
        });
    };
    document.querySelectorAll('[data-thumb]').forEach((t) => t.addEventListener('click', () => show(+t.dataset.thumb)));
    document.querySelectorAll('[data-step]').forEach((b) => b.addEventListener('click', (e) => { e.stopPropagation(); show(idx + +b.dataset.step); }));
    if (main) main.addEventListener('click', () => { show(idx); lb.showModal(); });
    lb.querySelector('[data-lb-close]').addEventListener('click', () => lb.close());
    document.addEventListener('keydown', (e) => {
        if (!lb.open) return;
        if (e.key === 'ArrowLeft') show(idx - 1);
        if (e.key === 'ArrowRight') show(idx + 1);
    });
    let x0 = null;
    [lb, main && main.parentElement].forEach((el) => el && (el.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true }), el.addEventListener('touchend', (e) => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) show(idx + (dx > 0 ? 1 : -1)); x0 = null; })));

    // مشاركة
    document.getElementById('shareBtn').addEventListener('click', async () => {
        const data = { title: @js($fullTitle), url: @js($car->url) };
        if (navigator.share) { try { await navigator.share(data); } catch (e) {} return; }
        try { await navigator.clipboard.writeText(data.url); LV.toast(@js(__('🔗 اتنسخ الرابط'))); } catch (e) { prompt(@js(__('انسخ الرابط')), data.url); }
    });

    // نوع الطلب من الأزرار + إخفاء الميعاد لو مش محتاجه
    const form = document.querySelector('#leadForm form'), dateWrap = document.getElementById('dateWrap');
    const syncDate = () => { const t = (form.querySelector('[name=type]:checked') || {}).value; dateWrap.classList.toggle('hidden', !['test_drive', 'reservation'].includes(t)); };
    form.addEventListener('change', syncDate); syncDate();
    document.querySelectorAll('[data-pick]').forEach((b) => b.addEventListener('click', () => { const r = form.querySelector('[name=type][value=' + b.dataset.pick + ']'); if (r) { r.checked = true; syncDate(); } }));
    @if ($errors->any() || session('inquiry_success')) document.getElementById('leadForm').scrollIntoView({ block: 'center' }); @endif

    // تتبع مشاهدة العربية
    LV.track('ViewContent', { content_ids: [@js((string) $car->id)], content_type: 'vehicle', content_name: @js($fullTitle), value: {{ round($car->price, 3) }}, currency: 'OMR' });

    // شفتها مؤخراً
    const me = { id: {{ $car->id }}, t: @js($fullTitle), i: @js($car->image), p: @js($car->on_request ? __('السعر عند الطلب') : $money($car->price)), u: @js($car->url) };
    let recent = LV.store.get('recent').filter((r) => r && r.id !== me.id);
    if (recent.length) {
        const list = document.getElementById('recentList');
        recent.slice(0, 8).forEach((r) => {
            const a = document.createElement('a'); a.href = r.u; a.className = 'flex w-60 shrink-0 items-center gap-3 rounded-2xl bg-white p-2 ring-1 ring-slate-200 hover:shadow-md';
            const img = document.createElement('div'); img.className = 'h-16 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-100';
            if (r.i) { const im = document.createElement('img'); im.src = r.i; im.className = 'h-full w-full object-cover'; im.loading = 'lazy'; img.appendChild(im); }
            const tx = document.createElement('div'); tx.className = 'min-w-0';
            const t1 = document.createElement('div'); t1.className = 'truncate text-sm font-extrabold text-slate-900'; t1.textContent = r.t;
            const t2 = document.createElement('div'); t2.className = 'text-xs font-bold text-slate-500'; t2.textContent = r.p;
            tx.append(t1, t2); a.append(img, tx); list.appendChild(a);
        });
        document.getElementById('recentBox').classList.remove('hidden');
    }
    LV.store.set('recent', [me, ...recent].slice(0, 12));
})();
</script>
@endpush
