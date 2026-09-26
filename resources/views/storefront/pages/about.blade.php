@extends('storefront.layouts.app')
@php use App\Support\Site; use App\Support\Media; @endphp
@section('title', __('من نحن — ').Site::name())

@section('content')
@include('storefront.partials.page-hero', ['title' => __('من نحن'), 'sub' => Site::t('tagline')])

<div class="relative z-10 mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="rounded-[2rem] bg-white p-6 ring-1 ring-slate-200 sm:p-10">
        <div class="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
            <div class="whitespace-pre-line text-lg leading-loose text-slate-700">{{ Site::t('about') ?: Site::name().__(' — معرض سيارات متخصص في البيع بالكاش والتقسيط، بخدمة واضحة ومتابعة كاملة لعملائنا من أول زيارة لحد ما يستلموا عربيتهم وبعدها.') }}</div>
            <div class="grid grid-cols-2 gap-3 self-start">
                @foreach ([['cars', __('سيارة متاحة'), '🚗'], ['sold', __('سيارة اتباعت'), '🤝'], ['branches', __('فرع'), '📍']] as [$k, $l, $ic])
                    @if ($stats[$k])<div class="rounded-3xl bg-slate-50 p-5 text-center"><div class="text-2xl">{{ $ic }}</div><div class="text-3xl font-black text-slate-900">{{ number_format($stats[$k]) }}</div><div class="text-xs font-bold text-slate-500">{{ $l }}</div></div>@endif
                @endforeach
            </div>
        </div>
    </div>

    @if ($features->isNotEmpty())
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($features as $f)
                <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200"><div class="text-3xl">{{ $f->icon ?: '✨' }}</div><h3 class="mt-3 font-extrabold text-slate-900">{{ \App\Support\Lang::pick($f, 'title') }}</h3><p class="mt-1 text-sm text-slate-500">{{ \App\Support\Lang::pick($f, 'body') }}</p></div>
            @endforeach
        </div>
    @endif

    @if ($testimonials->isNotEmpty())
        <div class="mt-10 grid gap-4 md:grid-cols-3">
            @foreach ($testimonials->take(6) as $t)
                <figure class="rounded-3xl bg-white p-6 ring-1 ring-slate-200"><div class="text-amber-400">{{ str_repeat('★', (int) ($t->rating ?: 5)) }}</div><blockquote class="mt-2 text-slate-700">“{{ \App\Support\Lang::pick($t, 'body') }}”</blockquote><figcaption class="mt-3 font-extrabold text-slate-900">— {{ \App\Support\Lang::pick($t, 'title') }}</figcaption></figure>
            @endforeach
        </div>
    @endif
</div>
@endsection
