<div class="grid grid-cols-2 gap-2 text-center">
    <div class="rounded-xl bg-emerald-50 p-2"><div class="text-xl font-black text-emerald-700">{{ $d['present'] }}</div><div class="text-[11px] text-emerald-700">{{ __('حاضر') }}</div></div>
    <div class="rounded-xl bg-amber-50 p-2"><div class="text-xl font-black text-amber-700">{{ $d['late'] }}</div><div class="text-[11px] text-amber-700">{{ __('متأخر') }}</div></div>
    <div class="rounded-xl bg-rose-50 p-2"><div class="text-xl font-black text-rose-700">{{ $d['absent'] }}</div><div class="text-[11px] text-rose-700">{{ __('غايب') }}</div></div>
    <div class="rounded-xl bg-slate-50 p-2"><div class="text-xl font-black">{{ $d['leave'] }}</div><div class="text-[11px] text-slate-500">{{ __('إجازة') }}</div></div>
</div>
@if ($d['pending_leaves'])<a href="{{ route('admin.hr.leaves.index') }}" class="mt-3 block rounded-xl bg-violet-50 p-2 text-center text-xs font-bold text-violet-700">📝 {{ $d['pending_leaves'] }} {{ __('طلب إجازة مستني موافقة') }}</a>@endif
