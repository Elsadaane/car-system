@extends('admin.layouts.app')

@section('title', __('مدفوعات عقود التفويض'))
@section('subtitle', __('كل اللي المعرض دفعه لأصحاب العربيات'))

@php use App\Support\Money; use Illuminate\Support\Carbon; @endphp

@section('actions')
    <a href="{{ route('admin.dealer-contracts.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('عقود التفويض') }}</a>
@endsection

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg">
            <div class="text-sm text-slate-400">{{ __('إجمالي المدفوع') }} {{ array_filter($filters) ? __('(حسب الفلتر)') : '' }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold">{{ Money::format($total, false) }}</div>
            <div class="text-xs text-slate-400">{{ Money::currency() }}</div>
        </div>
        @foreach ($byMethod->take(3) as $m)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-sm text-slate-500">{{ $m->method_name ?? '—' }}</div>
                <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">{{ Money::format($m->total, false) }}</div>
                <div class="text-xs text-slate-400">{{ $m->cnt }} {{ __('دفعة ·') }} {{ $total > 0 ? round($m->total / $total * 100) : 0 }}%</div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('رقم العقد، المالك، التليفون، رقم الإيصال...') }}"
               class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <select name="method" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل طرق الدفع') }}</option>
            @foreach ($methods as $id => $n) <option value="{{ $id }}" @selected($filters['method'] === $id)>{{ $n }}</option> @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        @if (array_filter($filters)) <a href="{{ route('admin.dealer-payments.index') }}" class="self-center text-sm text-slate-500 hover:text-slate-900">{{ __('مسح') }}</a> @endif
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($payments->isEmpty())
            <p class="px-6 py-16 text-center font-bold text-slate-600">{{ __('مفيش مدفوعات') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('التاريخ') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المالك') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('وذلك عن') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الطريقة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المبلغ') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $p)
                            <tr class="hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-3">
                                    <div class="font-semibold text-slate-800">{{ Carbon::parse($p->payment_date)->format('Y-m-d') }}</div>
                                    <div class="font-mono text-[11px] text-slate-400">{{ $p->payment_reference }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $p->supplier_name }}</div>
                                    <a href="tel:{{ $p->supplier_phone }}" class="text-xs text-blue-600" dir="ltr">{{ $p->supplier_phone }}</a>
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.dealer-contracts.show', $p->contract_id) }}" class="font-mono text-xs font-bold text-blue-700 hover:underline">{{ $p->contract_number }}</a>
                                    <div class="text-xs text-slate-500">{{ $p->brand_name }} {{ $p->model_name }}</div>
                                </td>
                                <td class="px-3 py-3"><span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $p->method_name }}</span></td>
                                <td class="whitespace-nowrap px-3 py-3 font-extrabold text-emerald-700">{{ Money::format($p->amount, false) }}</td>
                                <td class="px-5 py-3 text-end">
                                    <a href="{{ route('admin.dealer-payments.receipt', $p) }}" target="_blank" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100">{{ __('إيصال') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages()) <div class="border-t border-slate-100 p-4">{{ $payments->links() }}</div> @endif
        @endif
    </div>
@endsection
