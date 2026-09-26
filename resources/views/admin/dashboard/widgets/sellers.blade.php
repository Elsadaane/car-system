@php use App\Support\Money; $medals = ['🥇', '🥈', '🥉']; @endphp
@forelse ($d['rows'] as $i => $s)
    <div class="flex items-center justify-between gap-2 py-2 text-sm {{ $s->id === auth()->id() ? 'rounded-lg bg-blue-50 px-2' : '' }}">
        <span class="truncate font-bold text-slate-800">{{ $medals[$i] ?? ($i + 1).'.' }} {{ $s->n }}</span>
        <span class="shrink-0 text-xs text-slate-500">{{ $s->c }} {{ __('عقد ·') }} <b class="text-slate-900">{{ Money::format($s->v, false) }}</b></span>
    </div>
@empty
    <p class="py-6 text-center text-sm text-slate-400">{{ __('لسه مفيش مبيعات الشهر ده') }}</p>
@endforelse
