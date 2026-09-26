<!DOCTYPE html>
@php $rtl = app()->getLocale() !== 'en'; @endphp
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('لوحة الإدارة')) — {{ \App\Support\Company::name() }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @include('admin.partials.dir-css')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Tajawal', system-ui, sans-serif; }
        [dir="rtl"] [data-page-align] { text-align: right; } [dir="ltr"] [data-page-align] { text-align: left; }
        html[dir="ltr"] body { font-family: 'Inter', 'Tajawal', system-ui, sans-serif; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }

        @media (min-width: 1024px) {
            body.sidebar-mini #sidebar { width: 5rem; }
            body.sidebar-mini .main-wrap { margin-inline-start: 5rem; }
            body.sidebar-mini #sidebar .nav-text,
            body.sidebar-mini #sidebar .nav-sub { display: none; }
            body.sidebar-mini #sidebar summary,
            body.sidebar-mini #sidebar > nav > a { justify-content: center; }
        }
    </style>

    <script>
        try { if (localStorage.getItem('sidebar-mini') === '1') document.documentElement.classList.add('pre-mini'); } catch (e) {}
    </script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<script>
    if (document.documentElement.classList.contains('pre-mini')) document.body.classList.add('sidebar-mini');
</script>

@include('admin.partials.sidebar')

<div id="sidebarOverlay" class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

<div class="main-wrap flex min-h-screen flex-col transition-[margin] duration-200 lg:ms-64">

    {{-- ═══════ الشريط العلوي ═══════ --}}
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6">

            <button id="sidebarToggle" type="button" aria-label="{{ __('القائمة') }}"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <path d="M4 7h16M4 12h10M4 17h16"/>
                </svg>
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-extrabold text-slate-900">@yield('title', __('لوحة الإدارة'))</h1>
                @hasSection('subtitle')
                    <p class="truncate text-xs text-slate-500">@yield('subtitle')</p>
                @endif
            </div>

            @if (\Illuminate\Support\Facades\Route::has('storefront.home'))
                <a href="{{ route('storefront.home') }}" target="_blank" title="{{ __('عرض المتجر') }}"
                   class="hidden h-10 items-center gap-2 rounded-full px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 sm:flex">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 4h6v6M20 4l-8 8M18 14v5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V8a1.5 1.5 0 0 1 1.5-1.5H10"/>
                    </svg>
                    {{ __('المتجر') }}
                </a>
            @endif

            {{-- اللغة: كل موظف يختار لغة اللوحة بتاعته --}}
            @if (\Illuminate\Support\Facades\Route::has('admin.locale.switch'))
                @php $other = $rtl ? 'en' : 'ar'; @endphp
                <form method="POST" action="{{ route('admin.locale.switch', $other) }}">
                    @csrf
                    <button type="submit" title="{{ $rtl ? 'Switch to English' : __('التحويل للعربي') }}"
                            class="grid h-10 min-w-10 place-items-center rounded-full px-2 text-xs font-extrabold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-100 hover:text-slate-900">
                        {{ $rtl ? 'EN' : __('ع') }}
                    </button>
                </form>
            @endif

            {{-- الجرس: تنبيهات محسوبة حسب صلاحيات الموظف وفرعه --}}
            @if (\Illuminate\Support\Facades\Route::has('admin.notifications.index') && auth()->user())
                @php $bellCount = \App\Support\Alerts::unseenCount(auth()->user()); @endphp
                <details class="relative" id="bell">
                    <summary title="{{ __('التنبيهات') }}" class="relative grid h-10 w-10 cursor-pointer list-none place-items-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 [&::-webkit-details-marker]:hidden">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9a6 6 0 1 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9"/><path d="M10 19a2 2 0 0 0 4 0"/>
                        </svg>
                        @if ($bellCount > 0)
                            <span data-bell-count class="absolute start-1 top-1 min-w-[18px] rounded-full bg-rose-500 px-1 text-center text-[10px] font-bold leading-[18px] text-white">{{ $bellCount > 9 ? '9+' : $bellCount }}</span>
                        @endif
                    </summary>
                    <div class="absolute end-0 z-30 mt-2 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <span class="text-sm font-extrabold text-slate-900">{{ __('التنبيهات') }}</span>
                            <button type="button" data-bell-refresh class="text-xs font-bold text-blue-600 hover:underline">{{ __('🔄 تحديث') }}</button>
                        </div>
                        <div data-bell-list class="max-h-[70vh] overflow-y-auto"><div class="p-8 text-center text-sm text-slate-400">{{ __('جاري التحميل…') }}</div></div>
                    </div>
                </details>
                <script>
                    (() => {
                        const bell = document.getElementById('bell');
                        const list = bell.querySelector('[data-bell-list]');
                        const token = document.querySelector('meta[name="csrf-token"]').content;
                        const load = async (refresh) => {
                            if (refresh) list.innerHTML = @js(__('<div class="p-8 text-center text-sm text-slate-400">جاري التحميل…</div>'));
                            await fetch({{ \Illuminate\Support\Js::from(route('admin.notifications.seen')) }}, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ refresh: !!refresh }) }).catch(() => {});
                            const res = await fetch({{ \Illuminate\Support\Js::from(route('admin.notifications.index', ['partial' => 1])) }}, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                            list.innerHTML = res.ok ? await res.text() : @js(__('<div class="p-8 text-center text-sm text-rose-500">حصلت مشكلة — جرّب تاني</div>'));
                            bell.querySelector('[data-bell-count]')?.remove();
                        };
                        bell.addEventListener('toggle', () => bell.open && load(false));
                        bell.querySelector('[data-bell-refresh]').addEventListener('click', () => load(true));
                        document.addEventListener('click', (e) => { if (bell.open && !bell.contains(e.target)) bell.open = false; });
                    })();
                </script>
            @endif

            {{-- قائمة المستخدم --}}
            <details class="relative">
                <summary class="flex cursor-pointer items-center gap-2 rounded-full py-1 pe-1 ps-3 transition hover:bg-slate-100">
                    <svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                    <span class="hidden text-sm font-bold text-slate-800 sm:block">{{ auth()->user()?->name }}</span>
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white">
                        {{ mb_substr(auth()->user()?->name ?? __('؟'), 0, 1) }}
                    </span>
                </summary>

                <div class="absolute end-0 z-30 mt-2 w-60 overflow-hidden rounded-2xl bg-white py-2 shadow-xl ring-1 ring-slate-200">
                    <div class="border-b border-slate-100 px-4 pb-3 pt-1">
                        <div class="truncate text-sm font-bold text-slate-900">{{ auth()->user()?->name }}</div>
                        <div class="truncate text-xs text-slate-500" dir="ltr">{{ auth()->user()?->email }}</div>
                        <span class="mt-2 inline-block rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                            {{ match (auth()->user()?->role) { 'admin' => __('مدير النظام'), 'staff' => __('موظف'), default => __('مستخدم') } }}
                        </span>
                    </div>

                    @if (\Illuminate\Support\Facades\Route::has('profile.edit'))
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"/></svg>
                            {{ __('حسابي') }}
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12H4m0 0 3.5-3.5M4 12l3.5 3.5M10 7V5.5A1.5 1.5 0 0 1 11.5 4h7A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 10 18.5V17"/></svg>
                            {{ __('تسجيل الخروج') }}
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </header>

    {{-- ═══════ المحتوى ═══════ --}}
    <main class="flex-1 px-4 py-6 sm:px-6">

        @hasSection('actions')
            <div class="mb-5 flex flex-wrap items-center gap-2">@yield('actions')</div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white px-6 py-4 text-xs text-slate-400">
        © {{ date('Y') }} {{ \App\Support\Company::name() }}@if ($ft = \App\Support\Company::get('footer_text')) · {{ $ft }}@endif
    </footer>
</div>

{{-- رسالة نجاح بتختفي لوحدها --}}
@if (session('status'))
    <div id="toast"
         class="fixed bottom-6 end-6 z-50 flex max-w-sm items-center gap-3 rounded-2xl bg-slate-900 px-5 py-4 text-sm font-medium text-white shadow-2xl transition duration-300">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-emerald-500">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
        </span>
        <span class="flex-1">{{ session('status') }}</span>
        <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white" aria-label="{{ __('إغلاق') }}">✕</button>
    </div>
@endif

@if (session('error'))
    <div id="toast"
         class="fixed bottom-6 end-6 z-50 flex max-w-sm items-center gap-3 rounded-2xl bg-rose-600 px-5 py-4 text-sm font-medium text-white shadow-2xl transition duration-300">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-white/20 font-bold">!</span>
        <span class="flex-1">{{ session('error') }}</span>
        <button type="button" onclick="this.parentElement.remove()" class="text-white/70 hover:text-white" aria-label="{{ __('إغلاق') }}">✕</button>
    </div>
@endif

<script>
    (function () {
        const store = {
            get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } },
            set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} },
        };

        const bar     = document.getElementById('sidebar');
        const HIDE    = document.documentElement.dir === 'rtl' ? 'translate-x-full' : '-translate-x-full';
        const overlay = document.getElementById('sidebarOverlay');
        const toggle  = document.getElementById('sidebarToggle');
        const desktop = () => window.matchMedia('(min-width: 1024px)').matches;

        // زرار القائمة: على الكمبيوتر بيصغّر السايدبار، على الموبايل بيفتحه
        const setOpen = (open) => {
            bar.classList.toggle(HIDE, !open);
            overlay.classList.toggle('hidden', !open);
        };
        toggle?.addEventListener('click', () => {
            if (desktop()) {
                const mini = document.body.classList.toggle('sidebar-mini');
                store.set('sidebar-mini', mini ? '1' : '0');
            } else {
                setOpen(bar.classList.contains(HIDE));
            }
        });
        overlay?.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (e) => e.key === 'Escape' && setOpen(false));

        // المجموعات: تفتكر المفتوح، ولو السايدبار مصغّر تكبّره
        document.querySelectorAll('.nav-group').forEach((group) => {
            const key = group.dataset.key;
            if (!group.hasAttribute('data-active') && store.get(key) === '1') group.open = true;

            group.querySelector('summary').addEventListener('click', () => {
                if (desktop() && document.body.classList.contains('sidebar-mini')) {
                    document.body.classList.remove('sidebar-mini');
                    store.set('sidebar-mini', '0');
                }
            });
            group.addEventListener('toggle', () => store.set(key, group.open ? '1' : '0'));
        });

        // البحث في القائمة
        const search = document.getElementById('navSearch');
        search?.addEventListener('input', () => {
            const q = search.value.trim();
            document.querySelectorAll('.nav-group').forEach((group) => {
                let hits = 0;
                group.querySelectorAll('.nav-sub .nav-link').forEach((link) => {
                    const match = !q || link.textContent.includes(q);
                    link.style.display = match ? '' : 'none';
                    if (match) hits++;
                });
                group.style.display = hits ? '' : 'none';
                if (q && hits) group.open = true;
            });
        });

        // قفل قائمة المستخدم لما تدوس برة
        document.addEventListener('click', (e) => {
            document.querySelectorAll('header details[open]').forEach((d) => {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });

        // الرسالة تختفي بعد 4 ثواني
        const toast = document.getElementById('toast');
        if (toast) setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
    })();
</script>

@include('admin.partials.searchable-select')
<script>
    // إدخال البيانات بأي لغة: الخانة بتاخد اتجاه اللي بيتكتب فيها (عربي يمين / إنجليزي شمال)
    (function () {
        const SEL = 'input[type=text]:not([dir]), input:not([type]):not([dir]), input[type=search]:not([dir]), textarea:not([dir])';
        const auto = (root) => root.querySelectorAll(SEL).forEach((el) => el.setAttribute('dir', 'auto'));
        auto(document);
        new MutationObserver((m) => m.forEach((r) => r.addedNodes.forEach((n) => n.nodeType === 1 && auto(n)))).observe(document.body, { childList: true, subtree: true });

        // في الإنجليزي: لو الفورم فيها خانة عربي + خانة إنجليزي، الإنجليزي هي الإجبارية والعربي اختياري (بتتملى أوتوماتيك من الإنجليزي)
        if (document.documentElement.lang !== 'en') return;
        document.querySelectorAll('form').forEach((form) => {
            form.querySelectorAll('[name$="_ar"][required]').forEach((ar) => {
                const en = form.querySelector('[name="' + ar.name.slice(0, -3) + '_en"]');
                if (!en) return;
                ar.removeAttribute('required');
                en.setAttribute('required', '');
                const star = (el) => el.id && form.querySelector('label[for="' + el.id + '"] .text-rose-500');
                const s = star(ar);
                const enLabel = en.id && form.querySelector('label[for="' + en.id + '"]');
                if (s && enLabel && !enLabel.querySelector('.text-rose-500')) enLabel.appendChild(s);
            });
        });
    })();
</script>
</body>
</html>
