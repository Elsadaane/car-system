@extends('admin.print.layout')

@php use App\Support\Media; use Illuminate\Support\Carbon; $cell = 'border border-slate-300 px-3 py-2'; $head = 'border border-slate-300 bg-slate-50 px-3 py-2 font-bold'; @endphp

@section('title', __('عقد تسليم ').$d->delivery_number)
@section('heading', __('محضر تسليم مركبة'))
@section('subheading', __('رقم: ').$d->delivery_number.__(' — عقد البيع: ').$d->contract_number)

@section('content')
    <p class="leading-loose">
        {{ __('إنه في يوم') }} <b>{{ Carbon::parse($d->actual_delivery)->format('Y-m-d') }}</b> {{ __('الساعة') }} <b>{{ Carbon::parse($d->actual_delivery)->format('H:i') }}</b>
        @if ($d->delivery_location) {{ __('بمقر') }} <b>{{ $d->delivery_location }}</b>{{ __('،') }} @endif
        {{ __('تم تسليم المركبة الموضحة بياناتها أدناه إلى السيد/ة') }} <b>{{ $d->received_by }}</b>
        {{ __('بموجب عقد البيع رقم') }} <b class="font-mono">{{ $d->contract_number }}</b>.
    </p>

    <table class="mt-4 w-full border-collapse text-sm">
        <tr><td class="{{ $head }}">{{ __('المشتري') }}</td><td class="{{ $cell }}">{{ $d->customer_name }}</td><td class="{{ $head }}">{{ __('الرقم المدني') }}</td><td class="{{ $cell }}">{{ $d->customer_national_id ?: '—' }}</td></tr>
        <tr><td class="{{ $head }}">{{ __('المركبة') }}</td><td class="{{ $cell }}">{{ $d->brand_name }} {{ $d->model_name }} {{ $d->manufacturing_year }}</td><td class="{{ $head }}">{{ __('اللون') }}</td><td class="{{ $cell }}">{{ $d->color }}</td></tr>
        <tr><td class="{{ $head }}">{{ __('رقم اللوحة') }}</td><td class="{{ $cell }}">{{ $d->plate_number ?: '—' }}</td><td class="{{ $head }}">{{ __('رقم الشاسيه') }}</td><td class="{{ $cell }} font-mono">{{ $d->chassis_number ?: '—' }}</td></tr>
    </table>

    @if ($d->delivery_notes)
        <div class="mt-4 rounded-xl border border-slate-300 p-4"><b>{{ __('ملاحظات التسليم:') }}</b> {{ $d->delivery_notes }}</div>
    @endif

    <div class="mt-4 rounded-xl border border-slate-300 p-4 leading-loose">{{ \App\Support\RichText::render($terms ?: __('يقر المستلم بأنه استلم المركبة بحالة جيدة ومعها المفاتيح والمستندات الخاصة بها، وأنه عاينها المعاينة التامة، ويتحمل مسؤوليتها من تاريخ ووقت التسليم.'), 'terms') }}</div>

    <div class="mt-10 grid grid-cols-2 gap-10 text-center">
        <div>
            <div class="grid h-24 place-items-center">@if ($sig = Media::url($d->customer_signature)) <img src="{{ $sig }}" alt="" class="max-h-24 object-contain"> @endif</div>
            <div class="border-t border-slate-400 pt-2 font-bold">{{ __('المستلم:') }} {{ $d->received_by }}</div>
        </div>
        <div>
            <div class="grid h-24 place-items-center">@if ($sig = Media::url($d->seller_signature)) <img src="{{ $sig }}" alt="" class="max-h-24 object-contain"> @endif</div>
            <div class="border-t border-slate-400 pt-2 font-bold">{{ __('المسلّم:') }} {{ $d->delivered_by }}</div>
        </div>
    </div>
@endsection
