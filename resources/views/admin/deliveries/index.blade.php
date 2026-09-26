@extends('admin.layouts.app')

@section('title', __('عقود التسليم'))
@section('subtitle', __('العربيات اللي اتسلّمت لأصحابها'))

@php use Illuminate\Support\Carbon; $isAdmin = \App\Support\Access::can('deliveries.delete'); @endphp

@section('actions')
    <a href="{{ route('admin.sales-contracts.index', ['flag' => 'not_delivered']) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">
        {{ __('مستنية تسليم') }} <span class="ms-1 rounded-full bg-amber-100 px-2 text-xs font-bold text-amber-700">{{ $waiting }}</span>
    </a>
@endsection

@section('content')
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('اتسلّم الشهر ده') }}</div>
            <div class="mt-1 text-2xl font-extrabold text-emerald-600">{{ $month }}</div>
        </div>
        <a href="{{ route('admin.sales-contracts.index', ['flag' => 'not_delivered']) }}" class="rounded-2xl p-5 shadow-sm ring-1 transition hover:-translate-y-0.5 {{ $waiting ? 'bg-amber-50 ring-amber-200' : 'bg-white ring-slate-200' }}">
            <div class="text-sm {{ $waiting ? 'text-amber-700' : 'text-slate-500' }}">{{ __('عربيات اتباعت ولسه ماتسلّمتش') }}</div>
            <div class="mt-1 text-2xl font-extrabold {{ $waiting ? 'text-amber-800' : 'text-slate-900' }}">{{ $waiting }}</div>
        </a>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('العميل، التليفون، رقم العقد، رقم التسليم...') }}" class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <input type="date" name="date" value="{{ $date }}" title="{{ __('تاريخ التسليم') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        @if ($q || $date) <a href="{{ route('admin.deliveries.index') }}" class="self-center text-sm text-slate-500 hover:text-slate-900">{{ __('مسح') }}</a> @endif
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-600">{{ __('مفيش عقود تسليم') }}</p>
                <p class="mt-1 text-sm text-slate-400">{{ __('التسليم بيتسجل من صفحة عقد البيع ← الإجراءات ← "تسليم العربية".') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العميل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العربية / العقد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('وقت التسليم') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('استلمها / سلّمها') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $d)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.customers.show', $d->customer_id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $d->customer_name }}</a>
                                    <a href="tel:{{ $d->customer_phone }}" class="block text-xs text-blue-600" dir="ltr" data-page-align>{{ $d->customer_phone }}</a>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $d->brand_name }} {{ $d->model_name }}</div>
                                    <a href="{{ route('admin.sales-contracts.show', $d->sales_contract_id) }}" class="font-mono text-xs text-blue-600 hover:underline">{{ $d->contract_number }}</a>
                                    <span class="font-mono text-[11px] text-slate-400">· {{ $d->delivery_number }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $d->actual_delivery ? Carbon::parse($d->actual_delivery)->format('Y-m-d') : '—' }}</div>
                                    <div class="text-xs text-slate-400">{{ $d->actual_delivery ? Carbon::parse($d->actual_delivery)->format('H:i') : '' }} {{ $d->delivery_location ? '· '.$d->delivery_location : '' }}</div>
                                </td>
                                <td class="px-3 py-3 text-xs">
                                    <div class="font-semibold text-slate-700">{{ $d->received_by }}</div>
                                    <div class="text-slate-400">{{ __('سلّمها') }} {{ $d->delivered_by ?? $d->user_name }}</div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-end">
                                    <a href="{{ route('admin.deliveries.print', $d->id) }}" target="_blank" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200">{{ __('طباعة') }}</a>
                                    <a href="{{ route('admin.sales-contracts.show', $d->sales_contract_id) }}#signatures" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">{{ __('التوقيعات') }}</a>
                                    @if ($isAdmin)
                                        <form method="POST" action="{{ route('admin.deliveries.destroy', $d->id) }}" class="inline" onsubmit="{{ __('return confirm(\'مسح عقد التسليم؟\')') }}">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('حذف') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages()) <div class="border-t border-slate-100 p-4">{{ $rows->links() }}</div> @endif
        @endif
    </div>
@endsection
