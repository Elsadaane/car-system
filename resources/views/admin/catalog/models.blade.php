@extends('admin.layouts.app')

@section('title', __('طرازات السيارات'))
@section('subtitle', __('الموديلات تحت كل ماركة'))

@php
    $isAdmin = \App\Support\Access::can('catalog.delete');
    $reopen = $errors->any() && old('_modal') === 'model' ? (old('_edit_url') ?: 'new') : '';
@endphp

@section('actions')
    <button type="button" data-crud-new="modelModal"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('إضافة طراز') }}
    </button>
    <a href="{{ route('admin.brands.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('الماركات') }}</a>
@endsection

@section('content')
    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('اسم الطراز أو الماركة...') }}"
               class="min-w-[200px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <select name="brand" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل الماركات') }}</option>
            @foreach ($brands as $id => $name)
                <option value="{{ $id }}" @selected($filters['brand'] === $id)>{{ $name }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($models->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-700">{{ __('مفيش طرازات هنا') }}</p>
                <button type="button" data-crud-new="modelModal" class="mt-3 text-sm font-semibold text-blue-600 hover:underline">{{ __('أضف طراز') }}</button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('الطراز') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الماركة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('النوع') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الوقود') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العربيات') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($models as $model)
                            @php $img = \App\Support\Media::url($model->image); @endphp
                            <tr class="transition hover:bg-slate-50/70 {{ $model->is_active ? '' : 'opacity-60' }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-11 w-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-50 ring-1 ring-slate-100">
                                            @if ($img)
                                                <img src="{{ $img }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                            @else
                                                <svg class="h-5 w-5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17V12l1.8-4.6A2 2 0 0 1 8.7 6h6.6a2 2 0 0 1 1.9 1.4L19 12v5"/><path d="M3 17h18v2.5H3z"/></svg>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900">{{ \App\Support\Lang::pick($model, 'name') }} @if ($model->year)<span class="font-normal text-slate-400">{{ $model->year }}</span>@endif</div>
                                            <div class="text-xs text-slate-400" dir="ltr" data-page-align>{{ $model->name_en }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-700">{{ $model->brand_name }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $bodyTypes[$model->body_type] ?? '—' }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $fuelTypes[$model->fuel_type] ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.vehicles.index', ['model' => $model->id]) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ (int) $model->vehicles_count }}</a>
                                    @if ($model->available_count)
                                        <span class="text-xs text-emerald-600">({{ $model->available_count }} {{ __('متاحة)') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" data-crud-edit="modelModal" title="{{ __('تعديل') }}"
                                                data-values="{{ json_encode(['url' => route('admin.car-models.update', $model), 'brand_id' => $model->brand_id, 'name_ar' => $model->name_ar, 'name_en' => $model->name_en, 'year' => $model->year, 'body_type' => $model->body_type, 'fuel_type' => $model->fuel_type, 'is_active' => (bool) $model->is_active, '_image' => $img]) }}"
                                                class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-blue-600">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                                        </button>
                                        @if ($isAdmin)
                                            <form method="POST" action="{{ route('admin.car-models.destroy', $model) }}" onsubmit="{{ __('return confirm(\'مسح الطراز؟\')') }}">
                                                @csrf @method('DELETE')
                                                <button class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="{{ __('مسح') }}">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($models->hasPages())
                <div class="border-t border-slate-100 p-4">{{ $models->links() }}</div>
            @endif
        @endif
    </div>

    {{-- ═══════ مودال الطراز ═══════ --}}
    <dialog id="modelModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.car-models.store') }}"
            data-title-new="{{ __('إضافة طراز') }}" data-title-edit="{{ __('تعديل الطراز') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.car-models.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="model">
            <input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('إضافة طراز') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <div class="max-h-[65vh] space-y-4 overflow-y-auto px-6 py-5">
                <div>
                    <label for="m_brand_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('الماركة') }} <span class="text-rose-500">*</span></label>
                    <select id="m_brand_id" name="brand_id" required data-default="{{ $filters['brand'] }}"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                        <option value="">{{ __('— اختار —') }}</option>
                        @foreach ($brands as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('brand_id', $filters['brand']) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('brand_id') <p data-error class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'm_', 'name' => 'name_ar', 'label' => __('الاسم بالعربي'), 'required' => true, 'placeholder' => __('كامري')])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'm_', 'name' => 'name_en', 'label' => __('الاسم بالإنجليزي'), 'dir' => 'ltr', 'placeholder' => 'Camry'])
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'm_', 'name' => 'year', 'label' => __('السنة'), 'type' => 'number', 'min' => 1950])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'm_', 'name' => 'body_type', 'label' => __('النوع'), 'type' => 'select', 'options' => $bodyTypes, 'empty' => '—'])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'm_', 'name' => 'fuel_type', 'label' => __('الوقود'), 'type' => 'select', 'options' => $fuelTypes, 'empty' => '—'])
                </div>

                <div>
                    <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('صورة الطراز') }}</div>
                    <div class="flex items-center gap-4">
                        <div data-crud-preview class="hidden h-16 w-24 shrink-0 overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200">
                            <img src="" alt="" class="h-full w-full object-cover">
                        </div>
                        <input type="file" name="image" accept="image/png,image/jpeg,image/webp"
                               onchange="const p = this.closest('dialog').querySelector('[data-crud-preview]'); if (this.files[0]) { p.classList.remove('hidden'); p.querySelector('img').src = URL.createObjectURL(this.files[0]); }"
                               class="block w-full text-sm text-slate-500 file:me-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <label data-edit-only class="mt-2 hidden items-center gap-2 text-xs text-rose-600 [&:not(.hidden)]:flex">
                        <input type="checkbox" name="remove_image" value="1" data-default="0"> {{ __('شيل الصورة الحالية') }}
                    </label>
                    @error('image') <p data-error class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <input type="hidden" name="is_active" value="0">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                    <input type="checkbox" name="is_active" value="1" data-default="1" class="accent-blue-600" @checked(old('is_active', '1') === '1')>
                    <span class="text-sm font-bold text-slate-800">{{ __('مفعّل') }}</span>
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
