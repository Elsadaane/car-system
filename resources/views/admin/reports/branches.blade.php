@extends('admin.layouts.app')

@section('title', __('منافسة الفروع'))
@section('subtitle', __('مين الفرع الأشطر؟ ترتيب بالنقاط + تارجت الشهر + لوحة شرف البياعين'))

@php
    use App\Support\Money;
    $podium = $rows->take(3);
    $medals = ['🥇', '🥈', '🥉'];
    $heights = ['h-40', 'h-28', 'h-20'];
    $order = [1, 0, 2]; // التاني - الأول - التالت
    $maxTotal = max(1, $rows->max('total'));
@endphp

@section('content')
    @include('admin.reports.partials.header', ['showBranch' => false])

    @if ($rows->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200"><p class="font-bold text-slate-600">{{ __('مفيش فروع') }}</p></div>
    @else
        {{-- ═══ منصة التتويج ═══ --}}
        @if ($rows->count() > 1)
            <section class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-b from-slate-900 to-slate-800 px-6 pt-8 text-white shadow-xl">
                <div class="text-center text-sm font-bold text-slate-400">{{ __('🏆 ترتيب الفروع في المدة') }}</div>
                <div class="mx-auto mt-6 flex max-w-2xl items-end justify-center gap-3">
                    @foreach ($order as $pos)
                        @if ($r = $podium[$pos] ?? null)
                            <div class="flex-1 text-center">
                                <div class="text-4xl">{{ $medals[$pos] }}</div>
                                <div class="mt-1 truncate font-extrabold">{{ $r->name }}</div>
                                <div class="text-xs text-slate-400">{{ $r->score }} {{ __('نقطة ·') }} {{ Money::format($r->total, false) }}</div>
                                <div class="mt-3 {{ $heights[$pos] }} rounded-t-2xl {{ $pos === 0 ? 'bg-gradient-to-t from-amber-500 to-yellow-300' : ($pos === 1 ? 'bg-gradient-to-t from-slate-400 to-slate-200' : 'bg-gradient-to-t from-orange-700 to-orange-400') }} grid place-items-center text-3xl font-black text-slate-900/60">{{ $pos + 1 }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ═══ الكروت ═══ --}}
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($rows as $i => $r)
                @php $tp = $r->target > 0 ? min(100, round($r->month / $r->target * 100)) : null; @endphp
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 {{ $i === 0 && $rows->count() > 1 ? 'ring-2 ring-amber-400' : 'ring-slate-200' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-slate-100 text-xl">{{ $medals[$i] ?? '#'.($i + 1) }}</span>
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900">{{ $r->name }}</h3>
                                <div class="text-xs text-slate-500">{{ $r->count }} {{ __('بيعة') }} @if ($r->growth !== null) · <span class="font-bold {{ $r->growth >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $r->growth >= 0 ? '▲' : '▼' }} {{ abs($r->growth) }}{{ __('% عن المدة اللي فاتت') }}</span> @endif</div>
                            </div>
                        </div>
                        <div class="text-end"><div class="text-2xl font-black text-slate-900">{{ $r->score }}</div><div class="text-[11px] text-slate-400">{{ __('نقطة') }}</div></div>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full rtl:bg-gradient-to-l ltr:bg-gradient-to-r from-blue-500 to-indigo-600" style="width: {{ $r->score }}%"></div></div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-xl bg-slate-50 p-2"><div class="text-slate-500">{{ __('المبيعات') }}</div><div class="truncate font-extrabold">{{ Money::format($r->total, false) }}</div></div>
                        <div class="rounded-xl bg-slate-50 p-2"><div class="text-slate-500">{{ __('صافي الربح') }}</div><div class="truncate font-extrabold {{ $r->profit < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ Money::format($r->profit, false) }}</div></div>
                        <div class="rounded-xl bg-slate-50 p-2"><div class="text-slate-500">{{ __('التحصيل') }}</div><div class="truncate font-extrabold">{{ Money::format($r->collected, false) }}</div></div>
                        <div class="rounded-xl bg-slate-50 p-2"><div class="text-slate-500">{{ __('متوسط البيعة') }}</div><div class="truncate font-bold">{{ Money::format($r->avg, false) }}</div></div>
                        <div class="rounded-xl bg-slate-50 p-2"><div class="text-slate-500">{{ __('التجارب ← بيع') }}</div><div class="font-bold">{{ $r->drives }} ← {{ $r->conversion !== null ? $r->conversion.'%' : '—' }}</div></div>
                        <div class="rounded-xl bg-slate-50 p-2"><div class="text-slate-500">{{ __('بيبيع في') }}</div><div class="font-bold">{{ $r->days !== null ? $r->days.__(' يوم') : '—' }}</div></div>
                    </div>
                    <div class="mt-3 flex justify-between text-xs text-slate-500"><span>{{ __('المخزون:') }} {{ $r->stock }} {{ __('عربية (') }}{{ Money::format($r->stock_value, false) }})</span><span>{{ __('مصروفات:') }} {{ Money::format($r->expenses, false) }}</span></div>

                    <div class="mt-4 rounded-xl p-3 {{ $tp === null ? 'bg-slate-50' : ($tp >= 100 ? 'bg-emerald-50' : 'bg-blue-50') }}">
                        @if ($tp !== null)
                            <div class="flex justify-between text-xs font-bold"><span>{{ __('تارجت الشهر ده') }}</span><span>{{ Money::format($r->month, false) }} / {{ Money::format($r->target, false) }} ({{ $tp }}%){{ $tp >= 100 ? ' 🎉' : '' }}</span></div>
                            <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full {{ $tp >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $tp }}%"></div></div>
                            @if ($tp < 100)
                                @php $daysLeft = (int) today()->diffInDays(now()->endOfMonth()) + 1; @endphp
                                <div class="mt-1 text-[11px] text-slate-500">{{ __('فاضل') }} {{ Money::format($r->target - $r->month, false) }} {{ __('في') }} {{ $daysLeft }} {{ __('يوم') }}</div>
                            @endif
                        @else
                            <div class="text-xs text-slate-400">{{ __('مفيش تارجت — حطه من تحت 👇') }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            {{-- ═══ لوحة الشرف ═══ --}}
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
                <h2 class="border-b border-slate-100 px-6 py-4 font-extrabold text-slate-900">{{ __('🌟 لوحة شرف البياعين') }}</h2>
                @if ($sellers->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-slate-400">{{ __('مفيش مبيعات في المدة دي') }}</p>
                @else
                    @php $maxS = max(1, $sellers->max('total')); @endphp
                    <div class="divide-y divide-slate-100">
                        @foreach ($sellers as $i => $s)
                            <div class="flex items-center gap-4 px-6 py-3">
                                <span class="w-8 text-center text-lg">{{ $medals[$i] ?? ($i + 1) }}</span>
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 font-bold text-white">{{ mb_substr($s->name, 0, 1) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between text-sm"><span class="truncate font-bold">{{ $s->name }} <span class="text-xs font-normal text-slate-400">{{ $s->branch }}</span></span><span class="font-extrabold">{{ Money::format($s->total, false) }}</span></div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-500" style="width: {{ $s->total / $maxS * 100 }}%"></div></div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ $s->count }} {{ __('بيعة · ربح') }} {{ Money::format($s->profit, false) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ═══ التارجت ═══ --}}
            <form method="POST" action="{{ route('admin.reports.targets') }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 print:hidden">
                @csrf @method('PATCH')
                <h2 class="font-extrabold text-slate-900">{{ __('🎯 التارجت الشهري') }}</h2>
                <p class="mb-4 text-xs text-slate-500">{{ __('مبيعات كل فرع المطلوبة في الشهر.') }}</p>
                <div class="space-y-3">
                    @foreach ($rows as $r)
                        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">{{ $r->name }}</span>
                            <input type="number" step="0.001" min="0" name="targets[{{ $r->id }}]" value="{{ $r->target ?: '' }}" placeholder="{{ __('بدون تارجت') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </label>
                    @endforeach
                </div>
                <button class="mt-4 w-full rounded-xl bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('حفظ التارجت') }}</button>
                <p class="mt-3 text-[11px] leading-relaxed text-slate-400">{{ __('النقاط: المبيعات 40% + صافي الربح 30% + التحصيل 20% + تحويل التجارب لبيع 10% (كل واحدة بالنسبة لأعلى فرع).') }}</p>
            </form>
        </div>
    @endif
@endsection
