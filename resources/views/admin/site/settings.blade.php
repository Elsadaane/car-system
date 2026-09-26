@extends('admin.layouts.app')

@section('title', __('إعدادات الموقع'))
@section('subtitle', __('شكل الموقع، الأقسام، التقسيط، السوشيال، والـ Pixels — من غير كود'))

@php
    use App\Support\Site;
    use App\Support\Media;
    $val = fn (string $k) => old($k, $values[$k] ?? Site::get($k));
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-50';
    $tab = request('tab') && isset(Site::FIELDS[request('tab')]) ? request('tab') : 'general';
    $sourceLabels = ['website' => __('مباشر / جوجل'), 'facebook' => __('فيسبوك / إنستجرام'), 'google' => __('إعلانات جوجل')];
@endphp

@section('actions')
    <a href="{{ route('storefront.home') }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('👁️ فتح الموقع') }}</a>
    <a href="{{ route('admin.site-blocks.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('🖼️ البانرات والمحتوى') }}</a>
@endsection

@section('content')
{{-- ═══ نبض الموقع ═══ --}}
<div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ([[__('طلبات النهارده'), $stats['today'], '📥', 'bg-blue-50 text-blue-700'], [__('طلبات الشهر'), $stats['month'], '📈', 'bg-emerald-50 text-emerald-700'], [__('طلبات لسه جديدة'), $stats['new'], '🔔', 'bg-rose-50 text-rose-700'], [__('مشاهدات العربيات'), number_format($stats['views']), '👁️', 'bg-amber-50 text-amber-700']] as [$l, $n, $ic, $tone])
        <a href="{{ route('admin.inquiries.index') }}" class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 hover:shadow-md">
            <span class="grid h-12 w-12 place-items-center rounded-2xl text-2xl {{ $tone }}">{{ $ic }}</span>
            <div><div class="text-2xl font-black text-slate-900">{{ $n }}</div><div class="text-xs font-bold text-slate-500">{{ $l }}</div></div>
        </a>
    @endforeach
</div>
<div class="mb-6 grid gap-4 lg:grid-cols-2">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-3 text-sm font-extrabold text-slate-900">{{ __('📊 مصادر الطلبات (آخر 30 يوم)') }}</h3>
        @php $totalSrc = max(1, $stats['sources']->sum()); @endphp
        @forelse ($stats['sources'] as $src => $c)
            <div class="mb-2">
                <div class="mb-1 flex justify-between text-xs font-bold text-slate-600"><span>{{ $sourceLabels[$src] ?? $src }}</span><span>{{ $c }} ({{ round($c / $totalSrc * 100) }}%)</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-500" style="width: {{ round($c / $totalSrc * 100) }}%"></div></div>
            </div>
        @empty
            <p class="text-sm text-slate-400">{{ __('مفيش طلبات لسه. استخدم روابط فيها') }} <code dir="ltr">?utm_source=instagram</code> {{ __('في إعلاناتك عشان تعرف كل طلب جاي منين.') }}</p>
        @endforelse
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-3 text-sm font-extrabold text-slate-900">{{ __('🔥 أكتر العربيات مشاهدة') }}</h3>
        @forelse ($stats['top'] as $t)
            <a href="{{ route('admin.vehicles.show', $t->id) }}" class="flex items-center justify-between rounded-xl px-2 py-2 text-sm hover:bg-slate-50"><span class="font-bold text-slate-700">{{ $t->brand }} {{ $t->model }} {{ $t->manufacturing_year }}</span><span class="text-xs font-bold text-slate-500">👁️ {{ number_format($t->views_count) }}</span></a>
        @empty
            <p class="text-sm text-slate-400">{{ __('لسه مفيش مشاهدات.') }}</p>
        @endforelse
    </div>
</div>

<form method="POST" action="{{ route('admin.site-settings.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[220px_1fr]"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf @method('PUT')
    <input type="hidden" name="_tab" value="{{ $tab }}" id="tabInput">

    <aside class="lg:sticky lg:top-24 lg:self-start">
        <nav class="flex gap-1 overflow-x-auto rounded-2xl bg-white p-2 shadow-sm ring-1 ring-slate-200 lg:flex-col">
            @foreach (Site::FIELDS as $g => [$label])
                <button type="button" data-tab="{{ $g }}" class="shrink-0 rounded-xl px-3 py-2.5 text-start text-sm font-bold transition {{ $tab === $g ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">{{ $label }}</button>
            @endforeach
        </nav>
        <button type="submit" data-save class="mt-4 w-full rounded-xl bg-blue-600 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ الإعدادات') }}</button>
    </aside>

    <div>
        @if ($errors->any())
            <div class="mb-4 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @foreach (Site::FIELDS as $g => [$label, $fields])
            <section data-panel="{{ $g }}" class="{{ $tab === $g ? '' : 'hidden' }} rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ $label }}</h2>
                @if ($g === 'seo')
                    <div class="mb-5 rounded-xl bg-blue-50 p-4 text-xs leading-relaxed text-blue-800">{{ __('💡 الـ Pixels بتسجّل تلقائياً:') }} <b>PageView</b> {{ __('في كل صفحة،') }} <b>ViewContent</b> {{ __('لما حد يفتح عربية،') }} <b>AddToWishlist</b> {{ __('للمفضلة،') }} <b>Contact</b> {{ __('لما يدوس واتساب/اتصال، و') }}<b>Lead</b> {{ __('لما يبعت أي فورم. وكل طلب بيتسجّل معاه الـ UTM والـ fbclid/gclid عشان تعرف أنهي إعلان جاب العميل.') }}</div>
                @endif
                <div class="grid gap-4 {{ $g === 'sections' ? 'sm:grid-cols-2' : 'md:grid-cols-2' }}">
                    @foreach ($fields as $key => [$fl, $type, $default, $hint])
                        @if ($type === 'bool')
                            <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:bg-slate-50">
                                <span class="text-sm font-bold text-slate-700">{{ $fl }}</span>
                                <input type="hidden" name="{{ $key }}" value="0">
                                <input type="checkbox" name="{{ $key }}" value="1" @checked((string) $val($key) === '1') class="h-5 w-5 rounded accent-blue-600">
                            </label>
                        @elseif ($type === 'image')
                            @php $url = Media::url($values[$key] ?? null); @endphp
                            <div class="rounded-2xl border-2 border-dashed border-slate-200 p-4 text-center">
                                <div class="text-sm font-bold text-slate-700">{{ $fl }}</div>
                                <label class="mt-3 grid h-36 cursor-pointer place-items-center overflow-hidden rounded-xl bg-slate-50">
                                    <img data-preview src="{{ $url }}" alt="" class="{{ $url ? '' : 'hidden' }} max-h-36 max-w-full object-contain">
                                    <span data-placeholder class="{{ $url ? 'hidden' : '' }} text-xs text-slate-400">{{ __('اضغط لاختيار صورة') }}</span>
                                    <input type="file" name="{{ $key }}" accept="image/*" class="sr-only" data-image-input>
                                </label>
                                @if ($hint)<p class="mt-2 text-xs text-slate-400">{{ $hint }}</p>@endif
                                @if ($url)<label class="mt-2 inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-rose-600"><input type="checkbox" name="remove_{{ $key }}" value="1" class="accent-rose-600"> {{ __('شيل الصورة') }}</label>@endif
                            </div>
                        @else
                            <div class="{{ $type === 'textarea' ? 'md:col-span-2' : '' }}">
                                <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $fl }}</label>
                                @if ($type === 'textarea')
                                    <textarea name="{{ $key }}" rows="{{ $key === 'about' ? 6 : 3 }}" class="{{ $input }}">{{ $val($key) }}</textarea>
                                @elseif ($type === 'color')
                                    <div class="flex items-center gap-2">
                                        <input type="color" value="{{ $val($key) ?: $default }}" oninput="this.nextElementSibling.value = this.value" class="h-11 w-14 cursor-pointer rounded-xl border border-slate-200 bg-white p-1">
                                        <input type="text" name="{{ $key }}" value="{{ $val($key) }}" dir="ltr" placeholder="{{ $default }}" oninput="if (/^#[0-9a-f]{6}$/i.test(this.value)) this.previousElementSibling.value = this.value" class="{{ $input }}">
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach (['#fbbf24', '#f97316', '#ef4444', '#22c55e', '#06b6d4', '#3b82f6', '#8b5cf6', '#e11d48'] as $sw)
                                            <button type="button" onclick="const w = this.closest('div').previousElementSibling; w.children[0].value = w.children[1].value = '{{ $sw }}'" class="h-7 w-7 rounded-lg ring-1 ring-slate-200" style="background: {{ $sw }}"></button>
                                        @endforeach
                                    </div>
                                @else
                                    <input type="{{ $type === 'number' ? 'number' : 'text' }}" name="{{ $key }}" value="{{ $val($key) }}" @if ($type === 'number') step="any" min="0" @endif
                                           @if (in_array($g, ['social', 'seo'], true) || in_array($key, ['phone', 'whatsapp', 'email', 'map_url', 'announcement_link'], true)) dir="ltr" @endif
                                           placeholder="{{ $default }}" class="{{ $input }}">
                                @endif
                                @if ($hint)<p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>@endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</form>

<script>
    document.querySelectorAll('[data-tab]').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('[data-tab]').forEach((x) => { const on = x === b; x.classList.toggle('bg-blue-50', on); x.classList.toggle('text-blue-700', on); x.classList.toggle('text-slate-600', !on); });
        document.querySelectorAll('[data-panel]').forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== b.dataset.tab));
        document.getElementById('tabInput').value = b.dataset.tab;
        history.replaceState(null, '', '?tab=' + b.dataset.tab);
    }));
    document.querySelectorAll('[data-image-input]').forEach((input) => input.addEventListener('change', () => {
        const box = input.closest('label'), file = input.files[0];
        if (!file) return;
        const img = box.querySelector('[data-preview]'); img.src = URL.createObjectURL(file); img.classList.remove('hidden');
        box.querySelector('[data-placeholder]').classList.add('hidden');
    }));
</script>
@endsection
