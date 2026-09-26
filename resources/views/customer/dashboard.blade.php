@extends('customer.layouts.app')

@section('title', 'الرئيسية')

@section('content')
    <div class="bg-white rounded-2xl shadow-sm p-6 sm:p-8">
        <h1 class="text-xl font-bold text-slate-800">
            أهلاً {{ auth()->user()->customer?->name_ar ?? auth()->user()->name }}
        </h1>
        <p class="text-slate-500 mt-2">عربياتك وأقساطك هتظهر هنا.</p>
    </div>
@endsection
