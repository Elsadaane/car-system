@extends('admin.layouts.app')

@section('title', __('ماركات السيارات'))
@section('subtitle', __('الماركات اللي المعرض بيتعامل معاها'))

@php
    $isAdmin = \App\Support\Access::can('catalog.delete');
    $reopen = $errors->any() && old('_modal') === 'brand' ? (old('_edit_url') ?: 'new') : '';
@endphp

@section('actions')
    <button type="button" data-crud-new="brandModal"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('إضافة ماركة') }}
    </button>
    <a href="{{ route('admin.car-models.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('الطرازات') }}</a>
@endsection

@section('content')
    <form method="GET" class="mb-5 flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ابحث باسم الماركة...') }}"
               class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
    </form>

    @if ($brands->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
            <p class="font-bold text-slate-700">{{ __('مفيش ماركات لسه') }}</p>
            <button type="button" data-crud-new="brandModal" class="mt-3 text-sm font-semibold text-blue-600 hover:underline">{{ __('أضف أول ماركة') }}</button>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ($brands as $brand)
                @php $logo = \App\Support\Media::url($brand->logo); @endphp
                <div class="group relative flex flex-col items-center rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg {{ $brand->is_active ? '' : 'opacity-60' }}">
                    <div class="absolute end-2 top-2 flex gap-1 opacity-0 transition group-hover:opacity-100">
                        <button type="button" data-crud-edit="brandModal" title="{{ __('تعديل') }}"
                                data-values="{{ json_encode(['url' => route('admin.brands.update', $brand), 'name_ar' => $brand->name_ar, 'name_en' => $brand->name_en, 'description_ar' => $brand->description_ar, 'is_active' => (bool) $brand->is_active, '_image' => $logo]) }}"
                                class="grid h-8 w-8 place-items-center rounded-lg bg-white text-slate-500 shadow ring-1 ring-slate-200 hover:text-blue-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                        </button>
                        @if ($isAdmin)
                            <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('مسح الماركة :name؟', ['name' => \App\Support\Lang::pick($brand, 'name')])) }})">
                                @csrf @method('DELETE')
                                <button class="grid h-8 w-8 place-items-center rounded-lg bg-white text-slate-500 shadow ring-1 ring-slate-200 hover:text-rose-600" title="{{ __('مسح') }}">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="grid h-20 w-20 place-items-center overflow-hidden rounded-2xl bg-slate-50 ring-1 ring-slate-100">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="{{ \App\Support\Lang::pick($brand, 'name') }}" class="h-14 w-14 object-contain" loading="lazy">
                        @else
                            <span class="text-2xl font-extrabold text-slate-300">{{ mb_substr($brand->name_en ?: \App\Support\Lang::pick($brand, 'name'), 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="mt-3 font-extrabold text-slate-900">{{ \App\Support\Lang::pick($brand, 'name') }}</div>
                    <div class="text-xs text-slate-400" dir="ltr">{{ $brand->name_en ?: '—' }}</div>

                    <div class="mt-4 grid w-full grid-cols-2 gap-2 text-center">
                        <a href="{{ route('admin.car-models.index', ['brand' => $brand->id]) }}" class="rounded-xl bg-slate-50 py-2 hover:bg-blue-50">
                            <div class="text-sm font-extrabold text-slate-800">{{ (int) $brand->models_count }}</div>
                            <div class="text-[10px] text-slate-500">{{ __('طراز') }}</div>
                        </a>
                        <a href="{{ route('admin.vehicles.index', ['brand' => $brand->id]) }}" class="rounded-xl bg-slate-50 py-2 hover:bg-blue-50">
                            <div class="text-sm font-extrabold text-slate-800">{{ (int) $brand->vehicles_count }}</div>
                            <div class="text-[10px] text-slate-500">{{ __('عربية (') }}{{ (int) $brand->available_count }} {{ __('متاحة)') }}</div>
                        </a>
                    </div>
                    @unless ($brand->is_active)
                        <span class="mt-3 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ __('غير مفعّلة') }}</span>
                    @endunless
                </div>
            @endforeach
        </div>
        @if ($brands->hasPages())
            <div class="mt-6">{{ $brands->links() }}</div>
        @endif
    @endif

    {{-- ═══════ مودال الماركة ═══════ --}}
    <dialog id="brandModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.brands.store') }}"
            data-title-new="{{ __('إضافة ماركة') }}" data-title-edit="{{ __('تعديل الماركة') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.brands.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="brand">
            <input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('إضافة ماركة') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'name_ar', 'label' => __('الاسم بالعربي'), 'required' => true, 'placeholder' => __('تويوتا')])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'name_en', 'label' => __('الاسم بالإنجليزي'), 'dir' => 'ltr', 'placeholder' => 'Toyota'])
                </div>

                <div>
                    <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('الشعار') }}</div>
                    <div class="flex items-center gap-4">
                        <div data-crud-preview class="hidden h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-50 p-2 ring-1 ring-slate-200">
                            <img src="" alt="" class="h-full w-full object-contain">
                        </div>
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                               onchange="const p = this.closest('dialog').querySelector('[data-crud-preview]'); if (this.files[0]) { p.classList.remove('hidden'); p.querySelector('img').src = URL.createObjectURL(this.files[0]); }"
                               class="block w-full text-sm text-slate-500 file:me-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <label data-edit-only class="mt-2 hidden items-center gap-2 text-xs text-rose-600 [&:not(.hidden)]:flex">
                        <input type="checkbox" name="remove_logo" value="1" data-default="0"> {{ __('شيل الشعار الحالي') }}
                    </label>
                    @error('logo') <p data-error class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-400">{{ __('PNG بخلفية شفافة أحسن. أقصى حجم 2 ميجا.') }}</p>
                </div>

                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'b_', 'name' => 'description_ar', 'label' => __('وصف مختصر'), 'type' => 'textarea', 'rows' => 2])

                <input type="hidden" name="is_active" value="0">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                    <input type="checkbox" name="is_active" value="1" data-default="1" class="accent-blue-600" @checked(old('is_active', '1') === '1')>
                    <span class="text-sm font-bold text-slate-800">{{ __('مفعّلة (تظهر في الاختيارات)') }}</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
            </div>
        </form>
    </dialog>

    @include('admin.partials.crud-modal-script')
@endsection
