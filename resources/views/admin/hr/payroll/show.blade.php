@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use App\Support\Payroll;
    use App\Http\Controllers\Admin\Hr\PayrollController as PC;
    $draft = $run->status === 'draft';
    $steps = ['draft' => __('① مراجعة'), 'approved' => __('② معتمد'), 'paid' => __('③ اتصرف')];
    $idx = array_search($run->status, array_keys($steps));
    $t = fn ($k) => $rows->sum($k);
    $dlg = 'm-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm';
@endphp

@section('title', __('مرتبات ').$run->label)
@section('subtitle', $rows->count().__(' موظف'))

@section('actions')
    <a href="{{ route('admin.hr.payroll.sheet', $run->id) }}" target="_blank" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">{{ __('🖨️ كشف المرتبات') }}</a>
    @if ($rows->where('payment_method', 'bank')->isNotEmpty())
        <a href="{{ route('admin.hr.payroll.bank-file', $run->id) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">{{ __('🏦 ملف البنك') }}</a>
    @endif
    @if ($draft)
        <form method="POST" action="{{ route('admin.hr.payroll.generate', $run->id) }}">@csrf<button class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-violet-700 ring-1 ring-violet-200">{{ __('🔄 إعادة حساب') }}</button></form>
        <form method="POST" action="{{ route('admin.hr.payroll.approve', $run->id) }}" onsubmit="{{ __('return confirm(\'اعتماد المرتبات؟ أقساط السلف هتتخصم.\')') }}">@csrf @method('PATCH')<button class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white">{{ __('✓ اعتماد') }}</button></form>
    @elseif ($run->status === 'approved')
        <button type="button" data-open="payModal" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{{ __('💸 صرف المرتبات') }}</button>
        <form method="POST" action="{{ route('admin.hr.payroll.unapprove', $run->id) }}" onsubmit="{{ __('return confirm(\'رجوع لمسودة؟\')') }}">@csrf @method('PATCH')<button class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-600 ring-1 ring-slate-200">{{ __('فك الاعتماد') }}</button></form>
    @else
        <form method="POST" action="{{ route('admin.hr.payroll.unpay', $run->id) }}" onsubmit="{{ __('return confirm(\'إلغاء الصرف؟ الفلوس هترجع للخزائن.\')') }}">@csrf @method('PATCH')<button class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-rose-600 ring-1 ring-rose-200">{{ __('إلغاء الصرف') }}</button></form>
    @endif
@endsection

@section('content')
    @include('admin.hr.partials-tabs')

    <div class="mb-5 flex gap-2">
        @foreach ($steps as $k => $l)
            <div class="flex-1 rounded-xl px-4 py-2.5 text-center text-sm font-bold {{ $loop->index <= $idx ? 'bg-violet-600 text-white' : 'bg-white text-slate-400 ring-1 ring-slate-200' }}">{{ $l }}</div>
        @endforeach
    </div>

    @foreach ($warnings as $w) <div class="mb-3 rounded-2xl bg-amber-50 px-5 py-3 text-sm font-bold text-amber-800 ring-1 ring-amber-200">⚠️ {{ $w }}</div> @endforeach

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg"><div class="text-sm text-slate-400">{{ __('الصافي') }}</div><div class="mt-1 truncate text-2xl font-extrabold">{{ Money::format($t('net'), false) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('💵 كاش (جهّزه)') }}</div><div class="mt-1 truncate text-xl font-extrabold">{{ Money::format($byMethod['cash'], false) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('🏦 تحويلات') }}</div><div class="mt-1 truncate text-xl font-extrabold">{{ Money::format($byMethod['bank'], false) }}</div></div>
        <div class="rounded-2xl bg-sky-50 p-5 ring-1 ring-sky-200"><div class="text-sm text-sky-700">{{ __('أوفر تايم') }}</div><div class="mt-1 truncate text-xl font-extrabold text-sky-800">{{ Money::format($t('overtime_amount'), false) }}</div></div>
        <div class="rounded-2xl bg-fuchsia-50 p-5 ring-1 ring-fuchsia-200"><div class="text-sm text-fuchsia-700">{{ __('عمولات') }}</div><div class="mt-1 truncate text-xl font-extrabold text-fuchsia-800">{{ Money::format($t('commission'), false) }}</div></div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('الموظف') }}</th><th class="px-2 py-3">{{ __('أيام') }}</th><th class="px-2 py-3">{{ __('الأساسي') }}</th><th class="px-2 py-3">{{ __('بدلات') }}</th>
                        <th class="px-2 py-3 text-sky-600">{{ __('أوفر') }}</th><th class="px-2 py-3 text-fuchsia-600">{{ __('عمولة') }}</th><th class="px-2 py-3 text-emerald-600">{{ __('حوافز') }}</th>
                        <th class="px-2 py-3 text-rose-600">{{ __('خصومات') }}</th><th class="px-2 py-3">{{ __('سلفة') }}</th><th class="px-3 py-3">{{ __('الصافي') }}</th><th class="px-2 py-3">{{ __('القبض') }}</th><th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $p)
                        @php $ded = $p->absence_deduction + $p->fixed_deductions + $p->adjustment_deductions + $p->insurance_deduction + $p->other_deductions; @endphp
                        <tr class="text-center hover:bg-slate-50/70 {{ $p->net < 0 ? 'bg-rose-50/60' : '' }}">
                            <td class="px-4 py-2.5 text-start"><a href="{{ route('admin.hr.employees.show', $p->employee_id) }}" class="font-bold text-slate-800 hover:text-violet-700">{{ \App\Support\Lang::pick($p, 'full_name') }}</a><div class="text-[11px] text-slate-400">{{ $p->job_title }}{{ $p->pay_basis === 'daily' ? __(' · يومية') : '' }}</div></td>
                            <td class="px-2 py-2.5 text-xs">{{ (float) $p->days_worked }}@if ((float) $p->absent_days) <div class="text-rose-500">{{ __('غ') }} {{ (float) $p->absent_days }}</div> @endif</td>
                            <td class="px-2 py-2.5">{{ Money::format($p->basic, false) }}</td>
                            <td class="px-2 py-2.5">{{ (float) $p->allowances ? Money::format($p->allowances, false) : '—' }}</td>
                            <td class="px-2 py-2.5 text-sky-700">{{ (float) $p->overtime_amount ? Money::format($p->overtime_amount, false) : '—' }}@if ((float) $p->overtime_hours) <div class="text-[10px]">{{ (float) $p->overtime_hours }} {{ __('س') }}</div> @endif</td>
                            <td class="px-2 py-2.5 text-fuchsia-700">{{ (float) $p->commission ? Money::format($p->commission, false) : '—' }}</td>
                            <td class="px-2 py-2.5 text-emerald-700">{{ ($p->bonuses + $p->other_additions) ? Money::format($p->bonuses + $p->other_additions, false) : '—' }}</td>
                            <td class="px-2 py-2.5 text-rose-600">{{ $ded ? Money::format($ded, false) : '—' }}</td>
                            <td class="px-2 py-2.5 text-amber-700">{{ (float) $p->loan_deduction ? Money::format($p->loan_deduction, false) : '—' }}</td>
                            <td class="px-3 py-2.5 text-base font-extrabold {{ $p->net < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ Money::format($p->net, false) }}</td>
                            <td class="px-2 py-2.5 text-xs">{{ $p->payment_method === 'bank' ? '🏦' : '💵' }}@if ($p->paid_date) <div class="text-emerald-600">✓</div> @endif</td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                <a href="{{ route('admin.hr.payroll.payslip', $p->id) }}" target="_blank" title="{{ __('القسيمة') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-violet-600 hover:bg-violet-50">🧾</a>
                                @if ($draft)
                                    <button type="button" title="{{ __('تعديل') }}" data-edit="{{ json_encode(['url' => route('admin.hr.payroll.row', $p->id), 'name' => $p->full_name_ar, 'other_additions' => (float) $p->other_additions, 'other_deductions' => (float) $p->other_deductions, 'commission' => (float) $p->commission, 'overtime_amount' => (float) $p->overtime_amount, 'payment_method' => $p->payment_method, 'notes' => $p->notes], JSON_UNESCAPED_UNICODE) }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100">✏️</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-center text-xs font-extrabold">
                    <tr>
                        <td class="px-4 py-3 text-start">{{ __('الإجمالي') }}</td><td></td><td>{{ Money::format($t('basic'), false) }}</td><td>{{ Money::format($t('allowances'), false) }}</td>
                        <td class="text-sky-700">{{ Money::format($t('overtime_amount'), false) }}</td><td class="text-fuchsia-700">{{ Money::format($t('commission'), false) }}</td>
                        <td class="text-emerald-700">{{ Money::format($t('bonuses') + $t('other_additions'), false) }}</td>
                        <td class="text-rose-600">{{ Money::format($t('absence_deduction') + $t('fixed_deductions') + $t('adjustment_deductions') + $t('insurance_deduction') + $t('other_deductions'), false) }}</td>
                        <td class="text-amber-700">{{ Money::format($t('loan_deduction'), false) }}</td><td class="text-base">{{ Money::format($t('net'), false) }}</td><td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @if ($draft) <p class="mt-3 text-xs text-slate-400">{{ __('💡 سجّلت حضور أو حافز بعد الحساب؟ دوس "إعادة حساب" — التعديلات اليدوية بتفضل زي ما هي.') }}</p> @endif

    @if ($draft)
        <dialog id="rowModal" class="{{ $dlg }}">
            <form method="POST">
                @csrf @method('PUT')
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><h2 class="text-lg font-extrabold" data-name></h2><button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button></div>
                <div class="grid grid-cols-2 gap-4 px-6 py-5">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'r_', 'name' => 'overtime_amount', 'label' => __('الأوفر تايم'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'r_', 'name' => 'commission', 'label' => __('العمولة'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'r_', 'name' => 'other_additions', 'label' => __('+ إضافة يدوي'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'r_', 'name' => 'other_deductions', 'label' => __('− خصم يدوي'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                    <div class="col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'r_', 'name' => 'payment_method', 'label' => __('القبض الشهر ده'), 'type' => 'select', 'options' => Payroll::PAY_METHOD])</div>
                    <div class="col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'r_', 'name' => 'notes', 'label' => __('ملاحظة على القسيمة')])</div>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button><button class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button></div>
            </form>
        </dialog>
    @endif

    @if ($run->status === 'approved')
        <dialog id="payModal" class="{{ $dlg }}">
            <form method="POST" action="{{ route('admin.hr.payroll.pay', $run->id) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf @method('PATCH')
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><h2 class="text-lg font-extrabold">{{ __('💸 صرف مرتبات') }} {{ $run->label }}</h2><button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button></div>
                <div class="space-y-4 px-6 py-5">
                    @if ($byMethod['cash'] > 0)
                        <div class="rounded-xl bg-slate-50 p-3 text-sm">{{ __('💵 كاش:') }} <b>{{ Money::format($byMethod['cash']) }}</b></div>
                        @include('admin.partials.field', ['model' => null, 'name' => 'cash_account_id', 'label' => __('من الخزنة'), 'type' => 'select', 'options' => $accounts, 'empty' => __('— اختار —'), 'value' => $accountTypes->search('cash') ?: null])
                    @endif
                    @if ($byMethod['bank'] > 0)
                        <div class="rounded-xl bg-slate-50 p-3 text-sm">{{ __('🏦 تحويلات:') }} <b>{{ Money::format($byMethod['bank']) }}</b></div>
                        @include('admin.partials.field', ['model' => null, 'name' => 'bank_account_id', 'label' => __('من الحساب البنكي'), 'type' => 'select', 'options' => $accounts, 'empty' => __('— اختار —'), 'value' => $accountTypes->search('bank') ?: null])
                    @endif
                    @include('admin.partials.field', ['model' => null, 'name' => 'paid_date', 'label' => __('تاريخ الصرف'), 'type' => 'date', 'required' => true, 'value' => today()->format('Y-m-d')])
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button><button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('تأكيد الصرف') }}</button></div>
            </form>
        </dialog>
    @endif

    <script>
        (function () {
            document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => document.getElementById(b.dataset.open)?.showModal()));
            document.querySelectorAll('dialog').forEach((d) => {
                d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
                d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
            });
            const rm = document.getElementById('rowModal');
            document.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
                const v = JSON.parse(b.dataset.edit), f = rm.querySelector('form');
                f.action = v.url; rm.querySelector('[data-name]').textContent = v.name;
                ['other_additions', 'other_deductions', 'commission', 'overtime_amount', 'payment_method', 'notes'].forEach((k) => { f[k].value = v[k] ?? ''; f[k].dispatchEvent(new Event('change')); });
                rm.showModal();
            }));
            @if ($errors->any() && $run->status === 'approved') document.getElementById('payModal')?.showModal(); @endif
        })();
    </script>
@endsection
