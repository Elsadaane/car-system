@php use App\Support\Money; @endphp
<div class="grid grid-cols-2 gap-3">
    <a href="{{ route('admin.installments.index', ['tab' => 'overdue']) }}" class="rounded-xl {{ $d['over_count'] ? 'bg-rose-600 text-white' : 'bg-slate-50' }} p-3"><div class="text-xs opacity-80">{{ __('🔴 متأخرة') }}</div><div class="truncate text-xl font-black">{{ Money::format($d['over_amount'], false) }}</div><div class="text-xs opacity-80">{{ $d['over_count'] }} {{ __('قسط') }}</div></a>
    <a href="{{ route('admin.installments.index', ['tab' => 'week']) }}" class="rounded-xl bg-amber-50 p-3"><div class="text-xs text-amber-700">{{ __('⏰ الأسبوع ده') }}</div><div class="truncate text-xl font-black text-amber-800">{{ Money::format($d['week_amount'], false) }}</div><div class="text-xs text-amber-700">{{ $d['week_count'] }} {{ __('قسط') }}</div></a>
</div>
<div class="mt-3 divide-y divide-slate-100">
    @foreach ($d['list'] as $i)
        <div class="flex items-center justify-between gap-2 py-2 text-sm">
            <div class="min-w-0"><div class="truncate font-bold text-slate-800">{{ $i->customer ?? '—' }}</div><div class="text-[11px] text-rose-600">{{ __('متأخر') }} {{ (int) \Illuminate\Support\Carbon::parse($i->due_date)->diffInDays(today()) }} {{ __('يوم') }}</div></div>
            <div class="flex shrink-0 items-center gap-2"><span class="font-extrabold">{{ Money::format($i->due, false) }}</span>@if ($i->phone)<a href="https://wa.me/{{ preg_replace('/\D/', '', $i->phone) }}" target="_blank" class="rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">{{ __('فكّره') }}</a>@endif</div>
        </div>
    @endforeach
</div>
