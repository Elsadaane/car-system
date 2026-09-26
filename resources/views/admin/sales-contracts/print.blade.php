@extends('admin.print.layout')

@php
    use App\Support\Money;
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $en = $lang === 'en';
    $t = fn (string $ar, string $enText) => $en ? $enText : $ar;
    $cell = 'border border-slate-300 px-3 py-2';
    $head = 'border border-slate-300 bg-slate-50 px-3 py-2 font-bold';
    $terms = $en ? ($contract->terms_en ?: $contract->terms_ar) : ($contract->terms_ar ?: $contract->terms_en);
    $customerName = $en && $contract->customer_name_en ? $contract->customer_name_en : $contract->customer_name;
    $vehicleName = $en ? trim(($contract->brand_name_en ?: $contract->brand_name).' '.($contract->model_name_en ?: $contract->model_name)) : $contract->brand_name.' '.$contract->model_name;
    $cancelled = $contract->status_code === 'cancelled';
@endphp

@section('title', $t(__('عقد بيع '), 'Sales Contract ').$contract->contract_number)
@section('heading', $t(__('عقد بيع مركبة'), 'Vehicle Sales Contract'))
@section('subheading', $t(__('رقم العقد: '), 'Contract No: ').$contract->contract_number.' — '.$t(__('التاريخ: '), 'Date: ').Carbon::parse($contract->contract_date)->format('Y-m-d'))

@section('content')
<div @if ($en) dir="ltr" style="text-align:left" @endif>
    @if ($cancelled)
        <div class="mb-4 rounded-xl border-2 border-rose-500 py-2 text-center text-lg font-extrabold text-rose-600">{{ $t(__('عقد ملغي'), 'CANCELLED') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-6">
        <div class="rounded-xl border border-slate-300 p-4">
            <div class="mb-2 font-extrabold text-slate-900">{{ $t(__('الطرف الأول (البائع)'), 'First Party (Seller)') }}</div>
            <div>{{ $t(__('الاسم'), 'Name') }}: <b>{{ $en ? ($company->name_en ?? \App\Support\Lang::pick($company, 'name') ?? config('app.name')) : (\App\Support\Lang::pick($company, 'name') ?? config('app.name')) }}</b></div>
            @if (! empty($company->commercial_register_number)) <div>{{ $t(__('السجل التجاري'), 'C.R.') }}: <b>{{ $company->commercial_register_number }}</b></div> @endif
            @if (! empty($company->phone)) <div>{{ $t(__('الهاتف'), 'Phone') }}: <b dir="ltr">{{ $company->phone }}</b></div> @endif
            @if ($contract->branch_name) <div>{{ $t(__('الفرع'), 'Branch') }}: <b>{{ $contract->branch_name }}</b></div> @endif
        </div>
        <div class="rounded-xl border border-slate-300 p-4">
            <div class="mb-2 font-extrabold text-slate-900">{{ $t(__('الطرف الثاني (المشتري)'), 'Second Party (Buyer)') }}</div>
            <div>{{ $t(__('الاسم'), 'Name') }}: <b>{{ $customerName }}</b></div>
            <div>{{ $t(__('الرقم المدني'), 'Civil ID') }}: <b>{{ $contract->customer_national_id ?: '—' }}</b></div>
            <div>{{ $t(__('الهاتف'), 'Phone') }}: <b dir="ltr">{{ $contract->customer_phone ?: '—' }}</b></div>
            @if ($contract->customer_nationality) <div>{{ $t(__('الجنسية'), 'Nationality') }}: <b>{{ $contract->customer_nationality }}</b></div> @endif
            @if ($contract->customer_address) <div>{{ $t(__('العنوان'), 'Address') }}: {{ $contract->customer_address }}</div> @endif
        </div>
    </div>

    <div class="mt-6 font-extrabold text-slate-900">{{ $t(__('بيانات المركبة'), 'Vehicle Details') }}</div>
    <table class="mt-2 w-full border-collapse text-sm">
        <tr><td class="{{ $head }}">{{ $t(__('الماركة / الطراز'), 'Make / Model') }}</td><td class="{{ $cell }}">{{ $vehicleName }}</td><td class="{{ $head }}">{{ $t(__('سنة الصنع'), 'Year') }}</td><td class="{{ $cell }}">{{ $contract->manufacturing_year }}</td></tr>
        <tr><td class="{{ $head }}">{{ $t(__('رقم اللوحة'), 'Plate No.') }}</td><td class="{{ $cell }}">{{ $contract->plate_number ?: '—' }}</td><td class="{{ $head }}">{{ $t(__('اللون'), 'Color') }}</td><td class="{{ $cell }}">{{ $contract->color }}</td></tr>
        <tr><td class="{{ $head }}">{{ $t(__('رقم الشاسيه'), 'Chassis No.') }}</td><td class="{{ $cell }} font-mono">{{ $contract->chassis_number ?: '—' }}</td><td class="{{ $head }}">{{ $t(__('رقم المحرك'), 'Engine No.') }}</td><td class="{{ $cell }} font-mono">{{ $contract->engine_number ?: '—' }}</td></tr>
        <tr><td class="{{ $head }}">{{ $t(__('العداد'), 'Mileage') }}</td><td class="{{ $cell }}">{{ number_format((int) $contract->mileage) }} {{ $t(__('كم'), 'km') }}</td><td class="{{ $head }}">{{ $t(__('كود المركبة'), 'Stock Code') }}</td><td class="{{ $cell }} font-mono">{{ $contract->vehicle_code }}</td></tr>
    </table>

    <div class="mt-6 font-extrabold text-slate-900">{{ $t(__('البيانات المالية'), 'Financial Details') }}</div>
    <table class="mt-2 w-full border-collapse text-sm">
        <tr><td class="{{ $head }}">{{ $t(__('سعر البيع'), 'Sale Price') }}</td><td class="{{ $cell }}">{{ Money::format($contract->sale_price) }}</td></tr>
        @if ((float) $contract->discount_amount > 0) <tr><td class="{{ $head }}">{{ $t(__('الخصم'), 'Discount') }}</td><td class="{{ $cell }}">− {{ Money::format($contract->discount_amount) }}</td></tr> @endif
        <tr><td class="{{ $head }}">{{ $t(__('الضريبة'), 'Tax') }} ({{ (float) $contract->tax_percent }}%)</td><td class="{{ $cell }}">{{ Money::format($contract->tax_amount) }}</td></tr>
        <tr><td class="border border-slate-300 bg-slate-100 px-3 py-2 font-extrabold">{{ $t(__('إجمالي قيمة العقد'), 'Contract Total') }}</td><td class="{{ $cell }} font-extrabold">{{ Money::format($contract->total_amount) }}</td></tr>
        <tr><td class="{{ $head }}">{{ $t(__('طريقة السداد'), 'Payment Type') }}</td><td class="{{ $cell }}">{{ $contract->payment_type === 'installments' ? $t(__('تقسيط'), 'Installments') : $t(__('نقداً'), 'Cash') }}</td></tr>
        <tr><td class="{{ $head }}">{{ $contract->payment_type === 'installments' ? $t(__('المقدم'), 'Down Payment') : $t(__('المدفوع عند التعاقد'), 'Paid at Signing') }}</td><td class="{{ $cell }}">{{ Money::format($contract->down_payment) }}</td></tr>
        <tr><td class="{{ $head }}">{{ $t(__('المدفوع حتى تاريخه'), 'Paid to Date') }}</td><td class="{{ $cell }}">{{ Money::format($contract->paid_amount) }}</td></tr>
        <tr><td class="{{ $head }}">{{ $t(__('المتبقي'), 'Balance') }}</td><td class="{{ $cell }} font-bold">{{ Money::format($contract->remaining_amount) }}</td></tr>
    </table>

    @if ($installments->isNotEmpty())
        <div class="mt-6 font-extrabold text-slate-900">{{ $t(__('جدول الأقساط'), 'Installment Schedule') }} ({{ $installments->count() }})</div>
        <table class="mt-2 w-full border-collapse text-xs">
            <thead><tr><th class="{{ $head }}">#</th><th class="{{ $head }}">{{ $t(__('تاريخ الاستحقاق'), 'Due Date') }}</th><th class="{{ $head }}">{{ $t(__('المبلغ'), 'Amount') }}</th><th class="{{ $head }}">{{ $t(__('الحالة'), 'Status') }}</th></tr></thead>
            <tbody>
                @foreach ($installments as $i)
                    <tr>
                        <td class="{{ $cell }} py-1 text-center">{{ $i->installment_sequence }}</td>
                        <td class="{{ $cell }} py-1">{{ Carbon::parse($i->due_date)->format('Y-m-d') }}</td>
                        <td class="{{ $cell }} py-1">{{ Money::format($i->amount, false) }}</td>
                        <td class="{{ $cell }} py-1">{{ $i->is_paid ? $t(__('مدفوع'), 'Paid') : ((float) $i->paid_amount > 0 ? $t(__('مدفوع جزئياً'), 'Partial') : '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($contract->has_guarantor)
        <div class="mt-6 font-extrabold text-slate-900">{{ $t(__('الضامن'), 'Guarantor') }}</div>
        <table class="mt-2 w-full border-collapse text-sm">
            <tr><td class="{{ $head }}">{{ $t(__('الاسم'), 'Name') }}</td><td class="{{ $cell }}">{{ $contract->guarantor_name }}</td><td class="{{ $head }}">{{ $t(__('الرقم المدني'), 'Civil ID') }}</td><td class="{{ $cell }}">{{ $contract->guarantor_national_id ?: '—' }}</td></tr>
            <tr><td class="{{ $head }}">{{ $t(__('الهاتف'), 'Phone') }}</td><td class="{{ $cell }}" dir="ltr">{{ $contract->guarantor_phone }}</td><td class="{{ $head }}">{{ $t(__('العنوان'), 'Address') }}</td><td class="{{ $cell }}">{{ $contract->guarantor_address ?: '—' }}</td></tr>
        </table>
    @endif

    @if ($terms)
        <div class="mt-6 font-extrabold text-slate-900">{{ $t(__('الشروط والأحكام'), 'Terms & Conditions') }}</div>
        <div class="mt-2 rounded-xl border border-slate-300 p-4 leading-loose">{{ \App\Support\RichText::render($terms, 'terms') }}</div>
    @endif

    <div class="mt-10 grid {{ $contract->has_guarantor ? 'grid-cols-3' : 'grid-cols-2' }} gap-10 text-center">
        @foreach (['customer' => $t(__('توقيع المشتري'), 'Buyer Signature'), 'seller' => $t(__('توقيع البائع'), 'Seller Signature')] as $party => $label)
            <div>
                <div class="grid h-28 place-items-center">
                    @if ($sig = Media::url($contract->{$party.'_signature'})) <img src="{{ $sig }}" alt="" class="max-h-28 object-contain"> @endif
                </div>
                <div class="border-t border-slate-400 pt-2 font-bold">{{ $label }}</div>
            </div>
        @endforeach
        @if ($contract->has_guarantor)
            <div><div class="h-28"></div><div class="border-t border-slate-400 pt-2 font-bold">{{ $t(__('توقيع الضامن'), 'Guarantor Signature') }}</div></div>
        @endif
    </div>
</div>
@endsection
