@php
    $company = $company ?? \App\Support\Company::info();
    $headerImage = \App\Support\Media::url($company->header_image ?? null);
    $seal = \App\Support\Media::url($company->seal ?? null);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ \App\Support\Lang::pick($company, 'name') ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    @include('admin.partials.dir-css')
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Tajawal', system-ui, sans-serif; }
        [dir="rtl"] [data-page-align] { text-align: right; } [dir="ltr"] [data-page-align] { text-align: left; }
        @page { size: A4; margin: 14mm; }
        @media print { .no-print { display: none !important; } body { background: #fff !important; } .sheet { box-shadow: none !important; margin: 0 !important; } }
    </style>
</head>
<body class="bg-slate-200 text-slate-800">

<div class="no-print sticky top-0 z-10 flex items-center justify-between bg-slate-900 px-6 py-3 text-sm text-white">
    <a href="{{ url()->previous() }}" class="font-semibold text-slate-300 hover:text-white">{{ __('→ رجوع') }}</a>
    <button onclick="window.print()" class="rounded-lg bg-blue-600 px-4 py-2 font-bold hover:bg-blue-700">{{ __('طباعة / حفظ PDF') }}</button>
</div>

<div class="sheet mx-auto my-8 max-w-[210mm] bg-white p-10 shadow-xl">
    {{-- ترويسة الشركة (من إعدادات النظام) --}}
    @if ($headerImage)
        <header class="border-b-2 border-slate-900 pb-3"><img src="{{ $headerImage }}" alt="" class="w-full object-contain"></header>
    @else
        <header class="flex items-start justify-between border-b-2 border-slate-900 pb-5">
            <div>
                @php $pen = app()->getLocale() === 'en' && ! empty($company->name_en); @endphp
                <div class="text-2xl font-extrabold text-slate-900">{{ $pen ? $company->name_en : (\App\Support\Lang::pick($company, 'name') ?? config('app.name')) }}</div>
                @if ($pen && ! empty($company->name_ar)) <div class="text-sm text-slate-500" dir="rtl" data-page-align>{{ \App\Support\Lang::pick($company, 'name') }}</div>
                @elseif (! $pen && ! empty($company->name_en)) <div class="text-sm text-slate-500" dir="ltr" data-page-align>{{ $company->name_en }}</div> @endif
                <div class="mt-2 space-y-0.5 text-xs text-slate-500">
                    @if (app()->getLocale() === 'en' && ! empty($company->address_en)) <div>{{ $company->address_en }}</div> @elseif (! empty($company->address_ar)) <div>{{ \App\Support\Lang::pick($company, 'address') }}</div> @endif
                    <div>
                        @if (! empty($company->phone)) {{ __('هاتف:') }} <span dir="ltr">{{ $company->phone }}</span> @endif
                        @if (! empty($company->email)) · <span dir="ltr">{{ $company->email }}</span> @endif
                        @if (! empty($company->commercial_register_number)) {{ __('· سجل تجاري:') }} {{ $company->commercial_register_number }} @endif
                        @if (! empty($company->tax_number)) {{ __('· رقم ضريبي:') }} {{ $company->tax_number }} @endif
                    </div>
                </div>
            </div>
            @if (! empty($company->logo))
                <img src="{{ \App\Support\Media::url($company->logo) }}" alt="" class="h-16 w-auto object-contain">
            @endif
        </header>
    @endif

    <div class="mt-6 text-center">
        <h1 class="text-xl font-extrabold text-slate-900">@yield('heading')</h1>
        @hasSection('subheading') <div class="mt-1 text-sm text-slate-500">@yield('subheading')</div> @endif
    </div>

    <main class="relative mt-6 text-sm leading-relaxed">
        @yield('content')
        @if ($seal && ! $__env->hasSection('no_seal'))
            <img src="{{ $seal }}" alt="" class="pointer-events-none absolute bottom-6 end-10 h-24 w-24 -rotate-6 object-contain opacity-80">
        @endif
    </main>

    <footer class="mt-10 border-t border-slate-200 pt-3 text-center text-[11px] text-slate-400">
        @if (! empty($company->footer_text)) <div class="mb-1 text-xs font-semibold text-slate-500">{{ $company->footer_text }}</div> @endif
        {{ __('اتطبع في') }} {{ now()->format('Y-m-d H:i') }} {{ __('بواسطة') }} {{ auth()->user()?->name }}
    </footer>
</div>

@if (request()->boolean('auto'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
