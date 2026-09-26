@extends('storefront.layouts.app')
@php
    use App\Support\Site;
    $in = 'w-full rounded-xl border-0 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-[var(--accent)]';
    $wa = Site::whatsapp(); $phone = Site::phone();
    $need = request()->boolean('need');
@endphp
@section('title', __('تواصل معنا — ').Site::name())

@section('content')
@include('storefront.partials.page-hero', ['title' => $need ? __('دوّرولي على عربية') : __('تواصل معنا'), 'icon' => $need ? '🙋' : '📞', 'sub' => $need ? __('قولنا مواصفات العربية اللي في دماغك وميزانيتك، وإحنا ندوّرلك عليها ونبلغك أول ما توصل.') : __('فريقنا موجود يرد على أي سؤال — اختار الطريقة اللي تريحك.')])

<div class="relative z-10 mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <div class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                @if ($wa)
                    <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" data-track="Contact" class="flex items-center gap-4 rounded-3xl bg-emerald-500 p-5 text-white shadow-lg shadow-emerald-500/20 transition hover:-translate-y-0.5"><span class="text-3xl">💬</span><div><div class="font-black">{{ __('واتساب') }}</div><div class="text-sm opacity-90">{{ __('أسرع طريقة — بنرد في دقايق') }}</div></div></a>
                @endif
                @if ($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" data-track="Contact" class="flex items-center gap-4 rounded-3xl bg-white p-5 ring-1 ring-slate-200 transition hover:-translate-y-0.5"><span class="text-3xl">📞</span><div><div class="font-black text-slate-900">{{ __('اتصل بينا') }}</div><div class="text-sm text-slate-500" dir="ltr">{{ $phone }}</div></div></a>
                @endif
                @if (Site::get('email'))
                    <a href="mailto:{{ Site::get('email') }}" class="flex items-center gap-4 rounded-3xl bg-white p-5 ring-1 ring-slate-200 transition hover:-translate-y-0.5"><span class="text-3xl">✉️</span><div><div class="font-black text-slate-900">{{ __('الإيميل') }}</div><div class="text-sm text-slate-500">{{ Site::get('email') }}</div></div></a>
                @endif
                @if (Site::t('working_hours'))
                    <div class="flex items-center gap-4 rounded-3xl bg-white p-5 ring-1 ring-slate-200"><span class="text-3xl">🕘</span><div><div class="font-black text-slate-900">{{ __('مواعيد العمل') }}</div><div class="text-sm text-slate-500">{{ Site::t('working_hours') }}</div></div></div>
                @endif
            </div>

            @if ($branches->isNotEmpty())
                <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200">
                    <h2 class="mb-3 font-black text-slate-900">{{ __('📍 فروعنا') }}</h2>
                    <div class="space-y-2">
                        @foreach ($branches as $b)
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="font-extrabold text-slate-900">{{ \App\Support\Lang::pick($b, 'name') }}</div>
                                @if ($b->address ?? $b->city ?? null)<div class="mt-0.5 text-sm text-slate-500">{{ $b->address ?? $b->city }}</div>@endif
                                @if ($b->phone ?? null)<a href="tel:{{ preg_replace('/\s+/', '', $b->phone) }}" class="mt-1 inline-block text-sm font-bold text-blue-600" dir="ltr">{{ $b->phone }}</a>@endif
                            </div>
                        @endforeach
                    </div>
                    @if (Site::get('map_url'))<a href="{{ Site::get('map_url') }}" target="_blank" rel="noopener" class="mt-3 block rounded-2xl bg-slate-900 py-3 text-center text-sm font-bold text-white">{{ __('🗺️ افتح الخريطة') }}</a>@endif
                </div>
            @endif
        </div>

        <div class="rounded-[2rem] bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200 sm:p-8">
            <h2 class="text-xl font-black text-slate-900">{{ $need ? __('مواصفات العربية اللي بتدوّر عليها') : __('ابعتلنا رسالة') }}</h2>
            <p class="mb-5 mt-1 text-sm text-slate-500">{{ __('هنرد عليك في أقرب وقت.') }}</p>
            @include('storefront.partials.form-alerts')
            <form action="{{ lroute('storefront.leads.store') }}" method="POST" data-lead>
                <input type="hidden" name="type" value="{{ $need ? 'inquiry' : 'contact' }}">
                @include('storefront.partials.contact-fields')
                @if ($need)
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الماركة / الموديل') }}</label><input type="text" name="car_brand" value="{{ old('car_brand') }}" placeholder="{{ __('مثلاً: تويوتا كامري') }}" class="{{ $in }}"></div>
                        <div><label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الميزانية (ر.ع)') }}</label><input type="number" name="budget" value="{{ old('budget') }}" min="0" class="{{ $in }}"></div>
                    </div>
                @endif
                <div class="mt-3">
                    <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ $need ? __('أي تفاصيل تانية (السنة، اللون، كاش ولا تقسيط...)') : __('رسالتك') }}</label>
                    <textarea name="message" rows="5" maxlength="1000" class="{{ $in }}">{{ old('message') }}</textarea>
                </div>
                <button type="submit" class="mt-5 w-full rounded-2xl bg-[var(--accent)] px-5 py-4 font-black text-[var(--accent-ink)] shadow-lg">{{ __('ابعت ←') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
