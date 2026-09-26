@extends('admin.print.layout')

@php use App\Support\Money; @endphp

@section('title', __('إيصال ').$payment->payment_reference)
@section('heading', __('إيصال صرف'))
@section('subheading', __('رقم الإيصال: ').$payment->payment_reference.__(' — التاريخ: ').\Illuminate\Support\Carbon::parse($payment->payment_date)->format('Y-m-d'))

@section('content')
    <div class="rounded-2xl border-2 border-slate-900 p-6 text-base leading-loose">
        {{ __('صرفنا للسيد/ة') }} <b>{{ $payment->supplier_name }}</b>
        {{ __('مبلغ وقدره') }} <b class="rounded bg-slate-100 px-2">{{ Money::format($payment->amount) }}</b>
        {{ __('بطريقة') }} <b>{{ $payment->method_name }}</b>
        @if ($payment->bank_reference) {{ __('(مرجع:') }} <span dir="ltr">{{ $payment->bank_reference }}</span>) @endif
        <br>
        {{ __('وذلك عن عقد تفويض بيع المركبة رقم') }} <b class="font-mono">{{ $payment->contract_number }}</b>
        — {{ $payment->brand_name }} {{ $payment->model_name }} (<span class="font-mono">{{ $payment->vehicle_code }}</span>).
        @if ($payment->notes) <div class="mt-2 text-sm text-slate-600">{{ __('ملاحظات:') }} {{ $payment->notes }}</div> @endif
    </div>

    <table class="mt-6 w-full border-collapse text-sm">
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('إجمالي المستحق بالعقد') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($payment->total_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('إجمالي المدفوع حتى الآن') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($payment->paid_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-100 px-3 py-2 font-extrabold">{{ __('المتبقي') }}</td><td class="border border-slate-300 px-3 py-2 font-extrabold">{{ Money::format($payment->remaining_amount) }}</td></tr>
    </table>

    <div class="mt-14 grid grid-cols-2 gap-10 text-center">
        <div><div class="h-16"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('المستلم:') }} {{ $payment->supplier_name }}</div></div>
        <div><div class="h-16"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('المحاسب:') }} {{ $payment->user_name }}</div></div>
    </div>
    @if ($note = \App\Support\Company::get('receipt_notes'))
        <p class="mt-8 rounded-xl bg-slate-50 p-3 text-center text-xs text-slate-500">{{ $note }}</p>
    @endif
@endsection
