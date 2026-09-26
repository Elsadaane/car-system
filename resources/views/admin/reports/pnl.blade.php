@extends('admin.layouts.app')

@section('title', __('قائمة الأرباح والخسائر'))
@section('subtitle', __('كسبت كام بالظبط في المدة دي — ومقارنة بالمدة اللي قبلها'))

@php
    use App\Support\Money;
    $row = function ($label, $a, $b, $strong = false, $neg = false) {
        $pct = (float) $b != 0.0 ? round(($a - $b) / abs($b) * 100) : null;
        return compact('label', 'a', 'b', 'strong', 'neg', 'pct');
    };
    $revNames = $cur['revenues']->pluck('name')->merge($prev['revenues']->pluck('name'))->unique();
    $expNames = $cur['expenses']->pluck('name')->merge($prev['expenses']->pluck('name'))->unique();
    $val = fn ($list, $name) => (float) ($list->firstWhere('name', $name)->total ?? 0);
@endphp

@section('content')
    @include('admin.reports.partials.header')

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl p-6 text-white shadow-lg {{ $cur['net'] >= 0 ? 'bg-gradient-to-br from-emerald-500 to-teal-600' : 'bg-gradient-to-br from-rose-500 to-red-600' }}">
            <div class="text-sm text-white/80">{{ $cur['net'] >= 0 ? __('صافي الربح') : __('صافي الخسارة') }}</div>
            <div class="mt-1 text-3xl font-extrabold">{{ Money::format(abs($cur['net']), false) }}</div>
            <div class="mt-1 text-xs text-white/80">{{ __('هامش') }} {{ $cur['margin'] }}{{ __('% من المبيعات') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('مجمل ربح العربيات') }}</div><div class="mt-1 text-2xl font-extrabold">{{ Money::format($cur['gross'], false) }}</div><div class="text-xs text-slate-400">{{ $cur['count'] }} {{ __('عربية') }}</div></div>
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('المدة اللي قبلها') }}</div><div class="mt-1 text-2xl font-extrabold {{ $prev['net'] < 0 ? 'text-rose-600' : '' }}">{{ Money::format($prev['net'], false) }}</div><div class="text-xs text-slate-400">{{ $prevFrom }} ← {{ $prevTo }}</div></div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-900 text-xs text-white"><tr><th class="px-6 py-3 text-start">{{ __('البند') }}</th><th class="px-4 py-3 text-end">{{ __('المدة دي') }}</th><th class="px-4 py-3 text-end">{{ __('اللي قبلها') }}</th><th class="px-6 py-3 text-end">{{ __('التغيير') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @php
                    $sections = [
                        [__('المبيعات'), [
                            $row(__('مبيعات العربيات (بدون ضريبة)'), $cur['sales'], $prev['sales']),
                            $row(__('− تكلفة العربيات المباعة'), -$cur['cogs'], -$prev['cogs'], false, true),
                            $row(__('− مصروفات على العربيات (صيانة/تجهيز)'), -$cur['car_expenses'], -$prev['car_expenses'], false, true),
                            $row(__('= مجمل الربح'), $cur['gross'], $prev['gross'], true),
                        ]],
                        [__('إيرادات أخرى'), $revNames->map(fn ($n) => $row($n ?? __('بدون تصنيف'), $val($cur['revenues'], $n), $val($prev['revenues'], $n)))->push($row(__('إجمالي الإيرادات الأخرى'), $cur['revenues_total'], $prev['revenues_total'], true))->all()],
                        [__('المصروفات العامة'), $expNames->map(fn ($n) => $row($n ?? __('بدون تصنيف'), -$val($cur['expenses'], $n), -$val($prev['expenses'], $n), false, true))->push($row(__('إجمالي المصروفات'), -$cur['expenses_total'], -$prev['expenses_total'], true, true))->all()],
                        [__('الموظفين'), [$row(__('− المرتبات (شاملة الأوفر تايم والعمولات)'), -$cur['salaries'], -$prev['salaries'], false, true), $row(__('− مكافآت نهاية الخدمة'), -$cur['eos'], -$prev['eos'], false, true)]],
                        [__('أخرى'), [$row(__('− إهلاك الأصول'), -$cur['depreciation'], -$prev['depreciation'], false, true), $row(__('− فوايد تمويل الأصول'), -($cur['finance_cost'] ?? 0), -($prev['finance_cost'] ?? 0), false, true)]],
                    ];
                @endphp
                @foreach ($sections as [$title, $items])
                    <tr class="bg-slate-50"><td colspan="4" class="px-6 py-2 text-xs font-extrabold text-slate-500">{{ $title }}</td></tr>
                    @foreach ($items as $r)
                        <tr class="{{ $r['strong'] ? 'font-extrabold' : '' }}">
                            <td class="px-6 py-2.5 {{ $r['strong'] ? '' : 'ps-10 text-slate-600' }}">{{ $r['label'] }}</td>
                            <td class="px-4 py-2.5 text-end {{ $r['a'] < 0 ? 'text-rose-600' : '' }}">{{ Money::format($r['a'], false) }}</td>
                            <td class="px-4 py-2.5 text-end text-slate-400">{{ Money::format($r['b'], false) }}</td>
                            <td class="px-6 py-2.5 text-end">
                                @if ($r['pct'] !== null)
                                    @php $good = $r['neg'] ? $r['pct'] <= 0 : $r['pct'] >= 0; @endphp
                                    <span class="text-xs font-bold {{ $good ? 'text-emerald-600' : 'text-rose-600' }}">{{ $r['pct'] >= 0 ? '▲' : '▼' }} {{ abs($r['pct']) }}%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
                <tr class="{{ $cur['net'] >= 0 ? 'bg-emerald-50' : 'bg-rose-50' }} text-base font-black">
                    <td class="px-6 py-4">{{ __('= صافي') }} {{ $cur['net'] >= 0 ? __('الربح') : __('الخسارة') }}</td>
                    <td class="px-4 py-4 text-end {{ $cur['net'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ Money::format($cur['net'], false) }}</td>
                    <td class="px-4 py-4 text-end text-slate-400">{{ Money::format($prev['net'], false) }}</td>
                    <td class="px-6 py-4 text-end">@include('admin.reports.partials.delta', ['cur' => $cur['net'], 'prev' => $prev['net']])</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs leading-relaxed text-slate-400">
        {{ __('الأرقام بدون ضريبة. مصروفات العربيات بتتحسب لما العربية تتباع (قبل كده بتبقى جزء من تكلفة المخزون). الضريبة المحصّلة في المدة:') }} {{ Money::format($cur['tax']) }} {{ __('— الخصومات اللي اتعملت:') }} {{ Money::format($cur['discounts']) }}.
    </p>
@endsection
