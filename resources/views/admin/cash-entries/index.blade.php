@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $isExp = $kind === 'expense';
    $tone = $isExp ? ['bg' => 'bg-rose-600', 'soft' => 'bg-rose-50 text-rose-700 ring-rose-200', 'text' => 'text-rose-600', 'bar' => 'bg-rose-500', 'btn' => 'bg-rose-600 hover:bg-rose-700']
                   : ['bg' => 'bg-emerald-600', 'soft' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'text' => 'text-emerald-600', 'bar' => 'bg-emerald-500', 'btn' => 'bg-emerald-600 hover:bg-emerald-700'];
    $reopen = $errors->any() && old('_modal') === 'entry' ? (old('_edit_url') ?: 'new') : '';
    $change = $prev > 0 ? round(($total - $prev) / $prev * 100) : null;
    $maxCat = max(1, (float) $byCategory->max('total'));
    $indexRoute = $isExp ? 'admin.expenses.index' : 'admin.revenues.index';
    $accOpts = $accounts->mapWithKeys(fn ($n, $id) => [$id => $n.' ('.Money::format($balances[$id]['balance'] ?? 0, false).')']);
@endphp

@section('title', $L['title'])
@section('subtitle', $isExp ? __('كل فلوس بتطلع من المعرض غير تمن العربيات — بالتصنيف والفرع والخزنة') : __('دخل المعرض من غير بيع العربيات: عمولات، خدمات، إيجارات...'))

@section('actions')
    <a href="{{ route('admin.finance-categories.index', $kind) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('📁 التصنيفات') }}</a>
    <button type="button" data-crud-new="entryModal" class="inline-flex items-center gap-2 rounded-xl {{ $tone['btn'] }} px-4 py-2.5 text-sm font-bold text-white shadow-sm">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ $L['voucher'] }} {{ __('جديد') }}
    </button>
@endsection

@section('content')
    @if (session('receipt_url'))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl px-5 py-4 text-sm ring-1 {{ $tone['soft'] }}">
            <span class="font-bold">{{ __('اتسجل') }} {{ $L['voucher'] }}.</span>
            <a href="{{ session('receipt_url') }}" target="_blank" class="rounded-lg {{ $tone['bg'] }} px-4 py-2 font-bold text-white">{{ __('🖨️ طباعة السند') }}</a>
        </div>
    @endif
    @if (session('warning')) <div class="mb-4 rounded-2xl bg-amber-50 px-5 py-4 text-sm font-bold text-amber-800 ring-1 ring-amber-200">{{ session('warning') }}</div> @endif

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl {{ $tone['bg'] }} p-6 text-white shadow-lg">
            <div class="text-sm text-white/80">{{ __('إجمالي') }} {{ $L['title'] }} {{ __('في المدة') }}</div>
            <div class="mt-1 text-3xl font-extrabold">{{ Money::format($total, false) }}</div>
            <div class="mt-2 text-xs text-white/80">
                {{ $count }} {{ __('سند') }}
                @if ($change !== null) · <span class="rounded-full bg-white/20 px-2 py-0.5 font-bold">{{ $change > 0 ? '▲' : '▼' }} {{ abs($change) }}%</span> {{ __('عن المدة اللي قبلها') }} @endif
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <div class="mb-3 text-sm font-extrabold text-slate-800">{{ __('حسب التصنيف') }}</div>
            @forelse ($byCategory->take(5) as $c)
                <div class="mb-2">
                    <div class="flex justify-between text-xs"><span class="font-bold text-slate-700">{{ $c->name ?? __('بدون تصنيف') }}</span><span class="text-slate-500">{{ Money::format($c->total, false) }} · {{ $total > 0 ? round($c->total / $total * 100) : 0 }}%</span></div>
                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $c->total / $maxCat * 100 }}%"></div></div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">{{ __('مفيش حركات في المدة دي') }}</p>
            @endforelse
        </div>
    </div>

    <form method="GET" class="mb-5 space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap gap-3">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('البيان، رقم السند، رقم الفاتورة...') }}" class="min-w-[200px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
            <select name="category" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل التصنيفات') }}</option>@foreach ($mainCats as $id => $n) <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $n }}</option> @endforeach</select>
            <select name="branch" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل الفروع') }}</option>@foreach ($branches as $id => $n) <option value="{{ $id }}" @selected($filters['branch'] === $id)>{{ $n }}</option> @endforeach</select>
            <select name="account" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل الخزائن') }}</option>@foreach ($accounts as $id => $n) <option value="{{ $id }}" @selected($filters['account'] === $id)>{{ $n }}</option> @endforeach</select>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">{{ __('بحث') }}</button>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach (['النهارده' => [today(), today()], 'الشهر ده' => [now()->startOfMonth(), today()], 'الشهر اللي فات' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'السنة دي' => [now()->startOfYear(), today()]] as $l => [$a, $b])
                <a href="{{ route($indexRoute, ['from' => $a->toDateString(), 'to' => $b->toDateString()]) }}" class="rounded-full px-3 py-1.5 font-bold {{ $filters['from'] === $a->toDateString() && $filters['to'] === $b->toDateString() ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $l }}</a>
            @endforeach
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($entries->isEmpty())
            <div class="px-6 py-16 text-center"><div class="text-4xl">🧾</div><p class="mt-2 font-bold text-slate-600">{{ __('مفيش') }} {{ $L['title'] }} {{ __('في المدة دي') }}</p></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr><th class="px-5 py-3 text-start">{{ __('التاريخ / السند') }}</th><th class="px-3 py-3 text-start">{{ __('البيان') }}</th><th class="px-3 py-3 text-start">{{ __('التصنيف') }}</th><th class="px-3 py-3 text-start">{{ __('الفرع / الخزنة') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($entries as $e)
                            <tr class="hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-3"><div class="font-semibold text-slate-800">{{ Carbon::parse($e->entry_date)->format('Y-m-d') }}</div><div class="font-mono text-[11px] {{ $tone['text'] }}">{{ $e->code }}</div></td>
                                <td class="max-w-xs px-3 py-3">
                                    <div class="font-bold text-slate-800">{{ $e->details }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $e->supplier_name ?? $e->customer_name }}
                                        @if ($e->invoice_no) {{ __('· فاتورة') }} {{ $e->invoice_no }} @endif
                                        @if ($e->vehicle_code) · <a href="{{ route('admin.vehicles.show', $e->vehicle_id) }}" class="font-bold text-blue-600">🚗 {{ $e->vehicle_code }}</a> @endif
                                        · {{ $e->user_name }}
                                    </div>
                                </td>
                                <td class="px-3 py-3"><span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $e->category_name ?? '—' }}</span>@if ($e->sub_category_name) <div class="mt-0.5 text-[11px] text-slate-400">↳ {{ $e->sub_category_name }}</div> @endif</td>
                                <td class="px-3 py-3 text-xs"><div class="font-semibold text-slate-700">{{ $e->branch_name ?? __('عام') }}</div><div class="text-slate-400">{{ $e->account_name }}</div></td>
                                <td class="whitespace-nowrap px-3 py-3"><div class="font-extrabold {{ $tone['text'] }}">{{ Money::format($e->total_amount, false) }}</div>@if ((float) $e->tax_amount > 0) <div class="text-[11px] text-slate-400">{{ __('شامل ضريبة') }} {{ Money::format($e->tax_amount, false) }}</div> @endif</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end">
                                    @if ($e->attachment) <a href="{{ Media::url($e->attachment) }}" target="_blank" title="{{ __('المرفق') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100">📎</a> @endif
                                    <a href="{{ route('admin.cash-entries.receipt', $e->id) }}" target="_blank" title="{{ __('طباعة') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100">🖨️</a>
                                    <button type="button" data-crud-edit="entryModal" title="{{ __('تعديل') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100"
                                            data-values="{{ json_encode(['url' => route('admin.cash-entries.update', $e->id), 'branch_id' => $e->branch_id, 'category_id' => $e->category_id, 'sub_category_id' => $e->sub_category_id, 'supplier_id' => $e->supplier_id, 'customer_id' => $e->customer_id, 'vehicle_id' => $e->vehicle_id, 'invoice_no' => $e->invoice_no, 'amount' => (float) $e->amount, 'tax_percent' => (float) $e->tax_percent, 'entry_date' => $e->entry_date, 'financial_account_id' => $e->financial_account_id, 'check_number' => $e->check_number, 'details' => $e->details, 'notes' => $e->notes], JSON_UNESCAPED_UNICODE) }}">✏️</button>
                                    <form method="POST" action="{{ route('admin.cash-entries.destroy', $e->id) }}" class="inline" onsubmit="{{ __('return confirm(\'إلغاء السند؟ المبلغ هيرجع للخزنة.\')') }}">@csrf @method('DELETE')<button title="{{ __('إلغاء') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-rose-500 hover:bg-rose-50">🗑️</button></form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($entries->hasPages()) <div class="border-t border-slate-100 p-4">{{ $entries->links() }}</div> @endif
        @endif
    </div>

    {{-- ═══ مودال السند ═══ --}}
    <dialog id="entryModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route($isExp ? 'admin.expenses.store' : 'admin.revenues.store') }}"
            data-title-new="{{ $L['voucher'] }} جديد" data-title-edit="تعديل {{ $L['voucher'] }}"
            class="m-auto w-[calc(100%-2rem)] max-w-4xl rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route($isExp ? 'admin.expenses.store' : 'admin.revenues.store') }}" enctype="multipart/form-data">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="entry"><input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ $L['voucher'] }} {{ __('جديد') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="max-h-[70vh] space-y-6 overflow-y-auto px-6 py-5">
                <div>
                    <div class="mb-3 text-xs font-extrabold {{ $tone['text'] }}">{{ __('① التصنيف والجهة') }}</div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'category_id', 'label' => __('التصنيف'), 'type' => 'select', 'options' => $mainCats, 'empty' => __('— اختار —'), 'required' => true])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'sub_category_id', 'label' => __('الفرعي'), 'type' => 'select', 'options' => [], 'empty' => '—'])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'branch_id', 'label' => __('الفرع'), 'type' => 'select', 'options' => $branches, 'empty' => __('عام')])
                        @if ($isExp)
                            @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'supplier_id', 'label' => $L['party'], 'type' => 'select', 'options' => $suppliers, 'empty' => __('مصروف عام')])
                        @else
                            @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'customer_id', 'label' => $L['party'], 'type' => 'select', 'options' => $customers, 'empty' => __('إيراد عام')])
                        @endif
                    </div>
                    @if ($isExp)
                        <div class="mt-4">
                            @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'vehicle_id', 'label' => __('🚗 المصروف ده على عربية معيّنة؟ (صيانة/تجهيز)'), 'type' => 'select', 'options' => $vehicles, 'empty' => __('لأ — مصروف عام'), 'hint' => __('لو اخترت عربية، المبلغ هيتحسب في تكلفتها وتقرير ربحها')])
                        </div>
                    @endif
                </div>
                <div>
                    <div class="mb-3 text-xs font-extrabold {{ $tone['text'] }}">{{ __('② المبلغ') }}</div>
                    <div class="grid gap-4 sm:grid-cols-4">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'amount', 'label' => __('المبلغ الأساسي'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'tax_percent', 'label' => __('الضريبة %'), 'type' => 'number', 'step' => '0.01', 'min' => 0])
                        <div><div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('الإجمالي') }}</div><div data-total class="rounded-xl px-3 py-2.5 text-lg font-extrabold ring-1 {{ $tone['soft'] }}">0</div></div>
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'invoice_no', 'label' => __('رقم الفاتورة'), 'dir' => 'ltr'])
                    </div>
                </div>
                <div>
                    <div class="mb-3 text-xs font-extrabold {{ $tone['text'] }}">{{ __('③ الخزنة والتاريخ') }}</div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'entry_date', 'label' => __('التاريخ'), 'type' => 'date', 'required' => true, 'value' => old('entry_date', today()->format('Y-m-d'))])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'financial_account_id', 'label' => $L['account'], 'type' => 'select', 'options' => $accOpts, 'empty' => __('— اختار الخزنة —'), 'required' => true])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'check_number', 'label' => __('رقم الشيك'), 'dir' => 'ltr'])
                    </div>
                </div>
                <div>
                    <div class="mb-3 text-xs font-extrabold {{ $tone['text'] }}">{{ __('④ البيان والمرفقات') }}</div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'details', 'label' => __('البيان'), 'required' => true, 'placeholder' => $isExp ? __('مثلاً: فاتورة كهرباء شهر 9') : __('مثلاً: عمولة تسويق عربية لعميل')])</div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('المرفق (صورة الفاتورة)') }}</label>
                            <input type="file" name="attachment" accept="image/*,.pdf" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-bold">
                        </div>
                        <div class="sm:col-span-3">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'e_', 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])</div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                <button type="submit" class="rounded-xl {{ $tone['btn'] }} px-6 py-2.5 text-sm font-bold text-white disabled:opacity-60">{{ __('حفظ السند') }}</button>
            </div>
        </form>
    </dialog>

    @php $subsJson = $subCats->map(fn ($s) => ['id' => $s->id, 'p' => $s->parent_id, 'n' => $s->name_ar])->values(); @endphp
    <script>
        (function () {
            const f = document.querySelector('#entryModal form'), subs = @json($subsJson), dec = {{ Money::decimals() }};
            const cat = f.category_id, sub = f.sub_category_id;
            const fill = () => {
                const keep = sub.value;
                sub.innerHTML = '<option value="">—</option>' + subs.filter((s) => String(s.p) === cat.value).map((s) => `<option value="${s.id}">${s.n}</option>`).join('');
                sub.value = subs.some((s) => String(s.id) === keep && String(s.p) === cat.value) ? keep : '';
            };
            cat.addEventListener('change', fill);
            const total = () => {
                const a = Number(f.amount.value || 0), t = a * Number(f.tax_percent.value || 0) / 100;
                f.querySelector('[data-total]').textContent = (a + t).toLocaleString('en', { minimumFractionDigits: dec, maximumFractionDigits: dec });
            };
            ['input', 'change'].forEach((ev) => { f.amount.addEventListener(ev, total); f.tax_percent.addEventListener(ev, total); });
            fill(); total();
            @if (old('sub_category_id')) sub.value = @json(old('sub_category_id')); @endif
        })();
    </script>

    {{-- زرار إضافة عائم: دايماً ظاهر --}}
    <button type="button" data-crud-new="entryModal" class="fixed bottom-6 end-6 z-30 inline-flex items-center gap-2 rounded-full {{ $tone['btn'] }} px-6 py-4 text-base font-extrabold text-white shadow-2xl transition hover:scale-105">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ $L['voucher'] }} {{ __('جديد') }}
    </button>
    @include('admin.partials.crud-modal-script')
@endsection
