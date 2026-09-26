@php use App\Support\Money; use Illuminate\Support\Carbon; @endphp
<div class="grid grid-cols-3 gap-2 text-center">
    <a href="{{ route('admin.vehicles.index', ['status' => 'available']) }}" class="rounded-xl bg-emerald-50 p-2"><div class="text-xl font-black text-emerald-700">{{ $d['available'] }}</div><div class="text-[11px] text-emerald-700">{{ __('متاحة') }}</div></a>
    <div class="rounded-xl bg-amber-50 p-2"><div class="text-xl font-black text-amber-700">{{ $d['reserved'] }}</div><div class="text-[11px] text-amber-700">{{ __('محجوزة') }}</div></div>
    <div class="rounded-xl bg-blue-50 p-2"><div class="text-xl font-black text-blue-700">{{ $d['published'] }}</div><div class="text-[11px] text-blue-700">{{ __('على الموقع') }}</div></div>
</div>
@if ($d['value'] !== null)<div class="mt-2 text-xs text-slate-500">{{ __('قيمة المخزون (تكلفة):') }} <b class="text-slate-800">{{ Money::format($d['value']) }}</b></div>@endif
@if ($d['stale_count'])
    <div class="mt-3 rounded-xl bg-rose-50 p-3">
        <div class="mb-1 text-xs font-extrabold text-rose-700">🐢 {{ $d['stale_count'] }} {{ __('عربية راكدة (+60 يوم) — فكّر في عرض') }}</div>
        @foreach ($d['stale'] as $v)
            <a href="{{ route('admin.vehicles.show', $v->id) }}" class="flex justify-between py-0.5 text-xs text-rose-800 hover:underline"><span>{{ $v->brand }} {{ $v->model }} {{ $v->manufacturing_year }}</span><span>{{ (int) Carbon::parse($v->created_at)->diffInDays(now()) }} {{ __('يوم') }}</span></a>
        @endforeach
    </div>
@endif
