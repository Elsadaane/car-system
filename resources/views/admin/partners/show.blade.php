@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $canEdit = \App\Support\Access::can('partners.edit');
    $vehStatus = \App\Http\Controllers\Admin\VehicleController::STATUSES;
@endphp

@section('title', $partner->name)
@section('subtitle', __('كشف حساب شريك').($partner->phone ? ' · '.$partner->phone : ''))

@section('actions')
    @if ($canEdit)
        <button type="button" onclick="document.getElementById('txModal').showModal()" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">＋ {{ __('تسجيل حركة فلوس') }}</button>
        <button type="button" onclick="document.getElementById('editModal').showModal()" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">{{ __('تعديل البيانات') }}</button>
    @endif
@endsection

@section('content')
    <section class="rounded-2xl bg-slate-900 p-6 text-white shadow-lg">
        <div class="grid gap-4 sm:grid-cols-5">
            <div><div class="text-xs text-slate-400">{{ __('رأس ماله في العربيات') }}</div><div class="mt-1 text-xl font-extrabold">{{ Money::format($totals['invested'], false) }}</div></div>
            <div><div class="text-xs text-slate-400">{{ __('لسه في عربيات متباعتش') }}</div><div class="mt-1 text-xl font-extrabold text-sky-300">{{ Money::format($totals['active'], false) }}</div></div>
            <div><div class="text-xs text-slate-400">{{ __('نصيبه من الربح') }}</div><div class="mt-1 text-xl font-extrabold {{ $totals['profit'] >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ Money::format($totals['profit'], false) }}</div></div>
            <div><div class="text-xs text-slate-400">{{ __('اتدفعله') }}</div><div class="mt-1 text-xl font-extrabold">{{ Money::format($totals['paid'], false) }}</div></div>
            <div><div class="text-xs text-slate-400">{{ __('الباقي له') }}</div><div class="mt-1 text-2xl font-black {{ $totals['balance'] > 0 ? 'text-amber-300' : 'text-slate-400' }}">{{ Money::format($totals['balance'], false) }}</div></div>
        </div>
        <p class="mt-4 border-t border-white/10 pt-3 text-xs text-slate-400">{{ __('المستحق له = رأس ماله + نصيبه من ربح كل عربية اتباعت. العربيات اللي لسه في المخزون مش داخلة في المستحق.') }}
            @if ($totals['unfunded'] > 0.0005) <span class="font-bold text-amber-300">{{ __('· رأس مال متسجل ولسه ماستلمناهوش:') }} {{ Money::format($totals['unfunded'], false) }}</span>@endif</p>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-extrabold text-slate-900">🚗 {{ __('العربيات') }} ({{ $shares->count() }})</h2></div>
        @if ($shares->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-slate-400">{{ __('الشريك مش داخل في عربيات لسه — من صفحة العربية حدد نصيبه.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('العربية') }}</th><th class="px-3 py-3 text-start">{{ __('رأس المال') }}</th><th class="px-3 py-3 text-start">{{ __('النسبة') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th><th class="px-3 py-3 text-start">{{ __('ربح العربية') }}</th><th class="px-3 py-3 text-start">{{ __('نصيبه') }}</th><th class="px-6 py-3 text-start">{{ __('المستحق') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($shares as $s)
                            <tr>
                                <td class="px-6 py-3"><a href="{{ route('admin.vehicles.show', $s->vehicle_id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $s->brand_name }} {{ $s->model_name }} {{ $s->manufacturing_year }}</a><div class="font-mono text-xs text-slate-400">{{ $s->vehicle_code }}</div></td>
                                <td class="whitespace-nowrap px-3 py-3">{{ Money::format($s->capital, false) }}</td>
                                <td class="px-3 py-3 font-bold">{{ (float) $s->profit_percent }}%</td>
                                <td class="px-3 py-3">
                                    @if ($s->sale)<a href="{{ route('admin.sales-contracts.show', $s->sale->id) }}" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ __('اتباعت') }} {{ Carbon::parse($s->sale->contract_date)->format('Y-m-d') }}</a>
                                    @else<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ __($vehStatus[$s->vehicle_status] ?? $s->vehicle_status) }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 {{ $s->sale && $s->sale->profit < 0 ? 'text-rose-600' : '' }}">{{ $s->sale ? Money::format($s->sale->profit, false) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 font-semibold {{ $s->profit_share < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $s->sale ? Money::format($s->profit_share, false) : '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 font-extrabold">{{ $s->sale ? Money::format($s->due, false) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-extrabold text-slate-900">💵 {{ __('حركات الفلوس') }}</h2></div>
        @if ($tx->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-slate-400">{{ __('مفيش حركات. سجّل لما تستلم منه رأس مال أو تدفعله.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('النوع') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('الخزنة') }}</th><th class="px-3 py-3 text-start">{{ __('ملاحظات') }}</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($tx as $t)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-3">{{ Carbon::parse($t->entry_date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $t->kind === 'payout' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $t->kind === 'payout' ? __('دفعناله ↑') : __('استلمنا منه ↓') }}</span>@if ($t->vehicle_code)<span class="ms-1 font-mono text-[11px] text-slate-400">{{ $t->vehicle_code }}</span>@endif</td>
                            <td class="whitespace-nowrap px-3 py-3 font-bold">{{ Money::format($t->amount, false) }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $t->account_name ?? '—' }}</td>
                            <td class="px-3 py-3 text-xs text-slate-500">{{ $t->reference }} {{ $t->notes }}</td>
                            <td class="px-5 py-3 text-end">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('admin.partners.transactions.destroy', [$partner->id, $t->id]) }}" onsubmit="return confirm(@js(__('مسح الحركة؟')))">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-500 hover:underline">{{ __('مسح') }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @if ($canEdit)
        <dialog id="txModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
            <form method="POST" action="{{ route('admin.partners.transactions.store', $partner->id) }}" class="space-y-4 p-6">
                @csrf
                <input type="hidden" name="_modal" value="tx">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('حركة فلوس مع الشريك') }}</h2>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer rounded-xl border border-slate-200 p-3 text-center text-sm font-bold has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50"><input type="radio" name="kind" value="capital_in" class="sr-only" @checked(old('kind', 'payout') === 'capital_in')> {{ __('استلمنا منه رأس مال') }}</label>
                    <label class="cursor-pointer rounded-xl border border-slate-200 p-3 text-center text-sm font-bold has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50"><input type="radio" name="kind" value="payout" class="sr-only" @checked(old('kind', 'payout') === 'payout')> {{ __('دفعناله (رأس مال + ربح)') }}</label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'name' => 'amount', 'label' => __('المبلغ'), 'type' => 'number', 'step' => '0.001', 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'name' => 'entry_date', 'label' => __('التاريخ'), 'type' => 'date', 'required' => true, 'value' => old('entry_date', today()->format('Y-m-d'))])
                </div>
                @include('admin.partials.field', ['model' => null, 'name' => 'financial_account_id', 'label' => __('الخزنة'), 'type' => 'select', 'options' => $accounts, 'required' => true, 'empty' => '—'])
                @include('admin.partials.field', ['model' => null, 'name' => 'vehicle_id', 'label' => __('عربية (اختياري)'), 'type' => 'select', 'empty' => '—', 'options' => $shares->mapWithKeys(fn ($s) => [$s->vehicle_id => $s->vehicle_code.' — '.$s->brand_name.' '.$s->model_name])])
                @include('admin.partials.field', ['model' => null, 'name' => 'notes', 'label' => __('ملاحظات')])
                <p class="text-xs text-slate-400">{{ __('الحركة بتتسجل في الخزنة المختارة (داخل أو خارج) وبتظهر في دفتر الخزائن.') }} {{ __('الرصيد المستحق دلوقتي:') }} <b>{{ Money::format($totals['balance']) }}</b></p>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button>
                    <button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button>
                </div>
            </form>
        </dialog>
        <dialog id="editModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
            <form method="POST" action="{{ route('admin.partners.update', $partner->id) }}" class="space-y-4 p-6">
                @csrf @method('PUT')
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('بيانات الشريك') }}</h2>
                @include('admin.partials.field', ['model' => $partner, 'name' => 'name', 'label' => __('الاسم'), 'required' => true])
                <div class="grid gap-3 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => $partner, 'name' => 'phone', 'label' => __('التليفون'), 'dir' => 'ltr'])
                    @include('admin.partials.field', ['model' => $partner, 'name' => 'national_id', 'label' => __('الرقم المدني')])
                </div>
                @include('admin.partials.field', ['model' => $partner, 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])
                <div class="flex justify-between gap-2">
                    <button type="submit" form="delPartner" class="text-sm font-bold text-rose-600">{{ __('مسح الشريك') }}</button>
                    <div class="flex gap-2">
                        <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button>
                        <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button>
                    </div>
                </div>
            </form>
            <form id="delPartner" method="POST" action="{{ route('admin.partners.destroy', $partner->id) }}" onsubmit="return confirm(@js(__('مسح الشريك؟')))">@csrf @method('DELETE')</form>
        </dialog>
        @if ($errors->any() && old('_modal') === 'tx') <script>document.getElementById('txModal').showModal();</script> @endif
    @endif
@endsection
