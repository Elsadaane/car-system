@extends('admin.layouts.app')

@section('title', __('طلبات الإلغاء'))
@section('subtitle', __('عقود بيع العملاء عايزين يلغوها أو اتلغت'))

@php
    use App\Support\Money;
    use App\Http\Controllers\Admin\CancellationController as CC;
    use Illuminate\Support\Carbon;
    $isAdmin = \App\Support\Access::can('cancellations.approve');
    $style = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-slate-800 text-white', 'rejected' => 'bg-emerald-50 text-emerald-700'];
@endphp

@section('content')
    <form method="GET" class="mb-5 space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="flex gap-3">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('بحث بالعميل أو رقم الهاتف أو رقم العقد...') }}" class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
            @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.cancellations.index', array_filter(['q' => $q])) }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ ! $status ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ __('الكل') }}</a>
            @foreach (CC::STATUSES as $k => $l)
                <a href="{{ route('admin.cancellations.index', array_filter(['q' => $q, 'status' => $k])) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-bold {{ $status === $k ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $l }} ({{ (int) ($counts[$k] ?? 0) }})</a>
            @endforeach
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-600">{{ __('مفيش طلبات إلغاء') }}</p>
                <p class="mt-1 text-sm text-slate-400">{{ __('طلب الإلغاء بيتعمل من صفحة عقد البيع ← الإجراءات.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العميل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العقد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('تاريخ الطلب') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('السبب') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('يرجع للعميل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $r)
                            <tr class="{{ $r->status === 'pending' ? 'bg-amber-50/40' : '' }} hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <div class="font-bold text-slate-800">{{ $r->customer_name }}</div>
                                    <a href="tel:{{ $r->customer_phone }}" class="text-xs text-blue-600" dir="ltr">{{ $r->customer_phone }}</a>
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.sales-contracts.show', $r->sales_contract_id) }}" class="font-mono text-xs font-bold text-blue-700 hover:underline">{{ $r->contract_number }}</a>
                                    <div class="text-xs text-slate-400">{{ $r->brand_name }} {{ $r->model_name }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs">
                                    <div class="font-semibold text-slate-700">{{ Carbon::parse($r->cancellation_date)->format('Y-m-d') }}</div>
                                    <div class="text-slate-400">{{ $r->user_name }}</div>
                                </td>
                                <td class="max-w-[220px] px-3 py-3 text-xs text-slate-600">{{ \App\Support\Lang::pick($r, 'reason') }}@if ($r->additional_notes) <div class="text-slate-400">{{ $r->additional_notes }}</div> @endif</td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <div class="font-bold text-slate-900">{{ Money::format($r->net_refund, false) }}</div>
                                    @if ((float) $r->penalty_amount > 0) <div class="text-[11px] text-rose-500">{{ __('غرامة') }} {{ Money::format($r->penalty_amount, false) }}</div> @endif
                                </td>
                                <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $style[$r->status] ?? '' }}">{{ CC::STATUSES[$r->status] ?? $r->status }}</span></td>
                                <td class="whitespace-nowrap px-5 py-3 text-end">
                                    @if ($isAdmin && $r->status === 'pending')
                                        <form method="POST" action="{{ route('admin.sales-contracts.cancellations.decide', [$r->sales_contract_id, $r->id, 'approve']) }}" class="inline" onsubmit="{{ __('return confirm(\'موافقة على الإلغاء؟ العربية هترجع متاحة.\')') }}">
                                            @csrf @method('PATCH')
                                            <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-700">{{ __('موافقة') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.sales-contracts.cancellations.decide', [$r->sales_contract_id, $r->id, 'reject']) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <button class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100">{{ __('رفض') }}</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.sales-contracts.show', $r->sales_contract_id) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">{{ __('فتح العقد') }}</a>
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
