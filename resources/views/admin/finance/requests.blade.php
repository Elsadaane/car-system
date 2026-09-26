@extends('admin.layouts.app')

@section('title', __('طلبات التمويل'))
@section('subtitle', __('الطلبات اللي اتبعتت للبنوك — من الطلب لحد ما الفلوس توصل'))

@php
    use App\Support\Money;
    use App\Http\Controllers\Admin\FinanceRequestController as FR;
    use Illuminate\Support\Carbon;
    $isAdmin = \App\Support\Access::can('finance.approve');
    $style = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-blue-50 text-blue-700', 'paid_off' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700', 'cancelled' => 'bg-slate-100 text-slate-500'];
    $dlg = 'm-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm';
@endphp

@section('actions')
    <a href="{{ route('admin.finance-companies.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('شركات التمويل') }}</a>
@endsection

@section('content')
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach (FR::STATUSES as $k => $label)
            <a href="{{ route('admin.finance-requests.index', array_filter(['status' => $filters['status'] === $k ? null : $k, 'company' => $filters['company']])) }}"
               class="rounded-2xl p-4 shadow-sm ring-1 transition hover:-translate-y-0.5 {{ $filters['status'] === $k ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white ring-slate-200' }}">
                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $filters['status'] === $k ? 'bg-white/15 text-white' : $style[$k] }}">{{ $label }}</span>
                <div class="mt-2 text-xl font-extrabold">{{ (int) ($stats[$k]->c ?? 0) }}</div>
                <div class="truncate text-xs opacity-70">{{ Money::format($stats[$k]->total ?? 0, false) }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('رقم الطلب، رقم العقد، العميل، التليفون...') }}" class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <select name="company" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل الشركات') }}</option>
            @foreach ($companies as $id => $n) <option value="{{ $id }}" @selected($filters['company'] === $id)>{{ $n }}</option> @endforeach
        </select>
        @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        @if (array_filter($filters)) <a href="{{ route('admin.finance-requests.index') }}" class="self-center text-sm text-slate-500 hover:text-slate-900">{{ __('مسح') }}</a> @endif
    </form>

    @if (session('receipt_url'))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-emerald-50 px-5 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
            <span class="font-bold">{{ __('اتسجل مبلغ التمويل على العقد.') }}</span>
            <a href="{{ session('receipt_url') }}" target="_blank" class="rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white hover:bg-emerald-700">{{ __('طباعة الإيصال') }}</a>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($requests->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-600">{{ __('مفيش طلبات تمويل') }}</p>
                <p class="mt-1 text-sm text-slate-400">{{ __('طلب التمويل بيتعمل من صفحة عقد البيع ← الإجراءات ← "طلب تمويل".') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('الطلب') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العميل / العقد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الشركة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المطلوب') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الموافق عليه') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($requests as $r)
                            @php $days = (int) Carbon::parse($r->request_date)->diffInDays(today()); @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <div class="font-mono text-xs font-bold text-slate-800">{{ $r->request_number }}</div>
                                    <div class="text-[11px] text-slate-400">{{ Carbon::parse($r->request_date)->format('Y-m-d') }} · {{ $r->user_name }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $r->customer_name }}</div>
                                    <a href="{{ route('admin.sales-contracts.show', $r->sales_contract_id) }}" class="font-mono text-xs text-blue-600 hover:underline">{{ $r->contract_number }}</a>
                                    <span class="text-xs text-slate-400">· {{ $r->brand_name }} {{ $r->model_name }}</span>
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-700">{{ $r->company_name }}</td>
                                <td class="whitespace-nowrap px-3 py-3 font-bold text-slate-900">{{ Money::format($r->requested_amount, false) }}</td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    @if ($r->approved_amount)
                                        <div class="font-bold text-emerald-700">{{ Money::format($r->approved_amount, false) }}</div>
                                        <div class="text-[11px] text-slate-400">
                                            @if ($r->interest_rate) {{ (float) $r->interest_rate }}% @endif
                                            @if ($r->installment_period) · {{ $r->installment_period }} {{ __('شهر') }} @endif
                                            @if ($r->monthly_payment) {{ __('· قسط') }} {{ Money::format($r->monthly_payment, false) }} @endif
                                        </div>
                                    @else — @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $style[$r->status] ?? '' }}">{{ FR::STATUSES[$r->status] ?? $r->status }}</span>
                                    @if ($r->status === 'pending' && $days >= 3) <div class="mt-1 text-[11px] font-bold text-amber-600">{{ __('من') }} {{ $days }} {{ __('يوم — تابع مع البنك') }}</div> @endif
                                    @if ($r->rejection_reason) <div class="mt-1 text-[11px] text-slate-500">{{ $r->rejection_reason }}</div> @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-end">
                                    @if ($isAdmin && $r->status === 'pending')
                                        <button type="button" data-dialog="approveModal" data-url="{{ route('admin.finance-requests.decide', [$r->id, 'approve']) }}"
                                                data-amount="{{ (float) $r->requested_amount }}" data-period="{{ $r->installment_period }}"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">{{ __('موافقة') }}</button>
                                    @endif
                                    @if ($isAdmin && $r->status === 'approved')
                                        <button type="button" data-dialog="receiveModal" data-url="{{ route('admin.finance-requests.receive', $r->id) }}"
                                                data-amount="{{ Money::format(min((float) $r->approved_amount, (float) $r->remaining_amount)) }}"
                                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700">{{ __('استلام المبلغ') }}</button>
                                    @endif
                                    @if ($isAdmin && in_array($r->status, ['pending', 'approved'], true))
                                        <button type="button" data-dialog="rejectModal" data-url="{{ route('admin.finance-requests.decide', [$r->id, 'reject']) }}"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('رفض') }}</button>
                                        <form method="POST" action="{{ route('admin.finance-requests.decide', [$r->id, 'cancel']) }}" class="inline" onsubmit="{{ __('return confirm(\'إلغاء الطلب؟\')') }}">
                                            @csrf @method('PATCH')
                                            <button class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-100">{{ __('إلغاء') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($requests->hasPages()) <div class="border-t border-slate-100 p-4">{{ $requests->links() }}</div> @endif
        @endif
    </div>

    @if ($isAdmin)
        <dialog id="approveModal" class="{{ $dlg }}">
            <form method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf @method('PATCH')
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('موافقة البنك') }}</h2>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'ap_', 'name' => 'approved_amount', 'label' => __('المبلغ الموافق عليه'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                    <div class="grid gap-4 sm:grid-cols-2">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'ap_', 'name' => 'interest_rate', 'label' => __('الفايدة السنوية %'), 'type' => 'number', 'step' => '0.01', 'min' => 0])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'ap_', 'name' => 'installment_period', 'label' => __('المدة (شهور)'), 'type' => 'number', 'min' => 1])
                    </div>
                    <p data-monthly class="hidden rounded-xl bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700"></p>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{{ __('تسجيل الموافقة') }}</button>
                </div>
            </form>
        </dialog>

        <dialog id="receiveModal" class="{{ $dlg }}">
            <form method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">{{ __('البنك حوّل الفلوس') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('هيتسجل') }} <b data-amount></b> {{ __('دفعة على العقد') }}</p>
                    </div>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'rc_', 'name' => 'payment_method_id', 'label' => __('اتحوّل على'), 'type' => 'select', 'options' => $methods, 'required' => true])
                    <div class="grid gap-4 sm:grid-cols-2">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'rc_', 'name' => 'payment_date', 'label' => __('التاريخ'), 'type' => 'date', 'required' => true, 'value' => today()->format('Y-m-d')])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'rc_', 'name' => 'reference', 'label' => __('رقم التحويل'), 'dir' => 'ltr'])
                    </div>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('تأكيد الاستلام') }}</button>
                </div>
            </form>
        </dialog>

        <dialog id="rejectModal" class="{{ $dlg }}">
            <form method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf @method('PATCH')
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('رفض الطلب') }}</h2>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="px-6 py-5">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 'rj_', 'name' => 'rejection_reason', 'label' => __('السبب'), 'list' => 'rejectReasons'])
                    <datalist id="rejectReasons"><option value="الراتب أقل من المطلوب"><option value="التزامات بنكية عالية"><option value="مستندات ناقصة"><option value="العميل اتراجع"></datalist>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                    <button type="submit" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-60">{{ __('رفض') }}</button>
                </div>
            </form>
        </dialog>

        <script>
            (function () {
                document.querySelectorAll('[data-dialog]').forEach((b) => b.addEventListener('click', () => {
                    const d = document.getElementById(b.dataset.dialog), f = d.querySelector('form');
                    f.action = b.dataset.url;
                    if (f.approved_amount) { f.approved_amount.value = b.dataset.amount || ''; f.installment_period.value = b.dataset.period || ''; f.interest_rate.value = ''; calc(); }
                    const a = d.querySelector('[data-amount]'); if (a) a.textContent = b.dataset.amount;
                    d.showModal();
                }));
                document.querySelectorAll('dialog').forEach((d) => {
                    d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
                    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
                });
                const f = document.querySelector('#approveModal form'), out = document.querySelector('[data-monthly]');
                const calc = () => {
                    const a = Number(f.approved_amount.value || 0), r = Number(f.interest_rate.value || 0), m = Number(f.installment_period.value || 0);
                    if (!a || !m) { out.classList.add('hidden'); return; }
                    const total = a + a * r / 100 * m / 12;
                    out.textContent = @js(__('قسط العميل مع البنك تقريباً ')) + (total / m).toLocaleString('en', { maximumFractionDigits: 3 }) + @js(__(' شهرياً (إجمالي ')) + total.toLocaleString('en', { maximumFractionDigits: 3 }) + ')';
                    out.classList.remove('hidden');
                };
                ['approved_amount', 'interest_rate', 'installment_period'].forEach((n) => f[n].addEventListener('input', calc));
            })();
        </script>
    @endif
@endsection
