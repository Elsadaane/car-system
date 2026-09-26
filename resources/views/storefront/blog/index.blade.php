@extends('storefront.layouts.app')
@php
    use App\Support\Site;
    use App\Support\Blog;
    $pageTitle = $category ? (app()->getLocale() === 'en' && $category->name_en ? $category->name_en : \App\Support\Lang::pick($category, 'name')) : __('المدونة');
    $first = ! $category && $q === '' && $posts->currentPage() === 1 ? $posts->first() : null;
@endphp
@section('title', $pageTitle.' — '.Site::name())
@section('meta_description', \App\Support\Lang::pick($category, 'description') ?: __('نصايح شراء العربيات، التقسيط، المراجعات والصيانة من ').Site::name())
@if ($posts->currentPage() > 1) @push('head')<meta name="robots" content="noindex,follow">@endpush @endif

@section('content')
@include('storefront.partials.page-hero', ['title' => $pageTitle, 'icon' => '📰', 'sub' => \App\Support\Lang::pick($category, 'description') ?: __('كل اللي محتاج تعرفه قبل ما تشتري عربيتك — من ناس بتبيع عربيات كل يوم.')])

<div class="relative z-10 mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center gap-2 rounded-3xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
        <a href="{{ lroute('storefront.blog.index') }}" class="rounded-xl px-4 py-2 text-sm font-bold {{ ! $category ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('الكل') }}</a>
        @foreach ($categories as $c)
            <a href="{{ lroute('storefront.blog.category', $c->slug) }}" class="rounded-xl px-4 py-2 text-sm font-bold {{ $category?->id == $c->id ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ \App\Support\Lang::pick($c, 'name') }} <span class="text-xs opacity-60">{{ $c->count }}</span></a>
        @endforeach
        <form action="{{ lroute('storefront.blog.index') }}" class="ms-auto w-full sm:w-64">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('🔍 دوّر في المقالات...') }}" class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-[var(--accent)]">
        </form>
    </div>

    @if ($posts->isEmpty())
        <div class="rounded-3xl bg-white px-6 py-20 text-center ring-1 ring-slate-200">
            <div class="text-5xl">📝</div>
            <h2 class="mt-3 text-lg font-black text-slate-900">{{ $q !== '' ? __('مفيش مقالات مطابقة') : __('لسه مفيش مقالات هنا') }}</h2>
            <a href="{{ lroute('storefront.vehicles.index') }}" class="mt-5 inline-block rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white">{{ __('تصفّح السيارات') }}</a>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-[1fr_300px]">
            <div>
                @if ($first)
                    <div class="mb-6">@include('storefront.partials.post-card', ['p' => $first, 'big' => true])</div>
                @endif
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($posts as $p)
                        @continue($first && $p->id === $first->id)
                        @include('storefront.partials.post-card', ['p' => $p])
                    @endforeach
                </div>
                <div class="mt-10">{{ $posts->onEachSide(1)->links() }}</div>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                @if ($popular->isNotEmpty())
                    <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200">
                        <h3 class="mb-3 font-black text-slate-900">{{ __('🔥 الأكثر قراءة') }}</h3>
                        @foreach ($popular as $i => $pp)
                            <a href="{{ lroute('storefront.blog.show', $pp->slug) }}" class="flex items-start gap-3 rounded-2xl p-2 hover:bg-slate-50">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[var(--accent)] text-sm font-black text-[var(--accent-ink)]">{{ $i + 1 }}</span>
                                <span class="text-sm font-bold leading-snug text-slate-700">{{ Blog::t($pp, 'title') }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
                <div class="rounded-3xl bg-slate-950 p-6 text-white">
                    <div class="text-3xl">💳</div>
                    <h3 class="mt-2 text-lg font-black">{{ __('احسب قسط عربيتك') }}</h3>
                    <p class="mt-1 text-sm text-slate-400">{{ __('في ثواني ومن غير ما تكلّم حد.') }}</p>
                    <a href="{{ lroute('storefront.finance') }}" class="mt-4 block rounded-2xl bg-[var(--accent)] py-3 text-center font-extrabold text-[var(--accent-ink)]">{{ __('الحاسبة ←') }}</a>
                </div>
            </aside>
        </div>
    @endif
</div>
@endsection
