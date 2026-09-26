@extends('admin.layouts.app')

@php
    use App\Support\Cheques;
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $tabs = \App\Http\Controllers\Admin\ChequeController::TABS;
    $today = today();
@endphp

@section('title', __('الشيكات المؤجلة'))
@section('subtitle', __('متابعة شيكات الأقساط: الإيداع، والصرف، والمرتجع'))

@section('content')
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-xs font-bold text-slate-500">{{ __('شيكات في الخزنة') }}</div><div class="mt-1 text-2xl font-black text-slate-900">{{ Money::format($totals['received'], false) }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-xs font-bold text-slate-500">{{ __('في البنك (مستني الصرف)') }}</div><div class="mt-1 text-2xl font-black text-blue-700">{{ Money::format($totals['deposited'], false) }}</div></div>
        <div class="rounded-2xl p-5 shadow-sm ring-1 {{ $totals['due'] ? 'bg-amber-50 ring-amber-200' : 'bg-white ring-slate-200' }}"><div class="text-xs font-bold text-amber-700">{{ __('لازم تتودع خلال 7 أيام') }}</div><div class="mt-1 text-2xl font-black text-amber-700">{{ $totals['due'] }}</div>@if ($totals['overdue'])<div class="text-xs font-bold text-rose-600">{{ $totals['overdue'] }} {{ __('فات ميعادها') }}</div>@endif</div>
        <div class="rounded-2xl p-5 shadow-sm ring-1 {{ $totals['bounced'] ? 'bg-rose-50 ring-rose-200' : 'bg-white ring-slate-200' }}"><div class="text-xs font-bold text-rose-700">{{ __('مرتجعة (محتاجة متابعة)') }}</div><div class="mt-1 text-2xl font-black text-rose-700">{{ Money::format($totals['bounced'], false) }}</div></div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5 rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.cheques.index', ['tab' => $key, 'q' => $q ?: null]) }}" class="rounded-xl px-3 py-2 text-sm font-bold {{ $tab === $key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                    {{ __($label) }}@if (in_array($key, ['received', 'deposited', 'bounced'], true) && ($counts[$key] ?? 0)) <span class="ms-1 rounded-full bg-white/20 px-1.5 text-xs">{{ $counts[$key] }}</span>@endif
                </a>
            @endforeach
        </div>
        <form class="flex gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('رقم الشيك، العقد، العميل، البنك') }}" class="w-64 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400">
            <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">{{ __('بحث') }}</button>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($rows->isEmpty())
            <div class="px-6 py-16 text-center text-sm text-slate-400">{{ __('مفيش شيكات هنا.') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start">{{ __('تاريخ الشيك') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('رقم الشيك') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('العميل / العقد') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('إجراء') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $c)
                            @php $due = Carbon::parse($c->due_date); $late = $c->status === 'received' && $due->lt($today); @endphp
                            <tr class="{{ $late ? 'bg-rose-50/50' : '' }}">
                                <td class="whitespace-nowrap px-5 py-3">
                                    <div class="font-bold {{ $late ? 'text-rose-700' : 'text-slate-800' }}">{{ $due->format('Y-m-d') }}</div>
                                    <div class="text-[11px] {{ $late ? 'font-bold text-rose-600' : 'text-slate-400' }}">{{ $late ? __('فات ميعاد إيداعه') : $due->diffForHumans() }}</div>
                                </td>
                                <td class="px-3 py-3"><div class="font-mono font-bold text-slate-900">{{ $c->cheque_number }}</div><div class="text-xs text-slate-500">{{ $c->bank_name ?: '—' }}</div></td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $c->customer_name }}</div>
                                    <a href="{{ route('admin.sales-contracts.show', $c->sales_contract_id) }}#cheques" class="font-mono text-xs text-blue-600">{{ $c->contract_number }}</a>
                                    @if ($c->installment_sequence)<span class="text-xs text-slate-400"> · {{ __('القسط رقم') }} {{ $c->installment_sequence }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 font-bold text-slate-900">{{ Money::format($c->amount, false) }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ Cheques::STATUSES[$c->status][1] ?? '' }}">{{ __(Cheques::STATUSES[$c->status][0] ?? $c->status) }}</span>
                                    @if ($c->status === 'bounced' && $c->bounce_reason)<div class="mt-1 text-xs text-rose-600">{{ $c->bounce_reason }}</div>@endif
                                </td>
                                <td class="px-5 py-3 text-end">@include('admin.cheques.actions', ['c' => $c])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages())<div class="border-t border-slate-100 p-4">{{ $rows->links() }}</div>@endif
        @endif
    </div>
@endsection
