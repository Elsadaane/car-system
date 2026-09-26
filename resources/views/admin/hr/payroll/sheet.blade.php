@extends('admin.print.layout')

@php use App\Support\Money; use App\Http\Controllers\Admin\Hr\PayrollController as PC; $c = 'border border-slate-300 px-2 py-1.5'; @endphp

@section('title', __('كشف مرتبات ').$run->label)
@section('heading', __('كشف مرتبات شهر ').$run->label)
@section('subheading', __('عدد الموظفين: ').$rows->count().__(' — الحالة: ').PC::RUN_STATUS[$run->status])

@section('content')
    @foreach (['cash' => __('💵 استلام نقدي (بالتوقيع)'), 'bank' => __('🏦 تحويلات بنكية')] as $m => $title)
        @php $list = $rows->where('payment_method', $m); @endphp
        @if ($list->isNotEmpty())
            <div class="mb-2 mt-6 font-extrabold">{{ $title }}</div>
            <table class="w-full border-collapse text-xs">
                <thead class="bg-slate-100"><tr><th class="{{ $c }}">#</th><th class="{{ $c }}">{{ __('الموظف') }}</th><th class="{{ $c }}">{{ __('الأساسي') }}</th><th class="{{ $c }}">{{ __('بدلات') }}</th><th class="{{ $c }}">{{ __('أوفر تايم') }}</th><th class="{{ $c }}">{{ __('عمولة/حوافز') }}</th><th class="{{ $c }}">{{ __('خصومات') }}</th><th class="{{ $c }}">{{ __('سلفة') }}</th><th class="{{ $c }}">{{ __('الصافي') }}</th><th class="{{ $c }}">{{ $m === 'cash' ? __('التوقيع') : 'IBAN' }}</th></tr></thead>
                <tbody>
                    @foreach ($list as $i => $r)
                        <tr>
                            <td class="{{ $c }} text-center">{{ $loop->iteration }}</td>
                            <td class="{{ $c }}"><b>{{ \App\Support\Lang::pick($r, 'full_name') }}</b><div class="text-[10px] text-slate-500">{{ $r->employee_code }} · {{ $r->job_title }}</div></td>
                            <td class="{{ $c }}">{{ Money::format($r->basic, false) }}</td>
                            <td class="{{ $c }}">{{ Money::format($r->allowances, false) }}</td>
                            <td class="{{ $c }}">{{ Money::format($r->overtime_amount, false) }}</td>
                            <td class="{{ $c }}">{{ Money::format($r->commission + $r->bonuses + $r->other_additions, false) }}</td>
                            <td class="{{ $c }}">{{ Money::format($r->absence_deduction + $r->fixed_deductions + $r->adjustment_deductions + $r->insurance_deduction + $r->other_deductions, false) }}</td>
                            <td class="{{ $c }}">{{ Money::format($r->loan_deduction, false) }}</td>
                            <td class="{{ $c }} font-extrabold">{{ Money::format($r->net, false) }}</td>
                            <td class="{{ $c }} {{ $m === 'cash' ? 'w-28' : 'font-mono text-[10px]' }}">{{ $m === 'bank' ? $r->iban : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50 font-extrabold"><td class="{{ $c }}" colspan="8">{{ __('الإجمالي') }}</td><td class="{{ $c }}">{{ Money::format($list->sum('net'), false) }}</td><td class="{{ $c }}"></td></tr>
                </tbody>
            </table>
        @endif
    @endforeach
    <div class="mt-6 text-end text-sm font-extrabold">{{ __('إجمالي الصافي:') }} {{ Money::format($rows->sum('net')) }}</div>

    <div class="mt-14 grid grid-cols-3 gap-8 text-center">
        <div><div class="h-12"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('إعداد') }}</div></div>
        <div><div class="h-12"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('مراجعة') }}</div></div>
        <div><div class="h-12"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('اعتماد المدير') }}</div></div>
    </div>
@endsection
