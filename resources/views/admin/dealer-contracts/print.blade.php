@extends('admin.print.layout')

@php
    use App\Support\Money;
    use App\Support\Media;
    $both = ($lang ?? 'both') === 'both';
    $date = \Illuminate\Support\Carbon::parse($contract->contract_date)->format('Y/m/d');
@endphp

@section('title', __('عقد تفويض ').$contract->contract_number)
@if ($both)
    @section('heading')تفويض بيع وشراء المركبات <div class="text-sm font-normal text-slate-500" dir="ltr">Vehicle Sale Authorization Contract</div>@endsection
    @section('subheading')<span dir="ltr">Contract No: <b class="font-mono text-slate-800">{{ $contract->contract_number }}</b></span> — رقم العقد@endsection
    @section('no_seal', true)
@else
    @section('heading', __('عقد تفويض بيع مركبة'))
    @section('subheading', __('رقم العقد: ').$contract->contract_number.__(' — التاريخ: ').\Illuminate\Support\Carbon::parse($contract->contract_date)->format('Y-m-d'))
@endif

@section('content')
@if ($both)
    @php
        $row = fn ($ar, $en) => '<div class="grid grid-cols-2 gap-4"><div dir="rtl">'.$ar.'</div><div dir="ltr" class="text-left">'.$en.'</div></div>';
        $h = fn ($ar, $en) => '<div>'.e($ar).'</div><div class="text-[10px] font-semibold text-slate-500" dir="ltr">'.e($en).'</div>';
        $co = $company->name_ar ?? config('app.name');
        $coEn = ($company->name_en ?? null) ?: $co;
    @endphp
    <div class="space-y-2 rounded-xl border border-slate-300 p-4 text-[13px]">
        {!! $row('في تاريخ <b>'.e($date).'</b> اتفق الطرفان على ما يلي:', 'On <b>'.e($date).'</b>, the parties agreed on the following:') !!}
        <div class="rounded-lg bg-slate-50 px-3 py-2">{!! $row('<b>الطرف الأول (المفوَّض له):</b> '.e($co), '<b>First Party (Delegated):</b> '.e($coEn)) !!}</div>
        <div class="rounded-lg bg-slate-50 px-3 py-2">{!! $row('<b>الطرف الثاني (المالك):</b> '.e($contract->supplier_name), '<b>Second Party (Owner):</b> '.e($contract->supplier_name)) !!}</div>
        <div class="grid grid-cols-2 gap-4 px-3">
            <div dir="rtl"><b>الهاتف / Phone:</b> <span dir="ltr">{{ $contract->supplier_phone ?: '—' }}</span></div>
            <div dir="rtl"><b>رقم البطاقة / National ID:</b> {{ $contract->supplier_national_id ?: '—' }}</div>
        </div>
        {!! $row('على أن يبيع الطرف الأول المركبة التي تحمل المواصفات التالية:', 'The first party shall sell the vehicle with the following specifications:') !!}
    </div>

    <table class="mt-4 w-full border-collapse text-center text-[12px]">
        <thead class="bg-slate-100 font-bold text-slate-800">
            <tr>
                <th class="border border-slate-300 p-2">{!! $h('نوع المركبة', 'Make') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('الطراز', 'Model') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('سنة الصنع', 'Year') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('رقم اللوحة', 'Plate No.') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('اللون', 'Color') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('الكيلومتر', 'Mileage') !!}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="border border-slate-300 p-2">{{ $contract->brand_name }}</td>
                <td class="border border-slate-300 p-2">{{ $contract->model_name }}</td>
                <td class="border border-slate-300 p-2">{{ $contract->manufacturing_year ?: '—' }}</td>
                <td class="border border-slate-300 p-2">{{ $contract->plate_number ?: '—' }}</td>
                <td class="border border-slate-300 p-2">{{ $contract->color ?: '—' }}</td>
                <td class="border border-slate-300 p-2">{{ $contract->mileage !== null ? number_format((float) $contract->mileage) : '—' }}</td>
            </tr>
            <tr>
                <td colspan="3" class="border border-slate-300 p-2"><b>رقم الشاصي / VIN No:</b> <span class="font-mono">{{ $contract->chassis_number ?: '—' }}</span></td>
                <td colspan="3" class="border border-slate-300 p-2"><b>رقم المحرك / Engine No:</b> <span class="font-mono">{{ $contract->engine_number ?: '—' }}</span></td>
            </tr>
        </tbody>
    </table>

    <table class="mt-4 w-full border-collapse text-center text-[12px]">
        <thead class="bg-slate-100 font-bold text-slate-800">
            <tr>
                <th class="border border-slate-300 p-2">{!! $h('السعر المتفق عليه', 'Agreed Price') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('الضريبة ('.(float) $contract->tax_percent.'%)', 'Tax') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('عمولة المعرض', 'Commission') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('صافي المستحق للمالك', 'Net Due to Owner') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('المدفوع', 'Paid') !!}</th>
                <th class="border border-slate-300 p-2">{!! $h('المتبقي', 'Remaining') !!}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="font-bold">
                <td class="border border-slate-300 p-2">{{ Money::format($contract->agreed_price, false) }}</td>
                <td class="border border-slate-300 p-2">{{ Money::format($contract->tax_amount, false) }}</td>
                <td class="border border-slate-300 p-2">{{ Money::format($contract->commission_amount, false) }}</td>
                <td class="border border-slate-300 bg-slate-50 p-2">{{ Money::format($contract->total_amount, false) }}</td>
                <td class="border border-slate-300 p-2">{{ Money::format($contract->paid_amount, false) }}</td>
                <td class="border border-slate-300 p-2">{{ Money::format($contract->remaining_amount, false) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($termsAr || $termsEn)
        <div class="mt-4 grid gap-3 {{ $termsAr && $termsEn ? 'grid-cols-2' : '' }} text-[11.5px] leading-relaxed">
            @if ($termsAr)
                <div class="rounded-xl border border-slate-300 p-3" dir="rtl">
                    <div class="mb-1 font-extrabold text-slate-900">الشروط والأحكام:</div>
                    {{ \App\Support\RichText::render($termsAr, 'terms') }}
                </div>
            @endif
            @if ($termsEn)
                <div class="rounded-xl border border-slate-300 p-3 text-left" dir="ltr">
                    <div class="mb-1 font-extrabold text-slate-900">Terms and Conditions:</div>
                    {{ \App\Support\RichText::render($termsEn, 'terms') }}
                </div>
            @endif
        </div>
    @endif

    @php $seal = Media::url($company->seal ?? null); @endphp
    <div class="mt-8 grid grid-cols-2 gap-10 text-center text-[12px]">
        <div>
            <div class="font-extrabold">الطرف الأول (المفوَّض له)</div>
            <div class="text-[10px] font-semibold text-slate-500" dir="ltr">First Party (Delegated)</div>
            <div class="mt-1">الاسم / Name: <b>{{ $co }}</b></div>
            <div>المستخدم / User: {{ $contract->user_name ?? '—' }}</div>
            <div class="relative mt-2 grid h-24 place-items-center">
                @if ($seal) <img src="{{ $seal }}" alt="" class="absolute h-20 w-20 -rotate-6 object-contain opacity-70"> @endif
                @if ($sig = Media::url($contract->showroom_signature)) <img src="{{ $sig }}" alt="" class="relative max-h-20 object-contain"> @endif
            </div>
            <div class="border-t border-slate-400 pt-1 font-bold">التوقيع / Signature</div>
        </div>
        <div>
            <div class="font-extrabold">الطرف الثاني (المالك)</div>
            <div class="text-[10px] font-semibold text-slate-500" dir="ltr">Second Party (Owner)</div>
            <div class="mt-1">الاسم / Name: <b>{{ $contract->supplier_name }}</b></div>
            <div>&nbsp;</div>
            <div class="mt-2 grid h-24 place-items-center">
                @if ($sig = Media::url($contract->owner_signature)) <img src="{{ $sig }}" alt="" class="max-h-20 object-contain"> @endif
            </div>
            <div class="border-t border-slate-400 pt-1 font-bold">التوقيع / Signature</div>
        </div>
    </div>
@else
    <div class="grid grid-cols-2 gap-6">
        <div class="rounded-xl border border-slate-300 p-4">
            <div class="mb-2 font-extrabold text-slate-900">{{ __('الطرف الأول (المالك)') }}</div>
            <div>{{ __('الاسم:') }} <b>{{ $contract->supplier_name }}</b></div>
            <div>{{ __('رقم البطاقة:') }} <b>{{ $contract->supplier_national_id ?: '—' }}</b></div>
            <div>{{ __('الهاتف:') }} <b dir="ltr">{{ $contract->supplier_phone ?: '—' }}</b></div>
            @if ($contract->supplier_address) <div>{{ __('العنوان:') }} {{ $contract->supplier_address }}</div> @endif
        </div>
        <div class="rounded-xl border border-slate-300 p-4">
            <div class="mb-2 font-extrabold text-slate-900">{{ __('الطرف الثاني (المعرض)') }}</div>
            <div>{{ __('الاسم:') }} <b>{{ \App\Support\Lang::pick($company, 'name') ?? config('app.name') }}</b></div>
            @if (! empty($company->commercial_register_number)) <div>{{ __('السجل التجاري:') }} <b>{{ $company->commercial_register_number }}</b></div> @endif
            @if (! empty($company->phone)) <div>{{ __('الهاتف:') }} <b dir="ltr">{{ $company->phone }}</b></div> @endif
        </div>
    </div>

    <div class="mt-6 font-extrabold text-slate-900">{{ __('بيانات المركبة') }}</div>
    <table class="mt-2 w-full border-collapse text-sm">
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('الماركة / الطراز') }}</td><td class="border border-slate-300 px-3 py-2">{{ $contract->brand_name }} {{ $contract->model_name }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('سنة الصنع') }}</td><td class="border border-slate-300 px-3 py-2">{{ $contract->manufacturing_year }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('رقم اللوحة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $contract->plate_number ?: '—' }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('اللون') }}</td><td class="border border-slate-300 px-3 py-2">{{ $contract->color }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('رقم الشاسيه') }}</td><td class="border border-slate-300 px-3 py-2 font-mono">{{ $contract->chassis_number ?: '—' }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('رقم المحرك') }}</td><td class="border border-slate-300 px-3 py-2 font-mono">{{ $contract->engine_number ?: '—' }}</td>
        </tr>
    </table>

    <div class="mt-6 font-extrabold text-slate-900">{{ __('البيانات المالية') }}</div>
    <table class="mt-2 w-full border-collapse text-sm">
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('السعر المتفق عليه') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($contract->agreed_price) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('الضريبة (') }}{{ (float) $contract->tax_percent }}%)</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($contract->tax_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('عمولة المعرض') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($contract->commission_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-100 px-3 py-2 font-extrabold">{{ __('صافي المستحق للمالك') }}</td><td class="border border-slate-300 px-3 py-2 font-extrabold">{{ Money::format($contract->total_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('المدفوع حتى تاريخه') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($contract->paid_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('المتبقي') }}</td><td class="border border-slate-300 px-3 py-2">{{ Money::format($contract->remaining_amount) }}</td></tr>
    </table>

    @if ($contract->terms_ar)
        <div class="mt-6 font-extrabold text-slate-900">{{ __('الشروط والأحكام') }}</div>
        <div class="mt-2 rounded-xl border border-slate-300 p-4 leading-loose">{{ \App\Support\RichText::render(\App\Support\Lang::pick($contract, 'terms'), 'terms') }}</div>
    @endif

    <div class="mt-10 grid grid-cols-2 gap-10 text-center">
        @foreach (['owner' => __('توقيع المالك'), 'showroom' => __('توقيع المعرض')] as $party => $label)
            <div>
                <div class="grid h-28 place-items-center">
                    @if ($sig = Media::url($contract->{$party.'_signature'})) <img src="{{ $sig }}" alt="" class="max-h-28 object-contain"> @endif
                </div>
                <div class="border-t border-slate-400 pt-2 font-bold">{{ $label }}</div>
            </div>
        @endforeach
    </div>
@endif
@endsection
