@extends('admin.layouts.app')

@php
    use App\Http\Controllers\Admin\SharedExpenseController as SE;
    $vStatus = \App\Http\Controllers\Admin\VehicleController::STATUSES;
    $picked = array_map('intval', old('vehicles', []));
@endphp

@section('title', __('مصروف مشترك جديد'))
@section('subtitle', __('مصروف واحد (شحن، تخليص، نقل…) بيتقسم على كذا عربية ويدخل في تكلفة كل واحدة'))

@section('content')
    <form method="POST" action="{{ route('admin.shared-expenses.store') }}" class="grid gap-6 lg:grid-cols-3" id="seForm">
        @csrf
        <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:self-start">
            @include('admin.partials.field', ['model' => null, 'name' => 'details', 'label' => __('البيان'), 'required' => true, 'placeholder' => __('مثلاً: شحن حاوية من دبي')])
            <div class="grid grid-cols-2 gap-3">
                @include('admin.partials.field', ['model' => null, 'name' => 'amount', 'label' => __('المبلغ'), 'type' => 'number', 'step' => '0.001', 'required' => true])
                @include('admin.partials.field', ['model' => null, 'name' => 'tax_percent', 'label' => __('الضريبة %'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'value' => old('tax_percent', 0)])
            </div>
            @include('admin.partials.field', ['model' => null, 'name' => 'entry_date', 'label' => __('التاريخ'), 'type' => 'date', 'required' => true, 'value' => old('entry_date', today()->format('Y-m-d'))])
            @include('admin.partials.field', ['model' => null, 'name' => 'category_id', 'label' => __('نوع المصروف'), 'type' => 'select', 'required' => true, 'options' => $categories, 'empty' => '—'])
            @include('admin.partials.field', ['model' => null, 'name' => 'financial_account_id', 'label' => __('اتدفع من خزنة'), 'type' => 'select', 'required' => true, 'options' => $accounts, 'empty' => '—'])
            @include('admin.partials.field', ['model' => null, 'name' => 'supplier_id', 'label' => __('المورد (اختياري)'), 'type' => 'select', 'options' => $suppliers, 'empty' => '—'])
            <div>
                <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('طريقة التقسيم') }}</div>
                @foreach (SE::METHODS as $k => $l)
                    <label class="mb-2 flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="method" value="{{ $k }}" @checked(old('method', 'equal') === $k)> {{ __($l) }}
                    </label>
                @endforeach
            </div>
            <button class="w-full rounded-xl bg-blue-600 py-3 text-sm font-bold text-white hover:bg-blue-700">{{ __('حفظ وتقسيم') }}</button>
        </section>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('العربيات') }} <span class="text-sm font-normal text-slate-500" id="pickedCount">(0)</span></h2>
                <input type="search" id="vFilter" placeholder="{{ __('فلترة بالكود أو الشاسيه أو الموديل') }}" class="w-64 rounded-xl border border-slate-200 px-3 py-2 text-sm">
            </div>
            @error('vehicles') <p class="bg-rose-50 px-6 py-2 text-sm font-bold text-rose-700">{{ $message }}</p> @enderror
            <div class="max-h-[560px] divide-y divide-slate-100 overflow-auto">
                @foreach ($vehicles as $v)
                    <label class="flex cursor-pointer items-center gap-3 px-6 py-2.5 text-sm hover:bg-slate-50" data-row="{{ mb_strtolower($v->vehicle_code.' '.$v->chassis_number.' '.$v->brand_name.' '.$v->model_name) }}">
                        <input type="checkbox" name="vehicles[]" value="{{ $v->id }}" data-cost="{{ (float) $v->total_cost }}" @checked(in_array($v->id, $picked, true)) class="h-4 w-4 accent-blue-600">
                        <div class="flex-1"><span class="font-bold text-slate-800">{{ $v->brand_name }} {{ $v->model_name }} {{ $v->manufacturing_year }}</span> <span class="font-mono text-xs text-slate-400">{{ $v->vehicle_code }}</span></div>
                        <span class="text-xs text-slate-400">{{ __($vStatus[$v->status] ?? $v->status) }}</span>
                        <span class="w-24 text-end font-bold text-blue-700" data-share></span>
                    </label>
                @endforeach
            </div>
        </section>
    </form>

    <script>
        (function () {
            const f = document.getElementById('seForm'), rows = [...f.querySelectorAll('[data-row]')];
            const calc = () => {
                const boxes = rows.map(r => r.querySelector('input')), on = boxes.filter(b => b.checked);
                document.getElementById('pickedCount').textContent = '(' + on.length + ')';
                const amt = Number(f.amount.value || 0) * (1 + Number(f.tax_percent.value || 0) / 100);
                const byCost = f.method.value === 'cost';
                let w = on.map(b => byCost ? Math.max(0, Number(b.dataset.cost)) : 1); if (w.reduce((a, b) => a + b, 0) <= 0) w = on.map(() => 1);
                const sum = w.reduce((a, b) => a + b, 0);
                boxes.forEach(b => b.closest('label').querySelector('[data-share]').textContent = '');
                on.forEach((b, i) => b.closest('label').querySelector('[data-share]').textContent = amt ? (amt * w[i] / sum).toLocaleString('en', { maximumFractionDigits: 3 }) : '');
            };
            f.addEventListener('input', calc); f.addEventListener('change', calc); calc();
            document.getElementById('vFilter').addEventListener('input', (e) => {
                const q = e.target.value.trim().toLowerCase();
                rows.forEach(r => r.style.display = !q || r.dataset.row.includes(q) ? '' : 'none');
            });
        })();
    </script>
@endsection
