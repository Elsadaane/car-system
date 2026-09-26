@extends('admin.layouts.app')

@section('title', __('التقرير الإجمالي'))
@section('subtitle', __('صورة المعرض كاملة في صفحة واحدة'))

@php
    use App\Support\Money;
    $conv = $kpi['drive_customers'] ? round($kpi['converted'] / $kpi['drive_customers'] * 100) : null;
    $d = fn ($c, $pv, $inv = false) => view('admin.reports.partials.delta', ['cur' => $c, 'prev' => $pv, 'invert' => $inv])->render();
    $maxBrand = max(1, $topBrands->max('count') ?? 1);
@endphp

@section('content')
    @include('admin.reports.partials.header')

    {{-- ═══ الأرقام الكبيرة ═══ --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 p-5 text-white shadow-lg">
            <div class="flex items-center justify-between text-sm text-blue-100">{{ __('المبيعات') }} {!! $d($pnl['sales'], $prev['sales']) !!}</div>
            <div class="mt-2 text-3xl font-extrabold">{{ Money::format($pnl['sales'], false) }}</div>
            <div class="mt-1 text-xs text-blue-100">{{ $pnl['count'] }} {{ __('عربية · بدون ضريبة') }}</div>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-5 text-white shadow-lg">
            <div class="flex items-center justify-between text-sm text-emerald-50">{{ __('صافي الربح') }} {!! $d($pnl['net'], $prev['net']) !!}</div>
            <div class="mt-2 text-3xl font-extrabold">{{ Money::format($pnl['net'], false) }}</div>
            <div class="mt-1 text-xs text-emerald-50">{{ __('هامش') }} {{ $pnl['margin'] }}{{ __('% · مجمل ربح العربيات') }} {{ Money::format($pnl['gross'], false) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('اتحصّل من العملاء') }}</div>
            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ Money::format($collected, false) }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ __('مقدمات + أقساط + كاش') }}</div>
        </div>
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg">
            <div class="text-sm text-slate-400">{{ __('السيولة دلوقتي') }}</div>
            <div class="mt-2 text-3xl font-extrabold">{{ Money::format($kpi['cash'], false) }}</div>
            <a href="{{ route('admin.accounts.index') }}" class="mt-1 inline-block text-xs text-slate-400 hover:text-white">{{ __('كل الخزائن ←') }}</a>
        </div>
    </div>

    {{-- ═══ الإحصائيات ═══ --}}
    <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
        @foreach ([
            [__('عربيات اتضافت'), $kpi['vehicles_added'], null, 'admin.vehicles.index'],
            [__('عربيات متاحة'), $kpi['available'], __('قيمتها ').Money::format($kpi['stock_value'], false), 'admin.reports.inventory'],
            [__('متوسط سعر البيعة'), $pnl['count'] ? Money::format($pnl['sales'] / $pnl['count'], false) : '—', null, null],
            [__('تجارب قيادة'), $kpi['drives'], $conv !== null ? __('اشترى منهم ').$conv.'%' : null, 'admin.test-drives.index'],
            [__('مصروفات عامة'), Money::format($pnl['expenses_total'], false), null, 'admin.expenses.index'],
            [__('إيرادات أخرى'), Money::format($pnl['revenues_total'], false), null, 'admin.revenues.index'],
            [__('فلوس برّه (عملاء)'), Money::format($kpi['receivables'], false), null, 'admin.reports.receivables'],
            [__('أقساط متأخرة'), Money::format($kpi['overdue'], false), null, 'admin.installments.index'],
            [__('مستحق للملاك (تفويض)'), Money::format($kpi['dealer_due'], false), null, 'admin.dealer-contracts.index'],
            [__('طلبات إلغاء'), $kpi['cancellations'], null, 'admin.cancellations.index'],
            [__('الخصومات'), Money::format($pnl['discounts'], false), null, null],
            [__('إهلاك الأصول'), Money::format($pnl['depreciation'], false), null, 'admin.assets.index'],
        ] as [$label, $value, $hint, $route])
            @php $tag = $route ? 'a' : 'div'; @endphp
            <{{ $tag }} @if ($route) href="{{ route($route) }}" @endif class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 {{ $route ? 'transition hover:-translate-y-0.5 hover:shadow-md' : '' }}">
                <div class="text-xs text-slate-500">{{ $label }}</div>
                <div class="mt-1 truncate text-lg font-extrabold text-slate-900">{{ $value }}</div>
                @if ($hint) <div class="text-[11px] text-slate-400">{{ $hint }}</div> @endif
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- الماركات --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 font-extrabold text-slate-900">{{ __('أكتر الماركات مبيعاً') }}</h2>
            @forelse ($topBrands as $i => $b)
                <div class="mb-3">
                    <div class="flex justify-between text-sm"><span class="font-bold">{{ ['🥇', '🥈', '🥉'][$i] ?? '•' }} {{ $b['name'] }}</span><span class="text-slate-500">{{ $b['count'] }} {{ __('عربية') }}</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-500" style="width: {{ $b['count'] / $maxBrand * 100 }}%"></div></div>
                    <div class="mt-0.5 text-[11px] text-slate-400">{{ __('ربح') }} {{ Money::format($b['profit'], false) }}</div>
                </div>
            @empty <p class="py-6 text-center text-sm text-slate-400">{{ __('مفيش مبيعات في المدة دي') }}</p> @endforelse
        </section>

        {{-- البياعين --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 font-extrabold text-slate-900">{{ __('أشطر البياعين') }}</h2>
            @forelse ($topSellers as $i => $s)
                <div class="mb-2 flex items-center gap-3 rounded-xl p-2 {{ $i === 0 ? 'bg-amber-50' : '' }}">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-sm font-bold text-white">{{ mb_substr($s['name'], 0, 1) }}</span>
                    <div class="min-w-0 flex-1"><div class="truncate text-sm font-bold">{{ $s['name'] }} {{ $i === 0 ? '👑' : '' }}</div><div class="text-[11px] text-slate-400">{{ $s['count'] }} {{ __('بيعة · ربح') }} {{ Money::format($s['profit'], false) }}</div></div>
                    <div class="text-sm font-extrabold">{{ Money::format($s['total'], false) }}</div>
                </div>
            @empty <p class="py-6 text-center text-sm text-slate-400">—</p> @endforelse
            <a href="{{ route('admin.reports.branches', request()->only('from', 'to')) }}" class="mt-2 block text-center text-xs font-bold text-blue-600 hover:underline">{{ __('لوحة الشرف كاملة ←') }}</a>
        </section>

        {{-- التمويل --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 font-extrabold text-slate-900">{{ __('طلبات التمويل حسب الشركة') }}</h2>
            @forelse ($finance as $f)
                <div class="mb-3 rounded-xl bg-slate-50 p-3">
                    <div class="flex justify-between text-sm"><span class="font-bold">{{ $f->name }}</span><span class="text-xs text-slate-500">{{ $f->ok }}/{{ $f->cnt }} {{ __('اتوافق') }}</span></div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $f->cnt ? $f->ok / $f->cnt * 100 : 0 }}%"></div></div>
                    <div class="mt-1 text-[11px] text-slate-400">{{ __('موّلت') }} {{ Money::format($f->amount, false) }}</div>
                </div>
            @empty <p class="py-6 text-center text-sm text-slate-400">{{ __('لا توجد بيانات') }}</p> @endforelse
        </section>
    </div>

    {{-- ملخص قائمة الدخل --}}
    <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold text-slate-900">{{ __('من المبيعات للربح الصافي') }}</h2><a href="{{ route('admin.reports.pnl', request()->only('from', 'to', 'branch')) }}" class="text-xs font-bold text-blue-600 hover:underline">{{ __('التفاصيل ←') }}</a></div>
        @php
            $flow = [[__('المبيعات'), $pnl['sales'], 'bg-blue-500'], [__('− تكلفة العربيات'), -$pnl['cogs'], 'bg-slate-400'], [__('− مصروفات العربيات'), -$pnl['car_expenses'], 'bg-slate-300'],
                     [__('+ إيرادات أخرى'), $pnl['revenues_total'], 'bg-emerald-400'], [__('− مصروفات عامة'), -$pnl['expenses_total'], 'bg-rose-400'], [__('− المرتبات'), -($pnl['salaries'] + $pnl['eos']), 'bg-violet-400'], [__('− الإهلاك'), -$pnl['depreciation'], 'bg-amber-400'], [__('= صافي الربح'), $pnl['net'], $pnl['net'] >= 0 ? 'bg-emerald-600' : 'bg-rose-600']];
            $maxFlow = max(1, collect($flow)->max(fn ($f) => abs($f[1])));
        @endphp
        <div class="space-y-2">
            @foreach ($flow as [$l, $val, $c])
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-36 shrink-0 font-semibold text-slate-600">{{ $l }}</div>
                    <div class="h-6 flex-1 overflow-hidden rounded-lg bg-slate-50"><div class="h-full rounded-lg {{ $c }}" style="width: {{ abs($val) / $maxFlow * 100 }}%"></div></div>
                    <div class="w-32 shrink-0 text-end font-extrabold {{ $val < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ Money::format($val, false) }}</div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
