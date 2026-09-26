@extends('admin.layouts.app')

@section('title', __('إعدادات النظام'))
@section('subtitle', __('هوية المعرض وشروط العقود — بتظهر في كل الطباعة والإيصالات'))

@php
    use App\Support\Media;
    use App\Http\Controllers\Admin\SettingsController as S;
    $c = $company;
    $v = fn (string $k) => old($k, $c->{$k} ?? '');
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-50';
    $sections = ['identity' => [__('الهوية والشعارات'), '🎨'], 'info' => [__('بيانات الشركة'), '🏢'], 'terms' => [__('شروط العقود'), '📜'], 'site' => [__('الموقع والسياسات'), '🌐'], 'print' => [__('الطباعة'), '🖨️']];
@endphp

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" id="settingsForm"
      class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[220px_1fr]"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf @method('PUT')

    {{-- ═══ التنقل ═══ --}}
    <aside class="lg:sticky lg:top-24 lg:self-start">
        <nav class="flex gap-1 overflow-x-auto rounded-2xl bg-white p-2 shadow-sm ring-1 ring-slate-200 lg:flex-col">
            @foreach ($sections as $id => [$label, $icon])
                <a href="#{{ $id }}" class="flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900" data-nav="{{ $id }}">
                    <span>{{ $icon }}</span> {{ $label }}
                </a>
            @endforeach
        </nav>
        <button type="submit" data-save class="mt-4 hidden w-full rounded-xl bg-blue-600 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 disabled:opacity-60 lg:block">{{ __('حفظ الإعدادات') }}</button>
    </aside>

    <div class="space-y-6">
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
            </div>
        @endif

        {{-- ═══ الهوية ═══ --}}
        <section id="identity" class="scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('الهوية والشعارات') }}</h2>
            <p class="mb-5 text-xs text-slate-500">{{ __('الشعار بيظهر في السايدبار والطباعة. الختم بيتحط جنب توقيع المعرض. الهيدر (اختياري) صورة عرضية بتحل محل ترويسة الطباعة بالكامل.') }}</p>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (S::IMAGES as $key => $label)
                    @php $url = Media::url($c->{$key} ?? null); @endphp
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 p-4 text-center transition hover:border-blue-300" data-image-box>
                        <div class="text-sm font-bold text-slate-700">{{ $label }}</div>
                        <label class="mt-3 grid h-32 cursor-pointer place-items-center overflow-hidden rounded-xl bg-slate-50">
                            <img data-preview src="{{ $url }}" alt="" class="{{ $url ? '' : 'hidden' }} max-h-32 max-w-full object-contain">
                            <span data-placeholder class="{{ $url ? 'hidden' : '' }} text-xs text-slate-400">{{ __('اضغط لاختيار صورة') }}</span>
                            <input type="file" name="{{ $key }}" accept="image/*" class="sr-only" data-image-input="{{ $key }}">
                        </label>
                        @if ($url)
                            <label class="mt-2 inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-rose-600">
                                <input type="checkbox" name="remove_{{ $key }}" value="1" class="accent-rose-600"> {{ __('شيل الصورة') }}
                            </label>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- معاينة ترويسة الطباعة --}}
            <div class="mt-6">
                <div class="mb-2 text-xs font-bold text-slate-500">{{ __('معاينة شكل الطباعة (بتتحدث وإنت بتكتب)') }}</div>
                <div class="rounded-2xl bg-slate-100 p-4">
                    <div class="mx-auto max-w-2xl bg-white p-6 shadow">
                        <img data-live="header_image" src="{{ Media::url($c->header_image ?? null) }}" alt="" class="{{ ! empty($c->header_image) ? '' : 'hidden' }} w-full object-contain">
                        <div data-live-default class="{{ ! empty($c->header_image) ? 'hidden' : 'flex' }} items-start justify-between border-b-2 border-slate-900 pb-4">
                            <div>
                                <div class="text-xl font-extrabold text-slate-900" data-live="name_ar">{{ \App\Support\Lang::pick($c, 'name') ?? __('اسم المعرض') }}</div>
                                <div class="text-xs text-slate-500" dir="ltr" data-page-align data-live="name_en">{{ $c->name_en ?? '' }}</div>
                                <div class="mt-1 text-[11px] text-slate-500"><span data-live="address_ar">{{ \App\Support\Lang::pick($c, 'address') ?? '' }}</span></div>
                                <div class="text-[11px] text-slate-500">{{ __('هاتف:') }} <span data-live="phone" dir="ltr">{{ $c->phone ?? '' }}</span> {{ __('· سجل تجاري:') }} <span data-live="commercial_register_number">{{ $c->commercial_register_number ?? '' }}</span></div>
                            </div>
                            <img data-live="logo" src="{{ Media::url($c->logo ?? null) }}" alt="" class="{{ ! empty($c->logo) ? '' : 'hidden' }} h-14 object-contain">
                        </div>
                        <div class="mt-4 text-center text-sm font-extrabold text-slate-800">{{ __('عقد بيع مركبة') }}</div>
                        <div class="mt-3 space-y-1.5"><div class="h-2 rounded bg-slate-100"></div><div class="h-2 w-5/6 rounded bg-slate-100"></div><div class="h-2 w-4/6 rounded bg-slate-100"></div></div>
                        <div class="mt-6 flex items-end justify-between">
                            <div class="w-32 border-t border-slate-400 pt-1 text-center text-[10px] font-bold">{{ __('توقيع المشتري') }}</div>
                            <div class="relative w-32 border-t border-slate-400 pt-1 text-center text-[10px] font-bold">
                                <img data-live="seal" src="{{ Media::url($c->seal ?? null) }}" alt="" class="{{ ! empty($c->seal) ? '' : 'hidden' }} absolute -top-16 left-1/2 h-14 -translate-x-1/2 opacity-80">
                                {{ __('توقيع المعرض') }}
                            </div>
                        </div>
                        <div class="mt-4 border-t border-slate-100 pt-2 text-center text-[10px] text-slate-400" data-live="footer_text">{{ $c->footer_text ?? '' }}</div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══ بيانات الشركة ═══ --}}
        <section id="info" class="scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('بيانات الشركة') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'name_ar' => [__('اسم الشركة *'), 'rtl'], 'name_en' => [__('اسم الشركة بالإنجليزي'), 'ltr'],
                    'commercial_register_number' => [__('رقم السجل التجاري'), 'ltr'], 'tax_number' => [__('الرقم الضريبي'), 'ltr'],
                    'address_ar' => [__('العنوان'), 'rtl'], 'address_en' => [__('العنوان بالإنجليزي'), 'ltr'],
                    'phone' => [__('رقم الهاتف'), 'ltr'], 'whatsapp' => [__('واتساب'), 'ltr'],
                    'email' => [__('الإيميل'), 'ltr'], 'website' => [__('الموقع الإلكتروني'), 'ltr'],
                ] as $name => [$label, $dir])
                    <div>
                        <label for="f_{{ $name }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>
                        <input id="f_{{ $name }}" name="{{ $name }}" value="{{ $v($name) }}" dir="{{ $dir }}" @if ($name === 'name_ar') required @endif data-bind="{{ $name }}" class="{{ $input }}">
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ═══ شروط العقود ═══ --}}
        <section id="terms" class="scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('شروط العقود الافتراضية') }}</h2>
            <p class="mb-5 text-xs text-slate-500">{{ __('بتتحط أوتوماتيك في أي عقد جديد (وتقدر تعدّلها في العقد نفسه). العقود القديمة مابتتغيرش. استخدم «بنود مرقّمة» عشان كل بند ياخد رقمه لوحده في الطباعة.') }}</p>
            <div class="mb-4 flex flex-wrap gap-2" data-term-tabs>
                @foreach (S::TERMS as $key => [$label])
                    <button type="button" data-term-tab="{{ $key }}" class="rounded-full px-4 py-2 text-sm font-bold transition {{ $loop->first ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $label }}</button>
                @endforeach
            </div>
            @foreach (S::TERMS as $key => [$label])
                <div data-term-pane="{{ $key }}" class="{{ $loop->first ? '' : 'hidden' }}">
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach (['ar' => __('عربي'), 'en' => 'English'] as $lang => $langLabel)
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="text-sm font-semibold text-slate-700">{{ $label }} ({{ $langLabel }})</label>
                                    <button type="button" data-default-for="{{ $key }}_terms_{{ $lang }}" data-default="{{ $defaults[$key][$lang] }}" class="text-xs font-bold text-blue-600 hover:underline">{{ __('النص الافتراضي') }}</button>
                                </div>
                                @include('admin.partials.rich-editor', ['name' => $key.'_terms_'.$lang, 'value' => $v($key.'_terms_'.$lang), 'mode' => 'terms', 'dir' => $lang === 'en' ? 'ltr' : 'rtl', 'minHeight' => '14rem'])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ═══ الموقع ═══ --}}
        <section id="site" class="scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('الموقع والسياسات') }}</h2>
            <p class="mb-5 text-xs text-slate-500">{{ __('بتظهر في صفحات المتجر (شروط الاستخدام وسياسة الخصوصية). تقدر تلصقها من Word أو جوجل دوكس بعناوينها وقوائمها.') }}</p>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach (['terms_and_conditions' => __('شروط الاستخدام'), 'privacy_policy' => __('سياسة الخصوصية')] as $key => $label)
                    @foreach (['ar' => __('عربي'), 'en' => 'English'] as $lang => $langLabel)
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }} ({{ $langLabel }})</label>
                            @include('admin.partials.rich-editor', ['name' => $key.'_'.$lang, 'value' => $v($key.'_'.$lang), 'mode' => 'full', 'dir' => $lang === 'en' ? 'ltr' : 'rtl', 'minHeight' => '12rem'])
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>

        {{-- ═══ الطباعة ═══ --}}
        <section id="print" class="scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('الطباعة') }}</h2>
            <div class="grid gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('نص الفوتر (أسفل كل ورقة مطبوعة)') }}</label>
                    <input name="footer_text" value="{{ $v('footer_text') }}" data-bind="footer_text" placeholder="{{ __('مثلاً: شكراً لثقتكم — معرض المليون للسيارات') }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('ملاحظة تحت الإيصالات') }}</label>
                    <textarea name="receipt_notes" rows="2" placeholder="{{ __('مثلاً: المبالغ المدفوعة غير قابلة للاسترداد إلا وفق شروط العقد.') }}" class="{{ $input }}">{{ $v('receipt_notes') }}</textarea>
                </div>
            </div>
        </section>

        <button type="submit" data-save class="w-full rounded-xl bg-blue-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 disabled:opacity-60 lg:hidden">{{ __('حفظ الإعدادات') }}</button>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('settingsForm');

    // معاينة الصور
    form.querySelectorAll('[data-image-input]').forEach((input) => input.addEventListener('change', () => {
        const file = input.files[0]; if (!file) return;
        const url = URL.createObjectURL(file), box = input.closest('[data-image-box]');
        const img = box.querySelector('[data-preview]'); img.src = url; img.classList.remove('hidden');
        box.querySelector('[data-placeholder]').classList.add('hidden');
        const live = document.querySelector(`[data-live="${input.dataset.imageInput}"]`);
        if (live) { live.src = url; live.classList.remove('hidden'); }
        if (input.dataset.imageInput === 'header_image') { const d = document.querySelector('[data-live-default]'); d.classList.add('hidden'); d.classList.remove('flex'); }
    }));

    // المعاينة بتتحدث مع الكتابة
    form.querySelectorAll('[data-bind]').forEach((el) => el.addEventListener('input', () => {
        const t = document.querySelector(`[data-live="${el.dataset.bind}"]`); if (t) t.textContent = el.value;
    }));

    // تابات الشروط
    const tabs = form.querySelectorAll('[data-term-tab]');
    tabs.forEach((b) => b.addEventListener('click', () => {
        tabs.forEach((x) => { const on = x === b; x.classList.toggle('bg-slate-900', on); x.classList.toggle('text-white', on); x.classList.toggle('bg-slate-100', !on); x.classList.toggle('text-slate-600', !on); });
        form.querySelectorAll('[data-term-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.termPane !== b.dataset.termTab));
    }));

    form.querySelectorAll('[data-default-for]').forEach((b) => b.addEventListener('click', () => {
        const t = form.querySelector(`[name="${b.dataset.defaultFor}"]`);
        if (t.value.trim() && !confirm(@js(__('هيتمسح النص الحالي ويتحط النص الافتراضي. متأكد؟')))) return;
        t.value = b.dataset.default;
        t.dispatchEvent(new Event('rich:load'));
    }));

    // تمييز القسم الظاهر في القايمة
    const links = document.querySelectorAll('[data-nav]');
    const io = new IntersectionObserver((es) => es.forEach((e) => {
        if (!e.isIntersecting) return;
        links.forEach((l) => { const on = l.dataset.nav === e.target.id; l.classList.toggle('bg-slate-900', on); l.classList.toggle('text-white', on); });
    }), { rootMargin: '-30% 0px -60% 0px' });
    document.querySelectorAll('section[id]').forEach((s) => io.observe(s));
})();
</script>
@endsection
