{{-- تابات التقارير + فلتر المدة والفرع (مشترك) --}}
@php
    $tabs = ['overview' => __('📊 الإجمالي'), 'branches' => __('🏆 منافسة الفروع'), 'sales' => __('🚗 المبيعات'), 'profits' => __('💰 ربح كل عربية'),
             'inventory' => __('📦 المخزون'), 'receivables' => __('⏳ المديونيات'), 'pnl' => __('📑 الأرباح والخسائر')];
    $current = \Illuminate\Support\Str::afterLast(request()->route()->getName(), '.');
    $noDates = $noDates ?? false;
    $showBranch = $showBranch ?? true;
    $qs = fn (array $o) => array_filter(array_merge(['from' => $from, 'to' => $to, 'branch' => $branch], $o));
    $ranges = [
        'النهارده' => [today(), today()],
        'الأسبوع' => [now()->startOfWeek(\Carbon\CarbonInterface::SATURDAY), today()],
        'الشهر' => [now()->startOfMonth(), today()],
        'الشهر اللي فات' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
        'الربع' => [now()->firstOfQuarter(), today()],
        'السنة' => [now()->startOfYear(), today()],
    ];
@endphp

<nav class="mb-4 flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200 print:hidden">
    @foreach ($tabs as $k => $l)
        <a href="{{ route('admin.reports.'.$k, $qs([])) }}" class="shrink-0 rounded-xl px-3.5 py-2 text-sm font-bold transition {{ $current === $k ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $l }}</a>
    @endforeach
</nav>

<form method="GET" class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 print:hidden">
    @unless ($noDates)
        <input type="date" name="from" value="{{ $from }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <span class="text-slate-400">←</span>
        <input type="date" name="to" value="{{ $to }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
    @endunless
    @if ($showBranch && $branchesList->count() > 1)
        <select name="branch" data-no-search class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">{{ __('كل الفروع') }}</option>
            @foreach ($branchesList as $id => $n) <option value="{{ $id }}" @selected((int) $branch === (int) $id)>{{ $n }}</option> @endforeach
        </select>
    @endif
    @foreach (request()->only('sort') as $k => $v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endforeach
    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">{{ __('عرض') }}</button>
    @unless ($noDates)
        <div class="flex flex-wrap gap-1">
            @foreach ($ranges as $l => [$a, $b])
                @php $on = $from === $a->toDateString() && $to === $b->toDateString(); @endphp
                <a href="{{ route('admin.reports.'.$current, $qs(['from' => $a->toDateString(), 'to' => $b->toDateString()])) }}" class="rounded-full px-2.5 py-1 text-xs font-bold {{ $on ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $l }}</a>
            @endforeach
        </div>
    @endunless
    <button type="button" onclick="window.print()" class="ms-auto rounded-xl bg-white px-3 py-2 text-sm font-bold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('🖨️ طباعة') }}</button>
</form>
<div class="mb-4 hidden text-sm font-bold print:block">{{ \App\Support\Company::name() }} — @yield('title') {{ __('— من') }} {{ $from }} {{ __('إلى') }} {{ $to }}</div>
