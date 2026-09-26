@php use App\Support\Money; @endphp
<div class="text-xs text-slate-500">{{ __('محصّل النهارده') }}</div>
<div class="truncate text-2xl font-black text-emerald-600">{{ Money::format($d['today'], false) }}</div>
<div class="mt-3 text-xs text-slate-500">{{ __('من أول الشهر') }}</div>
<div class="truncate text-lg font-extrabold text-slate-900">{{ Money::format($d['month'], false) }}</div>
