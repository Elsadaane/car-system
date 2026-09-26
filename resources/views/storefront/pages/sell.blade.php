@extends('storefront.layouts.app')
@php
    use App\Support\Site;
    $in = 'w-full rounded-xl border-0 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-[var(--accent)]';
@endphp
@section('title', __('قيّم عربيتك — ').Site::name())
@section('meta_description', __('بيع أو بدّل عربيتك القديمة بسهولة — ابعت بياناتها وخد عرض سعر من ').Site::name())

@section('content')
@include('storefront.partials.page-hero', ['title' => __('قيّم عربيتك'), 'icon' => '🔁', 'sub' => __('بيع عربيتك أو بدّلها بواحدة من عندنا — في 3 خطوات بسيطة ومن غير لف على المعارض.')])

<div class="relative z-10 mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
        <div class="rounded-[2rem] bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200 sm:p-8">
            @include('storefront.partials.form-alerts')
            <form action="{{ lroute('storefront.leads.store') }}" method="POST" data-lead>
                <input type="hidden" name="type" value="trade_in">
                <h2 class="mb-4 flex items-center gap-2 text-lg font-black text-slate-900"><span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--accent)] text-sm text-[var(--accent-ink)]">1</span> {{ __('بيانات عربيتك') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الماركة *') }}</label>
                        <input type="text" name="car_brand" value="{{ old('car_brand') }}" required list="brandList" class="{{ $in }}">
                        <datalist id="brandList">@foreach ($brands as $b)<option value="{{ $b }}">@endforeach</datalist>
                    </div>
                    <div><label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الموديل *') }}</label><input type="text" name="car_model" value="{{ old('car_model') }}" required class="{{ $in }}"></div>
                    <div><label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('سنة الصنع *') }}</label><input type="number" name="car_year" value="{{ old('car_year') }}" required min="1970" max="{{ date('Y') + 1 }}" class="{{ $in }}"></div>
                    <div><label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الكيلومترات') }}</label><input type="number" name="car_mileage" value="{{ old('car_mileage') }}" min="0" class="{{ $in }}"></div>
                    <div class="sm:col-span-2"><label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('السعر اللي في بالك (ر.ع)') }} <span class="font-normal text-slate-400">{{ __('اختياري') }}</span></label><input type="number" name="car_price" value="{{ old('car_price') }}" min="0" class="{{ $in }}"></div>
                    <div class="sm:col-span-2"><label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('حالتها (حوادث، صيانة، أي ملاحظات)') }}</label><textarea name="car_notes" rows="2" maxlength="500" class="{{ $in }}">{{ old('car_notes') }}</textarea></div>
                </div>
                <label class="mt-4 flex cursor-pointer items-center gap-3 rounded-2xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800">
                    <input type="checkbox" name="message" value="{{ __('عايز أبدّلها بعربية من المعرض') }}" @checked(old('message')) class="h-5 w-5 rounded accent-emerald-600">
                    {{ __('عايز أبدّلها بعربية من عندكم (مش بيع بس)') }}
                </label>

                <h2 class="mb-4 mt-8 flex items-center gap-2 text-lg font-black text-slate-900"><span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--accent)] text-sm text-[var(--accent-ink)]">2</span> {{ __('بياناتك') }}</h2>
                @include('storefront.partials.contact-fields', ['email' => false])
                <button type="submit" class="mt-6 w-full rounded-2xl bg-[var(--accent)] px-5 py-4 font-black text-[var(--accent-ink)] shadow-lg">{{ __('ابعت واستنى عرض السعر ←') }}</button>
            </form>
        </div>

        <div class="space-y-4">
            <div class="rounded-[2rem] bg-slate-950 p-6 text-white">
                <h3 class="mb-5 text-lg font-black">{{ __('إزاي بتمشي؟') }}</h3>
                @foreach ([['📝', __('ابعت البيانات'), __('دقيقة واحدة من غير تسجيل')], ['📞', __('هنكلمك'), __('بعرض سعر مبدئي ونحدد معاد معاينة')], ['🤝', __('استلم فلوسك أو بدّل'), __('كاش، أو نخصمها من تمن عربيتك الجديدة')]] as $i => [$ic, $t, $d])
                    <div class="flex gap-4 {{ $loop->last ? '' : 'mb-5' }}"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/10 text-2xl">{{ $ic }}</span><div><div class="font-extrabold">{{ $t }}</div><div class="text-sm text-slate-400">{{ $d }}</div></div></div>
                @endforeach
            </div>
            @include('storefront.partials.faq', ['faqs' => $faqs->take(4)])
        </div>
    </div>
</div>
@endsection
