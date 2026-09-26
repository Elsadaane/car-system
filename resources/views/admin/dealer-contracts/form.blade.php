@extends('admin.layouts.app')

@php
    $editing = $contract->exists;
    $f = fn (array $o) => array_merge(['model' => $contract], $o);
    $dec = \App\Support\Money::decimals();
@endphp

@section('title', $editing ? __('تعديل العقد ').$contract->contract_number : __('عقد تفويض جديد'))
@section('subtitle', __('المالك بيسيب عربيته في المعرض يبيعها بالنيابة عنه'))

@section('content')
<form method="POST" action="{{ $editing ? route('admin.dealer-contracts.update', $contract) : route('admin.dealer-contracts.store') }}"
      class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-3"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
            </div>
        @endif

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">1</span> {{ __('المالك والعربية') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="supplier_id" class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                        <span>{{ __('المالك') }} <span class="text-rose-500">*</span></span>
                        @if (\App\Support\Access::can('suppliers.create')) <a href="{{ route('admin.suppliers.create') }}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">{{ __('+ مالك جديد') }}</a> @endif
                    </label>
                    <select id="supplier_id" name="supplier_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                        <option value="">{{ __('— اختار —') }}</option>
                        @foreach ($suppliers as $s) <option value="{{ $s->id }}" @selected((string) old('supplier_id', $contract->supplier_id) === (string) $s->id)>{{ \App\Support\Lang::pick($s, 'name') }} — {{ $s->phone }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label for="vehicle_id" class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                        <span>{{ __('العربية') }} <span class="text-rose-500">*</span></span>
                        <a href="{{ route('admin.vehicles.create') }}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">{{ __('+ عربية جديدة') }}</a>
                    </label>
                    <select id="vehicle_id" name="vehicle_id" required class="w-full rounded-xl border {{ $errors->has('vehicle_id') ? 'border-rose-300' : 'border-slate-200' }} bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                        <option value="">{{ __('— اختار المالك الأول —') }}</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}" data-supplier="{{ $v->supplier_id }}" data-price="{{ (float) $v->selling_price }}" @selected((string) old('vehicle_id', $contract->vehicle_id) === (string) $v->id)>
                                {{ $v->brand_name }} {{ $v->model_name }} {{ $v->manufacturing_year }} — {{ $v->vehicle_code }}
                            </option>
                        @endforeach
                    </select>
                    <p id="noVehicles" class="mt-1 hidden text-xs text-amber-600">{{ __('المالك ده مالوش عربيات متاحة. سجّل العربية الأول وحط المورد = المالك.') }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">2</span> {{ __('المبالغ') }}</h2>
            @php $hasCt = \App\Http\Controllers\Admin\DealerContractController::hasCommissionType(); $ct = old('commission_type', $contract->commission_type ?? 'fixed'); @endphp
            <div class="grid gap-5 sm:grid-cols-3">
                @include('admin.partials.field', $f(['name' => 'agreed_price', 'label' => __('السعر المتفق عليه'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true, 'hint' => __('اللي المالك عايزه في العربية')]))
                <div>
                    @if ($hasCt)
                        <div class="mb-1.5 flex items-center justify-between text-sm font-semibold text-slate-700">
                            <span>{{ __('عمولة المعرض') }}</span>
                            <span class="inline-flex overflow-hidden rounded-lg text-xs ring-1 ring-slate-200">
                                <label class="cursor-pointer px-2 py-0.5 has-[:checked]:bg-slate-900 has-[:checked]:text-white"><input type="radio" name="commission_type" value="fixed" class="sr-only" @checked($ct !== 'percent')> {{ __('مبلغ') }}</label>
                                <label class="cursor-pointer px-2 py-0.5 has-[:checked]:bg-slate-900 has-[:checked]:text-white"><input type="radio" name="commission_type" value="percent" class="sr-only" @checked($ct === 'percent')> %</label>
                            </span>
                        </div>
                        <input name="commission_amount" type="number" step="0.001" min="0" value="{{ old('commission_amount', $contract->commission_amount) }}" data-ct="fixed" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 {{ $ct === 'percent' ? 'hidden' : '' }}">
                        <input name="commission_rate" type="number" step="0.001" min="0" max="100" value="{{ old('commission_rate', $contract->commission_rate ?? '') }}" data-ct="percent" placeholder="%" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 {{ $ct === 'percent' ? '' : 'hidden' }}">
                        @error('commission_amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @error('commission_rate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-400">{{ __('بتتخصم من المستحق للمالك') }}</p>
                    @else
                        @include('admin.partials.field', $f(['name' => 'commission_amount', 'label' => __('عمولة المعرض'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'hint' => __('بتتخصم من المستحق للمالك')]))
                    @endif
                </div>
                @include('admin.partials.field', $f(['name' => 'tax_percent', 'label' => __('الضريبة %'), 'type' => 'number', 'step' => '0.01', 'min' => 0]))
                @if ($hasCt)
                    @include('admin.partials.field', $f(['name' => 'min_price', 'label' => __('أقل سعر بيع (اختياري)'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'hint' => __('المالك موافق يبيع لحد السعر ده — السيستم مش هيسمح بعقد بيع أقل منه')]))
                @endif
            </div>
            <div class="mt-5 grid gap-3 rounded-2xl bg-slate-900 p-5 text-white sm:grid-cols-4">
                <div><div class="text-[11px] text-slate-400">{{ __('السعر') }}</div><div class="font-bold" data-out="agreed">—</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('+ الضريبة') }}</div><div class="font-bold" data-out="tax">—</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('− العمولة') }}</div><div class="font-bold text-violet-300" data-out="commission">—</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('المستحق للمالك') }}</div><div class="text-xl font-extrabold text-emerald-300" data-out="total">—</div></div>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">3</span> {{ __('الشروط') }}</h2>
            <div class="grid gap-5 xl:grid-cols-2">
                @include('admin.partials.field', $f(['name' => 'terms_ar', 'label' => __('الشروط والأحكام بالعربي'), 'type' => 'rich']))
                @include('admin.partials.field', $f(['name' => 'terms_en', 'label' => __('الشروط والأحكام بالإنجليزي'), 'type' => 'rich', 'dir' => 'ltr']))
            </div>
            <p class="mt-2 text-xs text-slate-400">{{ __('العقد بيتطبع بالعربي والإنجليزي جنب بعض. لو سبت الإنجليزي فاضي بيتطبع العربي بس.') }}</p>
            <div class="mt-4">@include('admin.partials.field', $f(['name' => 'notes', 'label' => __('ملاحظات داخلية'), 'type' => 'textarea', 'rows' => 2]))</div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-1 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">4</span> {{ __('التوقيعات') }}</h2>
            <p class="mb-5 text-xs text-slate-500">{{ __('اختياري دلوقتي — تقدر توقّع بعدين من صفحة العقد.') }}</p>
            <div class="grid gap-5 sm:grid-cols-2">
                @include('admin.partials.signature-pad', ['name' => 'owner_signature_data', 'label' => __('توقيع المالك'), 'existing' => \App\Support\Media::url($contract->owner_signature)])
                @include('admin.partials.signature-pad', ['name' => 'showroom_signature_data', 'label' => __('توقيع المعرض'), 'existing' => \App\Support\Media::url($contract->showroom_signature)])
            </div>
        </section>
    </div>

    <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-base font-extrabold text-slate-900">{{ __('بيانات العقد') }}</h2>
            <div class="space-y-4">
                @include('admin.partials.field', $f(['name' => 'contract_number', 'label' => __('رقم العقد'), 'required' => true, 'dir' => 'ltr']))
                @include('admin.partials.field', $f(['name' => 'contract_date', 'label' => __('تاريخ العقد'), 'type' => 'date', 'required' => true]))
                @include('admin.partials.field', $f(['name' => 'expiry_date', 'label' => __('ينتهي في'), 'type' => 'date', 'hint' => __('لو فاضي: مفتوح لحد البيع')]))
            </div>
        </section>
        <div class="flex gap-2">
            <a href="{{ $editing ? route('admin.dealer-contracts.show', $contract) : route('admin.dealer-contracts.index') }}" class="flex-1 rounded-xl bg-white py-3 text-center text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('إلغاء') }}</a>
            <button type="submit" data-save class="flex-[2] rounded-xl bg-blue-600 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">{{ $editing ? __('حفظ التعديلات') : __('إنشاء العقد') }}</button>
        </div>
    </aside>
</form>

<script>
(function () {
    const dec = {{ $dec }};
    const fmt = (n) => Number(n || 0).toLocaleString('en', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const q = (n) => document.querySelector(`[name=${n}]`);
    const supplier = q('supplier_id'), vehicle = q('vehicle_id');

    const sync = () => {
        let count = 0;
        [...vehicle.options].forEach((o) => {
            if (!o.dataset.supplier) return;
            o.hidden = o.dataset.supplier !== supplier.value;
            if (!o.hidden) count++;
        });
        if (vehicle.selectedOptions[0]?.hidden) vehicle.value = '';
        vehicle.options[0].textContent = supplier.value ? @js(__('— اختار العربية —')) : @js(__('— اختار المالك الأول —'));
        document.getElementById('noVehicles').classList.toggle('hidden', !supplier.value || count > 0);
    };
    supplier.addEventListener('change', sync);
    sync();

    const calc = () => {
        const pct = document.querySelector('[name=commission_type]:checked')?.value === 'percent';
        document.querySelectorAll('[data-ct]').forEach((el) => el.classList.toggle('hidden', el.dataset.ct !== (pct ? 'percent' : 'fixed')));
        const a = Number(q('agreed_price').value || 0), t = a * Number(q('tax_percent').value || 0) / 100;
        const c = pct ? a * Number(document.querySelector('[name=commission_rate]')?.value || 0) / 100 : Number(q('commission_amount').value || 0);
        document.querySelector('[data-out=agreed]').textContent = fmt(a);
        document.querySelector('[data-out=tax]').textContent = fmt(t);
        document.querySelector('[data-out=commission]').textContent = fmt(c);
        document.querySelector('[data-out=total]').textContent = fmt(a + t - c);
    };
    ['agreed_price', 'commission_amount', 'tax_percent'].forEach((n) => q(n).addEventListener('input', calc));
    document.querySelectorAll('[name=commission_rate], [name=commission_type]').forEach((el) => el.addEventListener('input', calc));
    calc();
})();
</script>
@endsection
