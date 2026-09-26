@extends('admin.layouts.app')

@php use App\Support\Money; @endphp

@section('title', __('الشركاء'))
@section('subtitle', __('شركاء داخلين برأس مال في العربيات ولهم نسبة من ربحها'))

@section('actions')
    @if (\App\Support\Access::can('partners.create'))
        <button type="button" onclick="document.getElementById('partnerModal').showModal()" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">＋ {{ __('شريك جديد') }}</button>
    @endif
@endsection

@section('content')
    <div class="mb-6 grid grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-xs font-bold text-slate-500">{{ __('رأس مال في عربيات لسه متباعتش') }}</div><div class="mt-1 text-2xl font-black text-slate-900">{{ Money::format($totals['active'], false) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-xs font-bold text-slate-500">{{ __('نصيب الشركاء من الأرباح') }}</div><div class="mt-1 text-2xl font-black text-emerald-600">{{ Money::format($totals['profit'], false) }}</div></div>
        <div class="rounded-2xl p-5 shadow-sm ring-1 {{ $totals['balance'] > 0 ? 'bg-amber-50 ring-amber-200' : 'bg-white ring-slate-200' }}"><div class="text-xs font-bold text-amber-700">{{ __('مستحق للشركاء (لسه مادفعناهوش)') }}</div><div class="mt-1 text-2xl font-black text-amber-700">{{ Money::format($totals['balance'], false) }}</div></div>
    </div>

    <form class="mb-4"><input type="search" name="q" value="{{ $q }}" placeholder="{{ __('بحث بالاسم أو التليفون') }}" class="w-72 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"></form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($partners->isEmpty())
            <div class="px-6 py-16 text-center text-sm text-slate-400">{{ __('مفيش شركاء لسه. ضيف شريك، وبعدين من صفحة أي عربية حدد نصيبه فيها.') }}</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('الشريك') }}</th><th class="px-3 py-3 text-start">{{ __('عربيات') }}</th><th class="px-3 py-3 text-start">{{ __('رأس المال') }}</th><th class="px-3 py-3 text-start">{{ __('نصيبه من الربح') }}</th><th class="px-3 py-3 text-start">{{ __('اتدفعله') }}</th><th class="px-3 py-3 text-start">{{ __('الباقي له') }}</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($partners as $p)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-5 py-3"><a href="{{ route('admin.partners.show', $p->id) }}" class="font-bold text-slate-900 hover:text-blue-700">{{ $p->name }}</a><div class="text-xs text-slate-400" dir="ltr" data-page-align>{{ $p->phone }}</div></td>
                            <td class="px-3 py-3">{{ $p->cars }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ Money::format($p->st['invested'], false) }}@if ($p->st['active'] > 0)<div class="text-[11px] text-slate-400">{{ Money::format($p->st['active'], false) }} {{ __('لسه في المخزون') }}</div>@endif</td>
                            <td class="whitespace-nowrap px-3 py-3 font-semibold {{ $p->st['profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ Money::format($p->st['profit'], false) }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ Money::format($p->st['paid'], false) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 font-extrabold {{ $p->st['balance'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ Money::format($p->st['balance'], false) }}</td>
                            <td class="px-5 py-3 text-end"><a href="{{ route('admin.partners.show', $p->id) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ __('كشف الحساب') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <dialog id="partnerModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
        <form method="POST" action="{{ route('admin.partners.store') }}" class="space-y-4 p-6">
            @csrf
            <h2 class="text-lg font-extrabold text-slate-900">{{ __('شريك جديد') }}</h2>
            @include('admin.partials.field', ['model' => null, 'name' => 'name', 'label' => __('الاسم'), 'required' => true])
            <div class="grid gap-3 sm:grid-cols-2">
                @include('admin.partials.field', ['model' => null, 'name' => 'phone', 'label' => __('التليفون'), 'dir' => 'ltr'])
                @include('admin.partials.field', ['model' => null, 'name' => 'national_id', 'label' => __('الرقم المدني')])
            </div>
            @include('admin.partials.field', ['model' => null, 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])
            <div class="flex justify-end gap-2">
                <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button>
            </div>
        </form>
    </dialog>
    @if ($errors->any()) <script>document.getElementById('partnerModal').showModal();</script> @endif
@endsection
