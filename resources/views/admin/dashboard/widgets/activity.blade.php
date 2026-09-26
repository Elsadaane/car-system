@php use App\Support\Audit; @endphp
@forelse ($d['rows'] as $a)
    @php [$label, $tone] = Audit::EVENTS[$a->event] ?? [$a->event, 'bg-slate-100 text-slate-600']; @endphp
    <div class="flex items-start gap-2 py-1.5 text-sm">
        <span class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $tone }}">{{ $label }}</span>
        <div class="min-w-0"><div class="truncate text-slate-700">{{ $a->description ?: Audit::describe($a->route, $a->method) }}</div><div class="text-[11px] text-slate-400">{{ $a->name ?? '—' }} · {{ \Illuminate\Support\Carbon::parse($a->created_at)->diffForHumans() }}</div></div>
    </div>
@empty
    <p class="py-6 text-center text-sm text-slate-400">{{ __('لسه مفيش نشاط') }}</p>
@endforelse
<a href="{{ route('admin.audit.index') }}" class="mt-2 block text-xs font-bold text-blue-600 hover:underline">{{ __('السجل كامل ←') }}</a>
