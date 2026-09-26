<div class="relative overflow-hidden bg-slate-950">
    <div class="absolute inset-0" style="background:radial-gradient(50% 90% at 10% 0%, color-mix(in srgb, var(--accent) 30%, transparent), transparent 60%)"></div>
    <div class="relative mx-auto max-w-7xl px-4 pb-20 pt-12 sm:px-6 lg:px-8">
        <nav class="mb-3 text-xs font-bold text-slate-500"><a href="{{ lroute('storefront.home') }}" class="hover:text-white">{{ __('الرئيسية') }}</a> / <span class="text-slate-300">{{ $title }}</span></nav>
        <h1 class="text-3xl font-black text-white sm:text-5xl">{{ $icon ?? '' }} {{ $title }}</h1>
        @if (! empty($sub))<p class="mt-3 max-w-2xl text-lg text-slate-400">{{ $sub }}</p>@endif
    </div>
</div>
