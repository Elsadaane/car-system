@php use App\Support\Money; $medals = ['🥇', '🥈', '🥉']; @endphp
@forelse ($d['rows'] as $i => $b)
    @php $pct = (float) $b->target > 0 ? min(100, round($b->v / $b->target * 100)) : round($b->v / $d['max'] * 100); @endphp
    <div class="mb-2.5">
        <div class="mb-1 flex justify-between text-sm"><span class="font-bold text-slate-800">{{ $medals[$i] ?? ($i + 1).'.' }} {{ $b->n }}</span><span class="text-xs text-slate-500">{{ $b->c }} {{ __('عقد ·') }} <b class="text-slate-800">{{ Money::format($b->v, false) }}</b></span></div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $i === 0 ? 'bg-amber-400' : 'bg-blue-400' }}" style="width: {{ $pct }}%"></div></div>
        @if ((float) $b->target > 0)<div class="mt-0.5 text-[10px] text-slate-400">{{ $pct }}{{ __('% من التارجت') }}</div>@endif
    </div>
@empty
    <p class="py-6 text-center text-sm text-slate-400">{{ __('لسه مفيش مبيعات الشهر ده') }}</p>
@endforelse
