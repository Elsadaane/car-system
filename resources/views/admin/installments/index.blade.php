@extends('admin.layouts.app')

@section('title', __('متابعة الأقساط'))
@section('subtitle', __('مين عليه قسط، امتى، وكام — وتفكّره بضغطة'))

@php
    use App\Support\Money;
    use App\Http\Controllers\Admin\InstallmentController as IC;
    use Illuminate\Support\Carbon;
    $today = today();
    $tones = [
        'overdue'  => ['bg-rose-600 text-white', 'bg-rose-50 text-rose-700 ring-rose-200'],
        'week'     => ['bg-amber-500 text-white', 'bg-amber-50 text-amber-700 ring-amber-200'],
        'month'    => ['bg-blue-600 text-white', 'bg-blue-50 text-blue-700 ring-blue-200'],
        'upcoming' => ['bg-slate-800 text-white', 'bg-white text-slate-700 ring-slate-200'],
        'paid'     => ['bg-emerald-600 text-white', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    ];
@endphp

@section('actions')
    <a href="{{ route('admin.sale-payments.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('المدفوعات') }}</a>
    <a href="{{ route('admin.sales-contracts.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('عقود البيع') }}</a>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-900 px-5 py-4 text-white">
        <div class="text-sm text-slate-300">{{ __('اتحصّل من الأقساط الشهر ده') }}</div>
        <div class="text-2xl font-extrabold text-emerald-300">{{ Money::format($collectedMonth) }}</div>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach (IC::TABS as $k => $label)
            <a href="{{ route('admin.installments.index', array_filter(['tab' => $k, 'q' => $q])) }}"
               class="rounded-2xl p-4 shadow-sm ring-1 transition hover:-translate-y-0.5 {{ $tab === $k ? $tones[$k][0].' ring-transparent' : $tones[$k][1] }}">
                <div class="text-sm font-bold">{{ $label }}</div>
                <div class="mt-1 truncate text-xl font-extrabold">{{ Money::format($stats[$k]['amount'], false) }}</div>
                <div class="text-xs opacity-75">{{ $stats[$k]['count'] }} {{ __('قسط') }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-5 flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('اسم العميل، التليفون، رقم العقد...') }}"
               class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($rows->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="text-4xl">{{ $tab === 'overdue' ? '🎉' : '📭' }}</div>
                <p class="mt-2 font-bold text-slate-600">{{ $tab === 'overdue' ? __('مفيش أقساط متأخرة — كله ملتزم') : __('مفيش أقساط هنا') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العميل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العقد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('القسط') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الاستحقاق') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ $tab === 'paid' ? __('المدفوع') : __('المطلوب') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $r)
                            @php
                                $due = Carbon::parse($r->due_date);
                                $left = (float) $r->amount - (float) $r->paid_amount;
                                $late = ! $r->is_paid && $due->lt($today);
                                $wa = preg_replace('/\D/', '', (string) $r->customer_phone);
                                $msg = $late
                                    ? "أهلاً {$r->customer_name}\nنفكّرك إن القسط رقم {$r->installment_sequence} بقيمة ".Money::format($left)." كان مستحق يوم {$due->format('Y-m-d')} على عقد العربية {$r->brand_name} {$r->model_name}.\nياريت تتواصل معانا للسداد. شكراً لك."
                                    : "أهلاً {$r->customer_name}\nتذكير ودّي: القسط رقم {$r->installment_sequence} بقيمة ".Money::format($left)." مستحق يوم {$due->format('Y-m-d')}.\nشكراً لك.";
                            @endphp
                            <tr class="{{ $late ? 'bg-rose-50/40' : '' }} hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.customers.show', $r->customer_id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $r->customer_name }}</a>
                                    <a href="tel:{{ $r->customer_phone }}" class="block text-xs text-blue-600" dir="ltr" data-page-align>{{ $r->customer_phone }}</a>
                                    @if ($late && $r->guarantor_name) <div class="text-[11px] text-slate-400">{{ __('ضامن:') }} {{ $r->guarantor_name }} <span dir="ltr">{{ $r->guarantor_phone }}</span></div> @endif
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.sales-contracts.show', $r->sales_contract_id) }}#installments" class="font-mono text-xs font-bold text-blue-700 hover:underline">{{ $r->contract_number }}</a>
                                    <div class="text-xs text-slate-500">{{ $r->brand_name }} {{ $r->model_name }}</div>
                                </td>
                                <td class="px-3 py-3 font-bold text-slate-700">{{ $r->installment_sequence }} <span class="font-normal text-slate-400">/ {{ $r->total_installments }}</span></td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $due->format('Y-m-d') }}</div>
                                    @if ($r->is_paid)
                                        <div class="text-[11px] {{ (int) $r->days_overdue > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ __('اتدفع') }} {{ $r->actual_payment_date ? Carbon::parse($r->actual_payment_date)->format('Y-m-d') : '' }}{{ (int) $r->days_overdue > 0 ? __(' (متأخر ').$r->days_overdue.__(' يوم)') : ' ✓' }}</div>
                                    @elseif ($late)
                                        <div class="text-[11px] font-bold text-rose-600">{{ __('متأخر') }} {{ (int) $due->diffInDays($today) }} {{ __('يوم') }}</div>
                                    @else
                                        <div class="text-[11px] text-slate-400">{{ $due->isSameDay($today) ? __('النهارده') : __('بعد ').(int) $today->diffInDays($due).__(' يوم') }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <div class="font-extrabold {{ $late ? 'text-rose-700' : 'text-slate-900' }}">{{ Money::format($r->is_paid ? $r->paid_amount : $left, false) }}</div>
                                    @if (! $r->is_paid && (float) $r->paid_amount > 0) <div class="text-[11px] text-slate-400">{{ __('من') }} {{ Money::format($r->amount, false) }}</div> @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-end">
                                    @unless ($r->is_paid)
                                        @if ($wa)
                                            <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener" title="{{ __('تذكير واتساب') }}"
                                               class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold {{ $late ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">{{ __('⏰ تذكير') }}</a>
                                        @endif
                                        <a href="{{ route('admin.sales-contracts.show', [$r->sales_contract_id, 'open' => 'payModal']) }}" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800">{{ __('تحصيل') }}</a>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages()) <div class="border-t border-slate-100 p-4">{{ $rows->links() }}</div> @endif
        @endif
    </div>
@endsection
