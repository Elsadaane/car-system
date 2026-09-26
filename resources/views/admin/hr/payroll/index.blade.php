@extends('admin.layouts.app')

@section('title', __('المرتبات'))
@section('subtitle', __('كل شهر: احسب ← راجع ← اعتمد ← اصرف'))

@php
    use App\Support\Money;
    use App\Http\Controllers\Admin\Hr\PayrollController as PC;
    $style = ['draft' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-blue-50 text-blue-700', 'paid' => 'bg-emerald-50 text-emerald-700'];
@endphp

@section('content')
    @include('admin.hr.partials-tabs')

    <form method="POST" action="{{ route('admin.hr.payroll.store') }}" class="mb-6 flex flex-wrap items-end gap-4 rounded-3xl rtl:bg-gradient-to-l ltr:bg-gradient-to-r from-violet-600 to-indigo-700 p-6 text-white shadow-xl">
        @csrf
        <div class="flex-1">
            <div class="text-lg font-extrabold">{{ __('احسب مرتبات شهر') }}</div>
            <p class="mt-1 text-sm text-violet-100">{{ __('السيستم بيجمع الحضور والغياب والأوفر تايم والعمولات والحوافز والسلف والتأمينات لكل موظف لوحده.') }}</p>
        </div>
        <input type="month" name="month" value="{{ $next->format('Y-m') }}" class="rounded-xl border-0 px-4 py-3 text-slate-900">
        <button class="rounded-xl bg-white px-6 py-3 text-sm font-extrabold text-violet-700 shadow hover:bg-violet-50">{{ __('🧮 احسب') }}</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($runs as $r)
            <a href="{{ route('admin.hr.payroll.show', $r->id) }}" class="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="flex items-start justify-between">
                    <div><div class="text-xl font-extrabold text-slate-900 group-hover:text-violet-700">{{ PC::MONTHS[$r->month] }} {{ $r->year }}</div><div class="text-xs text-slate-400">{{ $r->employees }} {{ __('موظف') }}</div></div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $style[$r->status] }}">{{ PC::RUN_STATUS[$r->status] }}</span>
                </div>
                <div class="mt-4 text-2xl font-extrabold">{{ Money::format($r->total_net, false) }}</div>
                <div class="text-xs text-slate-400">{{ __('صافي · الإجمالي') }} {{ Money::format($r->total_gross, false) }} {{ __('· أوفر تايم وعمولات') }} {{ Money::format($r->extras, false) }}</div>
                <div class="mt-4 flex gap-1">
                    @foreach (['draft', 'approved', 'paid'] as $i => $s)
                        <div class="h-1.5 flex-1 rounded-full {{ array_search($r->status, ['draft', 'approved', 'paid']) >= $i ? 'bg-violet-500' : 'bg-slate-100' }}"></div>
                    @endforeach
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-2xl bg-white py-16 text-center text-slate-400 shadow-sm ring-1 ring-slate-200">{{ __('لسه ماحسبتش مرتبات أي شهر') }}</div>
        @endforelse
    </div>
@endsection
