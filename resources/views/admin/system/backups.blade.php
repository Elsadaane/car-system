@extends('admin.layouts.app')

@php use Illuminate\Support\Carbon; @endphp

@section('title', __('النسخ الاحتياطي'))
@section('subtitle', __('نسخة من كل بيانات السيستم — بتتعمل لوحدها مرتين في اليوم وبتترفع برّه السيرفر، وبيفضل آخر 14 يوم'))

@section('actions')
    @perm('backups.create')
        <form method="POST" action="{{ route('admin.backups.store') }}" onsubmit="{{ __('const b = this.querySelector(\'button\'); b.disabled = true; b.textContent = \'⏳ جاري عمل النسخة...\';') }}">@csrf
            <button class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-teal-700 disabled:opacity-60">{{ __('💾 اعمل نسخة دلوقتي') }}</button>
        </form>
    @endperm
@endsection

@section('content')
    <div class="mb-5 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl p-5 shadow-sm ring-1 {{ $dueToday ? 'bg-amber-50 ring-amber-200' : 'bg-emerald-50 ring-emerald-200' }}">
            <div class="text-sm font-bold {{ $dueToday ? 'text-amber-700' : 'text-emerald-700' }}">{{ $dueToday ? __('⏳ نسخة ميعاد ').$slot->format('H:i').__(' لسه ما اتعملتش') : __('✅ نسخة ميعاد ').$slot->format('H:i').__(' موجودة') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('مواعيدها:') }} {{ str_replace(',', __(' و '), config('backup.times')) }} {{ __('— لوحدها بالـ cron أو مع أول دخول للوحة بعد الميعاد') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('عدد النسخ') }}</div><div class="mt-1 text-2xl font-black">{{ count($backups) }} / {{ \App\Support\Backup::KEEP }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('آخر نسخة') }}</div><div class="mt-1 text-lg font-black">{{ $backups ? Carbon::createFromTimestamp($backups[0]['time'])->diffForHumans() : '—' }}</div></div>
    </div>

    {{-- ═══ برّه السيرفر ═══ --}}
    <h2 class="mb-3 text-base font-extrabold text-slate-900">{{ __('☁️ النسخ برّه السيرفر') }}</h2>
    @if (! $encrypted && ($s3Ready || $driveReady))
        <div class="mb-3 rounded-xl bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 ring-1 ring-amber-200">{{ __('⚠️ النسخ بتترفع من غير تشفير — حط BACKUP_PASSWORD في ملف .env (وفعّل إضافة zip) عشان بيانات العملاء تبقى محمية.') }}</div>
    @endif
    <div class="mb-6 grid gap-4 md:grid-cols-2">
        @foreach (['s3' => [$s3Ready, '🗄️'], 'gdrive' => [$driveReady, '📁']] as $t => [$ready, $icon])
            @php $st = $offsite[$t] ?? null; @endphp
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 {{ $ready && $st && ! $st['ok'] ? 'ring-rose-300' : 'ring-slate-200' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-xl {{ $ready ? 'bg-emerald-50' : 'bg-slate-100' }} text-xl">{{ $icon }}</span>
                        <div>
                            <div class="font-extrabold text-slate-900">{{ \App\Support\Offsite::TARGETS[$t] }}</div>
                            <div class="text-xs text-slate-500" dir="auto">
                                @if ($t === 's3') {{ $s3Ready ? __('فولدر: ').$s3Folder : __('مش متظبط — بيتظبط من ملف .env (BACKUP_S3_*)') }}
                                @else {{ $driveReady ? $driveEmail : ($driveConfigured ? __('مش مربوط') : __('مش متاح — محتاج GOOGLE_DRIVE_CLIENT_ID في .env')) }} @endif
                            </div>
                        </div>
                    </div>
                    @if ($t === 'gdrive' && $driveConfigured)
                        @if ($driveReady)
                            @perm('backups.delete')<form method="POST" action="{{ route('admin.backups.google.disconnect') }}" onsubmit="{{ __('return confirm(\'تفصل جوجل درايف؟ النسخ هتفضل تترفع على المخزن المركزي بس.\')') }}">@csrf @method('DELETE')<button class="rounded-lg px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('فصل') }}</button></form>@endperm
                        @else
                            @perm('backups.create')<a href="{{ route('admin.backups.google.connect') }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">{{ __('ربط Google Drive') }}</a>@endperm
                        @endif
                    @endif
                </div>
                @if ($ready)
                    <div class="mt-4 rounded-xl px-3 py-2.5 text-sm {{ ! $st ? 'bg-slate-50 text-slate-500' : ($st['ok'] ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800') }}">
                        @if (! $st) {{ __('لسه ما اترفعش ولا نسخة — دوس «اعمل نسخة دلوقتي» تجرّب') }}
                        @elseif ($st['ok']) {{ __('✅ آخر رفع') }} {{ Carbon::createFromTimestamp($st['at'])->diffForHumans() }} · {{ number_format(($st['size'] ?? 0) / 1048576, 1) }} MB{{ ! empty($st['files']) ? __(' · مع الصور والمرفقات') : '' }}
                        @else {{ __('❌ فشل') }} {{ Carbon::createFromTimestamp($st['at'])->diffForHumans() }}: {{ $st['error'] }}
                            @if (! empty($st['last_ok']))<div class="mt-1 text-xs opacity-75">{{ __('آخر رفع ناجح:') }} {{ Carbon::createFromTimestamp($st['last_ok'])->diffForHumans() }}</div>@endif
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @forelse ($backups as $b)
            <div class="flex items-center gap-4 border-b border-slate-100 p-4 last:border-0">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-teal-50 text-xl">🗄️</span>
                <div class="min-w-0 flex-1"><div class="truncate font-mono text-sm font-bold text-slate-800" dir="ltr">{{ $b['file'] }}</div><div class="text-xs text-slate-400">{{ Carbon::createFromTimestamp($b['time'])->format('Y-m-d H:i') }} · {{ number_format($b['size'] / 1024, 0) }} KB</div></div>
                <a href="{{ route('admin.backups.download', $b['file']) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-700">{{ __('⬇️ تنزيل') }}</a>
                @perm('backups.delete')<form method="POST" action="{{ route('admin.backups.destroy', $b['file']) }}" onsubmit="{{ __('return confirm(\'مسح النسخة دي؟\')') }}">@csrf @method('DELETE')<button class="rounded-xl px-3 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50">🗑️</button></form>@endperm
            </div>
        @empty
            <div class="p-12 text-center text-slate-400">{{ __('مفيش نسخ لسه — اضغط "اعمل نسخة دلوقتي".') }}</div>
        @endforelse
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl bg-blue-50 p-5 text-sm leading-relaxed text-blue-900 ring-1 ring-blue-100">
            <div class="mb-1 font-extrabold">{{ __('🔁 إزاي أرجّع نسخة؟') }}</div>
            {{ __('نزّل الملف، وافتحه في HeidiSQL (مع Laragon) أو phpMyAdmin من Import. ⚠️ الاسترجاع بيمسح البيانات الحالية ويحط مكانها بيانات النسخة.') }}
        </div>
        <div class="rounded-2xl bg-slate-50 p-5 text-sm leading-relaxed text-slate-700 ring-1 ring-slate-200">
            <div class="mb-1 font-extrabold">{{ __('☁️ النسخة اللي برّه السيرفر فيها إيه؟') }}</div>
            {{ __('ملف zip') }} {{ $encrypted ? __('مشفّر بباسورد (افتحه بـ 7-Zip واكتب الباسورد اللي في BACKUP_PASSWORD)') : __('عادي') }} {{ __('— جواه') }} <span dir="ltr" class="font-mono">database.sql.gz</span> {{ __('(قاعدة البيانات)، ومرة في اليوم كمان فولدر') }} <span dir="ltr" class="font-mono">files</span> {{ __('فيه صور العربيات والمرفقات') }}{{ $filesAt ? __(' (آخر مرة ').Carbon::createFromTimestamp($filesAt)->diffForHumans().')' : '' }}.
        </div>
    </div>
@endsection
