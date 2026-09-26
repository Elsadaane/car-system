@extends('admin.print.layout')

@php
    use App\Support\Money;
    use App\Http\Controllers\Admin\Hr\PayrollController as PC;
    $plus = collect($lines)->where('amount', '>', 0);
    $minus = collect($lines)->where('amount', '<', 0);
    $manual = [];
    if ((float) $p->other_additions) $plus->push(['label' => __('إضافة'), 'note' => $p->notes, 'amount' => (float) $p->other_additions]);
    if ((float) $p->other_deductions) $minus->push(['label' => __('خصم'), 'note' => $p->notes, 'amount' => -(float) $p->other_deductions]);
@endphp

@section('title', __('قسيمة مرتب ').$p->full_name_ar)
@section('heading', __('قسيمة مرتب'))
@section('subheading', __('شهر ').PC::MONTHS[$p->month].' '.$p->year)
@section('no_seal', '1')

@section('content')
    <div class="grid grid-cols-2 gap-4 rounded-xl border border-slate-300 p-4 text-sm">
        <div>{{ __('الموظف:') }} <b>{{ \App\Support\Lang::pick($p, 'full_name') }}</b></div><div>{{ __('الكود:') }} <b class="font-mono">{{ $p->employee_code }}</b></div>
        <div>{{ __('الوظيفة:') }} <b>{{ $p->job_title }}</b></div><div>{{ __('القسم / الفرع:') }} <b>{{ $p->department_name ?? '—' }} / {{ $p->branch_name ?? '—' }}</b></div>
        <div>{{ $p->pay_basis === 'daily' ? __('أيام الحضور') : __('أيام العمل') }}: <b>{{ (float) $p->days_worked }}</b>@if ((float) $p->absent_days) {{ __('— غياب') }} <b>{{ (float) $p->absent_days }}</b> @endif</div>
        <div>{{ __('القبض:') }} <b>{{ $p->payment_method === 'bank' ? __('تحويل ').($p->bank_name ?? '') : __('نقداً') }}</b></div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-6">
        <div>
            <div class="mb-2 rounded-lg bg-emerald-50 px-3 py-2 font-extrabold text-emerald-800">{{ __('المستحقات') }}</div>
            <table class="w-full text-sm">
                @foreach ($plus as $l) <tr class="border-b border-slate-100"><td class="py-1.5">{{ $l['label'] }} @if ($l['note']) <span class="text-[11px] text-slate-400">({{ $l['note'] }})</span> @endif</td><td class="py-1.5 text-end font-bold">{{ Money::format($l['amount'], false) }}</td></tr> @endforeach
                <tr><td class="pt-2 font-extrabold">{{ __('الإجمالي') }}</td><td class="pt-2 text-end font-extrabold">{{ Money::format($p->gross, false) }}</td></tr>
            </table>
        </div>
        <div>
            <div class="mb-2 rounded-lg bg-rose-50 px-3 py-2 font-extrabold text-rose-800">{{ __('الاستقطاعات') }}</div>
            <table class="w-full text-sm">
                @forelse ($minus as $l) <tr class="border-b border-slate-100"><td class="py-1.5">{{ $l['label'] }} @if ($l['note']) <span class="text-[11px] text-slate-400">({{ $l['note'] }})</span> @endif</td><td class="py-1.5 text-end font-bold">{{ Money::format(abs($l['amount']), false) }}</td></tr>
                @empty <tr><td class="py-1.5 text-slate-400">{{ __('لا يوجد') }}</td></tr> @endforelse
                <tr><td class="pt-2 font-extrabold">{{ __('الإجمالي') }}</td><td class="pt-2 text-end font-extrabold">{{ Money::format($p->total_deductions, false) }}</td></tr>
            </table>
        </div>
    </div>

    <div class="mt-6 flex items-center justify-between rounded-2xl border-2 border-slate-900 px-6 py-4">
        <span class="text-lg font-extrabold">{{ __('صافي المرتب') }}</span>
        <span class="text-2xl font-black">{{ Money::format($p->net) }}</span>
    </div>

    <div class="mt-14 grid grid-cols-2 gap-10 text-center">
        <div><div class="h-14"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('توقيع الموظف بالاستلام') }}</div></div>
        <div><div class="h-14"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('المحاسب') }}</div></div>
    </div>
@endsection
