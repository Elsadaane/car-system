<a href="{{ route('admin.backups.index') }}" class="block rounded-xl p-3 {{ $d['due'] ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800' }}">
    <div class="text-2xl">{{ $d['due'] ? '⏳' : '✅' }}</div>
    <div class="mt-1 text-sm font-extrabold">{{ $d['due'] ? __('نسخة النهارده لسه') : __('نسخة النهارده موجودة') }}</div>
    <div class="text-xs opacity-80">{{ $d['last'] ? __('آخر نسخة ').\Illuminate\Support\Carbon::createFromTimestamp($d['last'])->diffForHumans() : __('مفيش نسخ لسه') }} · {{ $d['count'] }}/14</div>
</a>
