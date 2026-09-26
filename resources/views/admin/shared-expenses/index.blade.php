@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $methods = \App\Http\Controllers\Admin\SharedExpenseController::METHODS;
@endphp

@section('title', __('المصاريف المشتركة'))
@section('subtitle', __('مصروف واحد بيتقسم على كذا عربية — وبيدخل في ربح كل عربية لوحده'))

@section('actions')
    @if (\App\Support\Access::can('cash.create'))
        <a href="{{ route('admin.shared-expenses.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">＋ {{ __('مصروف مشترك جديد') }}</a>
    @endif
@endsection

@section('content')
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($rows->isEmpty())
            <div class="px-6 py-16 text-center text-sm text-slate-400">{{ __('مفيش مصاريف مشتركة لسه. مثال: شحن حاوية فيها 3 عربيات بـ 900 — بيتقسم 300 على كل عربية.') }}</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">#</th><th class="px-3 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('البيان') }}</th><th class="px-3 py-3 text-start">{{ __('الإجمالي') }}</th><th class="px-3 py-3 text-start">{{ __('طريقة التقسيم') }}</th><th class="px-3 py-3 text-start">{{ __('العربيات') }}</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $r)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs font-bold text-slate-600">{{ $r->code }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ Carbon::parse($r->entry_date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-3"><div class="font-semibold text-slate-800">{{ $r->details }}</div><div class="text-xs text-slate-400">{{ $r->category_name }}@if ($r->supplier_name) · {{ $r->supplier_name }}@endif</div></td>
                            <td class="whitespace-nowrap px-3 py-3 font-bold">{{ Money::format($r->total_amount, false) }}@if ((float) $r->tax_amount > 0)<div class="text-[11px] font-normal text-slate-400">{{ __('منها ضريبة') }} {{ Money::format($r->tax_amount, false) }}</div>@endif</td>
                            <td class="px-3 py-3 text-xs">{{ __($methods[$r->method] ?? $r->method) }}</td>
                            <td class="px-3 py-3 font-bold">{{ $r->cars }}</td>
                            <td class="px-5 py-3 text-end"><a href="{{ route('admin.shared-expenses.show', $r->id) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ __('التفاصيل') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($rows->hasPages())<div class="border-t border-slate-100 p-4">{{ $rows->links() }}</div>@endif
        @endif
    </div>
@endsection
