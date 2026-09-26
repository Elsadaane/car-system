@extends('admin.layouts.app')

@section('title', __('التنبيهات'))
@section('subtitle', __('الحاجات اللي محتاجة منك تدخل — بتتحدّث لوحدها كل كام دقيقة'))

@section('content')
    <div class="mx-auto max-w-2xl overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @include('admin.notifications.list')
    </div>
@endsection
