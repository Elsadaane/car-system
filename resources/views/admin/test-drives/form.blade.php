@extends('admin.layouts.app')

@section('title', __('تجربة قيادة جديدة'))
@section('subtitle', __('العميل يوقّع على الشروط قبل ما ياخد العربية'))

@section('content')
<form method="POST" action="{{ route('admin.test-drives.store') }}" class="mx-auto max-w-5xl space-y-6"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf

    @if ($errors->any())
        <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
    @endif

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">1</span> {{ __('العميل والعربية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="customer_id" class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                    <span>{{ __('العميل') }} <span class="text-rose-500">*</span></span>
                    <a href="{{ route('admin.customers.create') }}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">{{ __('+ عميل جديد') }}</a>
                </label>
                <input type="search" id="customerSearch" placeholder="{{ __('ابحث بالاسم أو التليفون أو الرقم المدني...') }}" autocomplete="off"
                       class="mb-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                <select id="customer_id" name="customer_id" required size="5" class="w-full rounded-xl border border-slate-200 bg-white p-1 text-sm outline-none focus:border-blue-400">
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" data-search="{{ $c->name_ar }} {{ $c->phone }} {{ $c->national_id }}" class="rounded-lg px-2 py-1.5"
                                @selected((string) old('customer_id', $drive->customer_id) === (string) $c->id)>{{ \App\Support\Lang::pick($c, 'name') }} — {{ $c->phone }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="vehicle_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('العربية') }} <span class="text-rose-500">*</span></label>
                <input type="search" id="vehicleSearch" placeholder="{{ __('ابحث بالماركة أو الكود أو اللوحة...') }}" autocomplete="off"
                       class="mb-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                <select id="vehicle_id" name="vehicle_id" required size="5" class="w-full rounded-xl border border-slate-200 bg-white p-1 text-sm outline-none focus:border-blue-400">
                    @foreach ($vehicles as $v)
                        <option value="{{ $v->id }}" data-mileage="{{ (int) $v->mileage }}" data-search="{{ $v->brand_name }} {{ $v->model_name }} {{ $v->vehicle_code }} {{ $v->plate_number }}" class="rounded-lg px-2 py-1.5"
                                @selected((string) old('vehicle_id', $drive->vehicle_id) === (string) $v->id)>{{ $v->brand_name }} {{ $v->model_name }} {{ $v->manufacturing_year }} — {{ $v->vehicle_code }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">{{ __('بيظهر بس العربيات المتاحة واللي مش في تجربة دلوقتي.') }}</p>
            </div>
            @include('admin.partials.field', ['model' => $drive, 'name' => 'license_number', 'label' => __('رقم رخصة القيادة'), 'dir' => 'ltr'])
            @include('admin.partials.field', ['model' => $drive, 'name' => 'start_mileage', 'label' => __('قراءة العداد وقت الخروج (كم)'), 'type' => 'number', 'min' => 0, 'hint' => __('بيتملى لوحده من بيانات العربية')])
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">2</span> {{ __('الشروط والأحكام') }}</h2>
        <div class="grid gap-5 md:grid-cols-2">
            @include('admin.partials.field', ['model' => $drive, 'name' => 'terms_ar', 'label' => __('بالعربي'), 'type' => 'rich'])
            <div dir="ltr">
                @include('admin.partials.field', ['model' => $drive, 'name' => 'terms_en', 'label' => 'English', 'type' => 'rich', 'dir' => 'ltr'])
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">3</span> {{ __('التوقيع') }}</h2>
        <div class="grid gap-5 md:grid-cols-2">
            @include('admin.partials.signature-pad', ['name' => 'customer_signature_data', 'label' => __('توقيع العميل')])
            @include('admin.partials.signature-pad', ['name' => 'showroom_signature_data', 'label' => __('توقيع الموظف')])
        </div>
    </section>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.test-drives.index') }}" class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</a>
        <button type="submit" data-save class="rounded-xl bg-blue-600 px-8 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">{{ __('ابدأ التجربة 🚗') }}</button>
    </div>
</form>

<script>
(function () {
    const filter = (inputId, selectId) => {
        const input = document.getElementById(inputId), select = document.getElementById(selectId);
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            [...select.options].forEach((o) => { o.hidden = q && !o.dataset.search.toLowerCase().includes(q); });
            const first = [...select.options].find((o) => !o.hidden);
            if (first && select.selectedOptions[0]?.hidden !== false) select.value = first.value;
        });
    };
    filter('customerSearch', 'customer_id');
    filter('vehicleSearch', 'vehicle_id');

    const vehicle = document.getElementById('vehicle_id'), mileage = document.querySelector('[name=start_mileage]');
    const fillMileage = () => { const o = vehicle.selectedOptions[0]; if (o && !mileage.dataset.touched) mileage.value = o.dataset.mileage || ''; };
    mileage.addEventListener('input', () => { mileage.dataset.touched = '1'; });
    vehicle.addEventListener('change', fillMileage);
    if (!mileage.value) fillMileage();
})();
</script>
@endsection
