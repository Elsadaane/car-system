@extends('admin.layouts.app')

@section('title', __('الحضور والانصراف'))
@section('subtitle', __('شيت اليوم: اختار الحالة واكتب الوقت — التأخير والأوفر تايم بيتحسبوا لوحدهم'))

@php
    use App\Support\Media;
    use App\Http\Controllers\Admin\Hr\AttendanceController as AC;
    use Illuminate\Support\Carbon;
    $d = Carbon::parse($date);
    $tones = ['present' => 'has-[:checked]:bg-emerald-500', 'late' => 'has-[:checked]:bg-amber-500', 'absent' => 'has-[:checked]:bg-rose-500', 'leave' => 'has-[:checked]:bg-violet-500', 'holiday' => 'has-[:checked]:bg-sky-500', 'off' => 'has-[:checked]:bg-slate-500'];
@endphp

@section('actions')
    <a href="{{ route('admin.hr.attendance.monthly') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('📅 الشيت الشهري') }}</a>
@endsection

@section('content')
    @include('admin.hr.partials-tabs')

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.hr.attendance.index', array_filter(['date' => $d->copy()->subDay()->toDateString(), 'branch' => $branch])) }}" class="grid h-10 w-10 place-items-center rounded-xl bg-white ring-1 ring-slate-200">→</a>
        <form method="GET" class="flex gap-2">
            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            @if ($branches->count() > 1)
                <select name="branch" data-no-search onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-2 text-sm"><option value="">{{ __('كل الفروع') }}</option>@foreach ($branches as $id => $n) <option value="{{ $id }}" @selected($branch === $id)>{{ $n }}</option> @endforeach</select>
            @endif
        </form>
        <a href="{{ route('admin.hr.attendance.index', array_filter(['date' => $d->copy()->addDay()->toDateString(), 'branch' => $branch])) }}" class="grid h-10 w-10 place-items-center rounded-xl bg-white ring-1 ring-slate-200">←</a>
        <div class="text-lg font-extrabold text-slate-900">{{ $d->translatedFormat('l') }} {{ $d->format('Y-m-d') }}</div>
        @if (! $d->isToday()) <a href="{{ route('admin.hr.attendance.index') }}" class="text-sm font-bold text-violet-600">{{ __('النهارده') }}</a> @endif
        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $saved === $employees->count() && $saved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $saved }} / {{ $employees->count() }} {{ __('متسجل') }}</span>
    </div>

    @if ($holiday) <div class="mb-4 rounded-2xl bg-sky-50 px-5 py-3 text-sm font-bold text-sky-800 ring-1 ring-sky-200">{{ __('🎉 عطلة رسمية:') }} {{ $holiday }}</div> @endif

    <form method="POST" action="{{ route('admin.hr.attendance.store') }}" id="attForm">
        @csrf <input type="hidden" name="date" value="{{ $date }}">
        <div class="mb-3 flex flex-wrap gap-2">
            <button type="button" data-all="present" class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100">{{ __('✓ الكل حاضر') }}</button>
            <button type="button" data-all="off" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200">{{ __('الكل راحة') }}</button>
            <button type="button" data-fill-times class="rounded-xl bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100">{{ __('🕘 املأ مواعيد الوردية') }}</button>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            @if ($employees->isEmpty())
                <p class="px-6 py-16 text-center text-slate-400">{{ __('مفيش موظفين') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('الموظف') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th><th class="px-3 py-3 text-start">{{ __('حضور') }}</th><th class="px-3 py-3 text-start">{{ __('انصراف') }}</th><th class="px-3 py-3 text-start">{{ __('أوفر تايم (س)') }}</th><th class="px-3 py-3 text-start">{{ __('النتيجة') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($employees as $emp)
                                @php
                                    $default = $holiday ? 'holiday' : (in_array($emp->id, $onLeave) ? 'leave' : null);
                                    $st = $emp->status ?? $default;
                                    $img = Media::url($emp->profile_image);
                                @endphp
                                <tr data-emp data-start="{{ substr((string) $emp->start_time, 0, 5) }}" data-end="{{ substr((string) $emp->end_time, 0, 5) }}" class="{{ $emp->att_id ? '' : 'bg-amber-50/30' }}">
                                    <td class="px-5 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            @if ($img) <img src="{{ $img }}" alt="" class="h-9 w-9 rounded-xl object-cover"> @else <span class="grid h-9 w-9 place-items-center rounded-xl bg-violet-100 font-bold text-violet-700">{{ mb_substr(\App\Support\Lang::pick($emp, 'full_name'), 0, 1) }}</span> @endif
                                            <div><div class="font-bold text-slate-800">{{ \App\Support\Lang::pick($emp, 'full_name') }}</div><div class="text-[11px] text-slate-400">{{ $emp->job_title }}{{ $emp->start_time ? ' · '.substr($emp->start_time, 0, 5).'-'.substr($emp->end_time, 0, 5) : '' }}</div></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach (['present' => __('حاضر'), 'absent' => __('غايب'), 'leave' => __('إجازة'), 'off' => __('راحة')] as $k => $l)
                                                <label class="cursor-pointer rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600 transition has-[:checked]:text-white {{ $tones[$k] }}">
                                                    <input type="radio" name="att[{{ $emp->id }}][status]" value="{{ $k }}" class="sr-only" @checked($st === $k || ($k === 'present' && $st === 'late') || ($k === 'off' && $st === 'holiday'))> {{ $l }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5"><input type="time" name="att[{{ $emp->id }}][check_in]" value="{{ substr((string) $emp->check_in, 0, 5) }}" class="w-28 rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></td>
                                    <td class="px-3 py-2.5"><input type="time" name="att[{{ $emp->id }}][check_out]" value="{{ substr((string) $emp->check_out, 0, 5) }}" class="w-28 rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></td>
                                    <td class="px-3 py-2.5">
                                        @if ($emp->overtime_eligible)
                                            <input type="number" step="0.25" min="0" name="att[{{ $emp->id }}][overtime]" value="" placeholder="{{ $emp->overtime_minutes ? round($emp->overtime_minutes / 60, 2) : __('تلقائي') }}" class="w-20 rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                                        @else <span class="text-xs text-slate-300">—</span> @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs">
                                        @if ($emp->att_id)
                                            @if ($emp->status === 'late') <span class="font-bold text-amber-600">{{ __('متأخر') }} {{ $emp->delay_minutes }} {{ __('د') }}</span>
                                            @else <span class="font-bold text-emerald-600">{{ AC::STATUSES[$emp->status] ?? '' }} ✓</span> @endif
                                            @if ($emp->overtime_minutes) <div class="text-sky-600">+{{ round($emp->overtime_minutes / 60, 2) }} {{ __('س') }}</div> @endif
                                        @else <span class="text-amber-600">{{ __('لسه') }}</span> @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="sticky bottom-4 mt-4 flex justify-end">
            <button class="rounded-2xl bg-violet-600 px-8 py-3.5 text-sm font-extrabold text-white shadow-xl shadow-violet-600/30 hover:bg-violet-700">{{ __('💾 حفظ حضور اليوم') }}</button>
        </div>
    </form>

    <script>
        (function () {
            const f = document.getElementById('attForm');
            f.querySelectorAll('[data-all]').forEach((b) => b.addEventListener('click', () => {
                f.querySelectorAll(`input[type=radio][value="${b.dataset.all}"]`).forEach((r) => { r.checked = true; });
            }));
            f.querySelector('[data-fill-times]').addEventListener('click', () => {
                f.querySelectorAll('[data-emp]').forEach((tr) => {
                    const present = tr.querySelector('input[type=radio][value=present]:checked'); if (!present) return;
                    const [i, o] = tr.querySelectorAll('input[type=time]');
                    if (!i.value && tr.dataset.start) i.value = tr.dataset.start;
                    if (!o.value && tr.dataset.end) o.value = tr.dataset.end;
                });
            });
        })();
    </script>
@endsection
