@extends('admin.layouts.app')

@section('title', __('كشف حساب: ').$account->name_ar)
@section('subtitle', __('من ').$from.__(' لحد ').$to)

@php
    use App\Support\Money;
    use App\Support\Ledger;
    use Illuminate\Support\Carbon;
    $inT = $rows->sum('amount_in'); $outT = $rows->sum('amount_out');
    $kindTone = ['sale_payment' => 'bg-emerald-50 text-emerald-700', 'revenue' => 'bg-emerald-50 text-emerald-700', 'asset_sale' => 'bg-emerald-50 text-emerald-700', 'transfer_in' => 'bg-blue-50 text-blue-700',
                 'dealer_payment' => 'bg-violet-50 text-violet-700', 'supplier_payment' => 'bg-amber-50 text-amber-700', 'expense' => 'bg-rose-50 text-rose-700', 'asset_purchase' => 'bg-slate-100 text-slate-700', 'transfer_out' => 'bg-blue-50 text-blue-700'];
    $link = function ($r) {
        return match ($r->kind) {
            'expense', 'revenue' => route('admin.cash-entries.receipt', $r->source_id),
            'sale_payment' => route('admin.sale-payments.receipt', $r->source_id),
            'dealer_payment' => route('admin.dealer-payments.receipt', $r->source_id),
            default => null,
        };
    };
@endphp

@section('actions')
    <button type="button" onclick="window.print()" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 print:hidden">{{ __('🖨️ طباعة') }}</button>
    <a href="{{ route('admin.accounts.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 print:hidden">{{ __('كل الحسابات') }}</a>
@endsection

@section('content')
    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 print:hidden">
        <input type="date" name="from" value="{{ $from }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
        <input type="date" name="to" value="{{ $to }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
        <select name="kind" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">{{ __('كل الحركات') }}</option>
            @foreach (Ledger::KINDS as $k => $l) <option value="{{ $k }}" @selected($kind === $k)>{{ $l }}</option> @endforeach
        </select>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">{{ __('عرض') }}</button>
        @foreach (['اليوم' => [today(), today()], 'الشهر ده' => [now()->startOfMonth(), today()], 'الشهر اللي فات' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'السنة' => [now()->startOfYear(), today()]] as $l => [$a, $b])
            <a href="{{ route('admin.accounts.show', [$account->id, 'from' => $a->toDateString(), 'to' => $b->toDateString()]) }}" class="self-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-200">{{ $l }}</a>
        @endforeach
    </form>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('رصيد أول المدة') }}</div><div class="mt-1 text-xl font-extrabold">{{ Money::format($opening, false) }}</div></div>
        <div class="rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><div class="text-sm text-emerald-700">{{ __('+ دخل') }}</div><div class="mt-1 text-xl font-extrabold text-emerald-800">{{ Money::format($inT, false) }}</div></div>
        <div class="rounded-2xl bg-rose-50 p-5 ring-1 ring-rose-200"><div class="text-sm text-rose-700">{{ __('− طلع') }}</div><div class="mt-1 text-xl font-extrabold text-rose-800">{{ Money::format($outT, false) }}</div></div>
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg"><div class="text-sm text-slate-400">{{ __('رصيد آخر المدة') }}</div><div class="mt-1 text-xl font-extrabold">{{ $closing !== null ? Money::format($closing, false) : '—' }}</div></div>
    </div>

    @if ($byKind->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($byKind as $k => $v)
                <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $kindTone[$k] ?? 'bg-slate-100' }}">{{ Ledger::KINDS[$k] ?? $k }}: {{ Money::format($v['in'] + $v['out'], false) }} ({{ $v['count'] }})</span>
            @endforeach
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr><th class="px-5 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('النوع') }}</th><th class="px-3 py-3 text-start">{{ __('البيان') }}</th><th class="px-3 py-3 text-start">{{ __('دخل') }}</th><th class="px-3 py-3 text-start">{{ __('طلع') }}</th><th class="px-5 py-3 text-start">{{ __('الرصيد') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr class="bg-slate-50/60 text-xs font-bold text-slate-500"><td class="px-5 py-2" colspan="5">{{ __('رصيد أول المدة') }}</td><td class="px-5 py-2">{{ Money::format($opening, false) }}</td></tr>
                @forelse ($rows as $r)
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-5 py-2.5">{{ Carbon::parse($r->entry_date)->format('Y-m-d') }}</td>
                        <td class="px-3 py-2.5"><span class="rounded-md px-2 py-0.5 text-xs font-bold {{ $kindTone[$r->kind] ?? '' }}">{{ Ledger::KINDS[$r->kind] ?? $r->kind }}</span></td>
                        <td class="px-3 py-2.5 text-xs">
                            @if ($url = $link($r)) <a href="{{ $url }}" target="_blank" class="font-mono font-bold text-blue-700 hover:underline">{{ $r->ref }}</a> @else <span class="font-mono font-bold">{{ $r->ref }}</span> @endif
                            <span class="text-slate-500">{{ $r->description }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 font-bold text-emerald-700">{{ (float) $r->amount_in ? Money::format($r->amount_in, false) : '' }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 font-bold text-rose-600">{{ (float) $r->amount_out ? Money::format($r->amount_out, false) : '' }}</td>
                        <td class="whitespace-nowrap px-5 py-2.5 font-extrabold {{ $r->running < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $kind ? '' : Money::format($r->running, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">{{ __('مفيش حركات في المدة دي') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
