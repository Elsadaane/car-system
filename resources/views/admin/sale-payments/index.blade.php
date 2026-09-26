@extends('admin.layouts.app')

@section('title', __('مدفوعات المبيعات'))
@section('subtitle', __('كل الفلوس اللي دخلت من العملاء — مقدمات وأقساط ودفعات كاش'))

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $isAdmin = \App\Support\Access::can('payments.edit');
    $dec = Money::decimals();
@endphp

@section('actions')
    <a href="{{ route('admin.sales-contracts.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('عقود البيع') }}</a>
    <a href="{{ route('admin.installments.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('متابعة الأقساط') }}</a>
@endsection

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg">
            <div class="text-sm text-slate-400">{{ __('إجمالي المحصّل') }} {{ array_filter($filters) ? __('(حسب الفلتر)') : '' }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold">{{ Money::format($total, false) }}</div>
            <div class="text-xs text-slate-400">{{ Money::currency() }}</div>
        </div>
        <div class="rounded-2xl bg-emerald-50 p-5 shadow-sm ring-1 ring-emerald-200">
            <div class="text-sm text-emerald-700">{{ __('النهارده') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-emerald-800">{{ Money::format($today, false) }}</div>
        </div>
        @foreach ($byMethod->take(2) as $m)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-sm text-slate-500">{{ $m->method_name ?? '—' }}</div>
                <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">{{ Money::format($m->total, false) }}</div>
                <div class="text-xs text-slate-400">{{ $m->cnt }} {{ __('دفعة ·') }} {{ $total > 0 ? round($m->total / $total * 100) : 0 }}%</div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('رقم العقد، العميل، التليفون، رقم الإيصال، رقم التحويل...') }}"
               class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <select name="method" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل طرق الدفع') }}</option>
            @foreach ($methods as $id => $n) <option value="{{ $id }}" @selected($filters['method'] === $id)>{{ $n }}</option> @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        <a href="{{ route('admin.sale-payments.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="self-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-200">{{ __('النهارده') }}</a>
        <a href="{{ route('admin.sale-payments.index', ['from' => now()->startOfMonth()->toDateString()]) }}" class="self-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-200">{{ __('الشهر ده') }}</a>
        @if (array_filter($filters)) <a href="{{ route('admin.sale-payments.index') }}" class="self-center text-sm text-slate-500 hover:text-slate-900">{{ __('مسح') }}</a> @endif
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($payments->isEmpty())
            <p class="px-6 py-16 text-center font-bold text-slate-600">{{ __('مفيش مدفوعات') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('التاريخ') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العميل') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('وذلك عن') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الطريقة') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المبلغ') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('بواسطة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $p)
                            @php $isDown = (int) $p->id === (int) $p->down_payment_id; @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-3">
                                    <div class="font-semibold text-slate-800">{{ Carbon::parse($p->payment_date)->format('Y-m-d') }}</div>
                                    <div class="font-mono text-[11px] text-slate-400">{{ $p->payment_reference }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.customers.show', $p->customer_id) }}" class="font-semibold text-slate-800 hover:text-blue-700">{{ $p->customer_name }}</a>
                                    <a href="tel:{{ $p->customer_phone }}" class="block text-xs text-blue-600" dir="ltr" data-page-align>{{ $p->customer_phone }}</a>
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.sales-contracts.show', $p->contract_id) }}" class="font-mono text-xs font-bold text-blue-700 hover:underline">{{ $p->contract_number }}</a>
                                    <div class="text-xs text-slate-500">
                                        @if ($isDown) <span class="font-bold text-violet-700">{{ __('مقدم') }}</span> ·
                                        @elseif ($p->covers) {{ __('قسط') }} {{ str_replace(',', __('، '), $p->covers) }} ·
                                        @endif
                                        {{ $p->brand_name }} {{ $p->model_name }}
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $p->method_name }}</span>
                                    @if ($p->bank_reference) <div class="mt-0.5 text-[11px] text-slate-400" dir="ltr" data-page-align>{{ $p->bank_reference }}</div> @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 font-extrabold text-emerald-700">{{ Money::format($p->amount, false) }}</td>
                                <td class="px-3 py-3 text-xs text-slate-500">{{ $p->user_name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end">
                                    <a href="{{ route('admin.sale-payments.receipt', $p) }}" target="_blank" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100">{{ __('إيصال') }}</a>
                                    @if ($isAdmin)
                                        <button type="button" title="{{ __('تعديل') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                                data-edit="{{ json_encode(['url' => route('admin.sale-payments.update', $p), 'amount' => number_format((float) $p->amount, $dec, '.', ''), 'method' => $p->payment_method_id, 'date' => Carbon::parse($p->payment_date)->format('Y-m-d'), 'reference' => $p->bank_reference, 'notes' => $p->notes, 'ref' => $p->payment_reference], JSON_UNESCAPED_UNICODE) }}">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
                                        </button>
                                        @unless ($isDown)
                                            <form method="POST" action="{{ route('admin.sale-payments.destroy', $p) }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('مسح الدفعة :ref؟ الأقساط هتتظبط تاني.', ['ref' => $p->payment_reference])) }})">
                                                @csrf @method('DELETE')
                                                <button title="{{ __('مسح') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-rose-500 hover:bg-rose-50">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>
                                                </button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages()) <div class="border-t border-slate-100 p-4">{{ $payments->links() }}</div> @endif
        @endif
    </div>

    @if ($isAdmin)
        <dialog id="editPayment" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
            <form method="POST" action="" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf @method('PUT')
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">{{ __('تعديل دفعة') }}</h2>
                        <p class="font-mono text-xs text-slate-500" data-ref></p>
                    </div>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('المبلغ') }}</label>
                            <input name="amount" type="number" step="0.001" min="0.001" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('التاريخ') }}</label>
                            <input name="payment_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('طريقة الدفع') }}</label>
                        <select name="payment_method_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                            @foreach ($methods as $id => $n) <option value="{{ $id }}">{{ $n }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('رقم التحويل / الشيك') }}</label>
                        <input name="reference" dir="ltr" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('ملاحظات') }}</label>
                        <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none"></textarea>
                    </div>
                    <p class="text-xs text-slate-400">{{ __('بعد الحفظ الأقساط بتتوزع تاني لوحدها، والتعديل بيتسجل في سجل العقد.') }}</p>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
                </div>
            </form>
        </dialog>
        <script>
            (function () {
                const d = document.getElementById('editPayment'), f = d.querySelector('form');
                document.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
                    const p = JSON.parse(b.dataset.edit);
                    f.action = p.url;
                    f.amount.value = p.amount; f.payment_date.value = p.date; f.payment_method_id.value = p.method;
                    f.reference.value = p.reference || ''; f.notes.value = p.notes || '';
                    d.querySelector('[data-ref]').textContent = p.ref;
                    d.showModal();
                }));
                d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
                d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
            })();
        </script>
    @endif
@endsection
