{{-- قايمة أعمدة أفقية: @include('...bars', ['title' => '', 'items' => [['name','count','total','profit']], 'color' => 'bg-blue-500']) --}}
@php $mx = max(1, (float) collect($items)->max('total')); @endphp
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h2 class="mb-4 font-extrabold text-slate-900">{{ $title }}</h2>
    @forelse ($items as $it)
        <div class="mb-3">
            <div class="flex justify-between text-sm"><span class="font-bold text-slate-700">{{ $it['name'] }}</span><span class="text-slate-500">{{ $it['count'] }} · {{ \App\Support\Money::format($it['total'], false) }}</span></div>
            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $color ?? 'bg-blue-500' }}" style="width: {{ $it['total'] / $mx * 100 }}%"></div></div>
            <div class="mt-0.5 text-[11px] {{ $it['profit'] < 0 ? 'text-rose-500' : 'text-slate-400' }}">{{ __('ربح') }} {{ \App\Support\Money::format($it['profit'], false) }}</div>
        </div>
    @empty
        <p class="py-6 text-center text-sm text-slate-400">{{ __('لا توجد بيانات') }}</p>
    @endforelse
</section>
