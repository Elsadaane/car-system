@extends('admin.layouts.app')

@php
    use App\Http\Controllers\Admin\InspectionController as IC;
    $parts = old('items') ? array_column(old('items'), 'part') : IC::PARTS;
@endphp

@section('title', __('تقرير فحص جديد'))
@section('subtitle', $vehicle->brand_name.' '.$vehicle->model_name.' '.$vehicle->manufacturing_year.' · '.$vehicle->vehicle_code)

@section('content')
    <form method="POST" action="{{ route('admin.inspections.store', $vehicle->id) }}" class="space-y-6">
        @csrf
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="grid gap-4 sm:grid-cols-4">
                @include('admin.partials.field', ['model' => null, 'name' => 'type', 'label' => __('نوع الفحص'), 'type' => 'select', 'required' => true, 'options' => collect(IC::TYPES)->map(fn ($l) => __($l)), 'value' => old('type', $type)])
                @include('admin.partials.field', ['model' => null, 'name' => 'inspected_at', 'label' => __('تاريخ الفحص'), 'type' => 'date', 'required' => true, 'value' => old('inspected_at', today()->format('Y-m-d'))])
                @include('admin.partials.field', ['model' => null, 'name' => 'inspector', 'label' => __('القائم بالفحص'), 'value' => old('inspector', auth()->user()->name)])
                @include('admin.partials.field', ['model' => null, 'name' => 'mileage', 'label' => __('عداد الكيلومترات'), 'type' => 'number', 'min' => 0, 'value' => old('mileage', $vehicle->mileage)])
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('قايمة الأجزاء') }}</h2>
                <div class="flex gap-2 text-xs">
                    <button type="button" onclick="document.querySelectorAll('[data-ok]').forEach(r => r.checked = true)" class="rounded-lg bg-emerald-50 px-3 py-1.5 font-bold text-emerald-700">{{ __('✓ الكل سليم') }}</button>
                    <button type="button" id="addPart" class="rounded-lg bg-slate-100 px-3 py-1.5 font-bold text-slate-700">＋ {{ __('جزء تاني') }}</button>
                </div>
            </div>
            <div class="divide-y divide-slate-100" id="parts">
                @foreach ($parts as $i => $part)
                    @php $st = old("items.$i.status", 'ok'); @endphp
                    <div class="grid items-center gap-3 px-6 py-2.5 sm:grid-cols-[1fr_auto_1fr]">
                        <div class="font-semibold text-slate-800">{{ __($part) }}<input type="hidden" name="items[{{ $i }}][part]" value="{{ $part }}"></div>
                        <div class="flex gap-1">
                            @foreach (IC::STATES as $k => [$label, $cls])
                                <label class="cursor-pointer rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-500 ring-1 ring-slate-200">
                                    <input type="radio" name="items[{{ $i }}][status]" value="{{ $k }}" class="sr-only" @checked($st === $k) @if ($k === 'ok') data-ok @endif> {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                        <input name="items[{{ $i }}][note]" value="{{ old("items.$i.note") }}" placeholder="{{ __('ملاحظة (اختياري)') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @include('admin.partials.field', ['model' => null, 'name' => 'notes', 'label' => __('ملاحظات عامة'), 'type' => 'textarea', 'rows' => 3])
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.vehicles.show', $vehicle->id) }}" class="rounded-xl px-5 py-3 text-sm font-bold text-slate-600">{{ __('إلغاء') }}</a>
            <button class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700">{{ __('حفظ تقرير الفحص') }}</button>
        </div>
    </form>

    {{-- has-[:checked] بألوان ثابتة (عشان تشتغل من غير build) --}}
    <style>
        #parts label:has(input[value=ok]:checked) { background: #d1fae5; color: #047857; box-shadow: none; }
        #parts label:has(input[value=attention]:checked) { background: #fef3c7; color: #92400e; box-shadow: none; }
        #parts label:has(input[value=damaged]:checked) { background: #ffe4e6; color: #be123c; box-shadow: none; }
    </style>
    <script>
        document.getElementById('addPart').addEventListener('click', () => {
            const name = prompt(@js(__('اسم الجزء:')));
            if (!name) return;
            const box = document.getElementById('parts'), i = box.children.length, tpl = box.lastElementChild.cloneNode(true);
            tpl.querySelector('div').firstChild.textContent = name;
            tpl.querySelectorAll('input').forEach((el) => {
                el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
                if (el.type === 'hidden') el.value = name;
                if (el.type === 'radio') el.checked = el.value === 'ok';
                if (!el.type || el.type === 'text') el.value = '';
            });
            box.appendChild(tpl);
        });
    </script>
@endsection
