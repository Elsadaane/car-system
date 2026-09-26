@php use App\Support\Money; @endphp
<div class="grid grid-cols-2 gap-3">
    <div class="rounded-xl bg-blue-600 p-4 text-white">
        <div class="text-xs text-blue-100">{{ __('مبيعات الشهر') }}</div>
        <div class="mt-1 truncate text-2xl font-black">{{ Money::format($d['month_value'], false) }}</div>
        <div class="mt-1 flex items-center gap-2 text-xs text-blue-100">{{ $d['month_count'] }} {{ __('عقد') }} @include('admin.dashboard.widgets._delta', ['v' => $d['change']])</div>
    </div>
    <div class="rounded-xl bg-slate-50 p-4">
        <div class="text-xs text-slate-500">{{ __('النهارده') }}</div>
        <div class="mt-1 truncate text-2xl font-black text-slate-900">{{ Money::format($d['today_value'], false) }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $d['today_count'] }} {{ __('عقد') }}</div>
    </div>
</div>
<a href="{{ route('admin.sales-contracts.index') }}" class="mt-3 block text-xs font-bold text-blue-600 hover:underline">{{ __('كل العقود ←') }}</a>
