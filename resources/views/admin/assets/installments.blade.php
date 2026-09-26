@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $today = today();
    $tabs = ['upcoming' => __('⏳ لسه ما اتدفعتش'), 'overdue' => __('🔴 متأخرة'), 'paid' => __('✅ مدفوعة'), 'all' => __('الكل')];
    $accOpts = $accounts->mapWithKeys(fn ($n, $id) => [$id => $n.' ('.Money::format($balances[$id]['balance'] ?? 0, false).')']);
@endphp

@section('title', $asset ? __('أقساط: ').$asset->name_ar : __('أقساط الأصول'))
@section('subtitle', __('الأقساط بتتدفع يدوي من الخزنة اللي تختارها — والرصيد بيتحدّث لوحده'))

@section('actions')
    <a href="{{ route('admin.assets.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('→ الأصول') }}</a>
    @if ($asset)<a href="{{ route('admin.asset-installments.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('كل الأقساط') }}</a>@endif
@endsection

@section('content')
    @if (session('error')) <div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 ring-1 ring-rose-200">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800 ring-1 ring-rose-200">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div> @endif

    @if ($asset && $summary)
        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl bg-violet-600 p-5 text-white shadow-lg md:col-span-2">
                <div class="text-sm text-violet-200">{{ $asset->asset_code }} · {{ $asset->financier_type === 'bank' ? ($asset->lender_name ?: __('بنك')) : __('تقسيط مع المورد') }}</div>
                <div class="mt-1 text-2xl font-extrabold">{{ __('باقي') }} {{ Money::format($summary['remaining']) }}</div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-violet-400/40"><div class="h-full rounded-full bg-white" style="width: {{ $summary['count'] ? round($summary['paid'] / $summary['count'] * 100) : 0 }}%"></div></div>
                <div class="mt-2 text-xs text-violet-100">{{ __('اتدفع') }} {{ $summary['paid'] }} {{ __('من') }} {{ $summary['count'] }} {{ __('قسط (') }}{{ Money::format($summary['paid_sum'], false) }})</div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('التكلفة / المقدم') }}</div><div class="mt-1 text-lg font-extrabold">{{ Money::format($asset->purchase_cost, false) }} / {{ Money::format($asset->down_payment, false) }}</div><div class="text-xs text-slate-400">{{ __('فايدة') }} {{ (float) $asset->interest_rate }}{{ __('% سنوي =') }} {{ Money::format($summary['interest'], false) }}</div></div>
            <div class="flex flex-col justify-center rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                @if ($summary['remaining'] > 0)
                    <button type="button" onclick="document.getElementById('payoffModal').showModal()" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700">{{ __('💰 سداد كل الباقي') }}</button>
                    <p class="mt-2 text-center text-[11px] text-slate-400">{{ __('لو البنك عمل خصم على السداد المبكر اكتب المبلغ الفعلي') }}</p>
                @else
                    <div class="text-center text-lg font-extrabold text-emerald-600">{{ __('✅ الأصل ده خلص أقساطه') }}</div>
                @endif
            </div>
        </div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <a href="?tab=overdue" class="rounded-2xl p-5 shadow-sm ring-1 {{ $alerts['overdue'] ? 'bg-rose-600 text-white ring-rose-600' : 'bg-white ring-slate-200' }}"><div class="text-sm opacity-80">{{ __('🔴 متأخرة') }}</div><div class="mt-1 text-2xl font-extrabold">{{ $alerts['overdue'] }} {{ __('قسط ·') }} {{ Money::format($alerts['overdue_amount'], false) }}</div></a>
            <a href="?tab=upcoming" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('⏰ مستحقة خلال 7 أيام') }}</div><div class="mt-1 text-2xl font-extrabold text-violet-700">{{ $alerts['soon'] }} {{ __('قسط ·') }} {{ Money::format($alerts['soon_amount'], false) }}</div></a>
        </div>
    @endif

    <div class="mb-4 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200 sm:w-fit">
        @foreach ($tabs as $k => $l)
            <a href="{{ route('admin.asset-installments.index', array_filter(['tab' => $k, 'asset' => $asset?->id])) }}" class="rounded-xl px-4 py-2 text-sm font-bold {{ $tab === $k ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900' }}">{{ $l }}</a>
        @endforeach
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200"><div class="text-4xl">📅</div><p class="mt-2 font-bold text-slate-600">{{ __('مفيش أقساط هنا') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('لما تضيف أصل بالتقسيط، جدول أقساطه بيظهر هنا.') }}</p></div>
    @else
        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-xs font-bold text-slate-500">
                    <tr><th class="p-3 text-start">{{ __('الأصل') }}</th><th class="p-3 text-start">{{ __('القسط') }}</th><th class="p-3 text-start">{{ __('الاستحقاق') }}</th><th class="p-3 text-start">{{ __('المبلغ') }}</th><th class="p-3 text-start">{{ __('لمين') }}</th><th class="p-3 text-start">{{ __('الحالة') }}</th><th class="p-3"></th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php
                            $due = Carbon::parse($r->due_date);
                            $late = ! $r->paid_date && $due->lt($today);
                            $soon = ! $r->paid_date && ! $late && $due->lte($today->copy()->addDays(7));
                        @endphp
                        <tr class="border-t border-slate-100 {{ $late ? 'bg-rose-50/60' : '' }}">
                            <td class="p-3"><a href="{{ route('admin.asset-installments.index', ['asset' => $r->asset_id, 'tab' => 'all']) }}" class="font-bold text-slate-900 hover:text-blue-700">{{ $r->asset_name }}</a><div class="font-mono text-[11px] text-slate-400">{{ $r->asset_code }}</div></td>
                            <td class="p-3 font-bold">{{ $r->seq }} / {{ $r->installments_count }}</td>
                            <td class="p-3">
                                <div class="font-bold">{{ $due->format('Y-m-d') }}</div>
                                @if ($late)<div class="text-[11px] font-bold text-rose-600">{{ __('متأخر') }} {{ (int) $due->diffInDays($today) }} {{ __('يوم') }}</div>
                                @elseif ($soon)<div class="text-[11px] font-bold text-violet-600">{{ $due->isToday() ? __('النهارده') : __('بعد ').(int) $today->diffInDays($due).__(' يوم') }}</div>@endif
                            </td>
                            <td class="p-3"><div class="font-extrabold">{{ Money::format($r->amount, false) }}</div>@if ((float) $r->interest > 0)<div class="text-[11px] text-slate-400">{{ __('أصل') }} {{ Money::format($r->principal, false) }} {{ __('+ فايدة') }} {{ Money::format($r->interest, false) }}</div>@endif</td>
                            <td class="p-3 text-xs">{{ $r->financier_type === 'bank' ? '🏦 '.($r->lender_name ?: __('بنك')) : '🏪 '.($r->supplier_name ?: __('المورد')) }}</td>
                            <td class="p-3">
                                @if ($r->paid_date)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">{{ __('✅ اتدفع') }} {{ $r->paid_date }}</span>
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ $r->account_name }}{{ $r->reference ? ' · '.$r->reference : '' }}</div>
                                @else
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $late ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600' }}">{{ $late ? __('متأخر') : __('مستحق') }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-end">
                                @if ($r->paid_date)
                                    <form method="POST" action="{{ route('admin.asset-installments.unpay', $r->id) }}" onsubmit="{{ __('return confirm(\'إلغاء دفع القسط؟ المبلغ هيرجع للخزنة.\')') }}">@csrf @method('PATCH')<button class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-100">{{ __('↩️ إلغاء') }}</button></form>
                                @else
                                    <button type="button" data-pay="{{ route('admin.asset-installments.pay', $r->id) }}" data-info="{{ $r->asset_name }} — قسط {{ $r->seq }} — {{ Money::format($r->amount) }}" class="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">{{ __('💵 دفع') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif

    {{-- دفع قسط --}}
    <dialog id="payModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            @csrf @method('PATCH')
            <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-lg font-extrabold text-slate-900">{{ __('💵 دفع قسط') }}</h2><p class="text-xs text-slate-500" data-info></p></div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'p_', 'name' => 'financial_account_id', 'label' => __('اتدفع من خزنة'), 'type' => 'select', 'options' => $accOpts, 'empty' => __('— اختار —'), 'required' => true])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'p_', 'name' => 'paid_date', 'label' => __('تاريخ الدفع'), 'type' => 'date', 'required' => true, 'value' => $today->toDateString()])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'p_', 'name' => 'reference', 'label' => __('رقم التحويل / الإيصال'), 'dir' => 'ltr'])
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{{ __('تأكيد الدفع') }}</button>
            </div>
        </form>
    </dialog>

    @if ($asset && $summary && $summary['remaining'] > 0)
        <dialog id="payoffModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
            <form method="POST" action="{{ route('admin.assets.payoff', $asset->id) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf
                <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-lg font-extrabold text-slate-900">{{ __('💰 سداد كل الباقي') }}</h2><p class="text-xs text-slate-500">{{ __('الباقي حسب الجدول:') }} {{ Money::format($summary['remaining']) }}</p></div>
                <div class="space-y-4 px-6 py-5">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'po_', 'name' => 'financial_account_id', 'label' => __('اتدفع من خزنة'), 'type' => 'select', 'options' => $accOpts, 'empty' => __('— اختار —'), 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'po_', 'name' => 'paid_date', 'label' => __('تاريخ السداد'), 'type' => 'date', 'required' => true, 'value' => $today->toDateString()])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'po_', 'name' => 'payoff_amount', 'label' => __('المبلغ اللي اتدفع فعلاً'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'value' => $summary['remaining'], 'hint' => __('لو أقل من الباقي، الفرق بيتشال من الفوايد (خصم سداد مبكر)')])
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{{ __('تأكيد السداد') }}</button>
                </div>
            </form>
        </dialog>
    @endif

    <script>
        (function () {
            const d = document.getElementById('payModal'), f = d.querySelector('form');
            document.querySelectorAll('[data-pay]').forEach((b) => b.addEventListener('click', () => {
                f.action = b.dataset.pay; d.querySelector('[data-info]').textContent = b.dataset.info; d.showModal();
            }));
            d.querySelector('[data-close]').addEventListener('click', () => d.close());
            document.querySelectorAll('dialog').forEach((x) => x.addEventListener('click', (e) => { if (e.target === x) x.close(); }));
        })();
    </script>
@endsection
