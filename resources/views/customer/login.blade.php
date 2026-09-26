<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بوابة العملاء | {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>body { font-family: 'Tajawal', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-slate-800">بوابة العملاء</h1>
            <p class="text-slate-500 mt-1">تابع عربيتك وأقساطك في أي وقت</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 sm:p-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 text-green-700 text-sm p-3">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm p-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ url('/login') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="portal" value="customer">

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           required autofocus autocomplete="username" dir="ltr"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">كلمة المرور</label>
                    <input id="password" type="password" name="password"
                           required autocomplete="current-password" dir="ltr"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 outline-none">
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-slate-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300">
                        تذكرني
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-blue-600 hover:underline">نسيت كلمة المرور؟</a>
                    @endif
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 transition">
                    دخول
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-slate-500 mt-6">
            مش لاقي بيانات الدخول؟ تواصل مع المعرض وهيبعتهالك.
        </p>
    </div>

</body>
</html>
