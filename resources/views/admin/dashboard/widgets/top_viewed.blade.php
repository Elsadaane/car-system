@forelse ($d['rows'] as $i => $v)
    <a href="{{ route('admin.vehicles.show', $v->id) }}" class="flex items-center justify-between gap-2 py-1.5 text-sm hover:text-blue-700"><span class="truncate"><b class="text-slate-400">{{ $i + 1 }}.</b> {{ $v->brand }} {{ $v->model }} {{ $v->manufacturing_year }}</span><span class="shrink-0 text-xs text-slate-500">👁️ {{ number_format($v->views_count) }}</span></a>
@empty
    <p class="py-4 text-center text-sm text-slate-400">{{ __('لسه مفيش مشاهدات') }}</p>
@endforelse
