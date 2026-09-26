@extends('admin.layouts.app')

@section('title', __('عقود البيع'))
@section('subtitle', __('كل العربيات اللي اتباعت — كاش وتقسيط'))

@php
    use App\Support\Money;
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $statusStyle = ['active' => 'bg-emerald-50 text-emerald-700', 'completed' => 'bg-blue-50 text-blue-700', 'cancelled' => 'bg-slate-100 text-slate-500'];
    $isAdmin = \App\Support\Access::can('sales.delete');
    $flags = ['' => __('الكل'), 'overdue' => __('عليها متأخرات'), 'unsigned' => __('ناقصة توقيع'), 'not_delivered' => __('لسه ماتسلّمتش'), 'not_transferred' => __('لسه مانقلتش ملكيتها')];
    $today = today();
    $financeOn = \App\Support\Module::enabled('finance');
    $item = 'flex w-full items-center gap-3 px-4 py-2 text-start font-semibold text-slate-700 hover:bg-slate-50';
@endphp

@section('actions')
    <a href="{{ route('admin.sales-contracts.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('عقد بيع جديد') }}
    </a>
    <a href="{{ route('admin.sale-payments.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('المدفوعات') }}</a>
    <a href="{{ route('admin.installments.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('الأقساط') }}</a>
@endsection

@section('content')
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg">
            <div class="text-sm text-slate-400">{{ __('مبيعات الشهر') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold">{{ Money::format($totals['month_sales'], false) }}</div>
            <div class="text-xs text-slate-400">{{ $totals['month_count'] }} {{ __('عربية') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('اتحصّل الشهر ده') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-emerald-600">{{ Money::format($totals['collected_month'], false) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('فلوس برّه (على العملاء)') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">{{ Money::format($totals['remaining'], false) }}</div>
        </div>
        <a href="{{ route('admin.sales-contracts.index', ['flag' => 'overdue']) }}" class="rounded-2xl p-5 shadow-sm ring-1 transition hover:-translate-y-0.5 {{ $totals['overdue'] > 0 ? 'bg-rose-50 ring-rose-200' : 'bg-white ring-slate-200' }}">
            <div class="text-sm {{ $totals['overdue'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">{{ __('أقساط متأخرة') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold {{ $totals['overdue'] > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ Money::format($totals['overdue'], false) }}</div>
        </a>
    </div>

    <form method="GET" class="mb-5 space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap gap-3">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('رقم العقد، العميل، التليفون، الرقم المدني، اللوحة، الشاسيه...') }}"
                   class="min-w-[240px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
            <select name="type" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                <option value="">{{ __('كاش وتقسيط') }}</option>
                <option value="cash" @selected($filters['type'] === 'cash')>{{ __('كاش') }}</option>
                <option value="installments" @selected($filters['type'] === 'installments')>{{ __('تقسيط') }}</option>
            </select>
            <select name="status" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach ($statuses as $code => $n) <option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $n }}</option> @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] }}" title="{{ __('من') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <input type="date" name="to" value="{{ $filters['to'] }}" title="{{ __('إلى') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            @if ($filters['flag']) <input type="hidden" name="flag" value="{{ $filters['flag'] }}"> @endif
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
            @if (array_filter($filters)) <a href="{{ route('admin.sales-contracts.index') }}" class="self-center text-sm text-slate-500 hover:text-slate-900">{{ __('مسح') }}</a> @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($flags as $k => $l)
                <a href="{{ route('admin.sales-contracts.index', array_filter(array_merge($filters, ['flag' => $k]))) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-bold transition {{ (string) $filters['flag'] === $k ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $l }}</a>
            @endforeach
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($contracts->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-700">{{ __('مفيش عقود') }}</p>
                <a href="{{ route('admin.sales-contracts.create') }}" class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:underline">{{ __('اعمل أول بيعة') }}</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العقد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العربية') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المشتري') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الإجمالي') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('السداد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($contracts as $c)
                            @php
                                $pct = (float) $c->total_amount > 0 ? min(100, round((float) $c->paid_amount / (float) $c->total_amount * 100)) : 0;
                                $img = Media::url(Media::list($c->vehicle_images)[0] ?? null);
                                $rowOpen = $c->status_code !== 'cancelled';
                                $rowWa = preg_replace('/\D/', '', (string) $c->customer_phone);
                                $nextDue = $c->next_due ? Carbon::parse($c->next_due) : null;
                            @endphp
                            <tr class="group transition hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.sales-contracts.show', $c) }}" class="font-mono text-sm font-bold text-slate-900 group-hover:text-blue-700">{{ $c->contract_number }}</a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1 text-xs text-slate-400">
                                        {{ Carbon::parse($c->contract_date)->format('Y-m-d') }}@if (! empty($c->rep_name)) · 🤝 {{ $c->rep_name }}@endif
                                        <span class="rounded px-1 text-[10px] font-bold {{ $c->payment_type === 'installments' ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600' }}">{{ $c->payment_type === 'installments' ? __('تقسيط ').$c->installments_count : __('كاش') }}</span>
                                        @if ($rowOpen && (! $c->customer_signature || ! $c->seller_signature)) <span class="rounded bg-amber-50 px-1 text-[10px] font-bold text-amber-700">{{ __('ناقص توقيع') }}</span> @endif
                                        @if ($c->pending_cancel) <span class="rounded bg-rose-50 px-1 text-[10px] font-bold text-rose-700">{{ __('طلب إلغاء') }}</span> @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-10 w-14 shrink-0 overflow-hidden rounded-lg bg-slate-100">@if ($img) <img src="{{ $img }}" alt="" class="h-full w-full object-cover" loading="lazy"> @endif</div>
                                        <div>
                                            <div class="font-bold text-slate-800">{{ $c->brand_name }} {{ $c->model_name }} {{ $c->manufacturing_year }}</div>
                                            <div class="text-xs text-slate-400"><span class="font-mono">{{ $c->vehicle_code }}</span>@if ($c->ownership_transferred_at) · <span class="text-violet-600">{{ __('نُقلت') }} {{ Carbon::parse($c->ownership_transferred_at)->format('Y-m-d') }}</span>@endif</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.customers.show', $c->customer_id) }}" class="font-semibold text-slate-800 hover:text-blue-700">{{ $c->customer_name }}</a>
                                    <a href="tel:{{ $c->customer_phone }}" class="block text-xs text-blue-600" dir="ltr" data-page-align>{{ $c->customer_phone }}</a>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <div class="font-bold text-slate-900">{{ Money::format($c->total_amount, false) }}</div>
                                    @if ((float) $c->discount_amount > 0) <div class="text-[11px] text-rose-500">{{ __('خصم') }} {{ Money::format($c->discount_amount, false) }}</div> @endif
                                </td>
                                <td class="min-w-[170px] px-3 py-3">
                                    <div class="flex justify-between text-xs">
                                        <span class="font-bold text-emerald-600">{{ Money::format($c->paid_amount, false) }}</span>
                                        <span class="{{ (float) $c->remaining_amount > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ (float) $c->remaining_amount > 0 ? __('باقي ').Money::format($c->remaining_amount, false) : __('خالص ✓') }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $c->overdue_count ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $pct }}%"></div></div>
                                    @if ($rowOpen && $c->overdue_count)
                                        <div class="mt-1 text-[11px] font-bold text-rose-600">{{ $c->overdue_count }} {{ __('قسط متأخر') }}</div>
                                    @elseif ($rowOpen && $nextDue)
                                        <div class="mt-1 text-[11px] text-slate-400">{{ __('القسط الجاي') }} {{ $nextDue->format('Y-m-d') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusStyle[$c->status_code] ?? 'bg-slate-100 text-slate-600' }}">{{ $c->status_name ?? '—' }}</span></td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($rowOpen && (float) $c->remaining_amount > 0)
                                            <a href="{{ route('admin.sales-contracts.show', [$c, 'open' => 'payModal']) }}" title="{{ __('إضافة دفعة') }}" class="grid h-8 w-8 place-items-center rounded-lg text-emerald-700 hover:bg-emerald-50">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M12 10v5M9.5 12.5h5"/></svg>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.sales-contracts.print', $c) }}" target="_blank" title="{{ __('طباعة') }}" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7z"/></svg>
                                        </a>
                                        <details class="relative" data-row-menu>
                                            <summary class="grid h-8 w-8 cursor-pointer list-none place-items-center rounded-lg text-slate-500 hover:bg-slate-100" title="{{ __('الإجراءات') }}">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                                            </summary>
                                            <div data-menu class="fixed z-40 w-60 overflow-hidden rounded-2xl bg-white py-2 text-sm shadow-xl ring-1 ring-slate-200">
                                                @if ($rowOpen)
                                                    @if ((float) $c->remaining_amount > 0)
                                                        <a href="{{ route('admin.sales-contracts.show', [$c, 'open' => 'payModal']) }}" class="{{ $item }}">{{ __('💵 إضافة دفعة مالية') }}</a>
                                                    @endif
                                                    @if (! $c->customer_signature && $rowWa)
                                                        @php $link = \App\Http\Controllers\Admin\SalesContractController::signLink($c); @endphp
                                                        <a href="https://wa.me/{{ $rowWa }}?text={{ rawurlencode("أهلاً {$c->customer_name}\nرابط توقيع عقد شراء عربيتك {$c->brand_name} {$c->model_name}:\n{$link}") }}" target="_blank" rel="noopener" class="{{ $item }}">{{ __('✍️ رابط توقيع المشتري') }}</a>
                                                    @endif
                                                    <a href="{{ route('admin.sales-contracts.show', $c) }}#signatures" class="{{ $item }}">{{ __('🖊️ توقيع المشتري / البائع') }}</a>
                                                    <a href="{{ route('admin.sales-contracts.show', [$c, 'open' => 'deliverModal']) }}" class="{{ $item }}">{{ __('🔑 عقد تسليم') }}</a>
                                                    <a href="{{ route('admin.sales-contracts.show', [$c, 'open' => 'transferModal']) }}" class="{{ $item }}">{{ __('📄 نقل الملكية') }}</a>
                                                    @if ($financeOn && (float) $c->remaining_amount > 0)
                                                        <a href="{{ route('admin.sales-contracts.show', [$c, 'open' => 'financeModal']) }}" class="{{ $item }}">{{ __('🏦 طلب تمويل') }}</a>
                                                    @endif
                                                    <a href="{{ route('admin.sales-contracts.edit', $c) }}" class="{{ $item }}">{{ __('✏️ تعديل') }}</a>
                                                @endif
                                                <a href="{{ route('admin.sales-contracts.print', $c) }}" target="_blank" class="{{ $item }}">{{ __('🖨️ طباعة') }}</a>
                                                @if ($rowOpen && ! $c->pending_cancel)
                                                    <div class="my-1 border-t border-slate-100"></div>
                                                    <a href="{{ route('admin.sales-contracts.show', [$c, 'open' => 'cancelModal']) }}" class="flex w-full items-center gap-3 px-4 py-2 text-start font-semibold text-amber-700 hover:bg-amber-50">⏸️ {{ $isAdmin ? __('إلغاء العقد') : __('طلب إلغاء') }}</a>
                                                @endif
                                            </div>
                                        </details>
                                        <a href="{{ route('admin.sales-contracts.show', $c) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">{{ __('فتح') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($contracts->hasPages()) <div class="border-t border-slate-100 p-4">{{ $contracts->links() }}</div> @endif
        @endif
    </div>

    <script>
        (function () {
            // المنيو بتتحط "fixed" جنب الزرار عشان الجدول مايقصّهاش
            document.querySelectorAll('details[data-row-menu]').forEach((d) => {
                d.addEventListener('toggle', () => {
                    if (!d.open) return;
                    document.querySelectorAll('details[data-row-menu][open]').forEach((o) => { if (o !== d) o.removeAttribute('open'); });
                    const r = d.querySelector('summary').getBoundingClientRect(), m = d.querySelector('[data-menu]');
                    const h = m.offsetHeight, below = window.innerHeight - r.bottom > h + 8;
                    m.style.top = (below ? r.bottom + 6 : Math.max(8, r.top - h - 6)) + 'px';
                    m.style.left = Math.max(8, r.right - m.offsetWidth) + 'px';
                });
            });
            const closeAll = () => document.querySelectorAll('details[data-row-menu][open]').forEach((d) => d.removeAttribute('open'));
            document.addEventListener('click', (e) => { if (!e.target.closest('details[data-row-menu]')) closeAll(); });
            window.addEventListener('scroll', closeAll, true);
        })();
    </script>
@endsection
