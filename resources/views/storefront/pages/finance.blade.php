@extends('storefront.layouts.app')
@php
    use App\Support\Site;
    $in = 'w-full rounded-xl border-0 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-[var(--accent)]';
@endphp
@section('title', __('التقسيط — ').Site::name())
@section('meta_description', __('احسب قسط عربيتك واطلب التقسيط أونلاين — مقدم يبدأ من ').Site::get('min_down_percent', 20).__('% ومدة لحد ').Site::get('max_months', 60).__(' شهر.'))

@section('content')
@include('storefront.partials.page-hero', ['title' => __('قسّط عربيتك'), 'icon' => '💳', 'sub' => __('احسب قسطك، وقدّم طلبك في دقيقة — ومستشار التمويل هيكلمك بكل التفاصيل.')])

<div class="relative z-10 mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            @include('storefront.partials.calculator', ['price' => $price ?: 8000])
            <a href="#" id="seeCars" class="mt-3 block rounded-2xl bg-slate-900 px-5 py-3.5 text-center font-extrabold text-white hover:bg-slate-700">{{ __('🔍 وريني العربيات اللي في حدود القسط ده') }}</a>

            @if ($companies->isNotEmpty())
                <div class="mt-6 rounded-3xl bg-white p-5 ring-1 ring-slate-200">
                    <h3 class="mb-3 font-black text-slate-900">{{ __('🏦 بنتعامل كمان مع') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($companies as $c)<span class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">{{ \App\Support\Lang::pick($c, 'name') }}</span>@endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-[2rem] bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200 sm:p-8">
            <h2 class="text-xl font-black text-slate-900">{{ __('اطلب التقسيط') }}</h2>
            <p class="mb-5 mt-1 text-sm text-slate-500">{{ __('الأرقام اللي في الحاسبة بتتبعت مع طلبك أوتوماتيك.') }}</p>
            @include('storefront.partials.form-alerts')
            <form action="{{ lroute('storefront.leads.store') }}" method="POST" data-lead id="financeForm">
                <input type="hidden" name="type" value="finance">
                <input type="hidden" name="vehicle_id" value="{{ $vehicleId }}">
                <input type="hidden" name="budget" value="{{ old('budget', $price ?: '') }}">
                <input type="hidden" name="down" value="{{ old('down', request('down')) }}">
                <input type="hidden" name="months" value="{{ old('months', request('months')) }}">
                @include('storefront.partials.contact-fields', ['email' => false])
                <div class="mt-3">
                    <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الراتب الشهري التقريبي (ر.ع)') }} <span class="font-normal text-slate-400">{{ __('بيساعدنا نرشّحلك أنسب نظام') }}</span></label>
                    <input type="number" name="salary" value="{{ old('salary') }}" min="0" class="{{ $in }}">
                </div>
                <div class="mt-3">
                    <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('ملاحظات') }}</label>
                    <textarea name="message" rows="2" maxlength="1000" class="{{ $in }}">{{ old('message') }}</textarea>
                </div>
                <div id="calcSummary" class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-600"></div>
                <button type="submit" class="mt-4 w-full rounded-2xl bg-[var(--accent)] px-5 py-4 font-black text-[var(--accent-ink)] shadow-lg">{{ __('ابعت طلب التقسيط ←') }}</button>
            </form>
        </div>
    </div>

    @if ($faqs->isNotEmpty())
        <div class="mx-auto mt-16 max-w-3xl">
            @include('storefront.partials.section-head', ['title' => __('أسئلة عن التقسيط')])
            @include('storefront.partials.faq', ['faqs' => $faqs])
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const f = document.getElementById('financeForm'), sum = document.getElementById('calcSummary');
    let monthly = 0;
    document.addEventListener('calc', (e) => {
        const d = e.detail; monthly = Math.ceil(d.monthly);
        f.budget.value = Math.round(d.price); f.down.value = Math.round(d.down); f.months.value = d.months;
        sum.textContent = '🚗 ' + Math.round(d.price).toLocaleString('en-US') + @js(__(' · مقدم ')) + Math.round(d.down).toLocaleString('en-US') + ' · ' + d.months + @js(__(' شهر ≈ ')) + monthly.toLocaleString('en-US') + @js(__(' ر.ع/شهر'));
    });
    document.getElementById('seeCars').addEventListener('click', (e) => { e.preventDefault(); location.href = @js(lroute('storefront.vehicles.index')) + '?monthly_max=' + monthly; });
})();
</script>
@endpush
