@extends('admin.print.layout')

@php
    use App\Http\Controllers\Admin\InspectionController as IC;
    use Illuminate\Support\Carbon;
    $count = collect($inspection->items)->countBy('status');
    $resultStyle = ['ok' => 'border-emerald-300 bg-emerald-50 text-emerald-800', 'attention' => 'border-amber-300 bg-amber-50 text-amber-800', 'damaged' => 'border-rose-300 bg-rose-50 text-rose-800'];
@endphp

@section('title', __('تقرير فحص').' '.$vehicle->vehicle_code)
@section('heading', __('تقرير فحص مركبة').' — '.__(IC::TYPES[$inspection->type] ?? $inspection->type))
@section('subheading', __('رقم التقرير:').' INS-'.str_pad($inspection->id, 5, '0', STR_PAD_LEFT).' · '.Carbon::parse($inspection->inspected_at)->format('Y-m-d'))
@section('no_seal', true)

@section('content')
    <div class="no-print mb-4 flex justify-between gap-2">
        <a href="{{ route('admin.vehicles.show', $vehicle->id) }}" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">{{ __('← صفحة العربية') }}</a>
        @if (\App\Support\Access::can('vehicles.edit'))
            <form method="POST" action="{{ route('admin.inspections.destroy', $inspection->id) }}" onsubmit="return confirm(@js(__('مسح تقرير الفحص؟')))">@csrf @method('DELETE')<button class="rounded-lg px-3 py-2 text-sm font-bold text-rose-600">{{ __('مسح') }}</button></form>
        @endif
    </div>

    <table class="w-full border-collapse text-sm">
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('المركبة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $vehicle->brand_name }} {{ $vehicle->model_name }} {{ $vehicle->manufacturing_year }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('الكود') }}</td><td class="border border-slate-300 px-3 py-2 font-mono">{{ $vehicle->vehicle_code }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('رقم الشاسيه') }}</td><td class="border border-slate-300 px-3 py-2 font-mono">{{ $vehicle->chassis_number ?: '—' }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('اللوحة') }}</td><td class="border border-slate-300 px-3 py-2">{{ $vehicle->plate_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('عداد الكيلومترات') }}</td><td class="border border-slate-300 px-3 py-2">{{ $inspection->mileage !== null ? number_format($inspection->mileage) : '—' }}</td>
            <td class="border border-slate-300 bg-slate-50 px-3 py-2 font-bold">{{ __('القائم بالفحص') }}</td><td class="border border-slate-300 px-3 py-2">{{ $inspection->inspector ?: '—' }}</td>
        </tr>
    </table>

    <div class="mt-4 rounded-xl border-2 px-4 py-3 text-center font-extrabold {{ $resultStyle[$inspection->result] ?? '' }}">
        {{ __('النتيجة:') }} {{ __(IC::STATES[$inspection->result][0] ?? $inspection->result) }}
        <span class="ms-3 text-xs font-bold">✓ {{ $count['ok'] ?? 0 }} · ⚠ {{ $count['attention'] ?? 0 }} · ✕ {{ $count['damaged'] ?? 0 }}</span>
    </div>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead><tr class="bg-slate-900 text-white"><th class="border border-slate-900 p-2 text-start">#</th><th class="border border-slate-900 p-2 text-start">{{ __('الجزء') }}</th><th class="border border-slate-900 p-2 text-center">{{ __('الحالة') }}</th><th class="border border-slate-900 p-2 text-start">{{ __('ملاحظات') }}</th></tr></thead>
        <tbody>
            @foreach ($inspection->items as $n => $it)
                <tr class="break-inside-avoid {{ $it['status'] === 'damaged' ? 'bg-rose-50' : ($it['status'] === 'attention' ? 'bg-amber-50' : '') }}">
                    <td class="border border-slate-300 p-2 text-slate-400">{{ $n + 1 }}</td>
                    <td class="border border-slate-300 p-2 font-semibold">{{ __($it['part']) }}</td>
                    <td class="border border-slate-300 p-2 text-center"><span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ IC::STATES[$it['status']][1] ?? '' }}">{{ __(IC::STATES[$it['status']][0] ?? $it['status']) }}</span></td>
                    <td class="border border-slate-300 p-2 text-slate-600">{{ $it['note'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($inspection->notes)
        <div class="mt-4 rounded-xl border border-slate-300 p-3"><b>{{ __('ملاحظات عامة:') }}</b> {{ $inspection->notes }}</div>
    @endif

    <div class="mt-12 grid grid-cols-2 gap-10 text-center text-sm">
        <div><div class="mb-12 font-bold">{{ __('القائم بالفحص') }}</div><div class="border-t border-slate-400 pt-1 text-xs text-slate-400">{{ __('الاسم والتوقيع') }}</div></div>
        <div><div class="mb-12 font-bold">{{ __('المالك / العميل (بالاطلاع)') }}</div><div class="border-t border-slate-400 pt-1 text-xs text-slate-400">{{ __('الاسم والتوقيع') }}</div></div>
    </div>
@endsection
