@extends('admin.layouts.app')

@php
    $editing = $supplier->exists;
    $f = fn (array $o) => array_merge(['model' => $supplier], $o);
@endphp

@section('title', $editing ? __('تعديل ').$supplier->name_ar : __('إضافة مورد'))
@section('subtitle', $editing ? $supplier->supplier_code : __('بيانات المورد الجديد'))

@section('content')
<form method="POST" action="{{ $editing ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}"
      class="mx-auto max-w-4xl space-y-6"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
            <div class="font-bold">{{ __('راجع الحقول المعلّمة بالأحمر (') }}{{ $errors->count() }})</div>
        </div>
    @endif

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('البيانات الأساسية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.partials.field', $f(['name' => 'name_ar', 'label' => __('الاسم'), 'required' => true]))
            @include('admin.partials.field', $f(['name' => 'name_en', 'label' => __('الاسم بالإنجليزي'), 'dir' => 'ltr']))
            @include('admin.partials.field', $f(['name' => 'supplier_code', 'label' => __('كود المورد'), 'required' => true, 'dir' => 'ltr']))
            @include('admin.partials.field', $f(['name' => 'phone', 'label' => __('رقم الهاتف (واتساب)'), 'dir' => 'ltr', 'placeholder' => '968XXXXXXXX']))
            @include('admin.partials.field', $f(['name' => 'email', 'label' => __('الإيميل'), 'type' => 'email', 'dir' => 'ltr']))
            @if ($branches->isNotEmpty())
                @include('admin.partials.field', $f(['name' => 'branch_id', 'label' => __('الفرع'), 'type' => 'select', 'options' => $branches, 'empty' => __('— كل الفروع —')]))
            @endif
            <div class="sm:col-span-2">
                @include('admin.partials.field', $f(['name' => 'address', 'label' => __('العنوان'), 'type' => 'textarea', 'rows' => 2]))
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('المستندات الرسمية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.partials.field', $f(['name' => 'national_id', 'label' => __('البطاقة الشخصية'), 'dir' => 'ltr']))
            @include('admin.partials.field', $f(['name' => 'national_id_expiry', 'label' => __('تاريخ انتهاء البطاقة'), 'type' => 'date', 'hint' => __('هيظهرلك تنبيه قبل الانتهاء بشهر')]))
            @include('admin.partials.field', $f(['name' => 'tax_number', 'label' => __('الرقم الضريبي'), 'dir' => 'ltr']))
            @include('admin.partials.field', $f(['name' => 'commercial_register', 'label' => __('السجل التجاري'), 'dir' => 'ltr']))
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('التسوية والائتمان') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.partials.field', $f(['name' => 'settlement_days', 'label' => __('مدة التسوية (بالأيام)'), 'type' => 'number', 'min' => 0, 'required' => true, 'list' => 'settlementPresets', 'hint' => __('ميعاد أول قسط الافتراضي بعد تاريخ الفاتورة')]))
            <datalist id="settlementPresets">
                @foreach ([0, 7, 15, 30, 45, 60, 90] as $d) <option value="{{ $d }}"></option> @endforeach
            </datalist>
            @include('admin.partials.field', $f(['name' => 'credit_limit', 'label' => __('حد الائتمان (').config('laroville.currency').')', 'type' => 'number', 'step' => '0.001', 'min' => 0, 'hint' => __('أقصى مبلغ ممكن يبقى متأخر عليكم. هيظهر تنبيه لو اتعدى')]))
            <div class="sm:col-span-2">
                @include('admin.partials.field', $f(['name' => 'settlement_terms', 'label' => __('شروط التسوية'), 'type' => 'textarea', 'rows' => 2]))
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('البيانات البنكية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.partials.field', $f(['name' => 'bank_name', 'label' => __('اسم البنك')]))
            @include('admin.partials.field', $f(['name' => 'account_holder_name', 'label' => __('اسم صاحب الحساب')]))
            <div class="sm:col-span-2">
                @include('admin.partials.field', $f(['name' => 'iban_number', 'label' => __('رقم الآيبان'), 'dir' => 'ltr', 'placeholder' => 'OM00 0000 0000 0000 0000 000']))
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('الحالة') }}</h2>
        <div class="grid gap-3 sm:grid-cols-4">
            @foreach ($statuses as $opt => $optLabel)
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                    <input type="radio" name="status" value="{{ $opt }}" class="accent-blue-600"
                           @checked(old('status', $supplier->status ?? 'active') === $opt)
                           onchange="document.getElementById('reasonBox').classList.toggle('hidden', !['suspended','blacklisted'].includes(this.value))">
                    {{ $optLabel }}
                </label>
            @endforeach
        </div>
        <div id="reasonBox" class="mt-4 {{ in_array(old('status', $supplier->status), ['suspended', 'blacklisted'], true) ? '' : 'hidden' }}">
            @include('admin.partials.field', $f(['name' => 'status_reason', 'label' => __('سبب الإيقاف'), 'type' => 'textarea', 'rows' => 2]))
        </div>
        <div class="mt-4">
            @include('admin.partials.field', $f(['name' => 'notes', 'label' => __('ملاحظات داخلية'), 'type' => 'textarea', 'rows' => 2]))
        </div>
    </section>

    <div class="sticky bottom-0 -mx-4 flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-100/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border-0 sm:bg-transparent sm:px-0">
        @if ($editing)
            <button type="submit" form="deleteSupplier" class="text-sm font-semibold text-rose-600 hover:underline">{{ __('مسح المورد') }}</button>
        @else
            <span></span>
        @endif
        <div class="flex gap-3">
            <a href="{{ $editing ? route('admin.suppliers.show', $supplier) : route('admin.suppliers.index') }}"
               class="rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</a>
            <button type="submit" data-save class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">
                {{ $editing ? __('حفظ التعديلات') : __('إضافة المورد') }}
            </button>
        </div>
    </div>
</form>

@if ($editing)
    <form id="deleteSupplier" method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}"
          onsubmit="{{ __('return confirm(\'متأكد إنك عايز تمسح المورد ده؟\')') }}">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection
