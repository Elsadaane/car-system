@extends('admin.layouts.app')

@section('title', __('المديونيات'))
@section('subtitle', __('فلوسك اللي برّه عند العملاء — متقسّمة حسب التأخير'))

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $labels = ['current' => [__('لسه ماجاش ميعادها'), 'from-slate-500 to-slate-600'], '1-30' => [__('متأخر لحد شهر'), 'from-amber-400 to-amber-500'], '31-60' => [__('متأخر 1-2 شهر'), 'from-orange-500 to-orange-600'], '61-90' => [__('متأخر 2-3 شهور'), 'from-rose-500 to-rose-600'], '+90' => [__('أكتر من 3 شهور'), 'from-rose-700 to-red-900']];
    $totalAging = max(1, $aging->sum());
@endphp

@section('content')
    @include('admin.reports.partials.header', ['noDates' => true])

    <section class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-slate-900">{{ __('أعمار الأقساط المفتوحة') }}</h2>
            <div class="text-sm">{{ __('الإجمالي:') }} <b>{{ Money::format($aging->sum()) }}</b></div>
        </div>
        <div class="mt-4 flex h-5 overflow-hidden rounded-full bg-slate-100">
            @foreach ($aging as $k => $v) @if ($v > 0) <div class="h-full rtl:bg-gradient-to-l ltr:bg-gradient-to-r {{ $labels[$k][1] }}" style="width: {{ $v / $totalAging * 100 }}%" title="{{ $labels[$k][0] }}"></div> @endif @endforeach
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
            @foreach ($aging as $k => $v)
                <div class="rounded-xl bg-gradient-to-br {{ $labels[$k][1] }} p-4 text-white">
                    <div class="text-xs text-white/80">{{ $labels[$k][0] }}</div>
                    <div class="mt-1 truncate text-lg font-extrabold">{{ Money::format($v, false) }}</div>
                    <div class="text-[11px] text-white/70">{{ round($v / $totalAging * 100) }}%</div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-100 px-6 py-4 font-extrabold text-slate-900">{{ __('العملاء المدينين (') }}{{ $customers->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('العميل') }}</th><th class="px-3 py-3 text-start">{{ __('عقود') }}</th><th class="px-3 py-3 text-start">{{ __('الباقي عليه') }}</th><th class="px-3 py-3 text-start">{{ __('متأخر') }}</th><th class="px-3 py-3 text-start">{{ __('أقدم تأخير') }}</th><th class="px-3 py-3 text-start">{{ __('القسط الجاي') }}</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $c)
                        @php $wa = preg_replace('/\D/', '', (string) $c->phone); @endphp
                        <tr class="{{ $c->overdue > 0 ? 'bg-rose-50/40' : '' }} hover:bg-slate-50/70">
                            <td class="px-5 py-2.5"><a href="{{ route('admin.customers.show', $c->id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ \App\Support\Lang::pick($c, 'name') }}</a><div class="text-xs text-slate-400" dir="ltr" data-page-align>{{ $c->phone }}</div></td>
                            <td class="px-3 py-2.5">{{ $c->contracts }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-extrabold">{{ Money::format($c->remaining, false) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">@if ($c->overdue > 0) <span class="font-bold text-rose-600">{{ Money::format($c->overdue, false) }}</span> <span class="text-xs text-slate-400">({{ $c->overdue_count }} {{ __('قسط)') }}</span> @else — @endif</td>
                            <td class="px-3 py-2.5 text-xs">{{ $c->oldest ? (int) Carbon::parse($c->oldest)->diffInDays(today()).__(' يوم') : '—' }}</td>
                            <td class="px-3 py-2.5 text-xs">{{ $c->next ? Carbon::parse($c->next)->format('Y-m-d') : '—' }}</td>
                            <td class="px-5 py-2.5 text-end print:hidden">
                                @if ($wa && $c->overdue > 0)
                                    <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode("أهلاً {$c->name_ar}\nنفكّرك إن عليك ".Money::format($c->overdue).' أقساط متأخرة. ياريت تتواصل معانا. شكراً لك.') }}" target="_blank" rel="noopener" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100">{{ __('⏰ تذكير') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">{{ __('🎉 مفيش مديونيات') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
