@extends('admin.print.layout')

@php use App\Support\Media; use Illuminate\Support\Carbon; @endphp

@section('title', __('تجربة قيادة — ').$drive->customer_name)
@section('heading', __('عقد تجربة قيادة / Test Drive Agreement'))
@section('subheading', __('التاريخ: ').Carbon::parse($drive->start_time)->format('Y-m-d H:i'))

@section('content')
    <table class="w-full border-collapse text-sm">
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('العميل') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->customer_name }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('الهاتف') }}</td><td class="border border-slate-300 px-3 py-2" dir="ltr">{{ $drive->customer_phone }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('الرقم المدني') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->national_id ?: '—' }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('رقم الرخصة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->license_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('المركبة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->brand_name }} {{ $drive->model_name }} {{ $drive->manufacturing_year }} — {{ $drive->color }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('رقم اللوحة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->plate_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('وقت الخروج') }}</td><td class="border border-slate-300 px-3 py-2">{{ Carbon::parse($drive->start_time)->format('Y-m-d H:i') }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('وقت العودة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->end_time ? Carbon::parse($drive->end_time)->format('Y-m-d H:i') : '................' }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('العداد عند الخروج') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->start_mileage !== null ? number_format($drive->start_mileage).__(' كم') : '—' }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('العداد عند العودة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $drive->end_mileage ? number_format($drive->end_mileage).__(' كم') : '................' }}</td>
        </tr>
    </table>

    <div class="mt-6 grid grid-cols-2 gap-6">
        <div>
            <div class="font-extrabold text-slate-900">{{ __('الإقرار') }}</div>
            <div class="mt-2 rounded-xl border border-slate-300 p-4 leading-loose">{{ \App\Support\RichText::render(\App\Support\Lang::pick($drive, 'terms'), 'terms') }}</div>
        </div>
        <div dir="ltr">
            <div class="font-extrabold text-slate-900">Declaration</div>
            <div class="mt-2 rounded-xl border border-slate-300 p-4 text-end leading-loose" dir="ltr">{{ \App\Support\RichText::render($drive->terms_en, 'terms') }}</div>
        </div>
    </div>

    <div class="mt-10 grid grid-cols-2 gap-10 text-center">
        @foreach (['customer' => __('توقيع العميل / Customer'), 'showroom' => __('توقيع المعرض / Showroom')] as $party => $label)
            <div>
                <div class="grid h-28 place-items-center">@if ($sig = Media::url($drive->{$party.'_signature'})) <img src="{{ $sig }}" alt="" class="max-h-28 object-contain"> @endif</div>
                <div class="border-t border-slate-400 pt-2 font-bold">{{ $label }}</div>
            </div>
        @endforeach
    </div>
@endsection
