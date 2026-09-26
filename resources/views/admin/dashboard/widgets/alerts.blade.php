@php use App\Support\Alerts; @endphp
@forelse ($d['list'] as $a)
    @php [$bg, $txt] = Alerts::LEVELS[$a['level']]; @endphp
    <a href="{{ $a['url'] ?? '#' }}" class="mb-2 flex items-center gap-3 rounded-xl p-2.5 last:mb-0 {{ $bg }} hover:opacity-90">
        <span class="text-lg">{{ $a['icon'] }}</span>
        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold {{ $txt }}">{{ $a['title'] }}</span>@if ($a['detail'])<span class="block truncate text-[11px] text-slate-500">{{ $a['detail'] }}</span>@endif</span>
    </a>
@empty
    <div class="rounded-xl bg-emerald-50 p-4 text-center text-sm font-bold text-emerald-700">{{ __('✅ كله تمام — مفيش حاجة مستنياك') }}</div>
@endforelse
@if ($d['total'] > count($d['list']))
    <a href="{{ route('admin.notifications.index') }}" class="mt-2 block text-center text-xs font-bold text-blue-600">+ {{ $d['total'] - count($d['list']) }} {{ __('تنبيه كمان') }}</a>
@endif
