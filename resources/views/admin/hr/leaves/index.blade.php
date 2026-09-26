@extends('admin.layouts.app')

@section('title', __('الإجازات'))
@section('subtitle', __('طلبات الإجازات والموافقة عليها — والرصيد بيتحسب لوحده'))

@php
    use Illuminate\Support\Carbon;
    $style = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-slate-100 text-slate-500'];
    $labels = ['pending' => __('مستنية'), 'approved' => __('موافق'), 'rejected' => __('مرفوضة')];
@endphp

@section('actions')
    <button type="button" data-open="leaveModal" class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">{{ __('+ إجازة جديدة') }}</button>
@endsection

@section('content')
    @include('admin.hr.partials-tabs')

    @if ($today->isNotEmpty())
        <div class="mb-5 flex flex-wrap items-center gap-2 rounded-2xl bg-violet-50 p-4 ring-1 ring-violet-200">
            <span class="text-sm font-extrabold text-violet-900">{{ __('🌴 في إجازة النهارده:') }}</span>
            @foreach ($today as $t) <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-violet-700">{{ \App\Support\Lang::pick($t, 'full_name') }} {{ __('(راجع') }} {{ Carbon::parse($t->end_date)->addDay()->format('m-d') }})</span> @endforeach
        </div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.hr.leaves.index', array_filter(['employee' => $employee])) }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ ! $status ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ __('الكل') }}</a>
        @foreach ($labels as $k => $l)
            <a href="{{ route('admin.hr.leaves.index', array_filter(['status' => $k, 'employee' => $employee])) }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $status === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $l }} ({{ (int) ($counts[$k] ?? 0) }})</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($rows->isEmpty())
            <p class="px-6 py-16 text-center text-slate-400">{{ __('مفيش إجازات') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('الموظف') }}</th><th class="px-3 py-3 text-start">{{ __('النوع') }}</th><th class="px-3 py-3 text-start">{{ __('المدة') }}</th><th class="px-3 py-3 text-start">{{ __('السبب') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $r)
                            <tr class="{{ $r->status === 'pending' ? 'bg-amber-50/40' : '' }}">
                                <td class="px-5 py-3"><a href="{{ route('admin.hr.employees.show', $r->employee_id) }}" class="font-bold text-slate-800 hover:text-violet-700">{{ \App\Support\Lang::pick($r, 'full_name') }}</a><div class="text-xs text-slate-400">{{ $r->job_title }}</div></td>
                                <td class="px-3 py-3"><span class="rounded-md px-2 py-0.5 text-xs font-bold {{ $r->is_paid ? 'bg-violet-50 text-violet-700' : 'bg-rose-50 text-rose-700' }}">{{ $r->type_name }}{{ $r->is_paid ? '' : __(' (بتتخصم)') }}</span></td>
                                <td class="whitespace-nowrap px-3 py-3"><b>{{ (float) $r->days }} {{ __('يوم') }}</b><div class="text-xs text-slate-400">{{ $r->start_date }} ← {{ $r->end_date }}</div></td>
                                <td class="max-w-xs px-3 py-3 text-xs text-slate-600">{{ $r->reason }} @if ($r->attachment) <a href="{{ \App\Support\Media::url($r->attachment) }}" target="_blank" class="font-bold text-violet-600">📎</a> @endif</td>
                                <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $style[$r->status] }}">{{ $labels[$r->status] }}</span>@if ($r->decider) <div class="mt-0.5 text-[11px] text-slate-400">{{ $r->decider }}</div> @endif</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end">
                                    @if ($r->status === 'pending')
                                        <form method="POST" action="{{ route('admin.hr.leaves.decide', [$r->id, 'approved']) }}" class="inline">@csrf @method('PATCH')<button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white">{{ __('موافقة') }}</button></form>
                                        <form method="POST" action="{{ route('admin.hr.leaves.decide', [$r->id, 'rejected']) }}" class="inline">@csrf @method('PATCH')<button class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100">{{ __('رفض') }}</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.hr.leaves.destroy', $r->id) }}" class="inline" onsubmit="{{ __('return confirm(\'مسح الإجازة؟\')') }}">@csrf @method('DELETE')<button class="rounded-lg px-2 py-1.5 text-xs text-rose-500 hover:bg-rose-50">🗑️</button></form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages()) <div class="border-t border-slate-100 p-4">{{ $rows->links() }}</div> @endif
        @endif
    </div>

    <dialog id="leaveModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.hr.leaves.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><h2 class="text-lg font-extrabold">{{ __('إجازة جديدة') }}</h2><button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button></div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'name' => 'employee_id', 'label' => __('الموظف'), 'type' => 'select', 'options' => $employees, 'empty' => __('— اختار —'), 'required' => true, 'value' => old('employee_id', $employee)])
                @include('admin.partials.field', ['model' => null, 'name' => 'leave_type_id', 'label' => __('النوع'), 'type' => 'select', 'options' => $types->mapWithKeys(fn ($t) => [$t->id => $t->name_ar.($t->days_per_year ? ' ('.$t->days_per_year.__(' يوم/سنة)') : '').($t->is_paid ? '' : __(' - بتتخصم'))]), 'required' => true])
                <div class="grid grid-cols-2 gap-4">
                    @include('admin.partials.field', ['model' => null, 'name' => 'start_date', 'label' => __('من'), 'type' => 'date', 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'name' => 'end_date', 'label' => __('لحد'), 'type' => 'date', 'required' => true])
                </div>
                <p data-days class="hidden rounded-xl bg-violet-50 px-3 py-2 text-sm font-bold text-violet-700"></p>
                @include('admin.partials.field', ['model' => null, 'name' => 'reason', 'label' => __('السبب'), 'type' => 'textarea', 'rows' => 2])
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('مرفق (تقرير طبي مثلاً)') }}</label><input type="file" name="attachment" accept="image/*,.pdf" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="approve_now" value="1" checked class="accent-violet-600"> {{ __('موافق عليها على طول') }}</label>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button><button class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button></div>
        </form>
    </dialog>
    <script>
        (function () {
            const d = document.getElementById('leaveModal'), f = d.querySelector('form');
            document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => d.showModal()));
            d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
            d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
            const days = () => {
                const a = new Date(f.start_date.value), b = new Date(f.end_date.value), out = f.querySelector('[data-days]');
                if (isNaN(a) || isNaN(b) || b < a) { out.classList.add('hidden'); return; }
                out.textContent = Math.round((b - a) / 864e5 + 1) + @js(__(' يوم')); out.classList.remove('hidden');
            };
            f.start_date.addEventListener('change', () => { if (!f.end_date.value) f.end_date.value = f.start_date.value; days(); });
            f.end_date.addEventListener('change', days);
            @if ($errors->any()) d.showModal(); @endif
        })();
    </script>
@endsection
