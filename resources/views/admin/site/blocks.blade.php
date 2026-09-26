@extends('admin.layouts.app')

@php
    use App\Support\Media;
    use App\Http\Controllers\Admin\SiteBlockController as SB;
    [$icon, $label, $help] = SB::TYPES[$type];
    $reopen = $errors->any() && old('_modal') === 'block' ? (old('_edit_url') ?: 'new') : '';
    $is = fn (...$t) => in_array($type, $t, true);
@endphp

@section('title', __('محتوى الموقع'))
@section('subtitle', __('البانرات وآراء العملاء والأسئلة الشائعة — اسحب الكروت عشان ترتّبها'))

@section('actions')
    <a href="{{ route('storefront.home') }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('👁️ فتح الموقع') }}</a>
    <button type="button" data-crud-new="blockModal" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ __('+ إضافة') }}</button>
@endsection

@section('content')
    <div class="mb-5 flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200 sm:w-fit">
        @foreach (SB::TYPES as $k => [$ic, $l])
            <a href="{{ route('admin.site-blocks.index', $k) }}" class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold {{ $type === $k ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900' }}">{{ $ic }} {{ $l }} <span class="rounded-full px-1.5 text-[11px] {{ $type === $k ? 'bg-white/20' : 'bg-slate-100' }}">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    <p class="mb-5 text-sm text-slate-500">{{ $icon }} {{ $help }}</p>

    @if ($blocks->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
            <div class="text-4xl">{{ $icon }}</div>
            <p class="mt-3 font-bold text-slate-600">{{ __('مفيش') }} {{ $label }} {{ __('لسه') }}</p>
            <button type="button" data-crud-new="blockModal" class="mt-4 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('+ أضف أول واحد') }}</button>
        </div>
    @else
        <div id="sortable" class="grid gap-4 {{ $is('banner') ? 'lg:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-3' }}">
            @foreach ($blocks as $b)
                @php
                    $img = Media::url($b->image);
                    $today = today()->toDateString();
                    $live = $b->is_active && (! $b->starts_at || $b->starts_at <= $today) && (! $b->ends_at || $b->ends_at >= $today);
                    $values = ['url' => route('admin.site-blocks.update', [$type, $b->id]), 'title' => $b->title, 'body' => $b->body, 'link' => $b->link, 'button_text' => $b->button_text, 'title_en' => $b->title_en ?? null, 'body_en' => $b->body_en ?? null, 'button_text_en' => $b->button_text_en ?? null, 'icon' => $b->icon, 'rating' => $b->rating, 'sort' => $b->sort, 'starts_at' => $b->starts_at, 'ends_at' => $b->ends_at, 'is_active' => (bool) $b->is_active, '_image' => $img];
                @endphp
                <div draggable="true" data-id="{{ $b->id }}" class="group cursor-grab overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition active:cursor-grabbing {{ $b->is_active ? '' : 'opacity-60' }}">
                    @if ($is('banner'))
                        <div class="relative aspect-[21/9] bg-slate-100">
                            @if ($img)<img src="{{ $img }}" alt="" class="h-full w-full object-cover">@endif
                            <div class="absolute inset-0 rtl:bg-gradient-to-l ltr:bg-gradient-to-r from-slate-900/80 to-transparent p-4 text-white">
                                <div class="text-lg font-black">{{ $b->title }}</div>
                                <div class="line-clamp-2 max-w-sm text-xs opacity-80">{{ $b->body }}</div>
                                @if ($b->button_text)<span class="mt-2 inline-block rounded-lg bg-amber-400 px-2 py-1 text-[11px] font-bold text-slate-900">{{ $b->button_text }}</span>@endif
                            </div>
                        </div>
                    @endif
                    <div class="flex items-start gap-3 p-4">
                        @if ($is('feature'))<span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-2xl">{{ $b->icon ?: '✨' }}</span>@endif
                        @if ($is('testimonial'))
                            @if ($img)<img src="{{ $img }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover">@else<span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-slate-900 font-black text-amber-400">{{ mb_substr($b->title, 0, 1) }}</span>@endif
                        @endif
                        <div class="min-w-0 flex-1">
                            @unless ($is('banner'))
                                <div class="font-extrabold text-slate-900">{{ $b->title }}</div>
                                @if ($is('testimonial'))<div class="text-xs text-amber-500">{{ str_repeat('★', (int) ($b->rating ?: 5)) }} <span class="text-slate-400">{{ $b->button_text }}</span></div>@endif
                                <p class="mt-1 line-clamp-3 text-sm text-slate-500">{{ $b->body }}</p>
                            @endunless
                            <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] font-bold">
                                <span class="rounded-full px-2 py-0.5 {{ $live ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $live ? __('● ظاهر على الموقع') : ($b->is_active ? __('⏸ خارج المدة') : __('○ مخفي')) }}</span>
                                @if ($b->starts_at || $b->ends_at)<span class="rounded-full bg-blue-50 px-2 py-0.5 text-blue-700">📅 {{ $b->starts_at ?: '...' }} ← {{ $b->ends_at ?: '...' }}</span>@endif
                            </div>
                        </div>
                        <span class="text-slate-300" title="{{ __('اسحب للترتيب') }}">⋮⋮</span>
                    </div>
                    <div class="flex border-t border-slate-100 text-xs font-bold">
                        <button type="button" data-crud-edit="blockModal" data-values="{{ json_encode($values) }}" class="flex-1 py-2.5 text-blue-600 hover:bg-blue-50">{{ __('✏️ تعديل') }}</button>
                        <form method="POST" action="{{ route('admin.site-blocks.toggle', [$type, $b->id]) }}" class="flex-1">@csrf @method('PATCH')<button class="w-full py-2.5 text-slate-600 hover:bg-slate-50">{{ $b->is_active ? __('🙈 إخفاء') : __('👁️ إظهار') }}</button></form>
                        <form method="POST" action="{{ route('admin.site-blocks.destroy', [$type, $b->id]) }}" class="flex-1" onsubmit="{{ __('return confirm(\'مسح نهائي؟\')') }}">@csrf @method('DELETE')<button class="w-full py-2.5 text-rose-600 hover:bg-rose-50">{{ __('🗑️ مسح') }}</button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <dialog id="blockModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.site-blocks.store', $type) }}" data-title-new="إضافة — {{ $label }}" data-title-edit="تعديل — {{ $label }}"
            class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.site-blocks.store', $type) }}" enctype="multipart/form-data">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="block"><input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('إضافة') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-5">
                @if ($is('banner', 'testimonial'))
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $is('banner') ? __('صورة البانر (عرضية 1920×800)') : __('صورة العميل (اختياري)') }} @if ($is('banner'))<span class="text-rose-500">*</span>@endif</label>
                        <div data-crud-preview class="mb-2 hidden overflow-hidden rounded-xl bg-slate-100"><img src="" alt="" class="max-h-40 w-full object-cover"></div>
                        <input type="file" name="image" accept="image/*" class="block w-full text-sm file:ml-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-bold">
                        @error('image')<p data-error class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'title', 'label' => ['banner' => __('العنوان الكبير'), 'testimonial' => __('اسم العميل'), 'faq' => __('السؤال'), 'feature' => __('الميزة')][$type], 'required' => ! $is('banner')])
                @if ($is('feature'))
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'icon', 'label' => __('أيقونة (إيموجي)'), 'placeholder' => '🛡️', 'hint' => __('انسخ أي إيموجي: 🛡️ 💳 🏷️ 🔁 🚗 ⭐ 🧰 📄')])
                @endif
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'body', 'type' => 'textarea', 'rows' => $is('faq', 'testimonial') ? 4 : 2, 'label' => ['banner' => __('الوصف تحت العنوان'), 'testimonial' => __('رأي العميل'), 'faq' => __('الإجابة'), 'feature' => __('الشرح')][$type], 'required' => $is('faq', 'testimonial')])
                @if ($is('testimonial'))
                    <div class="grid grid-cols-2 gap-3">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'rating', 'type' => 'select', 'label' => __('التقييم'), 'options' => [5 => '★★★★★', 4 => '★★★★', 3 => '★★★']])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'button_text', 'label' => __('وصف قصير'), 'placeholder' => __('اشترى كامري 2022')])
                    </div>
                @endif
                @if ($is('banner'))
                    <div class="grid grid-cols-2 gap-3">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'button_text', 'label' => __('نص الزرار'), 'placeholder' => __('اكتشف العرض')])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'link', 'label' => __('الزرار يودّي على'), 'dir' => 'ltr', 'placeholder' => '/offers'])
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'starts_at', 'type' => 'date', 'label' => __('يبدأ من')])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'ends_at', 'type' => 'date', 'label' => __('ينتهي في'), 'hint' => __('بيختفي لوحده بعدها')])
                    </div>
                @endif
                <details class="rounded-xl bg-slate-50 p-3" open>
                    <summary class="cursor-pointer text-sm font-bold text-slate-600">{{ __('🇬🇧 النسخة الإنجليزية') }} <span class="font-normal text-slate-400">{{ __('(للزوار على /en — لو فاضية بيظهر العربي)') }}</span></summary>
                    <div class="mt-3 space-y-3" dir="ltr">
                        <input name="title_en" placeholder="Title" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400">
                        <textarea name="body_en" rows="2" placeholder="Text" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400"></textarea>
                        @if ($is('banner', 'testimonial'))<input name="button_text_en" placeholder="{{ $is('banner') ? 'Button text' : 'Short description' }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400">@endif
                    </div>
                </details>
                <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" data-default="1" @checked(old('is_active', true)) class="h-4 w-4 accent-blue-600"> {{ __('ظاهر على الموقع') }}</label>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
            </div>
        </form>
    </dialog>
    @include('admin.partials.crud-modal-script')

    <script>
    (function () {
        // ترتيب بالسحب
        const box = document.getElementById('sortable');
        if (!box) return;
        let dragged = null;
        box.addEventListener('dragstart', (e) => { dragged = e.target.closest('[data-id]'); dragged.classList.add('opacity-40'); });
        box.addEventListener('dragend', () => {
            dragged.classList.remove('opacity-40'); dragged = null;
            const ids = [...box.querySelectorAll('[data-id]')].map((el) => el.dataset.id);
            fetch(@js(route('admin.site-blocks.reorder', $type)), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' }, body: JSON.stringify({ ids }) });
        });
        box.addEventListener('dragover', (e) => {
            e.preventDefault();
            const over = e.target.closest('[data-id]');
            if (!dragged || !over || over === dragged) return;
            const r = over.getBoundingClientRect();
            const after = (e.clientY - r.top) / r.height > .5 || (e.clientX - r.left) / r.width < .5;
            over.parentNode.insertBefore(dragged, after ? over.nextSibling : over);
        });
    })();
    </script>
@endsection
