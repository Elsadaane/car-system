@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use App\Http\Controllers\Admin\FinanceCategoryController as FC;
    $reopen = $errors->any() && old('_modal') === 'cat' ? (old('_edit_url') ?: 'new') : '';
    $back = ['expense' => 'admin.expenses.index', 'revenue' => 'admin.revenues.index', 'asset' => 'admin.assets.index'][$kind];
@endphp

@section('title', __('تصنيفات ').FC::KINDS[$kind])
@section('subtitle', __('قسم رئيسي وتحته أقسام فرعية — عشان التقارير تطلع مفصّلة'))

@section('actions')
    <div class="flex rounded-xl bg-white p-1 ring-1 ring-slate-200">
        @foreach (FC::KINDS as $k => $l)
            <a href="{{ route('admin.finance-categories.index', $k) }}" class="rounded-lg px-3 py-1.5 text-sm font-bold {{ $kind === $k ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900' }}">{{ $l }}</a>
        @endforeach
    </div>
    <button type="button" data-crud-new="catModal" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ __('+ تصنيف') }}</button>
@endsection

@section('content')
    <a href="{{ route($back) }}" class="mb-4 inline-block text-sm font-bold text-blue-600 hover:underline">{{ __('→ رجوع لـ') }}{{ FC::KINDS[$kind] }}</a>

    @if ($mains->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200"><p class="font-bold text-slate-600">{{ __('مفيش تصنيفات لسه') }}</p></div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($mains as $m)
                @php $children = $subs[$m->id] ?? collect(); $sum = $m->total; @endphp
                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 p-5">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">📁 {{ \App\Support\Lang::pick($m, 'name') }}</h3>
                            <div class="mt-1 text-xs text-slate-500">{{ $m->uses }} {{ __('حركة ·') }} {{ Money::format($sum) }}</div>
                        </div>
                        <div class="flex gap-1">
                            <button type="button" data-crud-edit="catModal" data-values="{{ json_encode(['url' => route('admin.finance-categories.update', [$kind, $m->id]), 'name_ar' => $m->name_ar, 'name_en' => $m->name_en, 'parent_id' => null]) }}" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100">✏️</button>
                            @unless ($m->uses)
                                <form method="POST" action="{{ route('admin.finance-categories.destroy', [$kind, $m->id]) }}" onsubmit="{{ __('return confirm(\'مسح التصنيف وكل الفرعي اللي تحته؟\')') }}">@csrf @method('DELETE')<button class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50">🗑️</button></form>
                            @endunless
                        </div>
                    </div>
                    <div class="space-y-1 p-3">
                        @foreach ($children as $s)
                            <div class="group flex items-center justify-between rounded-xl px-3 py-2 hover:bg-slate-50">
                                <span class="text-sm font-semibold text-slate-700">↳ {{ \App\Support\Lang::pick($s, 'name') }} <span class="text-xs font-normal text-slate-400">({{ $s->uses }})</span></span>
                                <span class="flex gap-1 opacity-0 group-hover:opacity-100">
                                    <button type="button" data-crud-edit="catModal" data-values="{{ json_encode(['url' => route('admin.finance-categories.update', [$kind, $s->id]), 'name_ar' => $s->name_ar, 'name_en' => $s->name_en, 'parent_id' => $s->parent_id]) }}" class="text-xs font-bold text-blue-600">{{ __('تعديل') }}</button>
                                    @unless ($s->uses)
                                        <form method="POST" action="{{ route('admin.finance-categories.destroy', [$kind, $s->id]) }}" onsubmit="{{ __('return confirm(\'مسح؟\')') }}">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600">{{ __('مسح') }}</button></form>
                                    @endunless
                                </span>
                            </div>
                        @endforeach
                        <button type="button" data-crud-new="catModal" data-parent="{{ $m->id }}" class="w-full rounded-xl border border-dashed border-slate-200 px-3 py-2 text-xs font-bold text-slate-400 hover:border-blue-300 hover:text-blue-600">{{ __('+ قسم فرعي تحت') }} {{ \App\Support\Lang::pick($m, 'name') }}</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <dialog id="catModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.finance-categories.store', $kind) }}" data-title-new="{{ __('تصنيف جديد') }}" data-title-edit="{{ __('تعديل التصنيف') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.finance-categories.store', $kind) }}">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="cat"><input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('تصنيف جديد') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'parent_id', 'label' => __('تابع لـ'), 'type' => 'select', 'options' => $mains->pluckLocal('name', 'id'), 'empty' => __('— قسم رئيسي —')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'name_ar', 'label' => __('الاسم'), 'required' => true])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'c_', 'name' => 'name_en', 'label' => __('بالإنجليزي'), 'dir' => 'ltr'])
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
            </div>
        </form>
    </dialog>
    @include('admin.partials.crud-modal-script')
    <script>
        document.querySelectorAll('[data-parent]').forEach((b) => b.addEventListener('click', () => {
            const s = document.querySelector('#catModal [name=parent_id]'); s.value = b.dataset.parent; s.dispatchEvent(new Event('change'));
        }));
    </script>
@endsection
