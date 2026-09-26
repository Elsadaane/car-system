{{-- كارت مقال: @include('storefront.partials.post-card', ['p' => $post, 'big' => false]) --}}
@php
    $url = lroute('storefront.blog.show', $p->slug);
    $img = \App\Support\Media::url($p->cover_image);
    $title = \App\Support\Blog::t($p, 'title');
    $big = $big ?? false;
@endphp
<article class="group flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200 transition hover:-translate-y-1 hover:shadow-xl {{ $big ? 'lg:flex-row' : '' }}">
    <a href="{{ $url }}" class="relative block shrink-0 overflow-hidden bg-slate-100 {{ $big ? 'aspect-[16/9] lg:aspect-auto lg:w-3/5' : 'aspect-[16/9]' }}">
        @if ($img)
            <img src="{{ $img }}" alt="{{ $p->cover_alt ?: $title }}" loading="lazy" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
        @else
            <div class="grid h-full w-full place-items-center bg-gradient-to-br from-slate-800 to-slate-950 text-5xl">📰</div>
        @endif
        @if (! empty($p->category_ar))
            <span class="absolute top-3 start-3 rounded-full bg-white/90 px-3 py-1 text-[11px] font-extrabold text-slate-800 backdrop-blur">{{ app()->getLocale() === 'en' && $p->category_en ? $p->category_en : $p->category_ar }}</span>
        @endif
    </a>
    <a href="{{ $url }}" class="flex flex-1 flex-col p-5 {{ $big ? 'lg:justify-center lg:p-10' : '' }}">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-400">
            <span>{{ \Illuminate\Support\Carbon::parse($p->published_at)->translatedFormat('j M Y') }}</span><span>•</span><span>⏱️ {{ $p->reading_minutes }} {{ __('دقايق قراءة') }}</span>
        </div>
        <h3 class="mt-2 font-black leading-snug text-slate-900 group-hover:text-slate-600 {{ $big ? 'text-2xl lg:text-3xl' : 'text-lg' }}">{{ $title }}</h3>
        @if ($ex = \App\Support\Blog::t($p, 'excerpt'))
            <p class="mt-2 text-sm leading-relaxed text-slate-500 {{ $big ? '' : 'line-clamp-2' }}">{{ $ex }}</p>
        @endif
        <span class="mt-auto pt-4 text-sm font-extrabold text-slate-900">{{ __('اقرأ المقال ←') }}</span>
    </a>
</article>
