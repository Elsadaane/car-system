@extends('admin.layouts.app')

@section('title', __('تجارب القيادة'))
@section('subtitle', __('مين جرّب إيه، وقد إيه منهم اشترى'))

@php
    use Illuminate\Support\Carbon;
    $conversion = $totals['completed'] ? round($totals['converted'] / $totals['completed'] * 100) : 0;
    $interestRate = $totals['completed'] ? round($totals['interested'] / $totals['completed'] * 100) : 0;
@endphp

@section('actions')
    <a href="{{ route('admin.test-drives.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('تجربة قيادة جديدة') }}
    </a>
@endsection

@section('content')
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('admin.test-drives.index', ['status' => 'scheduled']) }}"
           class="rounded-2xl p-5 shadow-sm ring-1 transition hover:-translate-y-0.5 {{ $totals['ongoing'] ? 'bg-blue-600 text-white ring-blue-600' : 'bg-white ring-slate-200' }}">
            <div class="flex items-center gap-2 text-sm {{ $totals['ongoing'] ? 'text-blue-100' : 'text-slate-500' }}">
                @if ($totals['ongoing']) <span class="relative flex h-2 w-2"><span class="absolute h-full w-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative h-2 w-2 rounded-full bg-white"></span></span> @endif
                {{ __('برة دلوقتي') }}
            </div>
            <div class="mt-2 text-2xl font-extrabold">{{ $totals['ongoing'] }}</div>
        </a>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('تجارب الشهر ده') }}</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $totals['month'] }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('نسبة الاهتمام') }}</div>
            <div class="mt-2 text-2xl font-extrabold text-amber-600">{{ $interestRate }}%</div>
            <div class="text-xs text-slate-400">{{ $totals['interested'] }} {{ __('من') }} {{ $totals['completed'] }} {{ __('قالوا مهتمين') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('اتحولت لبيعة') }}</div>
            <div class="mt-2 text-2xl font-extrabold text-emerald-600">{{ $conversion }}%</div>
            <div class="text-xs text-slate-400">{{ $totals['converted'] }} {{ __('اشتروا نفس العربية') }}</div>
        </div>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('اسم العميل، التليفون، كود العربية...') }}"
               class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <select name="vehicle" class="max-w-[220px] rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل العربيات') }}</option>
            @foreach ($vehicles as $v) <option value="{{ $v->id }}" @selected($filters['vehicle'] === $v->id)>{{ $v->brand_name }} {{ $v->model_name }} — {{ $v->vehicle_code }}</option> @endforeach
        </select>
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل الحالات') }}</option>
            @foreach ($statuses as $k => $l) <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option> @endforeach
        </select>
        <select name="interest" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('الاهتمام: الكل') }}</option>
            <option value="1" @selected($filters['interest'] === '1')>{{ __('مهتم') }}</option>
            <option value="0" @selected($filters['interest'] === '0')>{{ __('مش مهتم') }}</option>
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($drives->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-700">{{ __('مفيش تجارب قيادة') }}</p>
                <a href="{{ route('admin.test-drives.create') }}" class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:underline">{{ __('سجّل أول تجربة') }}</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العميل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العربية') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الوقت') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('النتيجة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('بواسطة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($drives as $d)
                            @php
                                $start = Carbon::parse($d->start_time);
                                $end = $d->end_time ? Carbon::parse($d->end_time) : null;
                                $minutes = (int) $start->diffInMinutes($end ?? now());
                                $wa = preg_replace('/\D/', '', (string) $d->customer_phone);
                            @endphp
                            <tr class="group hover:bg-slate-50/70 {{ $d->status === 'scheduled' ? 'bg-blue-50/40' : '' }}">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.test-drives.show', $d) }}" class="font-bold text-slate-900 group-hover:text-blue-700">{{ $d->customer_name }}</a>
                                    <div class="text-xs text-slate-400" dir="ltr" data-page-align>{{ $d->customer_phone }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $d->brand_name }} {{ $d->model_name }} {{ $d->manufacturing_year }}</div>
                                    <div class="font-mono text-xs text-slate-400">{{ $d->vehicle_code }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs">
                                    <div class="font-semibold text-slate-700">{{ $start->format('Y-m-d H:i') }}</div>
                                    <div class="{{ $d->status === 'scheduled' && $minutes > 60 ? 'font-bold text-rose-600' : 'text-slate-400' }}">
                                        {{ $d->status === 'scheduled' ? __('برة من ').$minutes.__(' دقيقة') : $minutes.__(' دقيقة') }}@if ($d->distance_km) · {{ $d->distance_km }} {{ __('كم') }} @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($d->status === 'scheduled')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-600 px-2.5 py-1 text-xs font-bold text-white">
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span> {{ __('جارية') }}
                                        </span>
                                    @elseif ($d->status === 'cancelled')
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">{{ __('ملغاة') }}</span>
                                    @elseif ($d->is_interested)
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ __('مهتم 👍') }}</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ __('مش مهتم') }}</span>
                                    @endif
                                    @if (! $d->customer_signature) <div class="mt-1 text-[10px] font-bold text-amber-600">{{ __('ناقص توقيع العميل') }}</div> @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-500">{{ $d->user_name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1">
                                        @if ($wa)
                                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" title="{{ __('واتساب') }}" class="grid h-8 w-8 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20l1.3-3.9A8 8 0 1 1 8 19z"/></svg>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.test-drives.print', $d) }}" target="_blank" title="{{ __('طباعة') }}" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7z"/></svg>
                                        </a>
                                        <a href="{{ route('admin.test-drives.show', $d) }}" class="rounded-lg px-3 py-1.5 text-xs font-bold {{ $d->status === 'scheduled' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' }}">
                                            {{ $d->status === 'scheduled' ? __('اقفل التجربة') : __('فتح') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($drives->hasPages()) <div class="border-t border-slate-100 p-4">{{ $drives->links() }}</div> @endif
        @endif
    </div>
@endsection
