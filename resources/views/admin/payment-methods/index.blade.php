@extends('admin.layouts.app')

@section('title', __('طرق الدفع'))
@section('subtitle', __('كاش، تحويل، شيك... ورصيد كل طريقة لحظة بلحظة'))

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $reopen = $errors->any() && old('_modal') === 'method' ? (old('_edit_url') ?: 'new') : '';
    $grand = $methods->sum('balance');
@endphp

@section('actions')
    <button type="button" data-crud-new="methodModal" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('إضافة طريقة') }}
    </button>
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-slate-900 p-6 text-white shadow-lg">
        <div>
            <div class="text-sm text-slate-400">{{ __('صافي الفلوس في كل الطرق') }}</div>
            <div class="mt-1 text-3xl font-extrabold">{{ Money::format($grand) }}</div>
        </div>
        <div class="flex gap-6 text-sm">
            <div><div class="text-slate-400">{{ __('دخل من العملاء') }}</div><div class="text-lg font-bold text-emerald-300">{{ Money::format($methods->sum('total_in'), false) }}</div></div>
            <div><div class="text-slate-400">{{ __('طلع (ملاك وموردين)') }}</div><div class="text-lg font-bold text-rose-300">{{ Money::format($methods->sum('total_out'), false) }}</div></div>
        </div>
    </div>

    @if ($methods->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
            <p class="font-bold text-slate-700">{{ __('مفيش طرق دفع') }}</p>
            <button type="button" data-crud-new="methodModal" class="mt-3 text-sm font-semibold text-blue-600 hover:underline">{{ __('أضف أول طريقة') }}</button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($methods as $m)
                @php $share = $grand > 0 ? max(0, min(100, round($m->balance / $grand * 100))) : 0; @endphp
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 {{ $m->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-extrabold text-slate-900">{{ \App\Support\Lang::pick($m, 'name') }}</h3>
                                <span class="h-2 w-2 rounded-full {{ $m->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}" title="{{ $m->is_active ? __('مفعّلة') : __('مقفولة') }}"></span>
                            </div>
                            @if ($m->name_en) <div class="text-xs text-slate-400" dir="ltr" data-page-align>{{ $m->name_en }}</div> @endif
                        </div>
                        <div class="flex gap-1">
                            <button type="button" data-crud-edit="methodModal" title="{{ __('تعديل') }}"
                                    data-values="{{ json_encode(['url' => route('admin.payment-methods.update', $m), 'name_ar' => $m->name_ar, 'name_en' => $m->name_en, 'description_ar' => $m->description_ar, 'sort_order' => $m->sort_order, 'is_active' => (bool) $m->is_active]) }}"
                                    class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-blue-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                            </button>
                            @unless ($m->payments_count)
                                <form method="POST" action="{{ route('admin.payment-methods.destroy', $m) }}" onsubmit="{{ __('return confirm(\'مسح طريقة الدفع؟\')') }}">
                                    @csrf @method('DELETE')
                                    <button class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="{{ __('مسح') }}">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-slate-500">{{ __('الرصيد الحالي') }}</div>
                    <div class="text-2xl font-extrabold {{ $m->balance < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ Money::format($m->balance) }}</div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $share }}%"></div></div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-emerald-700">{{ __('دخل') }} <b>{{ Money::format($m->total_in, false) }}</b></div>
                        <div class="rounded-lg bg-rose-50 px-2.5 py-1.5 text-rose-700">{{ __('طلع') }} <b>{{ Money::format($m->total_out, false) }}</b></div>
                    </div>
                    <div class="mt-3 flex justify-between text-[11px] text-slate-400">
                        <span>{{ $m->payments_count }} {{ __('عملية') }}</span>
                        <span>{{ $m->last_date ? __('آخر حركة ').Carbon::parse($m->last_date)->format('Y-m-d') : __('مفيش حركة') }}</span>
                    </div>
                    <a href="{{ route('admin.sale-payments.index', ['method' => $m->id]) }}" class="mt-3 block rounded-xl bg-slate-50 py-2 text-center text-xs font-bold text-slate-600 hover:bg-slate-100">{{ __('مدفوعات العملاء بالطريقة دي') }}</a>
                </div>
            @endforeach
        </div>
    @endif

    <dialog id="methodModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.payment-methods.store') }}"
            data-title-new="{{ __('إضافة طريقة دفع') }}" data-title-edit="{{ __('تعديل طريقة الدفع') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.payment-methods.store') }}">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="method">
            <input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('إضافة طريقة دفع') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'pm_', 'name' => 'name_ar', 'label' => __('الاسم'), 'required' => true, 'placeholder' => __('مثلاً: بنك مسقط، فيزا، كاش')])
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'pm_', 'name' => 'name_en', 'label' => __('بالإنجليزي'), 'dir' => 'ltr'])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'pm_', 'name' => 'sort_order', 'label' => __('الترتيب'), 'type' => 'number', 'min' => 0])
                </div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'pm_', 'name' => 'description_ar', 'label' => __('ملاحظات (رقم الحساب مثلاً)'), 'type' => 'textarea', 'rows' => 2])
                <input type="hidden" name="is_active" value="0">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                    <input type="checkbox" name="is_active" value="1" data-default="1" class="accent-blue-600" @checked(old('is_active', '1') === '1')>
                    <span class="text-sm font-bold text-slate-800">{{ __('مفعّلة (بتظهر وقت الدفع)') }}</span>
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
