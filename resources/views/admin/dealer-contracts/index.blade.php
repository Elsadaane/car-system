@extends('admin.layouts.app')

@section('title', __('عقود تفويض بيع المركبات'))
@section('subtitle', __('عربيات أصحابها سايبينها في المعرض يبيعها بالنيابة عنهم'))

@php
    use App\Support\Money;
    use App\Support\Media;
    $statusStyle = ['active' => 'bg-emerald-50 text-emerald-700', 'completed' => 'bg-blue-50 text-blue-700', 'cancelled' => 'bg-slate-100 text-slate-500'];
    $isAdmin = \App\Support\Access::can('dealer_contracts.delete');
@endphp

@section('actions')
    <a href="{{ route('admin.dealer-contracts.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('عقد تفويض جديد') }}
    </a>
    @if ($isAdmin)
        <a href="{{ route('admin.dealer-payments.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('المدفوعات') }}</a>
    @endif
    <script>
        document.addEventListener('click', (e) => document.querySelectorAll('details[data-row-menu][open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); }));
        // القايمة جوه جدول بيعمل scroll: بنثبّتها على الشاشة عشان ماتتقصّش
        document.querySelectorAll('details[data-row-menu]').forEach((d) => d.addEventListener('toggle', () => {
            if (!d.open) return;
            const p = d.querySelector('div'), r = d.querySelector('summary').getBoundingClientRect(), rtl = document.dir === 'rtl';
            Object.assign(p.style, { position: 'fixed', marginTop: '0', insetInlineEnd: 'auto' });
            const w = p.offsetWidth, h = p.offsetHeight;
            p.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, rtl ? r.left : r.right - w)) + 'px';
            p.style.top = (r.bottom + h + 8 > window.innerHeight ? Math.max(8, r.top - h - 4) : r.bottom + 4) + 'px';
        }));
        window.addEventListener('scroll', () => document.querySelectorAll('details[data-row-menu][open]').forEach((d) => d.removeAttribute('open')), { passive: true });
        document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(b.dataset.copy); } catch (e) { prompt('', b.dataset.copy); return; }
            const l = b.querySelector('[data-copy-label]'); const t = l.textContent; l.textContent = @js(__('اتنسخ ✓')); setTimeout(() => { l.textContent = t; }, 1500);
        }));
    </script>
@endsection

@section('content')
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('admin.dealer-contracts.index', ['status' => 'active']) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5">
            <div class="text-sm text-slate-500">{{ __('عقود مفتوحة') }}</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $totals['active'] }}</div>
        </a>
        <div class="rounded-2xl p-5 shadow-sm ring-1 {{ $totals['remaining'] > 0 ? 'bg-amber-50 ring-amber-200' : 'bg-white ring-slate-200' }}">
            <div class="text-sm {{ $totals['remaining'] > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ __('مستحق للملاك') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold {{ $totals['remaining'] > 0 ? 'text-amber-800' : 'text-slate-900' }}">{{ Money::format($totals['remaining'], false) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('اتدفع الشهر ده') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-emerald-600">{{ Money::format($totals['paid_month'], false) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('إجمالي عمولات المعرض') }}</div>
            <div class="mt-2 truncate text-2xl font-extrabold text-violet-600">{{ Money::format($totals['commission'], false) }}</div>
        </div>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('رقم العقد، المالك، التليفون، كود العربية...') }}"
               class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <select name="supplier" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل الملاك') }}</option>
            @foreach ($suppliers as $id => $n) <option value="{{ $id }}" @selected($filters['supplier'] === $id)>{{ $n }}</option> @endforeach
        </select>
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
            <option value="">{{ __('كل الحالات') }}</option>
            @foreach ($statuses as $code => $n) <option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $n }}</option> @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" title="{{ __('من') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <input type="date" name="to" value="{{ $filters['to'] }}" title="{{ __('إلى') }}" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
        @if (array_filter($filters)) <a href="{{ route('admin.dealer-contracts.index') }}" class="self-center text-sm text-slate-500 hover:text-slate-900">{{ __('مسح') }}</a> @endif
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        @if ($contracts->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="font-bold text-slate-700">{{ __('مفيش عقود') }}</p>
                <a href="{{ route('admin.dealer-contracts.create') }}" class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:underline">{{ __('اعمل أول عقد') }}</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('العقد') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('العربية') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المالك') }}</th>
                            <th class="px-3 py-3 text-start font-semibold">{{ __('المستحق') }}</th>
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
                                $signed = $c->owner_signature && $c->showroom_signature;
                            @endphp
                            <tr class="group transition hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.dealer-contracts.show', $c) }}" class="font-mono text-sm font-bold text-slate-900 group-hover:text-blue-700">{{ $c->contract_number }}</a>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                                        {{ \Illuminate\Support\Carbon::parse($c->contract_date)->format('Y-m-d') }}
                                        @unless ($signed) <span class="rounded bg-amber-50 px-1 text-[10px] font-bold text-amber-700">{{ __('ناقص توقيع') }}</span> @endunless
                                    </div>
                                    @if ($c->user_name)<div class="text-[11px] text-slate-400">👤 {{ $c->user_name }}</div>@endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-10 w-14 shrink-0 overflow-hidden rounded-lg bg-slate-100">@if ($img) <img src="{{ $img }}" alt="" class="h-full w-full object-cover" loading="lazy"> @endif</div>
                                        <div>
                                            <div class="font-bold text-slate-800">{{ $c->brand_name }} {{ $c->model_name }} {{ $c->manufacturing_year }}</div>
                                            <div class="font-mono text-xs text-slate-400">{{ $c->vehicle_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-800">{{ $c->supplier_name }}</div>
                                    <a href="tel:{{ $c->supplier_phone }}" class="text-xs text-blue-600" dir="ltr">{{ $c->supplier_phone }}</a>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <div class="font-bold text-slate-900">{{ Money::format($c->total_amount, false) }}</div>
                                    <div class="text-[11px] text-slate-400">{{ __('سعر') }} {{ Money::format($c->agreed_price, false) }}@if ((float) $c->commission_amount > 0) {{ __('− عمولة') }} {{ Money::format($c->commission_amount, false) }}@endif @if ((float) $c->tax_amount > 0) {{ __('+ ضريبة') }} {{ Money::format($c->tax_amount, false) }} ({{ (float) $c->tax_percent }}%)@endif</div>
                                </td>
                                <td class="min-w-[150px] px-3 py-3">
                                    <div class="flex justify-between text-xs">
                                        <span class="font-bold text-emerald-600">{{ Money::format($c->paid_amount, false) }}</span>
                                        <span class="{{ (float) $c->remaining_amount > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ __('باقي') }} {{ Money::format($c->remaining_amount, false) }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div></div>
                                </td>
                                <td class="px-3 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusStyle[$c->status_code] ?? 'bg-slate-100 text-slate-600' }}">{{ $c->status_name ?? '—' }}</span></td>
                                <td class="px-5 py-3">
                                    @php
                                        $rowWa = preg_replace('/\D/', '', (string) $c->supplier_phone);
                                        $rowActive = $c->status_code === 'active';
                                    @endphp
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($rowActive && ! $c->owner_signature && $rowWa)
                                            @php $link = \App\Http\Controllers\Admin\DealerContractController::signLink($c); @endphp
                                            <a href="https://wa.me/{{ $rowWa }}?text={{ rawurlencode("أهلاً {$c->supplier_name}\nرابط توقيع عقد تفويض عربيتك {$c->brand_name} {$c->model_name}:\n{$link}") }}"
                                               target="_blank" rel="noopener" title="{{ __('ابعت رابط توقيع للمالك') }}" class="grid h-8 w-8 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50">✍️</a>
                                        @endif
                                        @if ($isAdmin && $rowActive && (float) $c->remaining_amount > 0)
                                            <a href="{{ route('admin.dealer-contracts.show', [$c, 'open' => 'payModal']) }}" title="{{ __('إضافة دفعة') }}" class="grid h-8 w-8 place-items-center rounded-lg text-emerald-700 hover:bg-emerald-50">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M12 10v5M9.5 12.5h5"/></svg>
                                            </a>
                                        @endif
                                        @if ($rowActive)
                                            <a href="{{ route('admin.dealer-contracts.show', [$c, 'open' => 'priceModal']) }}" title="{{ __('تغيير السعر') }}" class="grid h-8 w-8 place-items-center rounded-lg text-amber-600 hover:bg-amber-50">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12 12 20l-8-8V4h8z"/><circle cx="8" cy="8" r="1.3"/></svg>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.dealer-contracts.print', $c) }}" target="_blank" title="{{ __('طباعة') }}" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7z"/></svg>
                                        </a>
                                        <a href="{{ route('admin.dealer-contracts.show', $c) }}" class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">{{ __('فتح') }}</a>
                                        <details class="relative" data-row-menu>
                                            <summary class="grid h-8 w-8 cursor-pointer list-none place-items-center rounded-lg text-slate-500 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">⋮</summary>
                                            <div class="absolute end-0 z-30 mt-1 w-60 overflow-hidden rounded-xl bg-white py-1.5 text-start text-sm shadow-xl ring-1 ring-slate-200">
                                                @if ($c->status_code !== 'cancelled')
                                                    @unless ($c->owner_signature)
                                                        <button type="button" data-copy="{{ \App\Http\Controllers\Admin\DealerContractController::signLink($c) }}" class="flex w-full items-center gap-2 px-3 py-2 text-start font-semibold text-slate-700 hover:bg-slate-50">✍️ <span data-copy-label>{{ __('نسخ رابط توقيع المالك') }}</span></button>
                                                    @endunless
                                                    @unless ($c->showroom_signature)
                                                        <button type="button" data-copy="{{ \App\Http\Controllers\Admin\DealerContractController::signLink($c, 'showroom') }}" class="flex w-full items-center gap-2 px-3 py-2 text-start font-semibold text-slate-700 hover:bg-slate-50">🏢 <span data-copy-label>{{ __('نسخ رابط توقيع المفوّض') }}</span></button>
                                                    @endunless
                                                    <a href="{{ route('admin.dealer-contracts.edit', $c) }}" class="flex items-center gap-2 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">✏️ {{ __('تعديل العقد') }}</a>
                                                @endif
                                                @if (\App\Support\Access::can('vehicles.edit'))
                                                    <a href="{{ route('admin.vehicles.edit', $c->vehicle_id) }}" class="flex items-center gap-2 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">🚗 {{ __('تعديل المركبة') }}</a>
                                                @endif
                                                <a href="{{ route('admin.dealer-contracts.print', [$c, 'lang' => 'ar']) }}" target="_blank" class="flex items-center gap-2 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">📄 {{ __('طباعة بالعربي بس') }}</a>
                                            </div>
                                        </details>
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
@endsection
