@extends('admin.print.layout')

@php use App\Support\Money; use App\Support\Payroll; $c = 'border border-slate-300 px-3 py-2'; $h = 'border border-slate-300 bg-slate-50 px-3 py-2 font-bold'; @endphp

@section('title', __('مخالصة ').$e->full_name_ar)
@section('heading', __('مخالصة نهائية وإنهاء خدمة'))
@section('subheading', $e->full_name_ar.' — '.$e->employee_code)

@section('content')
    <table class="w-full border-collapse text-sm">
        <tr><td class="{{ $h }}">{{ __('الموظف') }}</td><td class="{{ $c }}">{{ \App\Support\Lang::pick($e, 'full_name') }}</td><td class="{{ $h }}">{{ __('الرقم المدني') }}</td><td class="{{ $c }}">{{ $e->national_id ?: '—' }}</td></tr>
        <tr><td class="{{ $h }}">{{ __('الوظيفة') }}</td><td class="{{ $c }}">{{ $e->job_title }}</td><td class="{{ $h }}">{{ __('نوع التوظيف') }}</td><td class="{{ $c }}">{{ Payroll::EMPLOYMENT[$e->employment_type] }}</td></tr>
        <tr><td class="{{ $h }}">{{ __('تاريخ التعيين') }}</td><td class="{{ $c }}">{{ $e->join_date }}</td><td class="{{ $h }}">{{ __('آخر يوم عمل') }}</td><td class="{{ $c }}">{{ $eos->termination_date }}</td></tr>
        <tr><td class="{{ $h }}">{{ __('مدة الخدمة') }}</td><td class="{{ $c }}">{{ (float) $eos->service_years }} {{ __('سنة') }}</td><td class="{{ $h }}">{{ __('السبب') }}</td><td class="{{ $c }}">{{ $eos->reason }}</td></tr>
    </table>

    <table class="mt-6 w-full border-collapse text-sm">
        <tr><td class="{{ $h }}">{{ __('مكافأة نهاية الخدمة (') }}{{ (float) $eos->gratuity_days_per_year }} {{ __('يوم/سنة ×') }} {{ Money::format($eos->daily_rate, false) }})</td><td class="{{ $c }} text-end">{{ Money::format($eos->gratuity_amount) }}</td></tr>
        <tr><td class="{{ $h }}">{{ __('بدل رصيد الإجازات (') }}{{ (float) $eos->leave_days }} {{ __('يوم)') }}</td><td class="{{ $c }} text-end">{{ Money::format($eos->leave_encashment) }}</td></tr>
        @if ((float) $eos->other_additions) <tr><td class="{{ $h }}">{{ __('إضافات أخرى') }}</td><td class="{{ $c }} text-end">{{ Money::format($eos->other_additions) }}</td></tr> @endif
        @if ((float) $eos->loan_deduction) <tr><td class="{{ $h }}">{{ __('− خصم السلف') }}</td><td class="{{ $c }} text-end">{{ Money::format($eos->loan_deduction) }}</td></tr> @endif
        @if ((float) $eos->other_deductions) <tr><td class="{{ $h }}">{{ __('− خصومات أخرى') }}</td><td class="{{ $c }} text-end">{{ Money::format($eos->other_deductions) }}</td></tr> @endif
        <tr><td class="border border-slate-300 bg-slate-100 px-3 py-2 font-extrabold">{{ __('صافي المستحق') }}</td><td class="{{ $c }} text-end font-extrabold">{{ Money::format($eos->net_payout) }}</td></tr>
    </table>

    <p class="mt-6 rounded-xl border border-slate-300 p-4 leading-loose">
        {{ __('أقر أنا الموقع أدناه') }} <b>{{ \App\Support\Lang::pick($e, 'full_name') }}</b> {{ __('بأنني استلمت كافة مستحقاتي المالية لدى') }} <b>{{ \App\Support\Lang::pick($company, 'name') ?? config('app.name') }}</b>
        {{ __('وقدرها') }} <b>{{ Money::format($eos->net_payout) }}</b>{{ __('، وأنه لا يوجد لي أي مطالبات مالية أخرى تجاه الشركة، وهذه مخالصة نهائية مني بذلك.') }}
    </p>
    @if ($eos->notes) <p class="mt-3 text-sm text-slate-600">{{ __('ملاحظات:') }} {{ $eos->notes }}</p> @endif

    <div class="mt-14 grid grid-cols-2 gap-10 text-center">
        <div><div class="h-16"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('توقيع الموظف') }}</div></div>
        <div><div class="h-16"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ __('اعتماد الإدارة') }}</div></div>
    </div>
@endsection
