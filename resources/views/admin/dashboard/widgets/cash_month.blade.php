@php use App\Support\Money; $max = max(1, (float) $d['top']->max('t')); @endphp
<div class="grid grid-cols-2 gap-2">
    <div class="rounded-xl bg-rose-50 p-3"><div class="text-xs text-rose-700">{{ __('مصروفات الشهر') }}</div><div class="truncate text-lg font-black text-rose-700">{{ Money::format($d['expenses'], false) }}</div></div>
    <div class="rounded-xl bg-emerald-50 p-3"><div class="text-xs text-emerald-700">{{ __('إيرادات أخرى') }}</div><div class="truncate text-lg font-black text-emerald-700">{{ Money::format($d['revenues'], false) }}</div></div>
</div>
<div class="mt-3 space-y-2">
    @foreach ($d['top'] as $c)
        <div><div class="flex justify-between text-xs"><span class="text-slate-600">{{ $c->n }}</span><span class="font-bold">{{ Money::format($c->t, false) }}</span></div><div class="mt-0.5 h-1.5 rounded-full bg-slate-100"><div class="h-full rounded-full bg-rose-400" style="width: {{ round($c->t / $max * 100) }}%"></div></div></div>
    @endforeach
</div>
