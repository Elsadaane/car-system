@extends('admin.layouts.app')

@php
    use App\Http\Controllers\Admin\InspectionController as IC;
    use Illuminate\Support\Carbon;
@endphp

@section('title', __('الفحوصات'))
@section('subtitle', __('تقارير فحص العربيات — لإضافة فحص افتح صفحة العربية'))

@section('content')
    <div class="mb-4 flex flex-wrap gap-1.5">
        <a href="{{ route('admin.inspections.index') }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ ! $type ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ __('الكل') }}</a>
        @foreach (IC::TYPES as $k => $l)
            <a href="{{ route('admin.inspections.index', ['type' => $k]) }}" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $type === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ __($l) }}</a>
        @endforeach
    </div>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($rows->isEmpty())
            <div class="px-6 py-16 text-center text-sm text-slate-400">{{ __('مفيش تقارير فحص لسه.') }}</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('العربية') }}</th><th class="px-3 py-3 text-start">{{ __('نوع الفحص') }}</th><th class="px-3 py-3 text-start">{{ __('القائم بالفحص') }}</th><th class="px-3 py-3 text-start">{{ __('العداد') }}</th><th class="px-3 py-3 text-start">{{ __('النتيجة') }}</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $r)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3">{{ Carbon::parse($r->inspected_at)->format('Y-m-d') }}</td>
                            <td class="px-3 py-3"><a href="{{ route('admin.vehicles.show', $r->vehicle_id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $r->brand_name }} {{ $r->model_name }} {{ $r->manufacturing_year }}</a><div class="font-mono text-xs text-slate-400">{{ $r->chassis_number ?: $r->vehicle_code }}</div></td>
                            <td class="px-3 py-3">{{ __(IC::TYPES[$r->type] ?? $r->type) }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $r->inspector ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $r->mileage !== null ? number_format($r->mileage) : '—' }}</td>
                            <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ IC::STATES[$r->result][1] ?? '' }}">{{ __(IC::STATES[$r->result][0] ?? $r->result) }}</span></td>
                            <td class="px-5 py-3 text-end"><a href="{{ route('admin.inspections.show', $r->id) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700">{{ __('عرض / طباعة') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($rows->hasPages())<div class="border-t border-slate-100 p-4">{{ $rows->links() }}</div>@endif
        @endif
    </div>
@endsection
