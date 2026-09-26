@php
    use App\Support\Site;
    use App\Support\Company;
    use App\Support\Media;
    use App\Support\Showroom;

    $siteName = Site::name();
    $accent = Site::get('primary_color', '#fbbf24') ?: '#fbbf24';
    $ink = Showroom::inkFor($accent);
    $logo = Company::logo();
    $wa = Site::whatsapp();
    $phone = Site::phone();
    $metaTitle = Site::t('meta_title') ?: $siteName.' — '.Site::t('tagline');
    $metaDesc = Site::t('meta_description') ?: Site::t('tagline');
    $ogImage = Media::url(Site::get('og_image')) ?: $logo;
    $social = array_filter(['instagram' => Site::get('instagram'), 'facebook' => Site::get('facebook'), 'tiktok' => Site::get('tiktok'), 'snapchat' => Site::get('snapchat'), 'x' => Site::get('x'), 'youtube' => Site::get('youtube')]);
    $pixel = ['meta' => Site::get('meta_pixel_id'), 'ga4' => Site::get('ga4_id'), 'gtm' => Site::get('gtm_id'), 'tiktok' => Site::get('tiktok_pixel_id'), 'snap' => Site::get('snap_pixel_id')];
    $nav = [
        ['storefront.home', __('الرئيسية')],
        ['storefront.vehicles.index', __('السيارات')],
        ['storefront.offers', __('العروض 🔥')],
        ['storefront.finance', __('التقسيط')],
        ['storefront.sell', __('قيّم عربيتك')],
        ['storefront.blog.index', __('المدونة')],
        ['storefront.contact', __('تواصل معنا')],
    ];
    $branches = \Illuminate\Support\Facades\DB::table('branches')->orderBy('id')->limit(4)->get();
    $socialIcons = [
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
        'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v7h4v-7h3l1-4h-4V8z"/>',
        'tiktok'    => '<path d="M15 3v10.5a3.5 3.5 0 1 1-3.5-3.5M15 3c.5 2.5 2.5 4.5 5 4.5"/>',
        'snapchat'  => '<path d="M12 3c3 0 5 2 5 5v3l2 1-2 1c.5 2 2 3 3 3.5-1 .8-2.5.5-3 1.5s-1 2-5 2-4.5-1-5-2-2-.7-3-1.5c1-.5 2.5-1.5 3-3.5l-2-1 2-1V8c0-3 2-5 5-5z"/>',
        'x'         => '<path d="M4 4l16 16M20 4 4 20"/>',
        'youtube'   => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor"/>',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Lang::current() }}" dir="{{ \App\Support\Lang::dir() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">

    <title>@yield('title', $metaTitle)</title>
    <meta name="description" content="@yield('meta_description', $metaDesc)">
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach (\App\Support\Lang::LOCALES as $code => $meta)
        @if ($alt = \App\Support\Lang::switchUrl($code))
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $alt }}">
            @if ($code === 'ar')<link rel="alternate" hreflang="x-default" href="{{ $alt }}">@endif
        @endif
    @endforeach
    <meta property="og:locale" content="{{ \App\Support\Lang::LOCALES[\App\Support\Lang::current()]['og'] }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="@yield('title', $metaTitle)">
    <meta property="og:description" content="@yield('meta_description', $metaDesc)">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($ogImage)
        <meta property="og:image" content="@yield('og_image', url($ogImage))">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    @if ($logo) <link rel="icon" href="{{ $logo }}"> @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root { --accent: {{ $accent }}; --accent-ink: {{ $ink }}; }
        body { font-family: 'Tajawal', system-ui, sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; } .no-scrollbar { scrollbar-width: none; }
        [data-fav].is-on svg { fill: #f43f5e; stroke: #f43f5e; }
        [data-compare].is-on { background: var(--accent); color: var(--accent-ink); }
        .reveal { opacity: 0; transform: translateY(14px); transition: .6s cubic-bezier(.2,.7,.2,1); }
        .reveal.in { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) { .reveal { opacity: 1; transform: none; transition: none; } }
    </style>

    {{-- ═══════ JSON-LD: بيانات المعرض لجوجل ═══════ --}}
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => 'AutoDealer', 'name' => $siteName, 'url' => url('/'),
        'logo' => $logo ? url($logo) : null, 'telephone' => $phone, 'email' => Site::get('email'),
        'address' => Site::t('address'), 'openingHours' => Site::t('working_hours'), 'sameAs' => array_values($social) ?: null,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @stack('head')

    {{-- ═══════ التتبع: Meta / GA4 / GTM / TikTok / Snap ═══════ --}}
    @if ($pixel['gtm'])
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $pixel['gtm'] }}');</script>
    @endif
    @if ($pixel['ga4'])
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $pixel['ga4'] }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $pixel['ga4'] }}');</script>
    @endif
    @if ($pixel['meta'])
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $pixel['meta'] }}');fbq('track','PageView');</script>
    @endif
    @if ($pixel['tiktok'])
        <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load('{{ $pixel['tiktok'] }}');ttq.page();}(window,document,'ttq');</script>
    @endif
    @if ($pixel['snap'])
        <script>(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';var r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init','{{ $pixel['snap'] }}',{});snaptr('track','PAGE_VIEW');</script>
    @endif
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-[var(--accent)] selection:text-[var(--accent-ink)]">
@if ($pixel['gtm'])
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $pixel['gtm'] }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif

{{-- ═══════ شريط الإعلان ═══════ --}}
@if (Site::t('announcement_text'))
    <div class="bg-[var(--accent)] text-[var(--accent-ink)]">
        <div class="mx-auto flex max-w-7xl items-center justify-center gap-3 px-4 py-2 text-center text-sm font-bold">
            <span>{{ Site::t('announcement_text') }}</span>
            @if (Site::get('announcement_link'))
                <a href="{{ Site::get('announcement_link') }}" class="rounded-full bg-slate-900/10 px-3 py-0.5 text-xs underline-offset-2 hover:underline">{{ __('اعرف أكتر ←') }}</a>
            @endif
        </div>
    </div>
@endif

{{-- ═══════ الهيدر ═══════ --}}
<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur-xl">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <a href="{{ lroute('storefront.home') }}" class="flex shrink-0 items-center gap-2.5">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-10 w-auto max-w-[140px] object-contain">
                @else
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-slate-900 text-lg font-black text-[var(--accent)]">{{ mb_substr($siteName, 0, 1) }}</span>
                @endif
                <span class="hidden text-lg font-extrabold text-slate-900 sm:block">{{ $siteName }}</span>
            </a>

            <nav class="hidden items-center gap-0.5 text-sm font-bold lg:flex">
                @foreach ($nav as [$r, $l])
                    <a href="{{ lroute($r) }}" class="whitespace-nowrap rounded-lg px-2.5 py-2 transition {{ \App\Support\Lang::is($r) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">{{ $l }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-1.5">
                @php $otherLang = \App\Support\Lang::isEn() ? 'ar' : 'en'; $otherUrl = \App\Support\Lang::switchUrl($otherLang) ?? lroute('storefront.home', [], $otherLang); @endphp
                <a href="{{ $otherUrl }}" hreflang="{{ $otherLang }}" lang="{{ $otherLang }}" class="grid h-10 min-w-10 place-items-center rounded-xl px-2 text-sm font-extrabold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100" title="{{ \App\Support\Lang::LOCALES[$otherLang]['name'] }}">{{ \App\Support\Lang::LOCALES[$otherLang]['short'] }}</a>
                <a href="{{ lroute('storefront.favorites') }}" data-fav-link title="{{ __('المفضلة') }}" class="relative grid h-10 w-10 place-items-center rounded-xl text-slate-600 hover:bg-slate-100">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20s-7-4.5-9-9a4.8 4.8 0 0 1 9-3 4.8 4.8 0 0 1 9 3c-2 4.5-9 9-9 9z" stroke-linejoin="round"/></svg>
                    <span data-count="fav" class="absolute -top-0.5 -end-0.5 hidden min-w-5 rounded-full bg-rose-500 px-1 text-center text-[11px] font-bold leading-5 text-white"></span>
                </a>
                @if ($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hidden items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 2xl:inline-flex">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" stroke-linejoin="round"/></svg>
                        <span dir="ltr">{{ $phone }}</span>
                    </a>
                @endif
                @auth
                    <a href="{{ method_exists(auth()->user(), 'homeRoute') ? auth()->user()->homeRoute() : route('admin.dashboard') }}" class="inline-flex items-center gap-2 rounded-xl bg-[var(--accent)] px-3 py-2.5 text-sm font-extrabold text-[var(--accent-ink)] hover:brightness-95 sm:px-4">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6" stroke-linecap="round"/></svg>
                        <span class="hidden sm:inline">{{ __('حسابي') }}</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-3 py-2.5 text-sm font-extrabold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100 sm:px-4">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                        <span class="hidden sm:inline">{{ __('دخول') }}</span>
                    </a>
                @endauth
                <button type="button" onclick="document.getElementById('mobileNav').classList.toggle('hidden')" class="grid h-10 w-10 place-items-center rounded-xl text-slate-700 hover:bg-slate-100 lg:hidden" aria-label="{{ __('القائمة') }}">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        <nav id="mobileNav" class="hidden border-t border-slate-100 py-3 lg:hidden">
            <div class="grid grid-cols-2 gap-2">
                @foreach ($nav as [$r, $l])
                    <a href="{{ lroute($r) }}" class="rounded-xl px-4 py-3 text-sm font-bold {{ \App\Support\Lang::is($r) ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-700' }}">{{ $l }}</a>
                @endforeach
                @if ($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="col-span-2 rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-bold text-white" dir="ltr">📞 {{ $phone }}</a>
                @endif
            </div>
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

{{-- ═══════ الفوتر ═══════ --}}
<footer class="mt-24 bg-slate-950 text-slate-400">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="mb-4 flex items-center gap-2.5">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-10 w-auto rounded-lg bg-white p-1">
                    @else
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-[var(--accent)] text-lg font-black text-[var(--accent-ink)]">{{ mb_substr($siteName, 0, 1) }}</span>
                    @endif
                    <span class="text-lg font-extrabold text-white">{{ $siteName }}</span>
                </div>
                <p class="text-sm leading-relaxed">{{ Site::t('tagline') }}</p>
                @if ($social)
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($social as $k => $link)
                            <a href="{{ $link }}" target="_blank" rel="noopener" title="{{ ucfirst($k) }}" class="grid h-10 w-10 place-items-center rounded-xl bg-slate-800 text-slate-300 transition hover:bg-[var(--accent)] hover:text-[var(--accent-ink)]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $socialIcons[$k] !!}</svg>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-4 font-bold text-white">{{ __('تصفّح') }}</h3>
                <ul class="space-y-2.5 text-sm">
                    @foreach ($nav as [$r, $l])
                        <li><a href="{{ lroute($r) }}" class="transition hover:text-white">{{ $l }}</a></li>
                    @endforeach
                    <li><a href="{{ lroute('storefront.about') }}" class="transition hover:text-white">{{ __('من نحن') }}</a></li>
                </ul>
            </div>

            <div>
                <h3 class="mb-4 font-bold text-white">{{ __('فروعنا') }}</h3>
                <ul class="space-y-3 text-sm">
                    @forelse ($branches as $b)
                        <li>
                            <div class="font-semibold text-slate-200">📍 {{ \App\Support\Lang::pick($b, 'name') }}</div>
                            @if ($b->city ?? $b->address ?? null)<div class="text-xs">{{ $b->city ?? $b->address }}</div>@endif
                        </li>
                    @empty
                        <li>{{ Site::t('address') }}</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="mb-4 font-bold text-white">{{ __('تواصل معنا') }}</h3>
                <ul class="space-y-2.5 text-sm">
                    @if ($phone)<li>📞 <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" dir="ltr" class="hover:text-white">{{ $phone }}</a></li>@endif
                    @if ($wa)<li>💬 <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="hover:text-white">{{ __('واتساب') }}</a></li>@endif
                    @if (Site::get('email'))<li>✉️ <a href="mailto:{{ Site::get('email') }}" class="hover:text-white">{{ Site::get('email') }}</a></li>@endif
                    @if (Site::t('address'))<li>📍 {{ Site::t('address') }}</li>@endif
                    @if (Site::t('working_hours'))<li>🕘 {{ Site::t('working_hours') }}</li>@endif
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-slate-800 pt-6 text-xs sm:flex-row">
            <div>© {{ date('Y') }} {{ $siteName }}{{ __('. جميع الحقوق محفوظة.') }}</div>
            <div class="flex gap-4">
                <a href="{{ lroute('storefront.terms') }}" class="hover:text-white">{{ __('الشروط والأحكام') }}</a>
                <a href="{{ lroute('storefront.privacy') }}" class="hover:text-white">{{ __('سياسة الخصوصية') }}</a>
                @auth
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-white">{{ __('لوحة التحكم') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-white">{{ __('دخول') }}</a>
                @endauth
            </div>
        </div>
    </div>
</footer>

{{-- ═══════ شريط المقارنة ═══════ --}}
<div id="compareTray" class="fixed inset-x-0 z-40 hidden px-4 {{ $__env->hasSection('sticky_cta') ? 'bottom-24 lg:bottom-4' : 'bottom-4' }}">
    <div class="mx-auto flex max-w-md items-center justify-between gap-3 rounded-2xl bg-slate-900 p-2 ps-4 text-white shadow-2xl ring-1 ring-white/10">
        <span class="text-sm font-bold">{{ __('🔀 اخترت') }} <span data-count="compare">0</span> {{ __('من 3 للمقارنة') }}</span>
        <div class="flex gap-1.5">
            <button type="button" data-compare-clear class="rounded-xl px-3 py-2 text-xs font-bold text-slate-400 hover:text-white">{{ __('مسح') }}</button>
            <a href="{{ lroute('storefront.compare') }}" data-compare-link class="rounded-xl bg-[var(--accent)] px-4 py-2 text-sm font-extrabold text-[var(--accent-ink)]">{{ __('قارن دلوقتي') }}</a>
        </div>
    </div>
</div>

{{-- ═══════ زرار واتساب ثابت ═══════ --}}
@if ($wa)
    <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode(trim($__env->yieldContent('wa_text')) ?: __('السلام عليكم، عندي استفسار عن سيارة من ').$siteName) }}"
       target="_blank" rel="noopener" data-track="Contact" aria-label="{{ __('واتساب') }}"
       class="fixed bottom-5 end-5 z-40 grid h-14 w-14 place-items-center rounded-full bg-emerald-500 text-white shadow-xl shadow-emerald-500/30 transition hover:scale-110 {{ $__env->hasSection('sticky_cta') ? 'max-lg:hidden' : '' }}">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.3-.5 0-1 .2-3.3-.7a11.6 11.6 0 0 1-4.6-4.1c-.4-.5-1.1-1.6-1.1-3s.8-2.1 1-2.4a1.1 1.1 0 0 1 .8-.4h.6c.2 0 .4 0 .6.5l.9 2.1a.5.5 0 0 1 0 .5l-.4.6-.4.4c-.1.1-.3.3-.1.6a8.7 8.7 0 0 0 1.6 2 8 8 0 0 0 2.3 1.4c.3.2.5.1.6 0l.9-1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg>
    </a>
@endif

<div id="toast" class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center px-4 opacity-0 transition duration-300">
    <div class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-2xl"></div>
</div>

@yield('sticky_cta')

<script>
window.LV = (function () {
    const store = {
        get(k) { try { return JSON.parse(localStorage.getItem('lv_' + k) || '[]'); } catch (e) { return []; } },
        set(k, v) { try { localStorage.setItem('lv_' + k, JSON.stringify(v)); } catch (e) {} },
    };
    const toast = (msg) => {
        const t = document.getElementById('toast');
        t.querySelector('div').textContent = msg;
        t.style.opacity = 1; clearTimeout(t._h); t._h = setTimeout(() => { t.style.opacity = 0; }, 2200);
    };
    // تتبع موحّد لكل البيكسلات
    const track = (event, data = {}) => {
        const map = { Lead: ['generate_lead', 'SubmitForm', 'SIGN_UP'], Contact: ['contact', 'Contact', 'CUSTOM_EVENT_1'], ViewContent: ['view_item', 'ViewContent', 'VIEW_CONTENT'], AddToWishlist: ['add_to_wishlist', 'AddToWishlist', 'ADD_TO_WISHLIST'], Search: ['search', 'Search', 'SEARCH'] }[event] || [event, event, event];
        try { window.fbq && fbq('track', event, data); } catch (e) {}
        try { window.gtag && gtag('event', map[0], data); } catch (e) {}
        try { window.ttq && ttq.track(map[1], data); } catch (e) {}
        try { window.snaptr && snaptr('track', map[2]); } catch (e) {}
        try { (window.dataLayer = window.dataLayer || []).push(Object.assign({ event: 'lv_' + event.toLowerCase() }, data)); } catch (e) {}
    };
    const refresh = () => {
        const fav = store.get('fav'), cmp = store.get('compare');
        document.querySelectorAll('[data-fav]').forEach((b) => b.classList.toggle('is-on', fav.includes(+b.dataset.fav)));
        document.querySelectorAll('[data-compare]').forEach((b) => b.classList.toggle('is-on', cmp.includes(+b.dataset.compare)));
        document.querySelectorAll('[data-count=fav]').forEach((el) => { el.textContent = fav.length; el.classList.toggle('hidden', !fav.length); });
        document.querySelectorAll('[data-count=compare]').forEach((el) => { el.textContent = cmp.length; });
        document.querySelectorAll('[data-fav-link]').forEach((a) => { a.href = '{{ lroute('storefront.favorites') }}' + (fav.length ? '?ids=' + fav.join(',') : ''); });
        document.querySelectorAll('[data-compare-link]').forEach((a) => { a.href = '{{ lroute('storefront.compare') }}?ids=' + cmp.join(','); });
        document.getElementById('compareTray').classList.toggle('hidden', !cmp.length || location.pathname.endsWith('/compare'));
    };
    document.addEventListener('click', (e) => {
        const f = e.target.closest('[data-fav]'), c = e.target.closest('[data-compare]'), clr = e.target.closest('[data-compare-clear]'), tr = e.target.closest('[data-track]');
        if (tr) track(tr.dataset.track);
        if (f) {
            e.preventDefault();
            let list = store.get('fav'); const id = +f.dataset.fav;
            if (list.includes(id)) { list = list.filter((x) => x !== id); toast(@js(__('اتشالت من المفضلة'))); }
            else { list.push(id); toast(@js(__('❤️ اتضافت للمفضلة'))); track('AddToWishlist', { content_ids: [String(id)], content_type: 'vehicle' }); }
            store.set('fav', list); refresh();
        }
        if (c) {
            e.preventDefault();
            let list = store.get('compare'); const id = +c.dataset.compare;
            if (list.includes(id)) list = list.filter((x) => x !== id);
            else if (list.length >= 3) { toast(@js(__('أقصى حاجة 3 عربيات في المقارنة'))); return; }
            else { list.push(id); toast(@js(__('🔀 اتضافت للمقارنة'))); }
            store.set('compare', list); refresh();
        }
        if (clr) { store.set('compare', []); refresh(); }
    });

    // العدّاد التنازلي للعروض
    const tick = () => document.querySelectorAll('[data-countdown]').forEach((el) => {
        const diff = new Date(el.dataset.countdown) - new Date();
        if (diff <= 0) { el.textContent = @js(__('انتهى العرض')); return; }
        const d = Math.floor(diff / 864e5), h = Math.floor(diff / 36e5) % 24, m = Math.floor(diff / 6e4) % 60, s = Math.floor(diff / 1e3) % 60;
        const p = (n) => String(n).padStart(2, '0');
        el.textContent = (d ? d + @js(__(' يوم  ')) : '') + p(h) + ':' + p(m) + ':' + p(s);
    });
    tick(); setInterval(tick, 1000);

    // ظهور ناعم للأقسام
    const io = 'IntersectionObserver' in window ? new IntersectionObserver((es) => es.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }), { rootMargin: '0px 0px -40px' }) : null;
    document.querySelectorAll('.reveal').forEach((el) => io ? io.observe(el) : el.classList.add('in'));

    // وقت فتح الفورم (حماية من البوتات)
    document.querySelectorAll('input[name=_ts]').forEach((i) => { i.value = Math.floor(Date.now() / 1000); });
    document.querySelectorAll('form[data-lead]').forEach((f) => f.addEventListener('submit', () => {
        const b = f.querySelector('[type=submit]'); if (b) { b.disabled = true; b.dataset.label = b.textContent; b.textContent = @js(__('جاري الإرسال...')); }
    }));

    refresh();
    return { store, toast, track, refresh };
})();
</script>

@if (session('lead_event'))
    <script>LV.track('Lead', {{ \Illuminate\Support\Js::from(['content_category' => session('lead_event')['type'] ?? 'inquiry', 'value' => session('lead_event')['value'] ?? 0, 'currency' => 'OMR']) }});</script>
@endif
@stack('scripts')
</body>
</html>
