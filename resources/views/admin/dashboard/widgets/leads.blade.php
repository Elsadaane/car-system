@php $types = ['inquiry' => __('استفسار'), 'test_drive' => __('تجربة'), 'reservation' => __('حجز'), 'finance' => __('تقسيط'), 'trade_in' => __('بدل'), 'contact' => __('تواصل')]; @endphp
<div class="grid grid-cols-4 gap-2 text-center">
    <a href="{{ route('admin.inquiries.index', ['status' => 'new']) }}" class="rounded-xl bg-amber-50 p-2"><div class="text-xl font-black text-amber-600">{{ $d['new'] }}</div><div class="text-[11px] text-amber-700">{{ __('جديدة') }}</div></a>
    <div class="rounded-xl {{ $d['late'] ? 'bg-rose-600 text-white' : 'bg-slate-50' }} p-2"><div class="text-xl font-black">{{ $d['late'] }}</div><div class="text-[11px] opacity-80">{{ __('متأخرة +24س') }}</div></div>
    <div class="rounded-xl bg-slate-50 p-2"><div class="text-xl font-black">{{ $d['today'] }}</div><div class="text-[11px] text-slate-500">{{ __('النهارده') }}</div></div>
    <div class="rounded-xl bg-blue-50 p-2"><div class="text-xl font-black text-blue-700">{{ $d['mine'] }}</div><div class="text-[11px] text-blue-700">{{ __('عليّا') }}</div></div>
</div>
<div class="mt-3 divide-y divide-slate-100">
    @forelse ($d['latest'] as $l)
        <div class="flex items-center justify-between gap-2 py-2 text-sm">
            <div class="min-w-0"><div class="truncate font-bold text-slate-800">{{ $l->name }}</div><div class="text-[11px] text-slate-400">{{ $types[$l->type] ?? $l->type }} · {{ \Illuminate\Support\Carbon::parse($l->created_at)->diffForHumans() }}</div></div>
            <a href="https://wa.me/{{ preg_replace('/\D/', '', $l->phone) }}" target="_blank" class="shrink-0 rounded-lg bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ __('واتساب') }}</a>
        </div>
    @empty
        <p class="py-3 text-center text-sm text-slate-400">{{ __('مفيش طلبات مفتوحة 🎉') }}</p>
    @endforelse
</div>
