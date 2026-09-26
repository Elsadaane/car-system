@extends('admin.layouts.app')

@section('title', __('جهات الاستيراد'))
@section('subtitle', __('مصدر العربية: خليجي، أمريكي، وارد وكالة...'))

@php
    $isAdmin = \App\Support\Access::can('catalog.delete');
    $reopen = $errors->any() && old('_modal') === 'source' ? (old('_edit_url') ?: 'new') : '';
    $total = max(1, $sources->sum('vehicles_count'));
@endphp

@section('actions')
    <button type="button" data-crud-new="sourceModal"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('إضافة جهة') }}
    </button>
@endsection

@section('content')
    @if ($sources->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
            <p class="font-bold text-slate-700">{{ __('مفيش جهات استيراد لسه') }}</p>
            <p class="mt-1 text-sm text-slate-400">{{ __('أمثلة: خليجي جديد، خليجي مستعمل، وارد أمريكا، وارد اليابان') }}</p>
            <button type="button" data-crud-new="sourceModal" class="mt-3 text-sm font-semibold text-blue-600 hover:underline">{{ __('أضف أول جهة') }}</button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($sources as $source)
                @php $share = round((int) $source->vehicles_count / $total * 100); @endphp
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 {{ $source->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate font-extrabold text-slate-900">{{ \App\Support\Lang::pick($source, 'name') }}</h3>
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $source->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            </div>
                            <div class="text-xs text-slate-400" dir="ltr" data-page-align>{{ $source->name_en }}</div>
                        </div>
                        <div class="flex gap-1">
                            <button type="button" data-crud-edit="sourceModal" title="{{ __('تعديل') }}"
                                    data-values="{{ json_encode(['url' => route('admin.import-sources.update', $source), 'name_ar' => $source->name_ar, 'name_en' => $source->name_en, 'description' => $source->description, 'is_active' => (bool) $source->is_active]) }}"
                                    class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-blue-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                            </button>
                            @if ($isAdmin)
                                <form method="POST" action="{{ route('admin.import-sources.destroy', $source) }}" onsubmit="{{ __('return confirm(\'مسح الجهة؟\')') }}">
                                    @csrf @method('DELETE')
                                    <button class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="{{ __('مسح') }}">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    @if ($source->description)
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $source->description }}</p>
                    @endif
                    <a href="{{ route('admin.vehicles.index', ['source' => $source->id]) }}" class="mt-4 block">
                        <div class="flex justify-between text-xs">
                            <span class="font-bold text-slate-700">{{ (int) $source->vehicles_count }} {{ __('عربية') }}</span>
                            <span class="text-slate-400">{{ $share }}{{ __('% من المخزون') }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-blue-500" style="width: {{ $share }}%"></div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <dialog id="sourceModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.import-sources.store') }}"
            data-title-new="{{ __('إضافة جهة استيراد') }}" data-title-edit="{{ __('تعديل جهة الاستيراد') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.import-sources.store') }}">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="source">
            <input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('إضافة جهة استيراد') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 's_', 'name' => 'name_ar', 'label' => __('الاسم'), 'required' => true, 'placeholder' => __('خليجي جديد')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 's_', 'name' => 'name_en', 'label' => __('الاسم بالإنجليزي'), 'dir' => 'ltr', 'placeholder' => 'GCC New'])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 's_', 'name' => 'description', 'label' => __('وصف'), 'type' => 'textarea', 'rows' => 2])
                <input type="hidden" name="is_active" value="0">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                    <input type="checkbox" name="is_active" value="1" data-default="1" class="accent-blue-600" @checked(old('is_active', '1') === '1')>
                    <span class="text-sm font-bold text-slate-800">{{ __('مفعّلة') }}</span>
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
