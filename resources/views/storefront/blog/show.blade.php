@extends('storefront.layouts.app')
@php
    use App\Support\Site;
    use App\Support\Blog;
    use App\Support\Media;
    $title = Blog::t($post, 'title');
    $metaTitle = Blog::t($post, 'meta_title') ?: $title;
    $metaDesc = Blog::t($post, 'meta_description') ?: Blog::t($post, 'excerpt') ?: \Illuminate\Support\Str::limit(strip_tags($body), 155);
    $cover = Media::url($post->cover_image);
    $url = lroute('storefront.blog.show', $post->slug);
    $published = \Illuminate\Support\Carbon::parse($post->published_at);
    $isEn = app()->getLocale() === 'en';
    $faqItems = $faq->map(fn ($f) => ['q' => $isEn && ($f['q_en'] ?? '') ? $f['q_en'] : $f['q_ar'], 'a' => $isEn && ($f['a_en'] ?? '') ? $f['a_en'] : $f['a_ar']]);
    $wa = Site::whatsapp();
@endphp
@section('title', $metaTitle.' — '.Site::name())
@section('meta_description', $metaDesc)
@if ($cover) @section('og_image', url($cover)) @endif
@section('wa_text', __('السلام عليكم، قريت مقال ":title" وعندي استفسار', ['title' => $title]))

@push('head')
<meta property="article:published_time" content="{{ $published->toIso8601String() }}">
<script type="application/ld+json">{!! json_encode(array_values(array_filter([
    array_filter(['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => \Illuminate\Support\Str::limit($title, 110, ''), 'description' => $metaDesc,
        'image' => $cover ? [url($cover)] : null, 'datePublished' => $published->toIso8601String(), 'dateModified' => \Illuminate\Support\Carbon::parse($post->updated_at)->toIso8601String(),
        'author' => ['@type' => $author ? 'Person' : 'Organization', 'name' => $author ?: Site::name()], 'publisher' => ['@type' => 'Organization', 'name' => Site::name(), 'logo' => \App\Support\Company::logo() ? ['@type' => 'ImageObject', 'url' => url(\App\Support\Company::logo())] : null],
        'mainEntityOfPage' => $url, 'keywords' => $post->focus_keyword, 'wordCount' => Blog::wordCount($body), 'inLanguage' => $isEn ? 'en' : 'ar']),
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('الرئيسية'), 'item' => lroute('storefront.home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('المدونة'), 'item' => lroute('storefront.blog.index')],
        $category ? ['@type' => 'ListItem', 'position' => 3, 'name' => \App\Support\Lang::pick($category, 'name'), 'item' => lroute('storefront.blog.category', $category->slug)] : null,
    ]))],
    $faqItems->isNotEmpty() ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqItems->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()] : null,
])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<style>
    .article-body { font-size: 1.075rem; line-height: 2.05; color: #334155; }
    .article-body > * + * { margin-top: 1.1em; }
    .article-body h2 { margin-top: 2em; font-size: 1.6rem; font-weight: 900; line-height: 1.4; color: #0f172a; scroll-margin-top: 6rem; }
    .article-body h3 { margin-top: 1.6em; font-size: 1.25rem; font-weight: 800; color: #0f172a; scroll-margin-top: 6rem; }
    .article-body h4 { font-weight: 800; color: #0f172a; }
    .article-body a { color: #2563eb; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
    .article-body strong { color: #0f172a; font-weight: 800; }
    .article-body ul, .article-body ol { padding-inline-start: 1.5em; }
    .article-body ul { list-style: disc; } .article-body ol { list-style: decimal; }
    .article-body li + li { margin-top: .4em; }
    .article-body li::marker { color: var(--accent); font-weight: 900; }
    .article-body blockquote { border-inline-start: 4px solid var(--accent); background: #f8fafc; border-radius: 1rem; padding: 1rem 1.25rem; font-weight: 700; color: #0f172a; }
    .article-body img { border-radius: 1.25rem; width: 100%; height: auto; }
    .article-body figcaption { text-align: center; font-size: .85rem; color: #94a3b8; margin-top: .5rem; }
    .article-body table { width: 100%; border-collapse: collapse; font-size: .95rem; display: block; overflow-x: auto; }
    .article-body th, .article-body td { border: 1px solid #e2e8f0; padding: .6rem .8rem; text-align: start; }
    .article-body th { background: #f1f5f9; font-weight: 800; color: #0f172a; }
    .article-body mark { background: color-mix(in srgb, var(--accent) 35%, transparent); padding: 0 .2em; border-radius: .25rem; }
    .article-body hr { border-color: #e2e8f0; margin: 2em 0; }
</style>
@endpush

@section('content')
<div id="readProgress" class="fixed inset-x-0 top-0 z-50 h-1 rtl:origin-right ltr:origin-left scale-x-0 bg-[var(--accent)]"></div>

<article class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <nav class="mb-5 flex flex-wrap gap-2 text-xs font-bold text-slate-400">
        <a href="{{ lroute('storefront.home') }}" class="hover:text-slate-900">{{ __('الرئيسية') }}</a> /
        <a href="{{ lroute('storefront.blog.index') }}" class="hover:text-slate-900">{{ __('المدونة') }}</a>
        @if ($category) / <a href="{{ lroute('storefront.blog.category', $category->slug) }}" class="hover:text-slate-900">{{ \App\Support\Lang::pick($category, 'name') }}</a>@endif
    </nav>

    <header class="mx-auto max-w-3xl text-center">
        @if ($category)<a href="{{ lroute('storefront.blog.category', $category->slug) }}" class="inline-block rounded-full bg-[var(--accent)]/15 px-4 py-1 text-xs font-extrabold text-slate-800">{{ \App\Support\Lang::pick($category, 'name') }}</a>@endif
        <h1 class="mt-4 text-3xl font-black leading-tight text-slate-900 sm:text-5xl">{{ $title }}</h1>
        @if ($ex = Blog::t($post, 'excerpt'))<p class="mt-4 text-lg leading-relaxed text-slate-500">{{ $ex }}</p>@endif
        <div class="mt-5 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm font-bold text-slate-400">
            @if ($author)<span>✍️ {{ $author }}</span>@endif
            <time datetime="{{ $published->toDateString() }}">📅 {{ $published->translatedFormat('j F Y') }}</time>
            <span>⏱️ {{ $post->reading_minutes }} {{ __('دقايق قراءة') }}</span>
            @if ($post->views_count > 20)<span>👁️ {{ number_format($post->views_count) }}</span>@endif
        </div>
    </header>

    @if ($cover)
        <figure class="mx-auto mt-8 max-w-5xl overflow-hidden rounded-[2rem] bg-slate-100">
            <img src="{{ $cover }}" alt="{{ $post->cover_alt ?: $title }}" class="aspect-[16/8] w-full object-cover" fetchpriority="high">
        </figure>
    @endif

    <div class="mx-auto mt-10 grid max-w-6xl gap-10 lg:grid-cols-[1fr_280px]">
        <div class="min-w-0">
            @if (count($toc) >= 3)
                <details class="mb-8 rounded-3xl bg-white p-5 ring-1 ring-slate-200 lg:hidden" open>
                    <summary class="cursor-pointer font-black text-slate-900">{{ __('📑 محتويات المقال') }}</summary>
                    <ol class="mt-3 space-y-1.5 text-sm">
                        @foreach ($toc as $t)<li class="{{ $t['level'] === 3 ? 'pr-4' : '' }}"><a href="#{{ $t['id'] }}" class="font-bold text-slate-600 hover:text-slate-900">{{ $t['text'] }}</a></li>@endforeach
                    </ol>
                </details>
            @endif

            <div class="article-body" id="articleBody">{!! $body !!}</div>

            @if ($faqItems->isNotEmpty())
                <section class="mt-14">
                    <h2 class="mb-5 text-2xl font-black text-slate-900">{{ __('❓ أسئلة شائعة') }}</h2>
                    <div class="space-y-3">
                        @foreach ($faqItems as $f)
                            <details class="group rounded-2xl bg-white ring-1 ring-slate-200" @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-extrabold text-slate-900">{{ $f['q'] }}<span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 transition group-open:rotate-45 group-open:bg-[var(--accent)]">+</span></summary>
                                <div class="whitespace-pre-line px-5 pb-5 text-sm leading-relaxed text-slate-600">{{ $f['a'] }}</div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- مشاركة --}}
            <div class="mt-12 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-6">
                <span class="text-sm font-bold text-slate-500">{{ __('شارك المقال:') }}</span>
                <a href="https://wa.me/?text={{ rawurlencode($title.' '.$url) }}" target="_blank" rel="noopener" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-white">{{ __('واتساب') }}</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($url) }}" target="_blank" rel="noopener" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white">{{ __('فيسبوك') }}</a>
                <a href="https://x.com/intent/post?url={{ rawurlencode($url) }}&text={{ rawurlencode($title) }}" target="_blank" rel="noopener" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">X</a>
                <button type="button" onclick="navigator.clipboard.writeText(@js($url)).then(() => LV.toast({{ \Illuminate\Support\Js::from(__('🔗 اتنسخ الرابط')) }}))" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700">{{ __('نسخ الرابط') }}</button>
            </div>
        </div>

        <aside class="hidden space-y-5 lg:sticky lg:top-24 lg:block lg:self-start">
            @if (count($toc) >= 2)
                <nav class="rounded-3xl bg-white p-5 ring-1 ring-slate-200">
                    <h3 class="mb-3 text-sm font-black text-slate-900">{{ __('📑 في المقال ده') }}</h3>
                    <ol class="space-y-1 text-sm" id="tocList">
                        @foreach ($toc as $t)<li class="{{ $t['level'] === 3 ? 'pr-3' : '' }}"><a href="#{{ $t['id'] }}" data-toc="{{ $t['id'] }}" class="block rounded-lg px-2 py-1 font-semibold text-slate-500 transition hover:text-slate-900">{{ $t['text'] }}</a></li>@endforeach
                    </ol>
                </nav>
            @endif
            <div class="rounded-3xl bg-slate-950 p-5 text-white">
                <div class="font-black">{{ __('محتار تختار؟ 🤔') }}</div>
                <p class="mt-1 text-sm text-slate-400">{{ __('كلّمنا وهنرشّحلك الأنسب لميزانيتك.') }}</p>
                @if ($wa)<a href="https://wa.me/{{ $wa }}?text={{ rawurlencode(__('السلام عليكم، قريت مقال ":title" وعايز ترشيح', ['title' => $title])) }}" target="_blank" rel="noopener" data-track="Contact" class="mt-4 block rounded-2xl bg-emerald-500 py-3 text-center text-sm font-extrabold">{{ __('💬 واتساب') }}</a>@endif
                <a href="{{ lroute('storefront.finance') }}" class="mt-2 block rounded-2xl bg-white/10 py-3 text-center text-sm font-extrabold">{{ __('💳 احسب القسط') }}</a>
            </div>
        </aside>
    </div>

    {{-- العربيات المرتبطة --}}
    @if ($cars->isNotEmpty())
        <section class="mx-auto mt-16 max-w-7xl">
            @include('storefront.partials.section-head', ['kicker' => __('متاحة دلوقتي في المعرض'), 'title' => __('🚗 عربيات ليها علاقة بالمقال'), 'link' => lroute('storefront.vehicles.index', array_filter(['brand' => $post->brand_id, 'model' => $post->model_id]))])
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cars as $car)@include('storefront.partials.vehicle-card', ['car' => $car])@endforeach
            </div>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-16 max-w-7xl">
            @include('storefront.partials.section-head', ['title' => __('اقرأ كمان'), 'link' => lroute('storefront.blog.index')])
            <div class="grid gap-5 md:grid-cols-3">
                @foreach ($related as $p)@include('storefront.partials.post-card', ['p' => $p])@endforeach
            </div>
        </section>
    @endif
</article>
@endsection

@push('scripts')
<script>
(function () {
    const bar = document.getElementById('readProgress'), body = document.getElementById('articleBody');
    const links = [...document.querySelectorAll('[data-toc]')];
    const onScroll = () => {
        const r = body.getBoundingClientRect();
        const p = Math.min(1, Math.max(0, -r.top / Math.max(1, r.height - innerHeight)));
        bar.style.transform = 'scaleX(' + p + ')';
        let current = null;
        links.forEach((a) => { const h = document.getElementById(a.dataset.toc); if (h && h.getBoundingClientRect().top < 140) current = a; });
        links.forEach((a) => { a.classList.toggle('bg-slate-100', a === current); a.classList.toggle('text-slate-900', a === current); });
    };
    addEventListener('scroll', onScroll, { passive: true }); onScroll();
    LV.track('ViewContent', { content_type: 'article', content_name: @js($title) });
})();
</script>
@endpush
