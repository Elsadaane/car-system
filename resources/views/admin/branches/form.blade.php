@extends('admin.layouts.app')

@php $editing = $branch->exists; @endphp

@section('title', $editing ? __('تعديل ').$branch->name_ar : __('إضافة فرع'))
@section('subtitle', $editing ? $branch->branch_code : __('بيانات الفرع الجديد'))

@section('content')
<form method="POST" action="{{ $editing ? route('admin.branches.update', $branch) : route('admin.branches.store') }}"
      class="mx-auto max-w-4xl space-y-6">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
            <div class="font-bold">{{ __('راجع الحقول دي:') }}</div>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- البيانات الأساسية --}}
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('البيانات الأساسية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.branches.partials.field', ['name' => 'name_ar', 'label' => __('اسم الفرع بالعربي'), 'required' => true])
            @include('admin.branches.partials.field', ['name' => 'name_en', 'label' => __('اسم الفرع بالإنجليزي'), 'dir' => 'ltr'])
            @include('admin.branches.partials.field', ['name' => 'branch_code', 'label' => __('كود الفرع'), 'required' => true, 'dir' => 'ltr'])
            @include('admin.branches.partials.field', ['name' => 'opening_date', 'label' => __('تاريخ الافتتاح'), 'type' => 'date',
                'value' => old('opening_date', $branch->opening_date ? \Illuminate\Support\Carbon::parse($branch->opening_date)->format('Y-m-d') : '')])
        </div>
    </section>

    {{-- التواصل والموقع --}}
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('التواصل والموقع') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @include('admin.branches.partials.field', ['name' => 'phone', 'label' => __('التليفون'), 'dir' => 'ltr'])
            @include('admin.branches.partials.field', ['name' => 'mobile', 'label' => __('الموبايل (واتساب)'), 'dir' => 'ltr', 'placeholder' => '968XXXXXXXX'])
            @include('admin.branches.partials.field', ['name' => 'email', 'label' => __('البريد الإلكتروني'), 'type' => 'email', 'dir' => 'ltr'])
            @include('admin.branches.partials.field', ['name' => 'location_url', 'label' => __('رابط Google Maps'), 'type' => 'url', 'dir' => 'ltr', 'placeholder' => 'https://maps.google.com/...'])
            @include('admin.branches.partials.field', ['name' => 'city', 'label' => __('المدينة')])
            @include('admin.branches.partials.field', ['name' => 'region', 'label' => __('المنطقة / المحافظة')])
            <div class="sm:col-span-2">
                @include('admin.branches.partials.field', ['name' => 'address', 'label' => __('العنوان التفصيلي'), 'type' => 'textarea'])
            </div>
        </div>
    </section>

    {{-- الإدارة والبيانات الرسمية --}}
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('الإدارة والبيانات الرسمية') }}</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="manager_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('مدير الفرع') }}</label>
                <select id="manager_id" name="manager_id"
                        class="w-full rounded-xl border px-3 py-2.5 text-sm outline-none focus:ring-4 {{ $errors->has('manager_id') ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-400 focus:ring-blue-50' }}">
                    <option value="">{{ __('— بدون مدير —') }}</option>
                    @foreach ($managers as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('manager_id', $branch->manager_id) === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('manager_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="hidden sm:block"></div>
            @include('admin.branches.partials.field', ['name' => 'commercial_register', 'label' => __('السجل التجاري'), 'dir' => 'ltr'])
            @include('admin.branches.partials.field', ['name' => 'tax_number', 'label' => __('الرقم الضريبي'), 'dir' => 'ltr'])
        </div>
    </section>

    {{-- الحالة --}}
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('الحالة') }}</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['active' => [__('نشط'), __('الفرع شغال وبيظهر في كل القوائم')], 'inactive' => [__('غير نشط'), __('مقفول مؤقتاً، وبياناته محفوظة')]] as $opt => [$optLabel, $optHint])
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50 border-slate-200 hover:border-slate-300">
                    <input type="radio" name="status" value="{{ $opt }}" class="mt-1 accent-blue-600"
                           @checked(old('status', $branch->status ?? 'active') === $opt)>
                    <span>
                        <span class="block text-sm font-bold text-slate-800">{{ $optLabel }}</span>
                        <span class="block text-xs text-slate-500">{{ $optHint }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
            <input type="checkbox" name="is_main" value="1" class="mt-1 accent-blue-600"
                   @checked(old('is_main', $branch->is_main))>
            <span>
                <span class="block text-sm font-bold text-slate-800">{{ __('الفرع الرئيسي') }}</span>
                <span class="block text-xs text-slate-500">{{ __('لو اخترته، هيبقى هو الفرع الافتراضي للعربيات والعقود الجديدة، والفرع الرئيسي القديم هيتحول لفرع عادي.') }}</span>
            </span>
        </label>

        <div class="mt-5">
            @include('admin.branches.partials.field', ['name' => 'notes', 'label' => __('ملاحظات داخلية'), 'type' => 'textarea'])
        </div>
    </section>

    <div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-100/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border-0 sm:bg-transparent sm:px-0">
        <a href="{{ $editing ? route('admin.branches.show', $branch) : route('admin.branches.index') }}"
           class="rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</a>
        <button type="submit" class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
            {{ $editing ? __('حفظ التعديلات') : __('إضافة الفرع') }}
        </button>
    </div>
</form>
@endsection
