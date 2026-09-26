@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $canHr = \App\Support\Access::can('hr.edit');
@endphp

@section('title', __('عمولات المندوبين'))
@section('subtitle', __('نفس حسبة المرتبات بالظبط — البيعة بتتحسب لمندوب المبيعات المختار في العقد'))

@section('content')
    <form class="mb-6 flex flex-wrap items-end gap-3">
        <label class="grid gap-1"><span class="text-xs font-bold text-slate-500">{{ __('الشهر') }}</span>
            <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm">
        </label>
        <a href="{{ route('admin.reports.commissions', ['month' => $start->copy()->subMonth()->format('Y-m')]) }}" class="rounded-xl bg-white px-3 py-2.5 text-sm font-bold text-slate-600 ring-1 ring-slate-200">{{ __('← الشهر اللي فات') }}</a>
        @if ($run)
            <a href="{{ route('admin.hr.payroll.show', $run->id) }}" class="ms-auto rounded-xl bg-violet-50 px-4 py-2.5 text-sm font-bold text-violet-700 hover:bg-violet-100">{{ __('مسير مرتبات الشهر ←') }}</a>
        @endif
    </form>

    <div class="mb-6 grid grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-xs font-bold text-slate-500">{{ __('عربيات اتباعت') }}</div><div class="mt-1 text-2xl font-black text-slate-900">{{ $totals['cars'] }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-xs font-bold text-slate-500">{{ __('صافي المبيعات') }}</div><div class="mt-1 text-2xl font-black text-slate-900">{{ Money::format($totals['sales'], false) }}</div></div>
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg"><div class="text-xs font-bold text-slate-400">{{ __('إجمالي العمولات') }}</div><div class="mt-1 text-2xl font-black text-emerald-300">{{ Money::format($totals['commission'], false) }}</div></div>
    </div>

    <div class="space-y-3">
        @forelse ($rows as $r)
            <details class="group overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-4 px-5 py-4 [&::-webkit-details-marker]:hidden">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-600 font-bold text-white">{{ mb_substr($r->name, 0, 1) }}</span>
                    <div class="min-w-[160px] flex-1">
                        <div class="font-extrabold text-slate-900">{{ $r->name }}</div>
                        <div class="text-xs {{ $r->employee && $r->employee->commission_type !== 'none' ? 'text-slate-500' : 'font-bold text-amber-600' }}">
                            @if (! $r->employee) {{ __('مش مربوط بموظف في الموارد البشرية — العمولة مش بتتحسب') }}
                            @elseif ($r->employee->commission_type === 'none') {{ __('مفيش عمولة متحددة له') }}
                            @else {{ __($r->rule) }} @endif
                        </div>
                    </div>
                    <div class="text-center"><div class="text-lg font-black text-slate-900">{{ $r->cars }}</div><div class="text-[11px] text-slate-400">{{ __('عربية') }}</div></div>
                    <div class="text-center"><div class="font-bold text-slate-700">{{ Money::format($r->net_sales, false) }}</div><div class="text-[11px] text-slate-400">{{ __('مبيعات') }}</div></div>
                    <div class="text-center"><div class="font-bold {{ $r->profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ Money::format($r->profit, false) }}</div><div class="text-[11px] text-slate-400">{{ __('ربح') }}</div></div>
                    <div class="min-w-[120px] text-end">
                        <div class="text-xl font-black text-emerald-600">{{ Money::format($r->commission, false) }}</div>
                        <div class="text-[11px] font-bold {{ $r->payroll === 'paid' ? 'text-emerald-600' : ($r->payroll === 'pending' ? 'text-blue-600' : 'text-slate-400') }}">
                            {{ $r->payroll === 'paid' ? __('✓ اتصرفت في المرتب') : ($r->payroll === 'pending' ? __('في مسير المرتبات (لسه ماتصرفش)') : __('لسه ماتحسبتش في مرتب')) }}
                        </div>
                    </div>
                    <span class="text-slate-300 transition group-open:rotate-180">▾</span>
                </summary>
                <div class="border-t border-slate-100">
                    @if ($r->employee && $canHr && $r->employee->commission_type === 'none')
                        <a href="{{ route('admin.hr.employees.edit', $r->employee->id) }}" class="block bg-amber-50 px-5 py-2 text-xs font-bold text-amber-800">{{ __('حدد نظام عمولته من ملف الموظف ←') }}</a>
                    @endif
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-2 text-start">{{ __('العقد') }}</th><th class="px-3 py-2 text-start">{{ __('العربية') }}</th><th class="px-3 py-2 text-start">{{ __('العميل') }}</th><th class="px-3 py-2 text-start">{{ __('صافي البيع') }}</th><th class="px-3 py-2 text-start">{{ __('الربح') }}</th><th class="px-5 py-2 text-end">{{ __('العمولة') }}</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($r->details as $d)
                                    <tr>
                                        <td class="px-5 py-2"><a href="{{ route('admin.sales-contracts.show', $d->id) }}" class="font-mono text-xs font-bold text-blue-600">{{ $d->contract_number }}</a><div class="text-[11px] text-slate-400">{{ Carbon::parse($d->contract_date)->format('Y-m-d') }}</div></td>
                                        <td class="px-3 py-2">{{ $d->brand_name }} {{ $d->model_name }} {{ $d->manufacturing_year }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $d->customer_name }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ Money::format($d->net_sale, false) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 {{ $d->profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ Money::format($d->profit, false) }}</td>
                                        <td class="whitespace-nowrap px-5 py-2 text-end font-bold">{{ isset($d->commission) ? Money::format($d->commission, false) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        @empty
            <div class="rounded-2xl bg-white px-6 py-16 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">{{ __('مفيش مبيعات في الشهر ده.') }}</div>
        @endforelse
    </div>
    <p class="mt-4 text-xs text-slate-400">{{ __('💡 نظام العمولة (نسبة من البيعة، نسبة من الربح، أو مبلغ لكل عربية) بيتحدد لكل موظف من ملفه في الموارد البشرية، وبينزل في مرتب الشهر لوحده.') }}</p>
@endsection
