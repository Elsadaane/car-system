@extends('admin.print.layout')

@php use App\Support\Money; use Illuminate\Support\Carbon; $isExp = $e->kind === 'expense'; @endphp

@section('title', $L['voucher'].' '.$e->code)
@section('heading', $L['voucher'])
@section('subheading', __('رقم السند: ').$e->code.__(' — التاريخ: ').Carbon::parse($e->entry_date)->format('Y-m-d'))

@section('content')
    <div class="rounded-2xl border-2 border-slate-900 p-6 text-base leading-loose">
        @if ($isExp)
            {{ __('صرفنا للسيد/ة / الجهة:') }} <b>{{ $e->supplier_name ?? '........................' }}</b>
        @else
            {{ __('استلمنا من السيد/ة / الجهة:') }} <b>{{ $e->customer_name ?? '........................' }}</b>
        @endif
        {{ __('مبلغ وقدره') }} <b class="rounded bg-slate-100 px-2">{{ Money::format($e->total_amount) }}</b>
        {{ $isExp ? __('من') : __('في') }} <b>{{ $e->account_name }}</b>
        @if ($e->check_number) {{ __('بشيك رقم') }} <b dir="ltr">{{ $e->check_number }}</b> @endif
        <br>{{ __('وذلك عن:') }} <b>{{ $e->details }}</b>
        @if ($e->vehicle_code) {{ __('— على المركبة') }} <b class="font-mono">{{ $e->vehicle_code }}</b> @endif
    </div>

    <table class="mt-6 w-full border-collapse text-sm">
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('التصنيف') }}</td><td class="border border-slate-300 px-3 py-2">{{ $e->category_name }}{{ $e->sub_category_name ? ' / '.$e->sub_category_name : '' }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('الفرع') }}</td><td class="border border-slate-300 px-3 py-2">{{ $e->branch_name ?? __('عام') }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('المبلغ الأساسي') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($e->amount) }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('الضريبة (') }}{{ (float) $e->tax_percent }}%)</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($e->tax_amount) }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-100 px-3 py-2 font-extrabold">{{ __('الإجمالي') }}</td><td class="border border-slate-300 px-3 py-2 font-extrabold">{{ Money::format($e->total_amount) }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('رقم الفاتورة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $e->invoice_no ?: '—' }}</td>
        </tr>
    </table>
    @if ($e->notes) <p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm">{{ __('ملاحظات:') }} {{ $e->notes }}</p> @endif

    <div class="mt-14 grid grid-cols-3 gap-8 text-center">
        <div><div class="h-14"></div><div class="border-t border-dashed border-slate-400 pt-2 font-bold">{{ $isExp ? __('توقيع المستلم') : __('توقيع الدافع') }}</div></div>
        <div><div class="h-14"></div><div class="border-t border-dashed border-slate-400 pt-2 font-bold">{{ __('المحاسب:') }} {{ $e->user_name }}</div></div>
        <div><div class="h-14"></div><div class="border-t border-dashed border-slate-400 pt-2 font-bold">{{ __('اعتماد الإدارة') }}</div></div>
    </div>
    @if ($note = \App\Support\Company::get('receipt_notes')) <p class="mt-8 text-center text-xs text-slate-500">{{ $note }}</p> @endif
@endsection
