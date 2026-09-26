@extends('storefront.layouts.app')
@section('title', $title.' — '.\App\Support\Site::name())

@section('content')
@include('storefront.partials.page-hero', ['title' => $title])
<div class="relative z-10 mx-auto -mt-10 max-w-4xl px-4 sm:px-6 lg:px-8">
    <div class="rounded-[2rem] bg-white p-6 ring-1 ring-slate-200 sm:p-10">
        @if ($body)
            <div class="leading-loose text-slate-700">{{ \App\Support\RichText::render($body, 'full') }}</div>
        @else
            <p class="text-center text-slate-400">{{ __('المحتوى ده لسه ماتضافش — بيتعدّل من إعدادات النظام في لوحة التحكم.') }}</p>
        @endif
    </div>
</div>
@endsection
