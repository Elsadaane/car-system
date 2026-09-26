@php use App\Support\Alerts; @endphp
@forelse ($alerts as $a)
    @php [$bg, $txt, $dot] = Alerts::LEVELS[$a['level']]; @endphp
    <a href="{{ $a['url'] ?? '#' }}" class="flex gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 {{ $a['seen'] ? 'opacity-60' : '' }}">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-lg {{ $bg }}">{{ $a['icon'] }}</span>
        <span class="min-w-0 flex-1">
            <span class="flex items-start gap-2">
                <span class="flex-1 text-sm font-bold leading-5 {{ $a['level'] === 'danger' ? 'text-rose-700' : 'text-slate-800' }}">{{ $a['title'] }}</span>
                @unless ($a['seen'])<span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>@endunless
            </span>
            @if ($a['detail'])<span class="mt-0.5 block text-xs text-slate-500">{{ $a['detail'] }}</span>@endif
        </span>
    </a>
@empty
    <div class="px-6 py-10 text-center">
        <div class="text-3xl">✅</div>
        <div class="mt-2 text-sm font-bold text-slate-700">{{ __('كله تمام') }}</div>
        <div class="text-xs text-slate-400">{{ __('مفيش حاجة محتاجة منك تدخل دلوقتي') }}</div>
    </div>
@endforelse
