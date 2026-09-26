<div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-9">
    @forelse ($d['actions'] as $a)
        <a href="{{ $a['url'] }}" class="flex flex-col items-center gap-1.5 rounded-xl bg-slate-50 p-3 text-center text-xs font-bold text-slate-700 transition hover:-translate-y-0.5 hover:bg-blue-50 hover:text-blue-700">
            <span class="text-2xl">{{ $a['icon'] }}</span>{{ $a['label'] }}
        </a>
    @empty
        <p class="col-span-full text-sm text-slate-400">{{ __('مفيش إجراءات متاحة لصلاحياتك.') }}</p>
    @endforelse
</div>
