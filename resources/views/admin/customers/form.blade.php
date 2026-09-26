@extends('admin.layouts.app')

@php
    $editing = $customer->exists;
    $f = fn (array $o) => array_merge(['model' => $customer], $o);
@endphp

@section('title', $editing ? __('تعديل ').$customer->name_ar : __('إضافة عميل'))
@section('subtitle', $editing ? $customer->customer_code : __('سجّل عميل جديد'))

@section('content')
<form method="POST" action="{{ $editing ? route('admin.customers.update', $customer) : route('admin.customers.store') }}"
      class="mx-auto max-w-4xl space-y-6"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-2xl bg-rose-50 p-4 text-sm font-bold text-rose-700 ring-1 ring-rose-200">{{ __('راجع الحقول المعلّمة بالأحمر (') }}{{ $errors->count() }})</div>
    @endif

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('البيانات الأساسية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.partials.field', $f(['name' => 'name_ar', 'label' => __('الاسم الكامل'), 'required' => true]))
            @include('admin.partials.field', $f(['name' => 'name_en', 'label' => __('الاسم بالإنجليزي'), 'dir' => 'ltr', 'hint' => __('بيظهر في العقود الإنجليزي')]))
            @include('admin.partials.field', $f(['name' => 'phone', 'label' => __('رقم الهاتف (واتساب)'), 'required' => true, 'dir' => 'ltr', 'placeholder' => '968XXXXXXXX']))
            @include('admin.partials.field', $f(['name' => 'email', 'label' => __('الإيميل'), 'type' => 'email', 'dir' => 'ltr', 'hint' => __('مطلوب لو هتعمله حساب في بوابة العملاء')]))
            @include('admin.partials.field', $f(['name' => 'national_id', 'label' => __('الرقم المدني / الهوية'), 'dir' => 'ltr']))
            @include('admin.partials.field', $f(['name' => 'customer_code', 'label' => __('كود العميل'), 'required' => true, 'dir' => 'ltr']))
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('بيانات إضافية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.partials.field', $f(['name' => 'nationality', 'label' => __('الجنسية'), 'list' => 'nationalities']))
            <datalist id="nationalities">@foreach ([__('عماني'), __('مصري'), __('هندي'), __('باكستاني'), __('بنغالي'), __('سوداني'), __('أردني'), __('سوري'), __('فلبيني')] as $n) <option value="{{ $n }}"></option> @endforeach</datalist>
            @include('admin.partials.field', $f(['name' => 'job_title', 'label' => __('الوظيفة'), 'hint' => __('بتفيد في طلبات التمويل')]))
            @include('admin.partials.field', $f(['name' => 'credit_limit', 'label' => __('حد الائتمان للتقسيط'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'hint' => __('أقصى مبلغ ممكن يتقسّط على العميل')]))
            <div></div>
            <div class="sm:col-span-2">
                @include('admin.partials.field', $f(['name' => 'address', 'label' => __('العنوان'), 'type' => 'textarea', 'rows' => 2]))
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('الحالة') }}</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($statuses as $opt => $optLabel)
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                    <input type="radio" name="status" value="{{ $opt }}" class="accent-blue-600" @checked(old('status', $customer->status ?? 'active') === $opt)
                           onchange="document.getElementById('blockBox').classList.toggle('hidden', this.value !== 'blocked')">
                    {{ $optLabel }}
                </label>
            @endforeach
        </div>
        <div id="blockBox" class="mt-4 {{ old('status', $customer->status) === 'blocked' ? '' : 'hidden' }}">
            @include('admin.partials.field', $f(['name' => 'block_reason', 'label' => __('سبب الحظر'), 'type' => 'textarea', 'rows' => 2, 'hint' => __('العميل المحظور مش هيقدر يدخل البوابة')]))
        </div>
        <div class="mt-4">
            @include('admin.partials.field', $f(['name' => 'notes', 'label' => __('ملاحظات داخلية'), 'type' => 'textarea', 'rows' => 2]))
        </div>
    </section>

    <div class="flex justify-end gap-3">
        <a href="{{ $editing ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</a>
        <button type="submit" data-save class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">{{ $editing ? __('حفظ التعديلات') : __('إضافة العميل') }}</button>
    </div>
</form>
@endsection
