@extends('admin.layouts.app')

@section('title', __('شركات التمويل'))
@section('subtitle', __('البنوك وشركات التقسيط اللي بتموّل العملاء'))

@php
    use App\Support\Money;
    $isAdmin = \App\Support\Access::can('finance.edit');
    $reopen = $errors->any() && old('_modal') === 'company' ? (old('_edit_url') ?: 'new') : '';
@endphp

@section('actions')
    @if ($isAdmin)
        <button type="button" data-crud-new="companyModal" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            {{ __('إضافة شركة') }}
        </button>
    @endif
    <a href="{{ route('admin.finance-requests.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('طلبات التمويل') }}</a>
@endsection

@section('content')
    <form method="GET" class="mb-5 flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('اسم الشركة أو التليفون...') }}" class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
    </form>

    @if ($companies->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
            <p class="font-bold text-slate-700">{{ __('مفيش شركات تمويل') }}</p>
            @if ($isAdmin) <button type="button" data-crud-new="companyModal" class="mt-3 text-sm font-semibold text-blue-600 hover:underline">{{ __('أضف أول شركة') }}</button> @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($companies as $c)
                @php $rate = $c->requests_count ? round($c->approved_count / $c->requests_count * 100) : null; @endphp
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 {{ $c->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-lg font-extrabold text-white">{{ mb_substr(\App\Support\Lang::pick($c, 'name'), 0, 1) }}</span>
                            <div class="min-w-0">
                                <h3 class="truncate font-extrabold text-slate-900">{{ \App\Support\Lang::pick($c, 'name') }}</h3>
                                @if ($c->name_en) <div class="truncate text-xs text-slate-400" dir="ltr" data-page-align>{{ $c->name_en }}</div> @endif
                            </div>
                        </div>
                        @if ($isAdmin)
                            <div class="flex gap-1">
                                <button type="button" data-crud-edit="companyModal" title="{{ __('تعديل') }}"
                                        data-values="{{ json_encode(['url' => route('admin.finance-companies.update', $c->id), 'name_ar' => $c->name_ar, 'name_en' => $c->name_en, 'phone' => $c->phone, 'email' => $c->email, 'website' => $c->website, 'address' => $c->address, 'terms_ar' => $c->terms_ar, 'is_active' => (bool) $c->is_active]) }}"
                                        class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-blue-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                                </button>
                                @unless ($c->requests_count)
                                    <form method="POST" action="{{ route('admin.finance-companies.destroy', $c->id) }}" onsubmit="{{ __('return confirm(\'مسح الشركة؟\')') }}">
                                        @csrf @method('DELETE')
                                        <button class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="{{ __('مسح') }}">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        @endif
                    </div>
                    <dl class="mt-4 space-y-1.5 text-sm">
                        @if ($c->phone) <div class="flex justify-between"><dt class="text-slate-500">{{ __('التليفون') }}</dt><dd><a href="tel:{{ $c->phone }}" class="font-semibold text-blue-600" dir="ltr">{{ $c->phone }}</a></dd></div> @endif
                        @if ($c->email) <div class="flex justify-between"><dt class="text-slate-500">{{ __('الإيميل') }}</dt><dd class="truncate font-semibold">{{ $c->email }}</dd></div> @endif
                        @if ($c->website) <div class="flex justify-between"><dt class="text-slate-500">{{ __('الموقع') }}</dt><dd><a href="{{ str_starts_with($c->website, 'http') ? $c->website : 'https://'.$c->website }}" target="_blank" rel="noopener" class="font-semibold text-blue-600">{{ __('فتح ↗') }}</a></dd></div> @endif
                    </dl>
                    <div class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 text-center">
                        <div><div class="text-lg font-extrabold text-slate-900">{{ $c->requests_count }}</div><div class="text-[11px] text-slate-400">{{ __('طلب') }}</div></div>
                        <div><div class="text-lg font-extrabold {{ $rate === null ? 'text-slate-300' : ($rate >= 60 ? 'text-emerald-600' : 'text-amber-600') }}">{{ $rate === null ? '—' : $rate.'%' }}</div><div class="text-[11px] text-slate-400">{{ __('نسبة الموافقة') }}</div></div>
                        <div><div class="truncate text-lg font-extrabold text-slate-900">{{ Money::format($c->approved_total, false) }}</div><div class="text-[11px] text-slate-400">{{ __('اتموّل') }}</div></div>
                    </div>
                    <a href="{{ route('admin.finance-requests.index', ['company' => $c->id]) }}" class="mt-4 block rounded-xl bg-slate-50 py-2 text-center text-xs font-bold text-slate-600 hover:bg-slate-100">{{ __('طلبات الشركة') }}</a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($isAdmin)
        <dialog id="companyModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.finance-companies.store') }}"
                data-title-new="{{ __('إضافة شركة تمويل') }}" data-title-edit="{{ __('تعديل الشركة') }}"
                class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
            <form method="POST" action="{{ route('admin.finance-companies.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="_modal" value="company">
                <input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('إضافة شركة تمويل') }}</h2>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'fc_', 'name' => 'name_ar', 'label' => __('الاسم'), 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'fc_', 'name' => 'name_en', 'label' => __('الاسم بالإنجليزي'), 'dir' => 'ltr'])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'fc_', 'name' => 'phone', 'label' => __('رقم الهاتف'), 'dir' => 'ltr'])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'fc_', 'name' => 'email', 'label' => __('الإيميل'), 'type' => 'email', 'dir' => 'ltr'])
                    <div class="sm:col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'fc_', 'name' => 'website', 'label' => __('الموقع الإلكتروني'), 'dir' => 'ltr'])</div>
                    <div class="sm:col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'fc_', 'name' => 'address', 'label' => __('العنوان'), 'type' => 'textarea', 'rows' => 2])</div>
                    <div class="sm:col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'fc_', 'name' => 'terms_ar', 'label' => __('شروط التمويل (مقدم أدنى، مستندات...)'), 'type' => 'textarea', 'rows' => 2])</div>
                    <div class="sm:col-span-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                            <input type="checkbox" name="is_active" value="1" data-default="1" class="accent-blue-600" @checked(old('is_active', '1') === '1')>
                            <span class="text-sm font-bold text-slate-800">{{ __('شغالين معاها') }}</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
                </div>
            </form>
        </dialog>
        @include('admin.partials.crud-modal-script')
    @endif
@endsection
