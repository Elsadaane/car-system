@if ($v !== null)
    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $v >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}" title="{{ __('مقارنة بنفس الفترة الشهر اللي فات') }}">{{ $v >= 0 ? '▲' : '▼' }} {{ abs($v) }}%</span>
@endif
