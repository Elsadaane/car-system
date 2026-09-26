@php $names = ['website' => __('🌐 الموقع مباشرة'), 'facebook' => __('📘 فيسبوك/إنستجرام'), 'instagram' => __('📸 إنستجرام'), 'google' => __('🔍 جوجل'), 'tiktok' => __('🎵 تيك توك'), 'snapchat' => __('👻 سناب')]; @endphp
@forelse ($d['rows'] as $r)
    @php $pct = $d['total'] ? round($r->c / $d['total'] * 100) : 0; @endphp
    <div class="mb-2.5">
        <div class="mb-1 flex justify-between text-xs"><span class="font-bold text-slate-700">{{ $names[strtolower($r->s)] ?? $r->s }}</span><span class="text-slate-500">{{ $r->c }} {{ __('طلب ·') }} <span class="font-bold text-emerald-600">{{ $r->c ? round($r->w / $r->c * 100) : 0 }}{{ __('% اتباع') }}</span></span></div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-500" style="width: {{ $pct }}%"></div></div>
    </div>
@empty
    <p class="py-6 text-center text-sm text-slate-400">{{ __('مفيش طلبات آخر 30 يوم') }}</p>
@endforelse
<p class="mt-2 text-[11px] text-slate-400">{{ __('آخر 30 يوم — استخدم روابط فيها utm_source في إعلاناتك عشان المصدر يتسجل صح.') }}</p>
