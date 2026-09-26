@php use App\Support\Money; use Illuminate\Support\Carbon; @endphp
<div class="mb-3 flex items-baseline justify-between text-sm"><span class="font-extrabold text-slate-900">{{ Money::format($d['total'], false) }}</span><span class="text-xs text-slate-400">{{ $d['count'] }} {{ __('عقد في 30 يوم') }}</span></div>
<div class="flex h-32 items-end gap-[3px]" dir="ltr">
    @foreach ($d['days'] as $day)
        @php $h = $day['v'] > 0 ? max(4, round($day['v'] / $d['max'] * 100)) : 2; $isToday = $day['d'] === today()->toDateString(); @endphp
        <div class="group relative flex-1 rounded-t {{ $day['v'] > 0 ? ($isToday ? 'bg-blue-600' : 'bg-blue-400') : 'bg-slate-100' }}" style="height: {{ $h }}%">
            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[10px] font-bold text-white group-hover:block">{{ Carbon::parse($day['d'])->format('d/m') }} · {{ $day['c'] }} · {{ Money::format($day['v'], false) }}</div>
        </div>
    @endforeach
</div>
<div class="mt-1 flex justify-between text-[10px] text-slate-400" dir="ltr"><span>{{ Carbon::parse($d['days'][0]['d'])->format('d/m') }}</span><span>{{ __('النهارده') }}</span></div>
