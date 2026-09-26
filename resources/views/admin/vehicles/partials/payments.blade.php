@php
    $pct = (float) $total > 0 ? min(100, round((float) $paid / (float) $total * 100)) : 0;
@endphp

<div class="grid grid-cols-3 gap-3 text-center">
    <div class="rounded-xl bg-emerald-50 p-3">
        <div class="text-sm font-extrabold text-emerald-700">{{ $money($paid) }}</div>
        <div class="text-[11px] text-emerald-600">{{ __('إجمالي المدفوع') }}</div>
    </div>
    <div class="rounded-xl p-3 {{ (float) $remaining > 0 ? 'bg-amber-50' : 'bg-slate-50' }}">
        <div class="text-sm font-extrabold {{ (float) $remaining > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $money($remaining) }}</div>
        <div class="text-[11px] {{ (float) $remaining > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ __('المبلغ المتبقي') }}</div>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
        <div class="text-sm font-extrabold text-slate-800">{{ $money($total) }}</div>
        <div class="text-[11px] text-slate-500">{{ __('إجمالي المبلغ') }}</div>
    </div>
</div>
<div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div>
</div>

@if ($payments->isEmpty())
    <p class="mt-4 rounded-xl bg-slate-50 py-4 text-center text-sm text-slate-400">{{ $emptyText }}</p>
@else
    <div class="mt-4 overflow-x-auto rounded-xl ring-1 ring-slate-100">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-2.5 text-start font-semibold">{{ __('تاريخ الدفع') }}</th>
                    <th class="px-3 py-2.5 text-start font-semibold">{{ __('المبلغ') }}</th>
                    <th class="px-3 py-2.5 text-start font-semibold">{{ __('طريقة الدفع') }}</th>
                    <th class="px-3 py-2.5 text-start font-semibold">{{ __('ملاحظات') }}</th>
                    <th class="px-3 py-2.5 text-start font-semibold">{{ __('المستخدم') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($payments as $pay)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2.5 text-slate-700">{{ $date($pay->payment_date) }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 font-bold text-emerald-700">{{ $money($pay->amount) }}</td>
                        <td class="px-3 py-2.5"><span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $pay->method_name ?? '—' }}</span></td>
                        <td class="max-w-[200px] truncate px-3 py-2.5 text-xs text-slate-500" title="{{ $pay->notes }}">{{ $pay->notes ?: '—' }}</td>
                        <td class="px-3 py-2.5 text-xs text-slate-500">{{ $pay->user_name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
