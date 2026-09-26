@extends('admin.layouts.app')

@php
    use App\Support\Media;
    $reopen = $errors->any() && old('_modal') === 'cat' ? (old('_edit_url') ?: 'new') : '';
    $tabs = ['' => [__('الكل'), $counts['all']], 'published' => [__('منشور'), $counts['published']], 'scheduled' => [__('مجدول'), $counts['scheduled']], 'draft' => [__('مسودة'), $counts['draft']]];
@endphp

@section('title', __('المدونة'))
@section('subtitle', __('مقالات بتجيب زيارات من جوجل وبتحوّلها لطلبات على العربيات'))

@section('actions')
    <a href="{{ route('storefront.blog.index') }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('👁️ المدونة على الموقع') }}</a>
    <a href="{{ route('admin.blog.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ __('✍️ مقال جديد') }}</a>
@endsection

@section('content')
<div class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <div>
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex gap-1 rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200">
                @foreach ($tabs as $k => [$l, $n])
                    <a href="{{ route('admin.blog.index', array_filter(['status' => $k, 'q' => request('q')])) }}" class="rounded-xl px-4 py-2 text-sm font-bold {{ (string) $status === $k ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900' }}">{{ $l }} <span class="text-xs opacity-60">{{ $n }}</span></a>
                @endforeach
            </div>
            <form class="flex-1"><input type="hidden" name="status" value="{{ $status }}"><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('🔍 عنوان أو كلمة مفتاحية...') }}" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50"></form>
            <div class="rounded-xl bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-700">👁️ {{ number_format($counts['views']) }} {{ __('قراءة') }}</div>
        </div>

        @if ($posts->isEmpty())
            <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
                <div class="text-5xl">✍️</div>
                <p class="mt-3 font-bold text-slate-600">{{ __('مفيش مقالات لسه') }}</p>
                <p class="mt-1 text-sm text-slate-400">{{ __('ابدأ بمقال زي: "أفضل 5 عربيات عائلية بالتقسيط في عمان" أو "إزاي تقسّط عربيتك من غير تحويل راتب".') }}</p>
                <a href="{{ route('admin.blog.create') }}" class="mt-5 inline-block rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('✍️ اكتب أول مقال') }}</a>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                @foreach ($posts as $p)
                    @php
                        $scheduled = $p->status === 'published' && $p->published_at > now()->toDateTimeString();
                        [$badge, $tone] = $p->status === 'draft' ? [__('مسودة'), 'bg-slate-100 text-slate-600'] : ($scheduled ? [__('مجدول ⏰'), 'bg-violet-50 text-violet-700'] : [__('منشور'), 'bg-emerald-50 text-emerald-700']);
                    @endphp
                    <div class="flex items-center gap-4 border-b border-slate-100 p-4 last:border-0 hover:bg-slate-50/60">
                        <a href="{{ route('admin.blog.edit', $p->id) }}" class="h-16 w-24 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                            @if ($p->cover_image)<img src="{{ Media::url($p->cover_image) }}" alt="" class="h-full w-full object-cover">@else<div class="grid h-full place-items-center text-2xl">📰</div>@endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.blog.edit', $p->id) }}" class="block truncate font-extrabold text-slate-900 hover:text-blue-700">@if ($p->is_featured)⭐ @endif{{ \App\Support\Lang::pick($p, 'title') }}</a>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-400">
                                <span class="rounded-full px-2 py-0.5 {{ $tone }}">{{ $badge }}</span>
                                @if ($p->category)<span>📁 {{ $p->category }}</span>@endif
                                @if ($p->published_at)<span>📅 {{ \Illuminate\Support\Carbon::parse($p->published_at)->format('Y-m-d H:i') }}</span>@endif
                                @if ($p->focus_keyword)<span>🎯 {{ $p->focus_keyword }}</span>@endif
                                <span>{{ $p->title_en ? '🇬🇧 EN ✓' : '🇬🇧 EN —' }}</span>
                            </div>
                        </div>
                        <div class="hidden text-center sm:block"><div class="text-lg font-black text-slate-900">{{ number_format($p->views_count) }}</div><div class="text-[11px] font-bold text-slate-400">{{ __('قراءة') }}</div></div>
                        <div class="flex gap-1">
                            @if ($p->status === 'published' && ! $scheduled)<a href="{{ route('storefront.blog.show', $p->slug) }}" target="_blank" class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100" title="{{ __('عرض') }}">👁️</a>@endif
                            <a href="{{ route('admin.blog.edit', $p->id) }}" class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100" title="{{ __('تعديل') }}">✏️</a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">{{ $posts->links() }}</div>
        @endif
    </div>

    {{-- التصنيفات --}}
    <aside>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-extrabold text-slate-900">{{ __('📁 التصنيفات') }}</h3>
                <button type="button" data-crud-new="catModal" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200">{{ __('+ تصنيف') }}</button>
            </div>
            @forelse ($categories as $c)
                <div class="group flex items-center justify-between rounded-xl px-3 py-2 hover:bg-slate-50">
                    <div><div class="text-sm font-bold text-slate-800">{{ \App\Support\Lang::pick($c, 'name') }}</div><div class="text-[11px] text-slate-400" dir="ltr">/blog/category/{{ $c->slug }} · {{ $c->posts }}</div></div>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100">
                        <button type="button" data-crud-edit="catModal" data-values="{{ json_encode(['url' => route('admin.blog-categories.update', $c->id), 'name_ar' => $c->name_ar, 'name_en' => $c->name_en, 'slug' => $c->slug, 'description_ar' => $c->description_ar, 'description_en' => $c->description_en, 'sort' => $c->sort]) }}" class="text-xs font-bold text-blue-600">{{ __('تعديل') }}</button>
                        <form method="POST" action="{{ route('admin.blog-categories.destroy', $c->id) }}" onsubmit="{{ __('return confirm(\'مسح التصنيف؟\')') }}">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600">{{ __('مسح') }}</button></form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">{{ __('مفيش تصنيفات.') }}</p>
            @endforelse
        </div>
        <div class="mt-4 rounded-2xl bg-blue-50 p-5 text-sm leading-relaxed text-blue-900 ring-1 ring-blue-100">
            <div class="mb-1 font-extrabold">{{ __('💡 نصيحة SEO') }}</div>
            {{ __('اكتب مقالات بتجاوب على أسئلة الناس بتدوّر عليها فعلاً قبل الشراء (أسعار، مقارنات، تقسيط)، واربط كل مقال بموديل عشان العربيات المتاحة تظهر تحته أوتوماتيك.') }}
        </div>
    </aside>
</div>

<dialog id="catModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.blog-categories.store') }}" data-title-new="{{ __('تصنيف جديد') }}" data-title-edit="{{ __('تعديل التصنيف') }}"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
    <form method="POST" action="{{ route('admin.blog-categories.store') }}">
        @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="cat"><input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('تصنيف جديد') }}</h2>
            <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
        </div>
        <div class="space-y-4 px-6 py-5">
            <div class="grid grid-cols-2 gap-3">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'name_ar', 'label' => __('الاسم'), 'required' => true])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'name_en', 'label' => 'English', 'dir' => 'ltr'])
            </div>
            @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'slug', 'label' => __('الرابط'), 'dir' => 'ltr', 'hint' => __('لو فاضي بيتعمل أوتوماتيك')])
            @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'description_ar', 'label' => __('وصف (بيظهر فوق الصفحة ولجوجل)'), 'type' => 'textarea', 'rows' => 2])
            @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'description_en', 'label' => 'Description', 'type' => 'textarea', 'rows' => 2])
            @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'sort', 'label' => __('الترتيب'), 'type' => 'number'])
        </div>
        <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
            <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
        </div>
    </form>
</dialog>
@include('admin.partials.crud-modal-script')
@endsection
