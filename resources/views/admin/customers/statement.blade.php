@extends('admin.print.layout')

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $bal = fn ($v) => $v > 0 ? Money::format($v, false).__(' عليه') : ($v < 0 ? Money::format(abs($v), false).__(' له') : __('صفر'));
@endphp

@section('title', __('كشف حساب ').$customer->name_ar)
@section('heading', __('كشف حساب عميل'))
@section('subheading', ($from ? __('من ').$from.' ' : __('من البداية ')).__('لحد ').$to)
@section('no_seal', true)

@section('content')
    <form class="no-print mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-slate-100 p-3 text-sm">
        <label class="grid gap-1"><span class="text-xs font-bold text-slate-500">{{ __('من') }}</span><input type="date" name="from" value="{{ $from }}" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5"></label>
        <label class="grid gap-1"><span class="text-xs font-bold text-slate-500">{{ __('لحد') }}</span><input type="date" name="to" value="{{ $to }}" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5"></label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 font-bold text-white">{{ __('عرض') }}</button>
        @if ($from)<a href="{{ route('admin.customers.statement', $customer) }}" class="px-2 py-2 font-bold text-slate-500">{{ __('كل الفترات') }}</a>@endif
        <a href="{{ route('admin.customers.show', $customer) }}" class="ms-auto px-2 py-2 font-bold text-blue-600">{{ __('ملف العميل ←') }}</a>
    </form>

    <div class="mb-5 grid grid-cols-2 gap-4 rounded-xl border border-slate-300 p-4">
        <div class="space-y-1">
            <div><span class="text-slate-500">{{ __('العميل:') }}</span> <b>{{ \App\Support\Lang::pick($customer, 'name') }}</b></div>
            <div><span class="text-slate-500">{{ __('الكود:') }}</span> <span class="font-mono">{{ $customer->customer_code }}</span></div>
            @if ($customer->national_id)<div><span class="text-slate-500">{{ __('الرقم المدني:') }}</span> {{ $customer->national_id }}</div>@endif
            @if ($customer->phone)<div><span class="text-slate-500">{{ __('الموبايل:') }}</span> <span dir="ltr">{{ $customer->phone }}</span></div>@endif
        </div>
        <div class="space-y-1 text-end">
            <div class="text-xs text-slate-500">{{ __('الرصيد في') }} {{ $to }}</div>
            <div class="text-2xl font-black {{ $closing > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ Money::format(abs($closing)) }}</div>
            <div class="text-xs font-bold {{ $closing > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $closing > 0 ? __('مستحق على العميل') : ($closing < 0 ? __('مستحق للعميل') : __('الحساب مقفول ✓')) }}</div>
        </div>
    </div>

    <table class="w-full border-collapse text-[13px]">
        <thead>
            <tr class="bg-slate-900 text-white">
                <th class="border border-slate-900 p-2 text-start">{{ __('التاريخ') }}</th>
                <th class="border border-slate-900 p-2 text-start">{{ __('المرجع') }}</th>
                <th class="border border-slate-900 p-2 text-start">{{ __('البيان') }}</th>
                <th class="border border-slate-900 p-2 text-center">{{ __('مدين (عليه)') }}</th>
                <th class="border border-slate-900 p-2 text-center">{{ __('دائن (دفع)') }}</th>
                <th class="border border-slate-900 p-2 text-center">{{ __('الرصيد') }}</th>
            </tr>
        </thead>
        <tbody>
            @if ($from)
                <tr class="bg-slate-50 font-bold">
                    <td class="border border-slate-300 p-2">{{ $from }}</td><td class="border border-slate-300 p-2"></td>
                    <td class="border border-slate-300 p-2">{{ __('رصيد أول المدة') }}</td><td class="border border-slate-300 p-2"></td><td class="border border-slate-300 p-2"></td>
                    <td class="whitespace-nowrap border border-slate-300 p-2 text-center">{{ $bal($opening) }}</td>
                </tr>
            @endif
            @forelse ($rows as $r)
                <tr class="break-inside-avoid">
                    <td class="whitespace-nowrap border border-slate-300 p-2">{{ $r['date'] }}</td>
                    <td class="border border-slate-300 p-2 font-mono text-[11px]">{{ $r['ref'] }}</td>
                    <td class="border border-slate-300 p-2">{{ $r['text'] }}</td>
                    <td class="whitespace-nowrap border border-slate-300 p-2 text-center">{{ $r['debit'] ? Money::format($r['debit'], false) : '' }}</td>
                    <td class="whitespace-nowrap border border-slate-300 p-2 text-center">{{ $r['credit'] ? Money::format($r['credit'], false) : '' }}</td>
                    <td class="whitespace-nowrap border border-slate-300 p-2 text-center font-bold">{{ $bal($r['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="border border-slate-300 p-6 text-center text-slate-400">{{ __('مفيش حركات في الفترة دي') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-slate-100 font-extrabold">
                <td colspan="3" class="border border-slate-300 p-2">{{ __('الإجمالي') }}</td>
                <td class="whitespace-nowrap border border-slate-300 p-2 text-center">{{ Money::format($totals['debit'], false) }}</td>
                <td class="whitespace-nowrap border border-slate-300 p-2 text-center">{{ Money::format($totals['credit'], false) }}</td>
                <td class="whitespace-nowrap border border-slate-300 p-2 text-center">{{ $bal($closing) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($overdue->n || $next)
        <div class="mt-5 grid grid-cols-2 gap-4 text-sm">
            @if ($overdue->n)
                <div class="rounded-xl border-2 border-rose-300 p-3"><div class="text-xs text-rose-700">{{ __('أقساط متأخرة') }}</div><div class="font-extrabold text-rose-700">{{ $overdue->n }} {{ __('قسط بإجمالي') }} {{ Money::format($overdue->total) }}</div></div>
            @endif
            @if ($next)
                <div class="rounded-xl border border-slate-300 p-3"><div class="text-xs text-slate-500">{{ __('القسط الجاي') }}</div><div class="font-extrabold">{{ Money::format((float) $next->amount - (float) $next->paid_amount) }} {{ __('— يوم') }} {{ Carbon::parse($next->due_date)->format('Y-m-d') }}</div></div>
            @endif
        </div>
    @endif

    <div class="mt-12 grid grid-cols-2 gap-10 text-center text-sm">
        <div><div class="mb-12 font-bold">{{ __('توقيع العميل بالاطلاع') }}</div><div class="border-t border-slate-400 pt-1 text-xs text-slate-400">{{ __('الاسم والتوقيع') }}</div></div>
        <div><div class="mb-12 font-bold">{{ __('المحاسب') }}</div><div class="border-t border-slate-400 pt-1 text-xs text-slate-400">{{ __('الاسم والتوقيع') }}</div></div>
    </div>
@endsection
