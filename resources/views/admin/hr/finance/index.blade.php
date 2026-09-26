@extends('admin.layouts.app')

@section('title', __('السلف والحوافز والخصومات'))
@section('subtitle', __('السلفة بتتقسط من المرتب لوحدها، والحافز والخصم بينزلوا في مرتب الشهر'))

@php
    use App\Support\Money;
    use App\Support\Payroll;
    use App\Http\Controllers\Admin\Hr\PayrollController as PC;
    $dlg = 'm-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm';
    $active = $loans->where('status', 'active');
    $empOpts = $employees->pluckLocal('full_name', 'id');
    $rates = $employees->mapWithKeys(fn ($e) => [$e->id => round(Payroll::dailyRate($e), 3)]);
@endphp

@section('actions')
    <button type="button" data-open="loanModal" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('💵 سلفة') }}</button>
    <button type="button" data-open="adjModal" data-type="bonus" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">{{ __('🎁 حافز') }}</button>
    <button type="button" data-open="adjModal" data-type="deduction" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700">{{ __('⚠️ خصم / جزاء') }}</button>
@endsection

@section('content')
    @include('admin.hr.partials-tabs')

    <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200"><div class="text-sm text-amber-700">{{ __('سلف مفتوحة') }}</div><div class="mt-1 text-2xl font-extrabold text-amber-800">{{ $active->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('باقي على الموظفين') }}</div><div class="mt-1 truncate text-2xl font-extrabold">{{ Money::format($active->sum(fn ($l) => $l->amount - $l->paid_amount), false) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('أقساط الشهر الجاي') }}</div><div class="mt-1 truncate text-2xl font-extrabold">{{ Money::format($active->sum(fn ($l) => min($l->monthly_installment, $l->amount - $l->paid_amount)), false) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('حوافز/خصومات مستنية المرتب') }}</div><div class="mt-1 text-2xl font-extrabold">{{ $adjustments->whereNull('payroll_run_id')->count() }}</div></div>
    </div>

    <div class="mb-4 flex rounded-xl bg-white p-1 ring-1 ring-slate-200" style="width: fit-content">
        <a href="{{ route('admin.hr.finance.index') }}" class="rounded-lg px-4 py-2 text-sm font-bold {{ $tab === 'loans' ? 'bg-slate-900 text-white' : 'text-slate-500' }}">{{ __('السلف') }}</a>
        <a href="{{ route('admin.hr.finance.index', ['tab' => 'adjustments']) }}" class="rounded-lg px-4 py-2 text-sm font-bold {{ $tab === 'adjustments' ? 'bg-slate-900 text-white' : 'text-slate-500' }}">{{ __('الحوافز والخصومات') }}</a>
    </div>

    @if ($tab === 'loans')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($loans as $l)
                @php $pct = $l->amount > 0 ? min(100, $l->paid_amount / $l->amount * 100) : 0; $left = $l->amount - $l->paid_amount; $months = $l->monthly_installment > 0 ? (int) ceil($left / $l->monthly_installment) : 0; @endphp
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 {{ $l->status !== 'active' ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between">
                        <div><a href="{{ route('admin.hr.employees.show', $l->employee_id) }}" class="font-extrabold text-slate-900 hover:text-violet-700">{{ \App\Support\Lang::pick($l, 'full_name') }}</a><div class="text-xs text-slate-400">{{ $l->loan_date }}{{ $l->account_name ? __(' · من ').$l->account_name : '' }}</div></div>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ ['active' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-slate-100 text-slate-500'][$l->status] }}">{{ ['active' => __('شغالة'), 'completed' => __('خلصت ✓'), 'cancelled' => __('موقوفة')][$l->status] }}</span>
                    </div>
                    <div class="mt-3 text-2xl font-extrabold">{{ Money::format($l->amount, false) }}</div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-amber-500" style="width: {{ $pct }}%"></div></div>
                    <div class="mt-1.5 flex justify-between text-xs text-slate-500"><span>{{ __('اتسدد') }} {{ Money::format($l->paid_amount, false) }}</span><span>{{ __('قسط') }} {{ Money::format($l->monthly_installment, false) }}{{ $l->status === 'active' ? __(' · فاضل ').$months.__(' شهر') : '' }}</span></div>
                    @if ($l->notes) <p class="mt-2 text-xs text-slate-500">{{ $l->notes }}</p> @endif
                    @if ($l->status === 'active')
                        <form method="POST" action="{{ route('admin.hr.loans.cancel', $l->id) }}" class="mt-3" onsubmit="{{ __('return confirm(\'إيقاف السلفة؟ الباقي مش هيتخصم.\')') }}">@csrf @method('PATCH')<button class="text-xs font-bold text-rose-600 hover:underline">{{ __('إيقاف') }}</button></form>
                    @endif
                </div>
            @empty
                <div class="col-span-full rounded-2xl bg-white py-16 text-center text-slate-400 shadow-sm ring-1 ring-slate-200">{{ __('مفيش سلف') }}</div>
            @endforelse
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('الموظف') }}</th><th class="px-3 py-3 text-start">{{ __('البيان') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('نزل في مرتب') }}</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($adjustments as $a)
                        <tr>
                            <td class="px-5 py-2.5 text-xs">{{ $a->date }}</td>
                            <td class="px-3 py-2.5 font-bold">{{ \App\Support\Lang::pick($a, 'full_name') }}</td>
                            <td class="px-3 py-2.5">{{ $a->type === 'bonus' ? '🎁' : '⚠️' }} {{ $a->reason }}@if ($a->days) <span class="text-xs text-slate-400">({{ (float) $a->days }} {{ __('يوم)') }}</span> @endif</td>
                            <td class="px-3 py-2.5 font-extrabold {{ $a->type === 'bonus' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $a->type === 'bonus' ? '+' : '−' }} {{ Money::format($a->amount, false) }}</td>
                            <td class="px-3 py-2.5 text-xs">{{ $a->run_month ? PC::MONTHS[$a->run_month].' '.$a->run_year.' ('.PC::RUN_STATUS[$a->run_status].')' : __('لسه') }}</td>
                            <td class="px-5 py-2.5 text-end">@if (! $a->run_status || $a->run_status === 'draft') <form method="POST" action="{{ route('admin.hr.adjustments.destroy', $a->id) }}" onsubmit="{{ __('return confirm(\'مسح؟\')') }}">@csrf @method('DELETE')<button class="text-xs text-rose-500">🗑️</button></form> @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">{{ __('مفيش حوافز أو خصومات') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <dialog id="loanModal" class="{{ $dlg }}">
        <form method="POST" action="{{ route('admin.hr.loans.store') }}">
            @csrf
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><h2 class="text-lg font-extrabold">{{ __('💵 سلفة جديدة') }}</h2><button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button></div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'l_', 'name' => 'employee_id', 'label' => __('الموظف'), 'type' => 'select', 'options' => $empOpts, 'empty' => __('— اختار —'), 'required' => true])
                <div class="grid grid-cols-2 gap-4">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'l_', 'name' => 'amount', 'label' => __('المبلغ'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'l_', 'name' => 'monthly_installment', 'label' => __('القسط الشهري'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'l_', 'name' => 'loan_date', 'label' => __('تاريخ الصرف'), 'type' => 'date', 'required' => true, 'value' => today()->format('Y-m-d')])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'l_', 'name' => 'start_month', 'label' => __('أول خصم في شهر'), 'type' => 'month', 'required' => true, 'value' => now()->format('Y-m')])
                </div>
                <p data-months class="hidden rounded-xl bg-amber-50 px-3 py-2 text-sm font-bold text-amber-800"></p>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'l_', 'name' => 'financial_account_id', 'label' => __('اتصرفت من'), 'type' => 'select', 'options' => $accounts, 'empty' => __('مش هتتخصم من خزنة')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'l_', 'name' => 'notes', 'label' => __('ملاحظات')])
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button><button class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ السلفة') }}</button></div>
        </form>
    </dialog>

    <dialog id="adjModal" class="{{ $dlg }}">
        <form method="POST" action="{{ route('admin.hr.adjustments.store') }}">
            @csrf <input type="hidden" name="type" value="bonus">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><h2 data-title class="text-lg font-extrabold">{{ __('حافز') }}</h2><button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button></div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'employee_id', 'label' => __('الموظف'), 'type' => 'select', 'options' => $empOpts, 'empty' => __('— اختار —'), 'required' => true])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'reason', 'label' => __('السبب'), 'required' => true, 'list' => 'adjReasons'])
                <datalist id="adjReasons"><option value="حافز مبيعات"><option value="حافز تميز"><option value="غياب بدون إذن"><option value="تأخير متكرر"><option value="تلفيات"><option value="مخالفة مرورية"></datalist>
                <div class="grid grid-cols-2 gap-4">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'amount', 'label' => __('المبلغ'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                    <div data-days-box>@include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'days', 'label' => __('أو بالأيام'), 'type' => 'number', 'step' => '0.25', 'min' => 0, 'hint' => __('بيتحسب من أجر يومه')])</div>
                </div>
                <p data-calc class="hidden rounded-xl bg-rose-50 px-3 py-2 text-sm font-bold text-rose-700"></p>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'date', 'label' => __('التاريخ'), 'type' => 'date', 'required' => true, 'value' => today()->format('Y-m-d'), 'hint' => __('بينزل في مرتب الشهر اللي بعده أو فيه')])
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button><button data-submit class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button></div>
        </form>
    </dialog>

    <script>
        (function () {
            const rates = @json($rates);
            document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => {
                const d = document.getElementById(b.dataset.open);
                if (b.dataset.type) {
                    const f = d.querySelector('form'), bonus = b.dataset.type === 'bonus';
                    f.type.value = b.dataset.type;
                    d.querySelector('[data-title]').textContent = bonus ? @js(__('🎁 حافز')) : @js(__('⚠️ خصم / جزاء'));
                    d.querySelector('[data-submit]').className = 'rounded-xl px-5 py-2.5 text-sm font-bold text-white ' + (bonus ? 'bg-emerald-600' : 'bg-rose-600');
                    d.querySelector('[data-days-box]').style.display = bonus ? 'none' : '';
                }
                d.showModal();
            }));
            document.querySelectorAll('dialog').forEach((d) => {
                d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
                d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
            });
            const af = document.querySelector('#adjModal form'), out = af.querySelector('[data-calc]');
            const calc = () => {
                const r = rates[af.employee_id.value] || 0, days = Number(af.days.value || 0);
                if (!days || !r) { out.classList.add('hidden'); return; }
                out.textContent = `${days} يوم × ${r} = ${(days * r).toFixed(3)}`; out.classList.remove('hidden');
            };
            af.days.addEventListener('input', calc); af.employee_id.addEventListener('change', calc);
            const lf = document.querySelector('#loanModal form'), lm = lf.querySelector('[data-months]');
            const months = () => {
                const a = Number(lf.amount.value || 0), m = Number(lf.monthly_installment.value || 0);
                if (!a || !m) { lm.classList.add('hidden'); return; }
                lm.textContent = `هتخلص في ${Math.ceil(a / m)} شهر`; lm.classList.remove('hidden');
            };
            lf.amount.addEventListener('input', months); lf.monthly_installment.addEventListener('input', months);
        })();
    </script>
@endsection
