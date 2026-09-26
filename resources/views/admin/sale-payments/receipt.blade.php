@extends('admin.print.layout')

@php use App\Support\Money; use Illuminate\Support\Carbon; @endphp

@section('title', __('إيصال ').$payment->payment_reference)
@section('heading', __('إيصال استلام نقدية'))
@section('subheading', __('رقم الإيصال: ').$payment->payment_reference.__(' — التاريخ: ').Carbon::parse($payment->payment_date)->format('Y-m-d'))

@section('content')
    <div class="rounded-2xl border-2 border-slate-900 p-6 text-base leading-loose">
        {{ __('استلمنا من السيد/ة') }} <b>{{ $payment->customer_name }}</b>
        @if ($payment->customer_national_id) {{ __('(رقم مدني') }} {{ $payment->customer_national_id }}) @endif
        {{ __('مبلغ وقدره') }} <b class="rounded bg-slate-100 px-2">{{ Money::format($payment->amount) }}</b>
        {{ __('بطريقة') }} <b>{{ $payment->method_name }}</b>
        @if ($payment->bank_reference) {{ __('(مرجع:') }} <span dir="ltr">{{ $payment->bank_reference }}</span>) @endif
        <br>
        {{ __('وذلك عن') }}
        @if ((int) $payment->id === (int) $payment->down_payment_id)
            <b>{{ __('مقدم') }}</b>
        @elseif ($covers->isNotEmpty())
            <b>{{ __('القسط رقم') }} {{ $covers->pluck('installment_sequence')->implode(__('، ')) }}</b> {{ __('من') }}
        @else
            {{ __('دفعة من') }}
        @endif
        {{ __('عقد بيع المركبة رقم') }} <b class="font-mono">{{ $payment->contract_number }}</b>
        — {{ $payment->brand_name }} {{ $payment->model_name }} (<span class="font-mono">{{ $payment->vehicle_code }}</span>).
        @if ($payment->notes) <div class="mt-2 text-sm text-slate-600">{{ __('ملاحظات:') }} {{ $payment->notes }}</div> @endif
    </div>

    @if ($covers->isNotEmpty())
        <table class="mt-6 w-full border-collapse text-sm">
            <thead><tr class="bg-slate-50"><th class="border border-slate-300 px-3 py-2">{{ __('القسط') }}</th><th class="border border-slate-300 px-3 py-2">{{ __('الاستحقاق') }}</th><th class="border border-slate-300 px-3 py-2">{{ __('قيمة القسط') }}</th><th class="border border-slate-300 px-3 py-2">{{ __('المدفوع من الإيصال ده') }}</th></tr></thead>
            <tbody>
                @foreach ($covers as $c)
                    <tr>
                        <td class="border border-slate-300 px-3 py-2 text-center">{{ $c->installment_sequence }} / {{ $c->total_installments }}</td>
                        <td class="border border-slate-300 px-3 py-2">{{ Carbon::parse($c->due_date)->format('Y-m-d') }}</td>
                        <td class="border border-slate-300 px-3 py-2">{{ Money::format($c->installment_amount, false) }}</td>
                        <td class="border border-slate-300 px-3 py-2 font-bold">{{ Money::format($c->amount, false) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="mt-6 w-full border-collapse text-sm">
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('إجمالي العقد') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($payment->total_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('إجمالي المدفوع حتى الآن') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($payment->paid_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-100 px-3 py-2 font-extrabold">{{ __('المتبقي') }}</td><td class="border border-slate-300 px-3 py-2 font-extrabold">{{ Money::format($payment->remaining_amount) }}</td></tr>
    </table>

    <div class="mt-14 grid grid-cols-2 gap-10 text-center">
        <div><div class="h-16"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('المستلم (المعرض):') }} {{ $payment->user_name }}</div></div>
        <div><div class="h-16"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('الدافع:') }} {{ $payment->customer_name }}</div></div>
    </div>
    @if ($note = \App\Support\Company::get('receipt_notes'))
        <p class="mt-8 rounded-xl bg-slate-50 p-3 text-center text-xs text-slate-500">{{ $note }}</p>
    @endif
@endsection
