@php
    // لغة الصفحة: ?lang=en أو ?lang=ar (وبتفضل محفوظة للوحة بعد الدخول)
    if (in_array(request('lang'), ['ar', 'en'], true)) {
        session(['admin_locale' => request('lang')]);
    }
    $loc = in_array(session('admin_locale'), ['ar', 'en'], true) ? session('admin_locale') : config('app.admin_locale', 'ar');
    app()->setLocale($loc);
    $en = $loc === 'en';

    try {
        $companyName = \App\Support\Company::name($en);
        $logo = \App\Support\Company::logo();
    } catch (\Throwable $e) {
        $companyName = config('app.name');
        $logo = null;
    }
    $fail = $errors->first('email') ?: $errors->first('password');
    // Laravel بيكتب رسالة الرفض بلغة السيرفر (غالباً إنجليزي) — نعرضها بلغة الصفحة
    if ($fail) {
        $other = $en ? 'ar' : 'en';
        if ($fail === trans('auth.failed', [], $other)) {
            $fail = trans('auth.failed', [], $loc);
        } elseif (preg_match('/(\d+)/', $fail, $m) && str_starts_with($fail, \Illuminate\Support\Str::before(trans('auth.throttle', ['seconds' => '§'], $other), '§'))) {
            $fail = trans('auth.throttle', ['seconds' => $m[1], 'minutes' => ceil($m[1] / 60)], $loc);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $loc }}" dir="{{ $en ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('تسجيل الدخول') }} — {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    @includeIf('admin.partials.dir-css')
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: {{ $en ? "'Inter', 'Tajawal'" : "'Tajawal'" }}, system-ui, sans-serif; }
        .brand-bg { background: radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.35), transparent 60%), radial-gradient(900px 500px at -10% 110%, rgba(16,185,129,.22), transparent 60%), #0f172a; }
        .grid-lines { background-image: linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px); background-size: 40px 40px; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
<div class="grid min-h-screen lg:grid-cols-2">

    {{-- ═══ لوحة الهوية ═══ --}}
    <aside class="brand-bg relative hidden overflow-hidden p-12 text-white lg:flex lg:flex-col">
        <div class="grid-lines pointer-events-none absolute inset-0"></div>
        <div class="relative flex items-center gap-3">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="h-12 w-12 rounded-2xl bg-white object-contain p-1.5">
            @else
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-blue-600 text-2xl">🚗</span>
            @endif
            <span class="text-xl font-extrabold">{{ $companyName }}</span>
        </div>

        <div class="relative my-auto max-w-md">
            <h1 class="text-4xl font-extrabold leading-tight">{{ __('المعرض كله في مكان واحد') }}</h1>
            <p class="mt-4 text-lg leading-relaxed text-slate-300">{{ __('المخزون، والعقود، والأقساط، والعملاء، والتقارير — من أي جهاز وفي أي وقت.') }}</p>
            <ul class="mt-8 space-y-3 text-slate-200">
                @foreach ([['📊', __('أرقامك اليومية على الداشبورد لحظة بلحظة')], ['✍️', __('العقود بتتوقّع من الموبايل برابط')], ['🔔', __('تنبيهات بالأقساط والشيكات قبل ميعادها')], ['🔒', __('صلاحيات لكل موظف ونسخة احتياطية مشفّرة')]] as [$i, $t])
                    <li class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10">{{ $i }}</span>{{ $t }}</li>
                @endforeach
            </ul>
        </div>

        <div class="relative text-sm text-slate-400">© {{ date('Y') }} {{ $companyName }}</div>
    </aside>

    {{-- ═══ الفورم ═══ --}}
    <main class="flex flex-col px-4 py-6 sm:px-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 lg:invisible">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="h-10 w-10 rounded-xl bg-white object-contain p-1 ring-1 ring-slate-200">
                @else
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-lg text-white">🚗</span>
                @endif
                <span class="font-extrabold text-slate-900">{{ $companyName }}</span>
            </div>
            <a href="{{ request()->fullUrlWithQuery(['lang' => $en ? 'ar' : 'en']) }}" class="rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-600 shadow-sm ring-1 ring-slate-200 hover:text-slate-900">
                {!! $en ? 'العربية' : 'English' !!}
            </a>
        </div>

        <div class="mx-auto my-auto w-full max-w-md py-10">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold text-slate-900">{{ __('أهلاً بيك 👋') }}</h2>
                <p class="mt-2 text-slate-500">{{ __('سجّل دخولك عشان تكمّل شغلك') }}</p>
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 ring-1 ring-emerald-200">{{ session('status') }}</div>
            @endif
            @if ($fail)
                <div class="mb-5 flex items-start gap-3 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 ring-1 ring-rose-200">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-rose-600 text-xs text-white">!</span>
                    <span>{{ $fail }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5"
                  onsubmit="const b = this.querySelector('[type=submit]'); b.disabled = true; b.querySelector('span').textContent = @js(__('جاري الدخول...'));">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-bold text-slate-700">{{ __('البريد الإلكتروني') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 start-0 grid w-11 place-items-center text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" dir="ltr" placeholder="email@example.com"
                               class="w-full rounded-2xl border bg-white py-3.5 pe-4 ps-11 text-sm outline-none transition focus:ring-4 {{ $errors->has('email') ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-500 focus:ring-blue-50' }}">
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="password" class="text-sm font-bold text-slate-700">{{ __('كلمة المرور') }}</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-bold text-blue-600 hover:underline">{{ __('نسيت كلمة المرور؟') }}</a>
                        @endif
                    </div>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 start-0 grid w-11 place-items-center text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </span>
                        <input id="password" name="password" type="password" required autocomplete="current-password" dir="ltr" placeholder="••••••••"
                               class="w-full rounded-2xl border bg-white py-3.5 pe-12 ps-11 text-sm outline-none transition focus:ring-4 {{ $errors->has('password') ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-500 focus:ring-blue-50' }}">
                        <button type="button" id="togglePass" title="{{ __('إظهار / إخفاء') }}" class="absolute inset-y-0 end-0 grid w-12 place-items-center text-slate-400 hover:text-slate-700">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <p id="capsWarn" class="mt-1.5 hidden text-xs font-bold text-amber-600">⚠️ {{ __('زرار Caps Lock شغال') }}</p>
                </div>

                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded accent-blue-600">
                    {{ __('خليني مسجّل دخول على الجهاز ده') }}
                </label>

                <button type="submit" data-test="login-button"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 py-3.5 text-base font-extrabold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 disabled:opacity-70">
                    <span>{{ __('دخول') }}</span>
                    <svg class="h-5 w-5 {{ $en ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </button>
            </form>

            @if (Route::has('customer.login'))
                <p class="mt-8 text-center text-sm text-slate-500">
                    {{ __('عميل وعايز تتابع أقساطك؟') }}
                    <a href="{{ route('customer.login') }}" class="font-bold text-blue-600 hover:underline">{{ __('بوابة العملاء') }}</a>
                </p>
            @endif
        </div>

        <p class="text-center text-xs text-slate-400">{{ __('الدخول بيتسجل في سجل النشاط — لو دي مش أنت، بلّغ الإدارة.') }}</p>
    </main>
</div>

<script>
    (function () {
        const pass = document.getElementById('password');
        document.getElementById('togglePass').addEventListener('click', () => {
            pass.type = pass.type === 'password' ? 'text' : 'password';
            pass.focus();
        });
        const warn = document.getElementById('capsWarn');
        pass.addEventListener('keyup', (e) => warn.classList.toggle('hidden', !e.getModifierState?.('CapsLock')));
    })();
</script>
</body>
</html>
