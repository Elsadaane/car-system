@extends('admin.layouts.app')

@section('title', $supplier->name_ar)
@section('subtitle', $supplier->supplier_code.($supplier->phone ? ' · '.$supplier->phone : ''))

@php
    $dec = (int) config('laroville.decimals', 3);
    $money = fn ($v) => number_format((float) $v, $dec);
    $currency = config('laroville.currency');
    $rating = $summary['rating'];
    $paidPercent = $summary['invoiced'] > 0 ? min(100, round($summary['paid'] / $summary['invoiced'] * 100)) : 0;
    $wa = preg_replace('/\D/', '', (string) $supplier->phone);
    $creditLimit = (float) $supplier->credit_limit;
    $expiry = $supplier->national_id_expiry ? \Illuminate\Support\Carbon::parse($supplier->national_id_expiry) : null;

    $toneBg = ['emerald' => 'bg-emerald-500', 'blue' => 'bg-blue-500', 'amber' => 'bg-amber-400', 'rose' => 'bg-rose-500', 'slate' => 'bg-slate-300'];
    $toneText = ['emerald' => 'text-emerald-600', 'blue' => 'text-blue-600', 'amber' => 'text-amber-500', 'rose' => 'text-rose-600', 'slate' => 'text-slate-400'];

    $vehicleStatuses = ['available' => __('متاحة'), 'reserved' => __('محجوزة'), 'sold' => __('مباعة'), 'maintenance' => __('صيانة'), 'damaged' => __('متضررة'), 'returned' => __('مرتجعة')];
    $vehicleStyle = [
        'available' => 'bg-emerald-50 text-emerald-700', 'reserved' => 'bg-amber-50 text-amber-700',
        'sold' => 'bg-blue-50 text-blue-700', 'maintenance' => 'bg-orange-50 text-orange-700',
        'damaged' => 'bg-rose-50 text-rose-700', 'returned' => 'bg-slate-100 text-slate-500',
    ];
    $invoiceStyle = [
        'unpaid' => 'bg-rose-50 text-rose-700', 'partial' => 'bg-amber-50 text-amber-700',
        'paid' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-slate-100 text-slate-500',
    ];
    $returnStyle = ['pending' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-slate-100 text-slate-600', 'rejected' => 'bg-rose-50 text-rose-700'];

    $openInstallments = $installments->where('status', '!=', 'paid');
    $overdueInstallments = $openInstallments->filter->isOverdue();
    $modal = old('_modal');
@endphp

@section('actions')
    <button type="button" data-open="invoiceModal"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('فاتورة شراء') }}
    </button>
    <button type="button" data-open="returnModal" @disabled($returnableVehicles->isEmpty())
            class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg>
        {{ __('تسجيل مرتجع') }}
    </button>
    <a href="{{ route('admin.suppliers.edit', $supplier) }}"
       class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">
        {{ __('تعديل') }}
    </a>
    @if ($wa)
        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-600">{{ __('واتساب') }}</a>
    @endif
@endsection

@section('content')

    {{-- ═══════ التنبيهات ═══════ --}}
    <div class="space-y-3">
        @if (in_array($supplier->status, ['suspended', 'blacklisted'], true))
            <div class="rounded-2xl bg-rose-600 px-5 py-4 text-sm text-white">
                <span class="font-extrabold">{{ $statuses[$supplier->status] }}:</span> {{ $supplier->status_reason ?: __('بدون سبب مكتوب') }}
            </div>
        @endif
        @if ($overdueInstallments->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800 ring-1 ring-rose-200">
                <span><span class="font-extrabold">{{ $overdueInstallments->count() }} {{ __('قسط متأخر') }}</span> {{ __('بإجمالي') }} {{ $money($summary['overdue']) }} {{ $currency }}</span>
                <button type="button" data-tab-link="installments" class="font-bold underline">{{ __('عرض الأقساط') }}</button>
            </div>
        @endif
        @if ($creditLimit > 0 && $summary['balance'] > $creditLimit)
            <div class="rounded-2xl bg-amber-50 px-5 py-4 text-sm text-amber-800 ring-1 ring-amber-200">
                {{ __('الرصيد المستحق (') }}{{ $money($summary['balance']) }}) <span class="font-extrabold">{{ __('تعدّى حد الائتمان') }}</span> ({{ $money($creditLimit) }}).
            </div>
        @endif
        @if ($expiry && $expiry->lte(today()->addDays(30)))
            <div class="rounded-2xl bg-amber-50 px-5 py-4 text-sm text-amber-800 ring-1 ring-amber-200">
                {{ __('بطاقة المورد') }} {{ $expiry->isPast() ? __('انتهت في') : __('هتنتهي في') }} <span class="font-extrabold">{{ $expiry->format('Y-m-d') }}</span>{{ __('. حدّث بياناته.') }}
            </div>
        @endif
    </div>

    {{-- ═══════ الأرقام ═══════ --}}
    <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('إجمالي المشتريات') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">{{ $money($summary['invoiced']) }}</div>
            <div class="text-xs text-slate-400">{{ $invoices->count() }} {{ __('فاتورة') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between text-sm text-slate-500">
                <span>{{ __('المدفوع') }}</span><span class="font-bold text-emerald-600">{{ $paidPercent }}%</span>
            </div>
            <div class="mt-2 truncate text-2xl font-extrabold text-emerald-600">{{ $money($summary['paid']) }}</div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $paidPercent }}%"></div>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ $summary['balance'] < 0 ? __('لكم عند المورد') : __('المتبقي عليكم') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold {{ $summary['balance'] < 0 ? 'text-emerald-600' : 'text-slate-900' }}">{{ $money(abs($summary['balance'])) }}</div>
            <div class="text-xs text-slate-400">
                @if ($summary['credits'] > 0) {{ __('بعد خصم مرتجعات') }} {{ $money($summary['credits']) }} @else {{ $currency }} @endif
            </div>
        </div>
        <div class="rounded-2xl p-5 shadow-sm ring-1 {{ $summary['overdue'] > 0 ? 'bg-rose-50 ring-rose-200' : 'bg-white ring-slate-200' }}">
            <div class="text-sm {{ $summary['overdue'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">{{ __('متأخر') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold {{ $summary['overdue'] > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $money($summary['overdue']) }}</div>
            <div class="text-xs {{ $summary['due_soon'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                {{ __('مستحق خلال أسبوع:') }} {{ $money($summary['due_soon']) }}
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">

        {{-- التقييم --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('تقييم المورد') }}</h2>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $toneText[$rating['tone']] }} bg-slate-50">{{ $rating['label'] }}</span>
            </div>
            <div class="mt-4 flex items-end gap-3">
                <span class="text-4xl font-extrabold text-slate-900">{{ $rating['score'] ?? '—' }}</span>
                <div class="mb-1.5 flex gap-0.5 {{ $toneText[$rating['tone']] }}">
                    @for ($i = 1; $i <= 5; $i++)
                        <svg class="h-5 w-5 {{ $rating['score'] !== null && $i <= round($rating['score']) ? '' : 'opacity-20' }}" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>
                    @endfor
                </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-slate-50 py-2.5">
                    <div class="text-lg font-extrabold text-slate-900">{{ $summary['purchased'] }}</div>
                    <div class="text-[11px] text-slate-500">{{ __('اشترينا') }}</div>
                </div>
                <div class="rounded-xl bg-slate-50 py-2.5">
                    <div class="text-lg font-extrabold text-blue-600">{{ $summary['sold'] }}</div>
                    <div class="text-[11px] text-slate-500">{{ __('اتباعت') }}</div>
                </div>
                <div class="rounded-xl bg-slate-50 py-2.5">
                    <div class="text-lg font-extrabold {{ $summary['returned'] ? 'text-rose-600' : 'text-slate-900' }}">{{ $summary['returned'] }}</div>
                    <div class="text-[11px] text-slate-500">{{ __('رجّعنا') }}</div>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-xs text-slate-500">
                    <span>{{ __('نسبة المرتجعات') }}</span><span class="font-bold">{{ $rating['rate'] }}%</span>
                </div>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $toneBg[$rating['tone']] }}" style="width: {{ min(100, $rating['rate']) }}%"></div>
                </div>
                <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                    {{ __('التقييم بيتحسب من نسبة العربيات اللي اترجعت للمورد. محتاج 3 عربيات على الأقل عشان يطلع.') }}
                </p>
            </div>
        </section>

        {{-- القسط الجاي --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('القسط الجاي') }}</h2>
            @php $nextDue = $overdueInstallments->first() ?? $summary['next']; @endphp
            @if ($nextDue)
                @php $days = $nextDue->daysUntilDue(); @endphp
                <div class="mt-4 rounded-2xl p-4 {{ $days < 0 ? 'bg-rose-50' : ($days <= 7 ? 'bg-amber-50' : 'bg-blue-50') }}">
                    <div class="text-3xl font-extrabold text-slate-900">{{ $money($nextDue->remaining()) }}</div>
                    <div class="text-xs text-slate-500">{{ $currency }} · {{ $nextDue->invoice?->invoice_number }} {{ __('· قسط') }} {{ $nextDue->sequence }}</div>
                    <div class="mt-3 text-sm font-bold {{ $days < 0 ? 'text-rose-700' : ($days <= 7 ? 'text-amber-700' : 'text-blue-700') }}">
                        {{ $nextDue->due_date->format('Y-m-d') }} ·
                        {{ $days < 0 ? __('متأخر ').abs($days).__(' يوم') : ($days === 0 ? __('النهارده') : __('بعد ').$days.__(' يوم')) }}
                    </div>
                </div>
                <button type="button"
                        data-pay="{{ json_encode(['id' => $nextDue->id, 'remaining' => $nextDue->remaining(), 'label' => $nextDue->invoice?->invoice_number.__(' · قسط ').$nextDue->sequence]) }}"
                        class="mt-4 w-full rounded-xl bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800">
                    {{ __('ادفع القسط ده') }}
                </button>
            @else
                <div class="mt-4 rounded-2xl bg-emerald-50 p-6 text-center text-sm font-bold text-emerald-700">
                    {{ __('مفيش أقساط مستحقة') }}
                </div>
            @endif
        </section>

        {{-- بيانات التحويل --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('بيانات الدفع') }}</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('مدة التسوية') }}</dt><dd class="font-bold text-slate-800">{{ (int) $supplier->settlement_days }} {{ __('يوم') }}</dd></div>
                @if ($creditLimit > 0)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('حد الائتمان') }}</dt><dd class="font-bold text-slate-800">{{ $money($creditLimit) }}</dd></div>
                @endif
                @if ($supplier->bank_name)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('البنك') }}</dt><dd class="font-bold text-slate-800">{{ $supplier->bank_name }}</dd></div>
                @endif
                @if ($supplier->account_holder_name)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('صاحب الحساب') }}</dt><dd class="font-bold text-slate-800">{{ $supplier->account_holder_name }}</dd></div>
                @endif
            </dl>
            @if ($supplier->iban_number)
                <button type="button" data-copy="{{ $supplier->iban_number }}"
                        class="mt-4 flex w-full items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-end ring-1 ring-slate-200 hover:bg-slate-100">
                    <span class="truncate font-mono text-xs text-slate-700" dir="ltr">{{ $supplier->iban_number }}</span>
                    <span class="shrink-0 text-xs font-bold text-blue-600" data-copy-label>{{ __('نسخ الآيبان') }}</span>
                </button>
            @endif
            @if ($supplier->settlement_terms)
                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">{{ $supplier->settlement_terms }}</p>
            @endif
        </section>
    </div>

    {{-- ═══════ التبويبات ═══════ --}}
    <div class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex gap-1 overflow-x-auto border-b border-slate-100 px-3 pt-3 text-sm font-semibold">
            @foreach ([
                'installments' => [__('الأقساط'), $overdueInstallments->count(), 'bg-rose-500'],
                'invoices'     => [__('الفواتير'), $invoices->count(), 'bg-slate-300'],
                'payments'     => [__('المدفوعات'), $payments->count(), 'bg-slate-300'],
                'returns'      => [__('المرتجعات'), $returns->where('status', 'pending')->count(), 'bg-amber-400'],
                'vehicles'     => [__('العربيات'), $vehicles->count(), 'bg-slate-300'],
            ] as $key => [$label, $count, $badge])
                <button type="button" data-tab="{{ $key }}"
                        class="tab-btn flex shrink-0 items-center gap-2 border-b-2 border-transparent px-4 pb-3 text-slate-500 transition hover:text-slate-900">
                    {{ $label }}
                    @if ($count > 0)
                        <span class="rounded-full {{ $badge }} px-1.5 text-[11px] font-bold leading-5 text-white">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- الأقساط --}}
        <div data-panel="installments" class="hidden overflow-x-auto">
            @if ($installments->isEmpty())
                <p class="px-6 py-14 text-center text-sm text-slate-400">{{ __('لسه مفيش أقساط. سجّل فاتورة شراء وهتتقسم لوحدها.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('القسط') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('تاريخ الاستحقاق') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المبلغ') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المدفوع') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المتبقي') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($installments as $inst)
                            @php $days = $inst->daysUntilDue(); $late = $inst->isOverdue(); @endphp
                            <tr class="{{ $late ? 'bg-rose-50/40' : '' }} {{ $inst->status === 'paid' ? 'text-slate-400' : '' }}">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold {{ $inst->status === 'paid' ? '' : 'text-slate-900' }}">{{ __('قسط') }} {{ $inst->sequence }}</div>
                                    <div class="text-xs text-slate-400">{{ $inst->invoice?->invoice_number }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <div>{{ $inst->due_date->format('Y-m-d') }}</div>
                                    @if ($inst->status !== 'paid')
                                        <div class="text-xs {{ $late ? 'font-bold text-rose-600' : ($days <= 7 ? 'text-amber-600' : 'text-slate-400') }}">
                                            {{ $late ? __('متأخر ').abs($days).__(' يوم') : ($days === 0 ? __('النهارده') : __('بعد ').$days.__(' يوم')) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 font-semibold">{{ $money($inst->amount) }}</td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-emerald-600">{{ (float) $inst->paid_amount > 0 ? $money($inst->paid_amount) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3.5 font-bold {{ $inst->status === 'paid' ? '' : 'text-slate-900' }}">{{ $inst->status === 'paid' ? '—' : $money($inst->remaining()) }}</td>
                                <td class="px-3 py-3.5">
                                    @if ($inst->status === 'paid')
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ __('مدفوع') }} {{ $inst->paid_at?->format('Y-m-d') }}</span>
                                    @elseif ($late)
                                        <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700">{{ __('متأخر') }}</span>
                                    @elseif ($inst->status === 'partial')
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ __('مدفوع جزئياً') }}</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ __('مستحق') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-end">
                                    @if ($inst->status !== 'paid')
                                        <button type="button"
                                                data-pay="{{ json_encode(['id' => $inst->id, 'remaining' => $inst->remaining(), 'label' => $inst->invoice?->invoice_number.__(' · قسط ').$inst->sequence]) }}"
                                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700">{{ __('دفع') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- الفواتير --}}
        <div data-panel="invoices" class="hidden overflow-x-auto">
            @if ($invoices->isEmpty())
                <p class="px-6 py-14 text-center text-sm text-slate-400">{{ __('مفيش فواتير لسه.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('الفاتورة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('التاريخ') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العربيات') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الإجمالي') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('السداد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoices as $invoice)
                            @php $pct = (float) $invoice->total_amount > 0 ? min(100, round((float) $invoice->paid_amount / (float) $invoice->total_amount * 100)) : 0; @endphp
                            <tr>
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-slate-900">{{ $invoice->invoice_number }}</div>
                                    @if ($invoice->supplier_reference)
                                        <div class="text-xs text-slate-400">{{ __('رقم المورد:') }} {{ $invoice->supplier_reference }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5">{{ $invoice->invoice_date->format('Y-m-d') }}</td>
                                <td class="px-3 py-3.5">{{ $invoice->vehicles_count }}</td>
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <div class="font-bold text-slate-900">{{ $money($invoice->total_amount) }}</div>
                                    @if ((float) $invoice->tax_amount > 0)
                                        <div class="text-xs text-slate-400">{{ __('شامل ضريبة') }} {{ $money($invoice->tax_amount) }}</div>
                                    @endif
                                </td>
                                <td class="min-w-[140px] px-3 py-3.5">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-emerald-600">{{ $money($invoice->paid_amount) }}</span>
                                        <span class="text-slate-400">{{ $invoice->installments_count }} {{ __('قسط') }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $invoiceStyle[$invoice->status] }}">
                                        {{ \App\Models\SupplierInvoice::STATUSES[$invoice->status] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- المدفوعات --}}
        <div data-panel="payments" class="hidden overflow-x-auto">
            @if ($payments->isEmpty())
                <p class="px-6 py-14 text-center text-sm text-slate-400">{{ __('مفيش مدفوعات لسه.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('التاريخ') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('عن') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('طريقة الدفع') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المرجع') }}</th>
                            <th class="px-5 py-3 text-end font-semibold">{{ __('المبلغ') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <div class="font-semibold text-slate-800">{{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('Y-m-d') }}</div>
                                    <div class="text-xs text-slate-400">{{ $payment->payment_reference }}</div>
                                </td>
                                <td class="px-3 py-3.5 text-slate-600">{{ $installmentNumbers[$payment->payable_id] ?? '—' }}</td>
                                <td class="px-3 py-3.5"><span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ $payment->method_name ?? '—' }}</span></td>
                                <td class="px-3 py-3.5 text-xs text-slate-500" dir="ltr" style="text-align: right">{{ $payment->bank_reference ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-end font-bold text-emerald-600">{{ $money($payment->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- المرتجعات --}}
        <div data-panel="returns" class="hidden overflow-x-auto">
            @if ($returns->isEmpty())
                <p class="px-6 py-14 text-center text-sm text-slate-400">{{ __('مفيش مرتجعات. ده مؤشر كويس للمورد.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العربية') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('السبب') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المبلغ المخصوم') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($returns as $ret)
                            <tr>
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-slate-900">{{ $ret->brand_name }} {{ $ret->model_name }}</div>
                                    <div class="text-xs text-slate-400">{{ $ret->vehicle_code }} · {{ $ret->return_number }} · {{ $ret->return_date->format('Y-m-d') }}</div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="font-semibold text-slate-700">{{ \App\Models\SupplierReturn::REASONS[$ret->reason] ?? $ret->reason }}</div>
                                    @if ($ret->reason_details)
                                        <div class="max-w-xs truncate text-xs text-slate-400" title="{{ $ret->reason_details }}">{{ $ret->reason_details }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 font-bold text-slate-900">{{ $money($ret->refund_amount) }}</td>
                                <td class="px-3 py-3.5">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $returnStyle[$ret->status] }}">{{ \App\Models\SupplierReturn::STATUSES[$ret->status] }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($ret->status === 'pending')
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.suppliers.returns.update', [$supplier, $ret]) }}"
                                                  onsubmit="{{ __('return confirm(\'تأكيد إن العربية اترجعت للمورد؟ هتخرج من المخزون ويتخصم المبلغ من حسابه.\')') }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="action" value="complete">
                                                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800">{{ __('تأكيد الإرجاع') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.suppliers.returns.update', [$supplier, $ret]) }}"
                                                  onsubmit="{{ __('return confirm(\'رفض طلب الإرجاع؟\')') }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="action" value="reject">
                                                <button class="rounded-lg px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('رفض') }}</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- العربيات --}}
        <div data-panel="vehicles" class="hidden overflow-x-auto">
            @if ($vehicles->isEmpty())
                <p class="px-6 py-14 text-center text-sm text-slate-400">{{ __('مفيش عربيات مسجلة على المورد ده.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العربية') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الفاتورة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('سعر الشراء') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($vehicles as $vehicle)
                            <tr>
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-slate-900">{{ $vehicle->brand_name }} {{ $vehicle->model_name }} <span class="font-normal text-slate-500">{{ $vehicle->manufacturing_year }}</span></div>
                                    <div class="text-xs text-slate-400">{{ $vehicle->vehicle_code }} · {{ $vehicle->color }}</div>
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($vehicle->invoice_number)
                                        <span class="text-slate-700">{{ $vehicle->invoice_number }}</span>
                                    @else
                                        <span class="rounded bg-amber-50 px-1.5 text-[11px] font-bold text-amber-700">{{ __('بدون فاتورة') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 font-semibold">{{ $money($vehicle->purchase_price) }}</td>
                                <td class="px-3 py-3.5">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $vehicleStyle[$vehicle->status] ?? '' }}">{{ $vehicleStatuses[$vehicle->status] ?? $vehicle->status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- ═══════════════ مودال: فاتورة شراء ═══════════════ --}}
    <dialog id="invoiceModal" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.suppliers.invoices.store', $supplier) }}" data-lock>
            @csrf
            <input type="hidden" name="_modal" value="invoice">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('فاتورة شراء جديدة') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('الفاتورة هتتقسم أقساط شهرية بشكل تلقائي') }}</p>
                </div>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <div class="max-h-[65vh] space-y-5 overflow-y-auto px-6 py-5">
                @if ($modal === 'invoice' && $errors->any())
                    <div class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700 ring-1 ring-rose-200">{{ __('راجع الحقول المعلّمة بالأحمر.') }}</div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'invoice_number', 'label' => __('رقم الفاتورة'), 'required' => true, 'dir' => 'ltr', 'value' => old('invoice_number', $nextInvoiceNumber)])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'supplier_reference', 'label' => __('رقم فاتورة المورد'), 'dir' => 'ltr'])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'invoice_date', 'label' => __('تاريخ الفاتورة'), 'type' => 'date', 'required' => true, 'value' => old('invoice_date', today()->format('Y-m-d'))])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'first_due_date', 'label' => __('ميعاد أول قسط'), 'type' => 'date', 'required' => true, 'value' => old('first_due_date', today()->addDays((int) $supplier->settlement_days)->format('Y-m-d')), 'hint' => __('افتراضياً بعد ').(int) $supplier->settlement_days.__(' يوم (مدة التسوية)')])
                </div>

                @if ($uninvoicedVehicles->isNotEmpty())
                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-700">{{ __('العربيات اللي في الفاتورة') }} <span class="font-normal text-slate-400">{{ __('(اختياري - بيحسب الإجمالي لوحده)') }}</span></div>
                        <div class="max-h-44 space-y-1.5 overflow-y-auto rounded-xl border border-slate-200 p-2">
                            @foreach ($uninvoicedVehicles as $v)
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50 has-[:checked]:bg-blue-50">
                                    <span class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="vehicle_ids[]" value="{{ $v->id }}" data-price="{{ (float) $v->purchase_price }}" class="accent-blue-600"
                                               @checked(in_array($v->id, (array) old('vehicle_ids', [])))>
                                        <span class="font-semibold text-slate-800">{{ $v->brand_name }} {{ $v->model_name }} {{ $v->manufacturing_year }}</span>
                                        <span class="text-xs text-slate-400">{{ $v->vehicle_code }}</span>
                                    </span>
                                    <span class="text-xs font-bold text-slate-600">{{ $money($v->purchase_price) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('vehicle_ids.*') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-3">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'subtotal', 'label' => __('المبلغ قبل الضريبة'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'tax_percent', 'label' => __('الضريبة %'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'value' => old('tax_percent', 0)])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'installments_count', 'label' => __('عدد الأقساط'), 'type' => 'number', 'min' => 1, 'required' => true, 'value' => old('installments_count', 1), 'hint' => __('1 = دفعة واحدة')])
                </div>

                <div id="invoicePreview" class="rounded-xl bg-slate-900 p-4 text-sm text-white">
                    <div class="flex justify-between"><span class="text-slate-400">{{ __('الإجمالي بعد الضريبة') }}</span><span class="font-extrabold" data-preview="total">—</span></div>
                    <div class="mt-1 flex justify-between"><span class="text-slate-400">{{ __('القسط الشهري') }}</span><span class="font-bold" data-preview="per">—</span></div>
                    <div class="mt-1 flex justify-between"><span class="text-slate-400">{{ __('آخر قسط') }}</span><span class="font-bold" data-preview="last">—</span></div>
                </div>

                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'inv_', 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])
            </div>

            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ الفاتورة') }}</button>
            </div>
        </form>
    </dialog>

    {{-- ═══════════════ مودال: دفع قسط ═══════════════ --}}
    <dialog id="payModal" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" data-lock data-action-template="{{ route('admin.suppliers.installments.pay', [$supplier, '__ID__']) }}">
            @csrf
            <input type="hidden" name="_modal" value="pay">
            <input type="hidden" name="_installment" value="{{ old('_installment') }}">
            <input type="hidden" name="_label" value="{{ old('_label') }}">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('تسجيل دفعة') }}</h2>
                    <p class="text-xs text-slate-500" data-pay-label>{{ old('_label') }}</p>
                </div>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <div class="space-y-4 px-6 py-5">
                @if ($paymentMethods->isEmpty())
                    <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">{{ __('مفيش طرق دفع مفعّلة. ضيف طرق الدفع الأول.') }}</div>
                @endif

                <div>
                    <label for="pay_amount" class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                        <span>{{ __('المبلغ') }} <span class="text-rose-500">*</span></span>
                        <button type="button" data-pay-full class="text-xs font-bold text-blue-600 hover:underline">{{ __('المتبقي كله') }}</button>
                    </label>
                    <input id="pay_amount" name="amount" type="number" step="0.001" min="0.001" required value="{{ old('amount') }}"
                           class="w-full rounded-xl border px-3 py-3 text-lg font-bold outline-none focus:ring-4 {{ $errors->has('amount') ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-400 focus:ring-blue-50' }}">
                    @error('amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('طريقة الدفع') }} <span class="text-rose-500">*</span></div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($paymentMethods as $id => $name)
                            <label class="cursor-pointer rounded-xl border border-slate-200 px-2 py-2.5 text-center text-sm font-bold text-slate-600 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                                <input type="radio" name="payment_method_id" value="{{ $id }}" class="sr-only" required
                                       @checked((string) old('payment_method_id', $paymentMethods->keys()->first()) === (string) $id)>
                                {{ $name }}
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'pay_', 'name' => 'payment_date', 'label' => __('تاريخ الدفع'), 'type' => 'date', 'required' => true, 'value' => old('payment_date', today()->format('Y-m-d'))])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'pay_', 'name' => 'reference', 'label' => __('رقم التحويل / الشيك'), 'dir' => 'ltr'])
                </div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'pay_', 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])
            </div>

            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" @disabled($paymentMethods->isEmpty())
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{{ __('تأكيد الدفع') }}</button>
            </div>
        </form>
    </dialog>

    {{-- ═══════════════ مودال: مرتجع ═══════════════ --}}
    <dialog id="returnModal" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.suppliers.returns.store', $supplier) }}" data-lock>
            @csrf
            <input type="hidden" name="_modal" value="return">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('تسجيل مرتجع') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('الطلب هيتسجل "قيد المراجعة" لحد ما تأكد إن العربية اتسلمت للمورد') }}</p>
                </div>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <div class="max-h-[65vh] space-y-4 overflow-y-auto px-6 py-5">
                <div>
                    <label for="ret_vehicle" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('العربية') }} <span class="text-rose-500">*</span></label>
                    <select id="ret_vehicle" name="vehicle_id" required
                            class="w-full rounded-xl border px-3 py-2.5 text-sm outline-none focus:ring-4 {{ $errors->has('vehicle_id') ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-400 focus:ring-blue-50' }}">
                        <option value="">{{ __('— اختار —') }}</option>
                        @foreach ($returnableVehicles as $v)
                            <option value="{{ $v->id }}" data-price="{{ (float) $v->purchase_price }}" @selected((string) old('vehicle_id') === (string) $v->id)>
                                {{ $v->brand_name }} {{ $v->model_name }} {{ $v->manufacturing_year }} — {{ $v->vehicle_code }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'ret_', 'name' => 'reason', 'label' => __('السبب'), 'type' => 'select', 'required' => true, 'options' => \App\Models\SupplierReturn::REASONS])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'ret_', 'name' => 'return_date', 'label' => __('تاريخ الإرجاع'), 'type' => 'date', 'required' => true, 'value' => old('return_date', today()->format('Y-m-d'))])
                </div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'ret_', 'name' => 'refund_amount', 'label' => __('المبلغ اللي هيتخصم من حساب المورد'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true, 'hint' => __('بيتملى لوحده بسعر الشراء، وتقدر تعدّله')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'ret_', 'name' => 'reason_details', 'label' => __('تفاصيل المشكلة'), 'type' => 'textarea', 'rows' => 3])
            </div>

            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-60">{{ __('تسجيل المرتجع') }}</button>
            </div>
        </form>
    </dialog>

    <script>
    (function () {
        const dec = {{ $dec }};
        const fmt = (n) => Number(n || 0).toLocaleString('en', { minimumFractionDigits: dec, maximumFractionDigits: dec });

        // ── التبويبات ──
        const buttons = document.querySelectorAll('[data-tab]');
        const panels = document.querySelectorAll('[data-panel]');
        const showTab = (name) => {
            if (![...panels].some((p) => p.dataset.panel === name)) name = 'installments';
            panels.forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== name));
            buttons.forEach((b) => {
                const on = b.dataset.tab === name;
                b.classList.toggle('border-blue-600', on);
                b.classList.toggle('text-blue-700', on);
                b.classList.toggle('border-transparent', !on);
            });
            history.replaceState(null, '', '#' + name);
        };
        buttons.forEach((b) => b.addEventListener('click', () => showTab(b.dataset.tab)));
        document.querySelectorAll('[data-tab-link]').forEach((l) => l.addEventListener('click', () => {
            showTab(l.dataset.tabLink);
            document.querySelector('[data-panel]').parentElement.scrollIntoView({ behavior: 'smooth' });
        }));
        showTab(location.hash.slice(1) || 'installments');

        // ── المودالات ──
        document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => document.getElementById(b.dataset.open).showModal()));
        document.querySelectorAll('dialog').forEach((d) => {
            d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
            d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
        });
        document.querySelectorAll('form[data-lock]').forEach((f) => f.addEventListener('submit', () => {
            const b = f.querySelector('[type=submit]'); b.disabled = true; b.textContent = @js(__('جاري الحفظ...'));
        }));

        // ── دفع قسط ──
        const payModal = document.getElementById('payModal');
        const payForm = payModal.querySelector('form');
        const amount = payForm.querySelector('[name=amount]');
        let remaining = 0;
        const openPay = (data, keepAmount) => {
            remaining = Number(data.remaining);
            payForm.action = payForm.dataset.actionTemplate.replace('__ID__', data.id);
            payForm.querySelector('[name=_installment]').value = data.id;
            payForm.querySelector('[name=_label]').value = data.label;
            payForm.querySelector('[data-pay-label]').textContent = data.label + @js(__(' — المتبقي ')) + fmt(remaining);
            amount.max = remaining;
            if (!keepAmount) amount.value = remaining.toFixed(dec);
            payModal.showModal();
            amount.select();
        };
        document.querySelectorAll('[data-pay]').forEach((b) => b.addEventListener('click', () => openPay(JSON.parse(b.dataset.pay))));
        payForm.querySelector('[data-pay-full]').addEventListener('click', () => { amount.value = remaining.toFixed(dec); });

        // ── فاتورة: جمع أسعار العربيات + معاينة الأقساط ──
        const inv = document.getElementById('invoiceModal');
        const subtotal = inv.querySelector('[name=subtotal]');
        const tax = inv.querySelector('[name=tax_percent]');
        const count = inv.querySelector('[name=installments_count]');
        const firstDue = inv.querySelector('[name=first_due_date]');
        subtotal.addEventListener('input', () => { subtotal.dataset.touched = '1'; });
        const preview = () => {
            const total = Number(subtotal.value || 0) * (1 + Number(tax.value || 0) / 100);
            const n = Math.max(1, parseInt(count.value || 1, 10));
            inv.querySelector('[data-preview=total]').textContent = fmt(total);
            inv.querySelector('[data-preview=per]').textContent = n === 1 ? @js(__('دفعة واحدة')) : fmt(Math.floor(total / n * 10 ** dec) / 10 ** dec) + ' × ' + n;
            if (firstDue.value) {
                const d = new Date(firstDue.value);
                d.setMonth(d.getMonth() + n - 1);
                inv.querySelector('[data-preview=last]').textContent = d.toISOString().slice(0, 10);
            }
        };
        inv.querySelectorAll('[data-price]').forEach((c) => c.addEventListener('change', () => {
            if (subtotal.dataset.touched) return preview();
            const sum = [...inv.querySelectorAll('[data-price]:checked')].reduce((s, x) => s + Number(x.dataset.price), 0);
            subtotal.value = sum ? sum.toFixed(dec) : '';
            preview();
        }));
        [subtotal, tax, count, firstDue].forEach((el) => el.addEventListener('input', preview));
        preview();

        // ── مرتجع: المبلغ = سعر الشراء ──
        const retModal = document.getElementById('returnModal');
        const retVehicle = retModal.querySelector('[name=vehicle_id]');
        const refund = retModal.querySelector('[name=refund_amount]');
        retVehicle.addEventListener('change', () => {
            const opt = retVehicle.selectedOptions[0];
            if (opt && opt.dataset.price) refund.value = Number(opt.dataset.price).toFixed(dec);
        });

        // ── نسخ الآيبان ──
        document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(b.dataset.copy); } catch (e) { return; }
            const label = b.querySelector('[data-copy-label]');
            const old = label.textContent; label.textContent = @js(__('اتنسخ ✓'));
            setTimeout(() => { label.textContent = old; }, 1500);
        }));

        // ── إعادة فتح المودال لو فيه أخطاء ──
        @if ($errors->any() && $modal === 'invoice')
            inv.showModal();
        @elseif ($errors->any() && $modal === 'return')
            retModal.showModal();
        @elseif ($errors->any() && $modal === 'pay' && old('_installment'))
            @php
                $failedInstallment = $installments->firstWhere('id', (int) old('_installment'));
                $reopenPay = ['id' => (int) old('_installment'), 'remaining' => $failedInstallment?->remaining() ?? 0, 'label' => (string) old('_label')];
            @endphp
            openPay(@json($reopenPay), true);
        @endif
    })();
    </script>
@endsection
