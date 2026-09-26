@php use App\Support\Money; @endphp
<div class="rounded-xl {{ $d['net'] >= 0 ? 'bg-emerald-600' : 'bg-rose-600' }} p-4 text-white">
    <div class="flex items-center justify-between text-xs opacity-80"><span>{{ __('صافي الربح من أول الشهر') }}</span>@include('admin.dashboard.widgets._delta', ['v' => $d['change']])</div>
    <div class="mt-1 truncate text-3xl font-black">{{ Money::format($d['net'], false) }}</div>
</div>
<div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
    <div class="rounded-lg bg-slate-50 p-2"><div class="text-slate-400">{{ __('المبيعات') }}</div><div class="truncate font-extrabold">{{ Money::format($d['sales'], false) }}</div></div>
    <div class="rounded-lg bg-slate-50 p-2"><div class="text-slate-400">{{ __('مجمل الربح') }}</div><div class="truncate font-extrabold">{{ Money::format($d['gross'], false) }}</div></div>
    <div class="rounded-lg bg-slate-50 p-2"><div class="text-slate-400">{{ __('الهامش') }}</div><div class="font-extrabold">{{ $d['margin'] }}%</div></div>
</div>
@if (Route::has('admin.reports.pnl'))<a href="{{ route('admin.reports.pnl') }}" class="mt-3 block text-xs font-bold text-blue-600 hover:underline">{{ __('قائمة الأرباح والخسائر ←') }}</a>@endif
