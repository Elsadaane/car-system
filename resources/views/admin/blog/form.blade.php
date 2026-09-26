@extends('admin.layouts.app')

@php
    use App\Support\Blog;
    use App\Support\Media;
    $p = $post;
    $v = fn (string $k, $d = '') => old($k, $p->{$k} ?? $d);
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-50';
    $cover = Media::url($p->cover_image ?? null);
    $faqRows = old('faq', $faq) ?: [];
    $selected = array_map('intval', old('vehicle_ids', $vehicleIds));
    $isScheduled = $p && $p->status === 'published' && $p->published_at > now()->toDateTimeString();
    $statusLabel = ! $p ? __('جديد') : ($p->status === 'draft' ? __('مسودة') : ($isScheduled ? __('مجدول ').\Illuminate\Support\Carbon::parse($p->published_at)->format('Y-m-d H:i') : __('منشور')));
@endphp

@section('title', $p ? __('تعديل المقال') : __('مقال جديد'))
@section('subtitle', $statusLabel)

@section('actions')
    <a href="{{ route('admin.blog.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('→ كل المقالات') }}</a>
    @if ($p && $p->status === 'published' && ! $isScheduled)
        <a href="{{ route('storefront.blog.show', $p->slug) }}" target="_blank" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('👁️ عرض على الموقع') }}</a>
    @endif
@endsection

@section('content')
<form method="POST" action="{{ $p ? route('admin.blog.update', $p->id) : route('admin.blog.store') }}" enctype="multipart/form-data" id="postForm" class="grid gap-6 xl:grid-cols-[1fr_360px]">
    @csrf
    @if ($p) @method('PUT') @endif

    <div class="min-w-0 space-y-5">
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- اللغة --}}
        <div class="flex gap-1 rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200 sm:w-fit">
            <button type="button" data-lang="ar" class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-bold text-white">{{ __('🇴🇲 عربي') }}</button>
            <button type="button" data-lang="en" class="rounded-xl px-5 py-2 text-sm font-bold text-slate-500">🇬🇧 English <span class="text-xs opacity-60">{{ __('(اختياري)') }}</span></button>
        </div>

        @foreach (['ar' => 'rtl', 'en' => 'ltr'] as $lang => $dir)
            <div data-pane="{{ $lang }}" class="{{ $lang === 'en' ? 'hidden' : '' }} space-y-5">
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <input type="text" name="title_{{ $lang }}" value="{{ $v('title_'.$lang) }}" dir="{{ $dir }}" @if ($lang === 'ar') required @endif
                           placeholder="{{ $lang === 'ar' ? __('عنوان المقال…') : 'Article title…' }}" class="w-full border-0 bg-transparent p-0 text-2xl font-black text-slate-900 outline-none placeholder:text-slate-300 focus:ring-0">
                    <textarea name="excerpt_{{ $lang }}" rows="2" dir="{{ $dir }}" maxlength="500" placeholder="{{ $lang === 'ar' ? __('مقدمة قصيرة بتظهر في كارت المقال وتحت العنوان…') : 'Short intro…' }}" class="mt-3 w-full resize-none border-0 bg-transparent p-0 text-slate-500 outline-none placeholder:text-slate-300 focus:ring-0">{{ $v('excerpt_'.$lang) }}</textarea>
                </div>

                {{-- المحرّر --}}
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200" data-editor-wrap>
                    <div class="sticky top-16 z-10 flex flex-wrap items-center gap-0.5 border-b border-slate-100 bg-white/95 p-2 backdrop-blur" data-toolbar>
                        @foreach ([['h2', 'H2', __('عنوان رئيسي')], ['h3', 'H3', __('عنوان فرعي')], ['p', '¶', __('فقرة عادية')], ['|'], ['bold', '<b>B</b>', __('بولد')], ['italic', '<i>I</i>', __('مائل')], ['underline', '<u>U</u>', __('خط تحت')], ['|'], ['ul', '• ―', __('قايمة نقط')], ['ol', '1. ―', __('قايمة أرقام')], ['quote', '❝', __('اقتباس')], ['|'], ['link', '🔗', __('رابط')], ['unlink', '⛓️‍💥', __('شيل الرابط')], ['image', '🖼️', __('صورة')], ['hr', '―', __('خط فاصل')], ['|'], ['clear', '🧹', __('شيل التنسيق')], ['undo', '↶', __('تراجع')], ['redo', '↷', __('إعادة')]] as $b)
                            @if ($b[0] === '|')
                                <span class="mx-1 h-6 w-px bg-slate-200"></span>
                            @else
                                <button type="button" data-cmd="{{ $b[0] }}" title="{{ $b[2] }}" class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-sm font-bold text-slate-600 hover:bg-slate-100">{!! $b[1] !!}</button>
                            @endif
                        @endforeach
                        <span class="ms-auto text-xs font-bold text-slate-400" data-counter>{{ __('0 كلمة') }}</span>
                    </div>
                    <div contenteditable="true" dir="{{ $dir }}" data-editor="body_{{ $lang }}" data-placeholder="{{ $lang === 'ar' ? __('اكتب هنا… أو الصق من جوجل دوكس / ChatGPT وهيتنضّف أوتوماتيك ✨') : 'Write or paste here…' }}"
                         class="article-editor min-h-[480px] px-6 py-5 outline-none">{!! Blog::sanitize(old('body_'.$lang, $p->{'body_'.$lang} ?? '')) !!}</div>
                    <textarea name="body_{{ $lang }}" class="hidden"></textarea>
                </div>
            </div>
        @endforeach

        {{-- أسئلة شائعة --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-3 flex items-center justify-between">
                <div><h3 class="font-extrabold text-slate-900">{{ __('❓ أسئلة شائعة في آخر المقال') }}</h3><p class="text-xs text-slate-400">{{ __('بتظهر في جوجل كـ FAQ تحت نتيجة المقال — بتزوّد الـ CTR') }}</p></div>
                <button type="button" id="addFaq" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200">{{ __('+ سؤال') }}</button>
            </div>
            <div id="faqList" class="space-y-3">
                @foreach ($faqRows as $i => $f)
                    <div data-faq class="grid gap-2 rounded-xl bg-slate-50 p-3 md:grid-cols-2">
                        <input name="faq[{{ $i }}][q_ar]" value="{{ $f['q_ar'] ?? '' }}" placeholder="{{ __('السؤال') }}" class="{{ $input }}">
                        <input name="faq[{{ $i }}][q_en]" value="{{ $f['q_en'] ?? '' }}" placeholder="Question (EN)" dir="ltr" class="{{ $input }}">
                        <textarea name="faq[{{ $i }}][a_ar]" rows="2" placeholder="{{ __('الإجابة') }}" class="{{ $input }}">{{ \App\Support\Lang::pick($f, 'a') ?? '' }}</textarea>
                        <textarea name="faq[{{ $i }}][a_en]" rows="2" placeholder="Answer (EN)" dir="ltr" class="{{ $input }}">{{ $f['a_en'] ?? '' }}</textarea>
                        <button type="button" data-remove-faq class="text-start text-xs font-bold text-rose-600 md:col-span-2">{{ __('🗑️ شيل السؤال') }}</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ═══════ الشريط الجانبي ═══════ --}}
    <aside class="space-y-5 xl:sticky xl:top-20 xl:max-h-[calc(100vh-6rem)] xl:self-start xl:overflow-y-auto xl:pb-4">
        {{-- النشر --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="grid grid-cols-2 gap-2">
                <button type="submit" name="action" value="draft" class="rounded-xl bg-slate-100 py-3 text-sm font-bold text-slate-700 hover:bg-slate-200">{{ __('💾 حفظ مسودة') }}</button>
                <button type="submit" name="action" value="publish" class="rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white hover:bg-emerald-700">🚀 {{ $p && $p->status === 'published' && ! $isScheduled ? __('تحديث') : __('نشر دلوقتي') }}</button>
            </div>
            <details class="mt-2 rounded-xl bg-violet-50 p-3" @if ($isScheduled || old('action') === 'schedule') open @endif>
                <summary class="cursor-pointer text-sm font-bold text-violet-700">{{ __('⏰ جدولة النشر لمعاد') }}</summary>
                <input type="datetime-local" name="publish_at" value="{{ old('publish_at', $isScheduled ? \Illuminate\Support\Carbon::parse($p->published_at)->format('Y-m-d\TH:i') : now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i')) }}" class="{{ $input }} mt-2">
                <button type="submit" name="action" value="schedule" class="mt-2 w-full rounded-xl bg-violet-600 py-2.5 text-sm font-bold text-white hover:bg-violet-700">{{ __('جدولة') }}</button>
            </details>
            <label class="mt-3 flex cursor-pointer items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $p->is_featured ?? false)) class="h-4 w-4 accent-amber-500"> {{ __('⭐ مقال مميز (يظهر كبير أول المدونة)') }}</label>
        </div>

        {{-- درجة الـ SEO --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <h3 class="font-extrabold text-slate-900">{{ __('📈 درجة الـ SEO') }}</h3>
                <span id="seoScore" class="rounded-full px-3 py-1 text-sm font-black">0</span>
            </div>
            <div class="mt-3">
                <label class="mb-1 block text-xs font-bold text-slate-600">{{ __('🎯 الكلمة المفتاحية (اللي الناس بتكتبها في جوجل)') }}</label>
                <input type="text" name="focus_keyword" value="{{ $v('focus_keyword') }}" placeholder="{{ __('مثلاً: تقسيط سيارات عمان') }}" class="{{ $input }}">
            </div>
            <ul id="seoChecks" class="mt-3 space-y-1.5 text-xs font-semibold"></ul>
        </div>

        {{-- معاينة جوجل --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="mb-3 font-extrabold text-slate-900">{{ __('🔍 شكله في جوجل') }}</h3>
            <div class="rounded-xl border border-slate-200 p-3" dir="rtl">
                <div class="truncate text-xs text-emerald-700" dir="ltr" id="gUrl"></div>
                <div class="mt-0.5 line-clamp-1 text-lg text-blue-700" id="gTitle"></div>
                <div class="mt-0.5 line-clamp-2 text-xs text-slate-600" id="gDesc"></div>
            </div>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="mb-1 flex justify-between text-xs font-bold text-slate-600"><span>{{ __('الرابط') }}</span><span class="font-normal text-slate-400">{{ __('عربي عادي ومقبول') }}</span></label>
                    <input type="text" name="slug" value="{{ $v('slug') }}" dir="auto" placeholder="{{ __('بيتعمل من العنوان') }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1 flex justify-between text-xs font-bold text-slate-600"><span>{{ __('عنوان جوجل') }}</span><span data-len="meta_title_ar"></span></label>
                    <input type="text" name="meta_title_ar" value="{{ $v('meta_title_ar') }}" placeholder="{{ __('لو فاضي بياخد عنوان المقال') }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1 flex justify-between text-xs font-bold text-slate-600"><span>{{ __('وصف جوجل') }}</span><span data-len="meta_description_ar"></span></label>
                    <textarea name="meta_description_ar" rows="3" maxlength="300" placeholder="{{ __('جملتين بيقنعوا اللي بيدوّر يدوس (120-160 حرف)') }}" class="{{ $input }}">{{ $v('meta_description_ar') }}</textarea>
                </div>
                <details class="rounded-xl bg-slate-50 p-3">
                    <summary class="cursor-pointer text-xs font-bold text-slate-600">{{ __('🇬🇧 SEO بالإنجليزي') }}</summary>
                    <input type="text" name="meta_title_en" value="{{ $v('meta_title_en') }}" dir="ltr" placeholder="Meta title" class="{{ $input }} mt-2">
                    <textarea name="meta_description_en" rows="2" dir="ltr" placeholder="Meta description" class="{{ $input }} mt-2">{{ $v('meta_description_en') }}</textarea>
                </details>
            </div>
        </div>

        {{-- الغلاف والتصنيف --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="mb-3 font-extrabold text-slate-900">{{ __('🖼️ صورة الغلاف') }}</h3>
            <label class="grid aspect-[16/9] cursor-pointer place-items-center overflow-hidden rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 hover:border-blue-300">
                <img id="coverPreview" src="{{ $cover }}" alt="" class="{{ $cover ? '' : 'hidden' }} h-full w-full object-cover">
                <span id="coverHint" class="{{ $cover ? 'hidden' : '' }} text-xs text-slate-400">{{ __('اضغط لاختيار صورة (1200×630 أحسن)') }}</span>
                <input type="file" name="cover" accept="image/*" class="sr-only" id="coverInput">
            </label>
            @if ($cover)<label class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-rose-600"><input type="checkbox" name="remove_cover" value="1" class="accent-rose-600"> {{ __('شيل الغلاف') }}</label>@endif
            <input type="text" name="cover_alt" value="{{ $v('cover_alt') }}" placeholder="{{ __('وصف الصورة (Alt) — مهم لجوجل') }}" class="{{ $input }} mt-2">
            <label class="mb-1 mt-4 block text-xs font-bold text-slate-600">{{ __('📁 التصنيف') }}</label>
            <select name="category_id" class="{{ $input }}" data-no-search>
                <option value="">{{ __('— من غير تصنيف —') }}</option>
                @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected((string) $v('category_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        </div>

        {{-- العربيات --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-extrabold text-slate-900">{{ __('🚗 العربيات تحت المقال') }}</h3>
            <p class="mb-3 text-xs text-slate-400">{{ __('المختارة بالإيد تظهر الأول، والباقي يتكمّل أوتوماتيك من الماركة/الموديل (والمباعة بتختفي لوحدها).') }}</p>
            <div class="grid grid-cols-2 gap-2">
                <select name="brand_id" id="brandSel" class="{{ $input }}" data-no-search>
                    <option value="">{{ __('الماركة') }}</option>
                    @foreach ($brands as $id => $name)<option value="{{ $id }}" @selected((string) $v('brand_id') === (string) $id)>{{ $name }}</option>@endforeach
                </select>
                <select name="model_id" id="modelSel" class="{{ $input }}" data-no-search>
                    <option value="">{{ __('الموديل') }}</option>
                    @foreach ($models as $m)<option value="{{ $m->id }}" data-brand="{{ $m->brand_id }}" @selected((string) $v('model_id') === (string) $m->id)>{{ \App\Support\Lang::pick($m, 'name') }}</option>@endforeach
                </select>
            </div>
            <input type="search" id="carFilter" placeholder="{{ __('🔍 دوّر بالاسم أو الكود...') }}" class="{{ $input }} mt-3">
            <div class="mt-2 max-h-56 space-y-0.5 overflow-y-auto rounded-xl bg-slate-50 p-1.5" id="carList">
                @forelse ($vehicles as $car)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-white" data-car="{{ $car->brand }} {{ $car->model }} {{ $car->manufacturing_year }} {{ $car->vehicle_code }}">
                        <input type="checkbox" name="vehicle_ids[]" value="{{ $car->id }}" @checked(in_array($car->id, $selected, true)) class="h-4 w-4 accent-blue-600">
                        <span class="flex-1 font-semibold text-slate-700">{{ $car->brand }} {{ $car->model }} {{ $car->manufacturing_year }}</span>
                        <span class="text-[11px] text-slate-400">{{ $car->status === 'available' ? $car->vehicle_code : __('مش متاحة') }}</span>
                    </label>
                @empty
                    <p class="p-2 text-xs text-slate-400">{{ __('مفيش عربيات متاحة.') }}</p>
                @endforelse
            </div>
            <div class="mt-1 text-xs font-bold text-slate-400"><span id="carCount">0</span> {{ __('/ 8 مختارة') }}</div>
        </div>

        @if ($p)
            <button type="submit" form="deleteForm" class="w-full rounded-xl py-2.5 text-sm font-bold text-rose-600 hover:bg-rose-50" onclick="{{ __('return confirm(\'مسح المقال؟\')') }}">{{ __('🗑️ مسح المقال') }}</button>
        @endif
    </aside>
</form>
@if ($p)
    <form id="deleteForm" method="POST" action="{{ route('admin.blog.destroy', $p->id) }}">@csrf @method('DELETE')</form>
@endif
<input type="file" id="editorImage" accept="image/*" class="hidden">

<style>
    .article-editor { font-size: 1.05rem; line-height: 2; color: #334155; }
    .article-editor:empty::before { content: attr(data-placeholder); color: #cbd5e1; }
    .article-editor > * + * { margin-top: .9em; }
    .article-editor h2 { font-size: 1.5rem; font-weight: 900; color: #0f172a; border-inline-start: 4px solid #3b82f6; padding-inline-start: .6rem; }
    .article-editor h3 { font-size: 1.2rem; font-weight: 800; color: #0f172a; }
    .article-editor h4 { font-weight: 800; }
    .article-editor a { color: #2563eb; text-decoration: underline; }
    .article-editor ul { list-style: disc; padding-inline-start: 1.5em; } .article-editor ol { list-style: decimal; padding-inline-start: 1.5em; }
    .article-editor blockquote { border-inline-start: 4px solid #fbbf24; background: #fffbeb; padding: .75rem 1rem; border-radius: .75rem; }
    .article-editor img { max-width: 100%; border-radius: .75rem; outline-offset: 2px; }
    .article-editor img:hover { outline: 2px dashed #3b82f6; cursor: pointer; }
    .article-editor table { border-collapse: collapse; } .article-editor td, .article-editor th { border: 1px solid #e2e8f0; padding: .4rem .6rem; }
</style>

<script>
(function () {
    const form = document.getElementById('postForm');
    const siteUrl = @js(url('/blog')) + '/';
    let seoReady = false;

    // ─── تنضيف اللصق (نفس قواعد السيرفر: السيرفر بينضّف تاني للأمان) ───
    const ALLOWED = { H2: [], H3: [], H4: [], P: [], BR: [], HR: [], STRONG: [], EM: [], U: [], S: [], MARK: [], UL: [], OL: [], LI: [], BLOCKQUOTE: [], A: ['href'], IMG: ['src', 'alt'], TABLE: [], THEAD: [], TBODY: [], TR: [], TH: [], TD: [], FIGURE: [], FIGCAPTION: [], SUB: [], SUP: [] };
    const RENAME = { B: 'STRONG', I: 'EM', H1: 'H2', H5: 'H4', H6: 'H4', DIV: 'P', SECTION: 'P', ARTICLE: 'P' };
    const DROP = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'BUTTON', 'SVG', 'META', 'LINK', 'TITLE', 'NOSCRIPT', 'TEMPLATE', 'O:P'];
    const clean = (node) => {
        [...node.childNodes].forEach((ch) => {
            if (ch.nodeType === 8) { ch.remove(); return; }
            if (ch.nodeType !== 1) return;
            let tag = ch.tagName.toUpperCase();
            if (DROP.includes(tag)) { ch.remove(); return; }
            const style = ch.getAttribute('style') || '';
            if (tag === 'SPAN' && /font-weight:\s*(700|bold)/i.test(style)) tag = 'STRONG';
            if ((tag === 'B' || tag === 'STRONG') && (/font-weight:\s*(normal|400)/i.test(style) || (ch.id || '').startsWith('docs-internal'))) tag = 'SPAN';
            tag = RENAME[tag] || tag;
            const parentTag = node.tagName ? node.tagName.toUpperCase() : '';
            if (tag === 'P' && ['LI', 'TD', 'TH', 'BLOCKQUOTE'].includes(parentTag)) tag = 'SPAN';
            if (['STRONG', 'EM', 'U'].includes(tag) && ch.querySelector('p,h1,h2,h3,h4,ul,ol,li,div,table,blockquote')) tag = 'SPAN';
            if (!ALLOWED[tag]) { clean(ch); ch.replaceWith(...ch.childNodes); return; }
            let el = ch;
            if (tag !== ch.tagName.toUpperCase()) { el = document.createElement(tag); [...ch.attributes].forEach((a) => el.setAttribute(a.name, a.value)); el.append(...ch.childNodes); ch.replaceWith(el); }
            [...el.attributes].forEach((a) => { if (!ALLOWED[tag].includes(a.name)) el.removeAttribute(a.name); });
            if (tag === 'A') {
                let href = el.getAttribute('href') || '';
                const g = href.match(/^https?:\/\/(www\.)?google\.[a-z.]+\/url\?(.*)$/i);
                if (g) href = new URLSearchParams(g[2]).get('q') || '';
                if (!/^(https?:\/\/|\/|#|mailto:|tel:)/i.test(href.trim())) el.removeAttribute('href'); else el.setAttribute('href', href);
            }
            clean(el);
        });
        return node;
    };

    // ─── المحرّر ───
    let active = null;
    document.querySelectorAll('[data-editor-wrap]').forEach((wrap) => {
        const ed = wrap.querySelector('[data-editor]'), ta = wrap.querySelector('textarea'), counter = wrap.querySelector('[data-counter]');
        const sync = () => {
            ta.value = ed.innerHTML.trim() === '<br>' ? '' : ed.innerHTML;
            counter.textContent = (ed.innerText.trim().split(/\s+/).filter(Boolean).length) + @js(__(' كلمة'));
            seo();
        };
        ed.addEventListener('focus', () => { active = ed; });
        ed.addEventListener('input', sync);
        ed.addEventListener('paste', (e) => {
            e.preventDefault();
            const html = e.clipboardData.getData('text/html');
            if (html) {
                const box = document.createElement('div');
                box.innerHTML = new DOMParser().parseFromString(html, 'text/html').body.innerHTML;
                document.execCommand('insertHTML', false, clean(box).innerHTML.replace(/<p>(\s|&nbsp;|<br>)*<\/p>/g, ''));
            } else {
                const text = e.clipboardData.getData('text/plain');
                // نص عادي (زي ChatGPT): كل سطر فقرة، و"## " عنوان، و"- " نقطة
                const lines = text.split(/\r?\n/);
                let out = '', list = null;
                const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                const close = () => { if (list) { out += '</' + list + '>'; list = null; } };
                lines.forEach((l) => {
                    const t = l.trim();
                    let m;
                    if (!t) { close(); return; }
                    if ((m = t.match(/^#{1,2}\s+(.*)/))) { close(); out += '<h2>' + esc(m[1]) + '</h2>'; }
                    else if ((m = t.match(/^#{3,6}\s+(.*)/))) { close(); out += '<h3>' + esc(m[1]) + '</h3>'; }
                    else if ((m = t.match(/^[-*•]\s+(.*)/))) { if (list !== 'ul') { close(); out += '<ul>'; list = 'ul'; } out += '<li>' + esc(m[1]) + '</li>'; }
                    else if ((m = t.match(/^\d+[.)]\s+(.*)/))) { if (list !== 'ol') { close(); out += '<ol>'; list = 'ol'; } out += '<li>' + esc(m[1]) + '</li>'; }
                    else { close(); out += '<p>' + esc(t) + '</p>'; }
                });
                close();
                document.execCommand('insertHTML', false, out);
            }
            sync();
        });
        // صورة: دوس عليها عشان تكتب الـ Alt
        ed.addEventListener('click', (e) => {
            if (e.target.tagName === 'IMG') { const alt = prompt(@js(__('وصف الصورة (Alt) — مهم لجوجل:')), e.target.alt || ''); if (alt !== null) { e.target.alt = alt; sync(); } }
        });
        wrap.querySelectorAll('[data-cmd]').forEach((b) => b.addEventListener('mousedown', (e) => {
            e.preventDefault(); ed.focus(); active = ed;
            const c = b.dataset.cmd;
            if (['h2', 'h3', 'p'].includes(c)) document.execCommand('formatBlock', false, c.toUpperCase());
            else if (c === 'quote') document.execCommand('formatBlock', false, 'BLOCKQUOTE');
            else if (c === 'ul') document.execCommand('insertUnorderedList');
            else if (c === 'ol') document.execCommand('insertOrderedList');
            else if (c === 'link') { const u = prompt(@js(__('الرابط (مثلاً /cars أو https://...):')), '/'); if (u) document.execCommand('createLink', false, u); }
            else if (c === 'hr') document.execCommand('insertHorizontalRule');
            else if (c === 'clear') { document.execCommand('removeFormat'); document.execCommand('formatBlock', false, 'P'); }
            else if (c === 'image') document.getElementById('editorImage').click();
            else document.execCommand(c);
            sync();
        }));
        sync();
    });

    // رفع صورة جوه المقال
    document.getElementById('editorImage').addEventListener('change', async (e) => {
        const file = e.target.files[0]; if (!file || !active) return;
        const fd = new FormData(); fd.append('image', file); fd.append('_token', @js(csrf_token()));
        try {
            const r = await fetch(@js(route('admin.blog.upload')), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message || 'upload');
            active.focus();
            const alt = prompt(@js(__('وصف الصورة (Alt):')), '') || '';
            document.execCommand('insertHTML', false, '<img src="' + j.url + '" alt="' + alt.replace(/"/g, '&quot;') + '"><p><br></p>');
            active.dispatchEvent(new Event('input'));
        } catch (err) { alert(@js(__('الصورة ما اترفعتش: ')) + err.message); }
        e.target.value = '';
    });

    // اللغات
    document.querySelectorAll('[data-lang]').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('[data-lang]').forEach((x) => { const on = x === b; x.classList.toggle('bg-slate-900', on); x.classList.toggle('text-white', on); x.classList.toggle('text-slate-500', !on); });
        document.querySelectorAll('[data-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.pane !== b.dataset.lang));
    }));

    // الأسئلة
    let faqIdx = {{ count($faqRows) + 100 }};
    document.getElementById('addFaq').addEventListener('click', () => {
        const i = faqIdx++, cls = @js($input);
        const div = document.createElement('div');
        div.dataset.faq = ''; div.className = 'grid gap-2 rounded-xl bg-slate-50 p-3 md:grid-cols-2';
        div.innerHTML = `<input name="faq[${i}][q_ar]" placeholder="السؤال" class="${cls}"><input name="faq[${i}][q_en]" placeholder="Question (EN)" dir="ltr" class="${cls}"><textarea name="faq[${i}][a_ar]" rows="2" placeholder="الإجابة" class="${cls}"></textarea><textarea name="faq[${i}][a_en]" rows="2" placeholder="Answer (EN)" dir="ltr" class="${cls}"></textarea><button type="button" data-remove-faq class="text-right text-xs font-bold text-rose-600 md:col-span-2">🗑️ شيل السؤال</button>`;
        document.getElementById('faqList').appendChild(div); div.querySelector('input').focus(); seo();
    });
    document.getElementById('faqList').addEventListener('click', (e) => { if (e.target.closest('[data-remove-faq]')) { e.target.closest('[data-faq]').remove(); seo(); } });

    // الغلاف
    document.getElementById('coverInput').addEventListener('change', (e) => {
        const f = e.target.files[0]; if (!f) return;
        const img = document.getElementById('coverPreview'); img.src = URL.createObjectURL(f); img.classList.remove('hidden');
        document.getElementById('coverHint').classList.add('hidden'); seo();
    });

    // العربيات
    const brandSel = document.getElementById('brandSel'), modelSel = document.getElementById('modelSel');
    const filterModels = () => { [...modelSel.options].forEach((o) => { if (o.value) o.hidden = brandSel.value && o.dataset.brand !== brandSel.value; }); if (modelSel.selectedOptions[0]?.hidden) modelSel.value = ''; };
    brandSel.addEventListener('change', () => { filterModels(); seo(); }); modelSel.addEventListener('change', seo); filterModels();
    document.getElementById('carFilter').addEventListener('input', (e) => { const q = e.target.value.trim().toLowerCase(); document.querySelectorAll('[data-car]').forEach((l) => { l.hidden = q && !l.dataset.car.toLowerCase().includes(q); }); });
    const carBoxes = [...document.querySelectorAll('[name="vehicle_ids[]"]')];
    const carCount = () => { const n = carBoxes.filter((c) => c.checked).length; document.getElementById('carCount').textContent = n; carBoxes.forEach((c) => { c.disabled = !c.checked && n >= 8; }); seo(); };
    carBoxes.forEach((c) => c.addEventListener('change', carCount));

    // ─── درجة الـ SEO ───
    const f = (n) => form.querySelector(`[name="${n}"]`);
    const norm = (s) => (s || '').toLowerCase().replace(/[ً-ٟـ]/g, '').replace(/[أإآ]/g, @js(__('ا'))).replace(/ة/g, @js(__('ه'))).replace(/ى/g, @js(__('ي'))).trim();
    const slugify = (s) => norm(s).replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '');
    function seo() {
        if (!seoReady) return;
        const kw = norm(f('focus_keyword').value), title = f('title_ar').value, metaT = f('meta_title_ar').value || title;
        const desc = f('meta_description_ar').value || f('excerpt_ar').value;
        const ed = document.querySelector('[data-editor="body_ar"]'), text = ed.innerText.trim();
        const words = text.split(/\s+/).filter(Boolean).length;
        const slug = f('slug').value || slugify(f('title_en').value || title);
        const imgs = [...ed.querySelectorAll('img')];
        const links = [...ed.querySelectorAll('a[href]')];
        const hasCover = !document.getElementById('coverPreview').classList.contains('hidden');
        const checks = [
            [!!kw, @js(__('حدّدت كلمة مفتاحية'))],
            [kw && norm(title).includes(kw), @js(__('الكلمة المفتاحية في العنوان'))],
            [kw && norm(text.slice(0, 300)).includes(kw), @js(__('الكلمة المفتاحية في أول فقرة'))],
            [kw && slug.includes(slugify(kw)), @js(__('الكلمة المفتاحية في الرابط'))],
            [metaT.length >= 30 && metaT.length <= 65, `طول عنوان جوجل ${metaT.length} (المثالي 30-65)`],
            [desc.length >= 110 && desc.length <= 165, `طول الوصف ${desc.length} (المثالي 120-160)`],
            [words >= 600, `طول المقال ${words} كلمة (600+ أحسن)`],
            [ed.querySelectorAll('h2').length >= 2, @js(__('فيه عناوين H2 (2 على الأقل)'))],
            [hasCover, @js(__('فيه صورة غلاف'))],
            [imgs.every((i) => i.alt.trim()), @js(__('كل الصور ليها وصف Alt'))],
            [links.some((a) => /^\//.test(a.getAttribute('href')) || a.href.startsWith(location.origin)), @js(__('فيه رابط داخلي لصفحة في الموقع'))],
            [brandSel.value || modelSel.value || carBoxes.some((c) => c.checked), @js(__('مربوط بعربيات (بيحوّل القارئ لعميل)'))],
            [document.querySelectorAll('[data-faq]').length > 0, @js(__('فيه أسئلة شائعة (FAQ)'))],
        ];
        const ok = checks.filter((c) => c[0]).length, score = Math.round(ok / checks.length * 100);
        const s = document.getElementById('seoScore');
        s.textContent = score + '/100';
        s.className = 'rounded-full px-3 py-1 text-sm font-black ' + (score >= 80 ? 'bg-emerald-100 text-emerald-700' : score >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
        document.getElementById('seoChecks').innerHTML = checks.map((c) => `<li class="flex gap-2 ${c[0] ? 'text-emerald-700' : 'text-slate-500'}"><span>${c[0] ? '✅' : '⚪'}</span><span></span></li>`).join('');
        document.querySelectorAll('#seoChecks li span:last-child').forEach((el, i) => { el.textContent = checks[i][1]; });
        document.getElementById('gUrl').textContent = decodeURIComponent(siteUrl + slug);
        document.getElementById('gTitle').textContent = metaT || @js(__('عنوان المقال'));
        document.getElementById('gDesc').textContent = desc || text.slice(0, 155) || @js(__('وصف المقال بيظهر هنا…'));
        ['meta_title_ar', 'meta_description_ar'].forEach((n) => { const el = form.querySelector(`[data-len="${n}"]`), l = f(n).value.length; el.textContent = l + @js(__(' حرف')); el.className = 'font-normal ' + (l === 0 ? 'text-slate-400' : (n === 'meta_title_ar' ? (l >= 30 && l <= 65) : (l >= 110 && l <= 165)) ? 'text-emerald-600' : 'text-amber-600'); });
    }
    form.addEventListener('input', (e) => { if (!e.target.closest('[data-editor]')) seo(); });
    seoReady = true;
    carCount();

    // قبل الحفظ: انقل المحتوى + امنع الخروج من غير حفظ
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; document.querySelectorAll('[data-editor]').forEach((ed) => { ed.closest('[data-editor-wrap]').querySelector('textarea').value = ed.innerHTML; }); });
    addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endsection
