@extends('admin.layouts.app')

@section('title', __('الشيت الشهري'))
@section('subtitle', __('حضور كل موظف يوم بيوم'))

@php
    use Illuminate\Support\Carbon;
    $cell = ['present' => ['✓', 'bg-emerald-100 text-emerald-700'], 'late' => ['⏰', 'bg-amber-100 text-amber-700'], 'absent' => ['✕', 'bg-rose-100 text-rose-700'], 'leave' => ['🌴', 'bg-violet-100 text-violet-700'], 'holiday' => ['🎉', 'bg-sky-100 text-sky-700'], 'off' => ['–', 'bg-slate-100 text-slate-400']];
    $prev = $start->copy()->subMonth()->format('Y-m'); $next = $start->copy()->addMonth()->format('Y-m');
@endphp

@section('content')
    @include('admin.hr.partials-tabs')

    <div class="mb-4 flex items-center gap-3 print:hidden">
        <a href="{{ route('admin.hr.attendance.monthly', ['month' => $prev]) }}" class="grid h-10 w-10 place-items-center rounded-xl bg-white ring-1 ring-slate-200">→</a>
        <div class="text-lg font-extrabold">{{ $start->translatedFormat('F Y') }}</div>
        <a href="{{ route('admin.hr.attendance.monthly', ['month' => $next]) }}" class="grid h-10 w-10 place-items-center rounded-xl bg-white ring-1 ring-slate-200">←</a>
        <div class="ms-auto flex flex-wrap gap-2 text-[11px]">@foreach ($cell as $k => [$i, $c]) <span class="rounded-md px-1.5 py-0.5 font-bold {{ $c }}">{{ $i }} {{ \App\Http\Controllers\Admin\Hr\AttendanceController::STATUSES[$k] }}</span> @endforeach</div>
        <button type="button" onclick="window.print()" class="rounded-xl bg-white px-3 py-2 text-sm font-bold ring-1 ring-slate-200">🖨️</button>
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="sticky start-0 z-10 bg-slate-50 px-4 py-2 text-start">{{ __('الموظف') }}</th>
                    @for ($i = 1; $i <= $days; $i++)
                        @php $dd = $start->copy()->day($i); @endphp
                        <th class="px-0.5 py-2 text-center font-semibold {{ $dd->isFriday() ? 'text-rose-400' : '' }} {{ $dd->isToday() ? 'text-violet-600' : '' }}">
                            <a href="{{ route('admin.hr.attendance.index', ['date' => $dd->toDateString()]) }}" class="hover:underline">{{ $i }}</a>
                        </th>
                    @endfor
                    <th class="px-2 py-2">{{ __('حضور') }}</th><th class="px-2 py-2">{{ __('غياب') }}</th><th class="px-2 py-2">{{ __('تأخير (د)') }}</th><th class="px-2 py-2">{{ __('أوفر (س)') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($employees as $e)
                    <tr class="hover:bg-slate-50/60">
                        <td class="sticky start-0 z-10 whitespace-nowrap bg-white px-4 py-1.5"><a href="{{ route('admin.hr.employees.show', $e->id) }}" class="font-bold text-slate-800 hover:text-violet-700">{{ \App\Support\Lang::pick($e, 'full_name') }}</a></td>
                        @for ($i = 1; $i <= $days; $i++)
                            @php $r = $e->days[$i] ?? null; @endphp
                            <td class="px-0.5 py-1 text-center">@if ($r) <span class="inline-grid h-6 w-6 place-items-center rounded-md font-bold {{ $cell[$r->status][1] ?? '' }}" title="{{ $r->check_in }} - {{ $r->check_out }}">{{ $cell[$r->status][0] ?? '' }}</span> @else <span class="text-slate-200">·</span> @endif</td>
                        @endfor
                        <td class="px-2 text-center font-bold text-emerald-700">{{ $e->present }}</td>
                        <td class="px-2 text-center font-bold text-rose-600">{{ $e->absent }}</td>
                        <td class="px-2 text-center text-amber-600">{{ $e->late ?: '' }}</td>
                        <td class="px-2 text-center text-sky-600">{{ $e->ot ?: '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
