@forelse ($d['rows'] as $t)
    <a href="{{ route('admin.test-drives.show', $t->id) }}" class="flex items-center justify-between gap-2 py-2 text-sm hover:text-blue-700">
        <span class="min-w-0"><span class="block truncate font-bold text-slate-800">{{ $t->customer ?? '—' }}</span><span class="block text-[11px] text-slate-400">{{ $t->brand }} {{ $t->model }}</span></span>
        <span class="shrink-0 rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold" dir="ltr">{{ \Illuminate\Support\Carbon::parse($t->start_time)->format('H:i') }}</span>
    </a>
@empty
    <p class="py-6 text-center text-sm text-slate-400">{{ __('مفيش تجارب قيادة النهارده') }}</p>
@endforelse
