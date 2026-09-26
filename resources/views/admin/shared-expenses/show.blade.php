@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $methods = \App\Http\Controllers\Admin\SharedExpenseController::METHODS;
@endphp

@section('title', __('مصروف مشترك').' '.$e->code)
@section('subtitle', $e->details)

@section('actions')
    @if (\App\Support\Access::can('cash.delete'))
        <form method="POST" action="{{ route('admin.shared-expenses.destroy', $e->id) }}" onsubmit="return confirm(@js(__('مسح المصروف المشترك؟ هيتمسح من كل العربيات ويرجع للخزنة.')))">
            @csrf @method('DELETE')
            <button class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50">{{ __('مسح') }}</button>
        </form>
    @endif
@endsection

@section('content')
    <section class="grid gap-4 rounded-2xl bg-slate-900 p-6 text-white shadow-lg sm:grid-cols-4">
        <div><div class="text-xs text-slate-400">{{ __('الإجمالي') }}</div><div class="mt-1 text-2xl font-extrabold">{{ Money::format($e->total_amount, false) }}</div>@if ((float) $e->tax_amount > 0)<div class="text-xs text-slate-400">{{ __('منها ضريبة') }} {{ Money::format($e->tax_amount, false) }}</div>@endif</div>
        <div><div class="text-xs text-slate-400">{{ __('التاريخ') }}</div><div class="mt-1 font-bold">{{ Carbon::parse($e->entry_date)->format('Y-m-d') }}</div></div>
        <div><div class="text-xs text-slate-400">{{ __('نوع المصروف / الخزنة') }}</div><div class="mt-1 font-bold">{{ $e->category_name }} · {{ $e->account_name }}</div></div>
        <div><div class="text-xs text-slate-400">{{ __('طريقة التقسيم') }}</div><div class="mt-1 font-bold">{{ __($methods[$e->method] ?? $e->method) }}</div></div>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-extrabold text-slate-900">{{ __('نصيب كل عربية') }} ({{ $parts->count() }})</h2></div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('العربية') }}</th><th class="px-3 py-3 text-start">{{ __('تكلفتها') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('الضريبة') }}</th><th class="px-6 py-3 text-start">{{ __('الإجمالي') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($parts as $p)
                    <tr>
                        <td class="px-6 py-3"><a href="{{ route('admin.vehicles.show', $p->vehicle_id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $p->brand_name }} {{ $p->model_name }} {{ $p->manufacturing_year }}</a> <span class="font-mono text-xs text-slate-400">{{ $p->vehicle_code }}</span></td>
                        <td class="px-3 py-3 text-slate-500">{{ Money::format($p->total_cost, false) }}</td>
                        <td class="px-3 py-3">{{ Money::format($p->amount, false) }}</td>
                        <td class="px-3 py-3 text-slate-500">{{ Money::format($p->tax_amount, false) }}</td>
                        <td class="px-6 py-3 font-extrabold">{{ Money::format($p->total_amount, false) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="border-t border-slate-100 bg-slate-50 px-6 py-3 text-xs text-slate-500">{{ __('كل جزء متسجل مصروف على عربيته ← بيظهر في تقرير ربح كل عربية وفي دفتر الخزنة. للتعديل: امسح المصروف المشترك وسجّله تاني.') }} · {{ __('سجّله') }} {{ $e->user_name ?? '—' }}</p>
    </section>
@endsection
