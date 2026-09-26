{{-- نسبة التغيير عن المدة اللي قبلها: @include('...delta', ['cur' => 10, 'prev' => 8, 'invert' => false]) --}}
@php $pct = (float) $prev != 0.0 ? round(($cur - $prev) / abs($prev) * 100) : null; $good = $pct !== null && (($invert ?? false) ? $pct <= 0 : $pct >= 0); @endphp
@if ($pct !== null)
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold {{ $good ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $pct >= 0 ? '▲' : '▼' }} {{ abs($pct) }}%</span>
@endif
