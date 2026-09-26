@extends('storefront.layouts.app')

@php
    use App\Support\Site;
    use App\Support\Media;
    use App\Support\Showroom;
    $heroImage = Media::url(Site::get('hero_image'));
    $in = 'w-full rounded-xl border-0 bg-slate-100 px-4 py-3.5 text-sm font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-[var(--accent)]';
@endphp

@section('content')

{{-- ═══════════════ البانرات / الواجهة ═══════════════ --}}
@if ($banners->isNotEmpty())
    <section class="relative bg-slate-950" id="heroSlider">
        <div class="relative h-[62vh] min-h-[420px] overflow-hidden sm:h-[70vh]">
            @foreach ($banners as $b)
                <div data-slide class="absolute inset-0 transition-opacity duration-1000 {{ $loop->first ? 'opacity-100' : 'pointer-events-none opacity-0' }}">
                    <img src="{{ Media::url($b->image) }}" alt="{{ \App\Support\Lang::pick($b, 'title') }}" class="h-full w-full object-cover" @unless ($loop->first) loading="lazy" @endunless>
                    <div class="absolute inset-0 rtl:bg-gradient-to-l ltr:bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent"></div>
                    <div class="absolute inset-0 mx-auto flex max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                        <div class="max-w-xl pb-16">
                            @if (\App\Support\Lang::pick($b, 'title'))<h1 class="text-4xl font-black leading-tight text-white sm:text-5xl lg:text-6xl">{{ \App\Support\Lang::pick($b, 'title') }}</h1>@endif
                            @if (\App\Support\Lang::pick($b, 'body'))<p class="mt-4 text-lg leading-relaxed text-slate-200">{{ \App\Support\Lang::pick($b, 'body') }}</p>@endif
                            @if ($b->link)
                                <a href="{{ $b->link }}" class="mt-7 inline-flex items-center gap-2 rounded-2xl bg-[var(--accent)] px-7 py-4 font-extrabold text-[var(--accent-ink)] shadow-xl transition hover:scale-105">{{ \App\Support\Lang::pick($b, 'button_text') ?: __('اكتشف العرض') }} {{ \App\Support\Lang::isEn() ? '→' : '←' }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($banners->count() > 1)
            <div class="absolute bottom-20 left-1/2 flex -translate-x-1/2 gap-2">
                @foreach ($banners as $b)
                    <button type="button" data-dot="{{ $loop->index }}" class="h-2 rounded-full bg-white/40 transition-all {{ $loop->first ? 'w-8 bg-white' : 'w-2' }}" aria-label="{{ __('بانر') }} {{ $loop->iteration }}"></button>
                @endforeach
            </div>
        @endif
    </section>
@else
    <section class="relative overflow-hidden bg-slate-950">
        @if ($heroImage)
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-50">
            <div class="absolute inset-0 rtl:bg-gradient-to-l ltr:bg-gradient-to-r from-slate-950 via-slate-950/70 to-slate-950/20"></div>
        @else
            <div class="absolute inset-0" style="background:radial-gradient(60% 80% at 15% 20%, color-mix(in srgb, var(--accent) 35%, transparent), transparent 60%),radial-gradient(50% 60% at 90% 90%, #334155, transparent 60%)"></div>
            <div class="absolute inset-0 opacity-[.07]" style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:48px 48px"></div>
        @endif
        <div class="relative mx-auto max-w-7xl px-4 pb-32 pt-20 sm:px-6 md:pt-28 lg:px-8">
            <div class="max-w-2xl">
                @if ($totalCars)
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-bold text-white ring-1 ring-white/15 backdrop-blur">
                        <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span></span>
                        {{ $totalCars }} {{ __('سيارة متاحة دلوقتي') }}
                    </div>
                @endif
                <h1 class="text-4xl font-black leading-[1.15] text-white sm:text-5xl lg:text-6xl">
                    {{ Site::t('hero_title') }}
                    <span class="text-[var(--accent)]">{{ Site::t('hero_highlight') }}</span>
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-slate-300">{{ Site::t('hero_subtitle') }}</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ lroute('storefront.vehicles.index') }}" class="rounded-2xl bg-[var(--accent)] px-7 py-4 font-extrabold text-[var(--accent-ink)] shadow-xl transition hover:scale-105">{{ __('تصفّح السيارات') }}</a>
                    <a href="{{ lroute('storefront.finance') }}" class="rounded-2xl bg-white/10 px-7 py-4 font-extrabold text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-white/20">{{ __('احسب قسطك 💳') }}</a>
                </div>
            </div>
        </div>
    </section>
@endif

{{-- ═══════════════ البحث الذكي ═══════════════ --}}
<section class="relative z-10 mx-auto -mt-16 max-w-6xl px-4 sm:px-6 lg:px-8">
    <form action="{{ lroute('storefront.vehicles.index') }}" method="GET" id="smartSearch" class="rounded-3xl bg-white p-4 shadow-2xl shadow-slate-900/10 ring-1 ring-slate-200 sm:p-5">
        <div class="mb-4 flex gap-1 rounded-2xl bg-slate-100 p-1 text-sm font-extrabold sm:w-fit">
            <button type="button" data-mode="price" class="flex-1 rounded-xl bg-white px-5 py-2 text-slate-900 shadow-sm sm:flex-none">{{ __('💰 بالسعر') }}</button>
            <button type="button" data-mode="monthly" class="flex-1 rounded-xl px-5 py-2 text-slate-500 sm:flex-none">{{ __('📅 بالقسط الشهري') }}</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]">
            <select name="brand" class="{{ $in }}">
                <option value="">{{ __('كل الماركات') }}</option>
                @foreach ($brands as $b)<option value="{{ $b->id }}">{{ \App\Support\Lang::pick($b, 'name') }} ({{ $b->vehicles_count }})</option>@endforeach
            </select>
            <select name="body" class="{{ $in }}">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach ($bodyTypes as $type => $c)<option value="{{ $type }}">{{ Showroom::bodyLabel($type) }}</option>@endforeach
            </select>
            <div data-for="price">
                <select name="price_to" class="{{ $in }}">
                    <option value="">{{ __('أي ميزانية') }}</option>
                    @foreach ([3000, 5000, 8000, 12000, 15000, 20000, 30000, 50000] as $p)<option value="{{ $p }}">{{ __('لحد') }} {{ number_format($p) }} {{ __('ر.ع') }}</option>@endforeach
                </select>
            </div>
            <div data-for="monthly" class="hidden">
                <select name="monthly_max" disabled class="{{ $in }}">
                    <option value="">{{ __('أي قسط') }}</option>
                    @foreach ([100, 150, 200, 250, 300, 400, 500] as $p)<option value="{{ $p }}">{{ __('لحد') }} {{ $p }} {{ __('ر.ع / شهر') }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-slate-900 px-8 py-3.5 font-extrabold text-white transition hover:bg-slate-700 sm:col-span-2 lg:col-span-1">{{ __('🔍 دوّر') }}</button>
        </div>
    </form>
</section>

{{-- ═══════════════ العروض بعداد تنازلي ═══════════════ --}}
@if ($offers->isNotEmpty())
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['kicker' => __('لفترة محدودة'), 'title' => __('🔥 عروض <span class="text-rose-500">ماتتفوّتش</span>'), 'link' => lroute('storefront.offers'), 'linkLabel' => __('كل العروض')])
    <div class="no-scrollbar -mx-4 flex snap-x gap-5 overflow-x-auto px-4 pb-4">
        @foreach ($offers as $car)
            <div class="w-[82%] shrink-0 snap-start sm:w-[45%] lg:w-[31%] xl:w-[23.5%]">@include('storefront.partials.vehicle-card', ['car' => $car])</div>
        @endforeach
    </div>
</section>
@endif

{{-- ═══════════════ حسب النوع ═══════════════ --}}
@if ($bodyTypes->count() > 1)
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['title' => __('بتدوّر على إيه؟'), 'sub' => __('اختار نوع العربية اللي تناسب استخدامك')])
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($bodyTypes as $type => $c)
            <a href="{{ lroute('storefront.vehicles.index', ['body' => $type]) }}" class="group rounded-3xl bg-white p-5 text-center ring-1 ring-slate-200 transition hover:-translate-y-1 hover:bg-slate-900 hover:shadow-xl">
                <div class="text-4xl transition group-hover:scale-125">{{ Showroom::bodyIcon($type) }}</div>
                <div class="mt-3 font-extrabold text-slate-900 group-hover:text-white">{{ Showroom::bodyLabel($type) }}</div>
                <div class="text-xs font-bold text-slate-400">{{ $c }} {{ __('سيارة') }}</div>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ═══════════════ المميزة ═══════════════ --}}
@if ($featured->isNotEmpty())
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['kicker' => __('اختيارات الفريق'), 'title' => __('⭐ سيارات مميزة'), 'link' => lroute('storefront.vehicles.index')])
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($featured as $car)@include('storefront.partials.vehicle-card', ['car' => $car])@endforeach
    </div>
</section>
@endif

{{-- ═══════════════ الماركات ═══════════════ --}}
@if ($brands->isNotEmpty())
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['title' => __('تصفّح حسب الماركة')])
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-8">
        @foreach ($brands as $brand)
            <a href="{{ lroute('storefront.vehicles.index', ['brand' => $brand->id]) }}" class="group flex flex-col items-center gap-2 rounded-3xl bg-white p-4 ring-1 ring-slate-200 transition hover:-translate-y-1 hover:shadow-lg hover:ring-[var(--accent)]">
                @if ($brand->logo)
                    <img src="{{ Media::url($brand->logo) }}" alt="{{ \App\Support\Lang::pick($brand, 'name') }}" loading="lazy" class="h-12 w-12 object-contain grayscale transition group-hover:grayscale-0">
                @else
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-lg font-black text-slate-500">{{ mb_substr($brand->name_en ?: \App\Support\Lang::pick($brand, 'name'), 0, 1) }}</span>
                @endif
                <div class="text-center text-sm font-extrabold text-slate-800">{{ \App\Support\Lang::pick($brand, 'name') }}</div>
                <div class="text-[11px] font-bold text-slate-400">{{ $brand->vehicles_count }} {{ __('سيارة') }}</div>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- ═══════════════ حاسبة التقسيط ═══════════════ --}}
@if (Site::on('show_calculator'))
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-12 md:px-12">
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div>
                <span class="inline-block rounded-full bg-[var(--accent)]/15 px-3 py-1 text-xs font-extrabold text-[var(--accent)]">{{ __('💳 تقسيط مع المعرض مباشرة') }}</span>
                <h2 class="mt-4 text-3xl font-black leading-tight text-white md:text-4xl">{{ __('اعرف قسطك في') }}<br>{{ __('ثواني — من غير ما تكلّم حد') }}</h2>
                <p class="mt-4 leading-relaxed text-slate-400">{{ __('حرّك السعر والمقدم والمدة، وشوف القسط الشهري على طول. ولما تلاقي الرقم المناسب، دوّر على العربيات اللي في حدود قسطك.') }}</p>
                <ul class="mt-6 space-y-3 text-sm font-bold text-slate-300">
                    <li>{{ __('✅ مقدم يبدأ من') }} {{ (float) Site::get('min_down_percent', 20) }}%</li>
                    <li>{{ __('✅ مدة لحد') }} {{ (int) Site::get('max_months', 60) }} {{ __('شهر') }}</li>
                    <li>✅ {{ (float) Site::get('installment_rate', 0) > 0 ? __('نسبة ربح ثابتة وواضحة') : __('من غير فوايد') }}</li>
                </ul>
                <a href="#" id="byMonthly" class="mt-8 inline-flex items-center gap-2 rounded-2xl bg-white px-6 py-3.5 font-extrabold text-slate-900 transition hover:scale-105">{{ __('🔍 وريني العربيات اللي في حدود القسط ده') }}</a>
            </div>
            @include('storefront.partials.calculator', ['price' => $priceRange->mn ? ($priceRange->mn + $priceRange->mx) / 2 : 8000, 'dark' => true, 'cta' => lroute('storefront.finance')])
        </div>
    </div>
</section>
@endif

{{-- ═══════════════ أحدث الإضافات ═══════════════ --}}
@if ($latest->isNotEmpty())
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['kicker' => __('لسه واصلة'), 'title' => __('أحدث الإضافات'), 'link' => lroute('storefront.vehicles.index')])
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($latest as $car)@include('storefront.partials.vehicle-card', ['car' => $car])@endforeach
    </div>
</section>
@endif

{{-- ═══════════════ لا يوجد سيارات ═══════════════ --}}
@if ($totalCars === 0)
<section class="mx-auto max-w-2xl px-4 py-24 text-center">
    <div class="mx-auto mb-5 grid h-20 w-20 place-items-center rounded-3xl bg-slate-100 text-4xl">🚗</div>
    <h2 class="text-xl font-black text-slate-900">{{ __('مفيش سيارات معروضة دلوقتي') }}</h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ __('عشان العربية تظهر هنا، لازم تكون حالتها') }} <strong>{{ __('متاحة') }}</strong> {{ __('وعليها علامة') }} <strong>{{ __('منشورة') }}</strong> {{ __('من لوحة الإدارة.') }}</p>
</section>
@endif

{{-- ═══════════════ ليه إحنا + أرقام ═══════════════ --}}
@if ($features->isNotEmpty())
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['title' => __('ليه تشتري من ').e(Site::name()).__('؟')])
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($features as $f)
            <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200">
                <div class="grid h-14 w-14 place-items-center rounded-2xl bg-[var(--accent)]/15 text-3xl">{{ $f->icon ?: '✨' }}</div>
                <h3 class="mt-4 text-lg font-extrabold text-slate-900">{{ \App\Support\Lang::pick($f, 'title') }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ \App\Support\Lang::pick($f, 'body') }}</p>
            </div>
        @endforeach
    </div>
    <div class="mt-4 grid grid-cols-2 gap-4 rounded-3xl bg-[var(--accent)] p-6 text-[var(--accent-ink)] lg:grid-cols-4">
        @foreach ([['cars', __('سيارة متاحة')], ['sold', __('سيارة اتباعت')], ['customers', __('عميل وثق فينا')], ['branches', __('فرع')]] as [$k, $l])
            @if ($stats[$k])
                <div class="text-center"><div class="text-3xl font-black tabular-nums sm:text-4xl" data-countup="{{ $stats[$k] }}">{{ number_format($stats[$k]) }}</div><div class="mt-1 text-sm font-bold opacity-75">{{ $l }}</div></div>
            @endif
        @endforeach
    </div>
</section>
@endif

{{-- ═══════════════ آراء العملاء ═══════════════ --}}
@if ($testimonials->isNotEmpty())
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['kicker' => __('من عملائنا'), 'title' => __('قالوا عننا')])
    <div class="no-scrollbar -mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-4">
        @foreach ($testimonials as $t)
            <figure class="w-[85%] shrink-0 snap-start rounded-3xl bg-white p-6 ring-1 ring-slate-200 sm:w-[46%] lg:w-[31.5%]">
                <div class="text-lg tracking-widest text-amber-400">{{ str_repeat('★', (int) ($t->rating ?: 5)) }}<span class="text-slate-200">{{ str_repeat('★', 5 - (int) ($t->rating ?: 5)) }}</span></div>
                <blockquote class="mt-3 leading-relaxed text-slate-700">“{{ \App\Support\Lang::pick($t, 'body') }}”</blockquote>
                <figcaption class="mt-5 flex items-center gap-3">
                    @if ($t->image)
                        <img src="{{ Media::url($t->image) }}" alt="" class="h-11 w-11 rounded-full object-cover">
                    @else
                        <span class="grid h-11 w-11 place-items-center rounded-full bg-slate-900 font-black text-[var(--accent)]">{{ mb_substr(\App\Support\Lang::pick($t, 'title'), 0, 1) }}</span>
                    @endif
                    <div><div class="font-extrabold text-slate-900">{{ \App\Support\Lang::pick($t, 'title') }}</div>@if (\App\Support\Lang::pick($t, 'button_text'))<div class="text-xs text-slate-400">{{ \App\Support\Lang::pick($t, 'button_text') }}</div>@endif</div>
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif

{{-- ═══════════════ قيّم عربيتك ═══════════════ --}}
@if (Site::on('show_sell_cta'))
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    <div class="grid items-center gap-6 rounded-[2rem] rtl:bg-gradient-to-l ltr:bg-gradient-to-r from-emerald-600 to-teal-700 p-8 text-white md:grid-cols-[1fr_auto] md:p-12">
        <div>
            <h2 class="text-2xl font-black md:text-3xl">{{ __('🔁 عندك عربية قديمة؟ بدّلها بجديدة') }}</h2>
            <p class="mt-2 max-w-2xl text-emerald-50">{{ __('ابعتلنا بياناتها في دقيقة، وهنرد عليك بعرض سعر — وتقدر تخصمه من تمن عربيتك الجديدة.') }}</p>
        </div>
        <a href="{{ lroute('storefront.sell') }}" class="rounded-2xl bg-white px-7 py-4 text-center font-extrabold text-emerald-700 shadow-xl transition hover:scale-105">{{ __('قيّم عربيتك مجاناً ←') }}</a>
    </div>
</section>
@endif

{{-- ═══════════════ من المدونة ═══════════════ --}}
@if ($posts->isNotEmpty())
<section class="reveal mx-auto max-w-7xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['kicker' => __('قبل ما تشتري'), 'title' => __('📰 من المدونة'), 'link' => lroute('storefront.blog.index'), 'linkLabel' => __('كل المقالات')])
    <div class="grid gap-5 md:grid-cols-3">
        @foreach ($posts as $p)@include('storefront.partials.post-card', ['p' => $p])@endforeach
    </div>
</section>
@endif

{{-- ═══════════════ الأسئلة الشائعة ═══════════════ --}}
@if ($faqs->isNotEmpty())
<section class="reveal mx-auto max-w-3xl px-4 pt-20 sm:px-6 lg:px-8">
    @include('storefront.partials.section-head', ['title' => __('أسئلة بتتسأل كتير')])
    @include('storefront.partials.faq', ['faqs' => $faqs])
</section>
@endif

@endsection

@push('scripts')
<script>
(function () {
    // سلايدر البانرات
    const slider = document.getElementById('heroSlider');
    if (slider) {
        const slides = slider.querySelectorAll('[data-slide]'), dots = slider.querySelectorAll('[data-dot]');
        let i = 0, timer;
        const go = (n) => {
            i = (n + slides.length) % slides.length;
            slides.forEach((s, k) => { s.classList.toggle('opacity-100', k === i); s.classList.toggle('opacity-0', k !== i); s.classList.toggle('pointer-events-none', k !== i); });
            dots.forEach((d, k) => { d.classList.toggle('w-8', k === i); d.classList.toggle('bg-white', k === i); d.classList.toggle('w-2', k !== i); });
        };
        const play = () => { clearInterval(timer); if (slides.length > 1) timer = setInterval(() => go(i + 1), 6000); };
        dots.forEach((d) => d.addEventListener('click', () => { go(+d.dataset.dot); play(); }));
        let x0 = null;
        slider.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', (e) => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) { go(i + (dx > 0 ? 1 : -1)); play(); } x0 = null; });
        play();
    }

    // البحث بالسعر / بالقسط
    const form = document.getElementById('smartSearch');
    form.querySelectorAll('[data-mode]').forEach((b) => b.addEventListener('click', () => {
        form.querySelectorAll('[data-mode]').forEach((x) => { const on = x === b; x.classList.toggle('bg-white', on); x.classList.toggle('shadow-sm', on); x.classList.toggle('text-slate-900', on); x.classList.toggle('text-slate-500', !on); });
        form.querySelectorAll('[data-for]').forEach((w) => { const on = w.dataset.for === b.dataset.mode; w.classList.toggle('hidden', !on); w.querySelector('select').disabled = !on; });
    }));
    form.addEventListener('submit', () => {
        [...form.elements].forEach((el) => { if (el.name && !el.value) el.disabled = true; });
        LV.track('Search');
    });

    // من الحاسبة للعربيات اللي في حدود القسط
    let monthly = 0;
    document.addEventListener('calc', (e) => { monthly = Math.ceil(e.detail.monthly); });
    const by = document.getElementById('byMonthly');
    if (by) by.addEventListener('click', (e) => { e.preventDefault(); location.href = @js(lroute('storefront.vehicles.index')) + '?monthly_max=' + monthly; });

    // عدّاد الأرقام
    document.querySelectorAll('[data-countup]').forEach((el) => {
        const end = +el.dataset.countup; let started = false;
        new IntersectionObserver(([en], o) => {
            if (!en.isIntersecting || started) return; started = true; o.disconnect();
            const t0 = performance.now();
            const step = (t) => { const p = Math.min(1, (t - t0) / 1200); el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString('en-US'); if (p < 1) requestAnimationFrame(step); };
            requestAnimationFrame(step);
        }).observe(el);
    });
})();
</script>
@endpush
