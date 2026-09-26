<div class="mb-7 flex items-end justify-between gap-4">
    <div>
        @if (! empty($kicker))<div class="mb-1 text-xs font-extrabold uppercase tracking-widest text-slate-400">{{ $kicker }}</div>@endif
        <h2 class="text-2xl font-black text-slate-900 sm:text-3xl">{!! $title !!}</h2>
        @if (! empty($sub))<p class="mt-1.5 text-sm text-slate-500">{{ $sub }}</p>@endif
    </div>
    @if (! empty($link))
        <a href="{{ $link }}" class="shrink-0 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-900 hover:text-white">{{ $linkLabel ?? __('عرض الكل') }} {{ \App\Support\Lang::isEn() ? '→' : '←' }}</a>
    @endif
</div>
