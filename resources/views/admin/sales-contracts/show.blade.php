@extends('admin.layouts.app')

@section('title', __('عقد بيع ').$contract->contract_number)
@section('subtitle', $contract->brand_name.' '.$contract->model_name.' '.$contract->manufacturing_year.' · '.$contract->customer_name)

@php
    use App\Support\Money;
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $isAdmin = \App\Support\Access::can('sales.delete');
    $cancelled = $contract->status_code === 'cancelled';
    $open = ! $cancelled;
    $total = (float) $contract->total_amount;
    $pct = $total > 0 ? min(100, round((float) $contract->paid_amount / $total * 100)) : 0;
    $img = Media::url(Media::list($contract->vehicle_images)[0] ?? null);
    $wa = preg_replace('/\D/', '', (string) $contract->customer_phone);
    $statusStyle = ['active' => 'bg-emerald-500', 'completed' => 'bg-blue-600', 'cancelled' => 'bg-slate-400'];
    $today = today();
    $unpaid = $installments->where('is_paid', false);
    $overdue = $unpaid->filter(fn ($i) => Carbon::parse($i->due_date)->lt($today));
    $overdueAmount = $overdue->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount);
    $next = $unpaid->first();
    $modal = old('_modal');
    $pendingCancel = $cancellation && $cancellation->status === 'pending' ? $cancellation : null;
    $canPay = $open && (float) $contract->remaining_amount > 0;
    $isCancelled = $contract->status_code === 'cancelled';
    $cheques = $cheques ?? collect();
    $canCheque = \App\Support\Access::can('payments.create');
    // آخر شيك لكل قسط (لو اترجع واتسجل بديل، البديل هو اللي يظهر)
    $chequeByInst = $cheques->whereNotIn('status', ['cancelled', 'returned'])->sortBy('id')->keyBy('installment_id');
    $downId = (int) $contract->down_payment_id;
    $financeEnabled = \App\Support\Module::enabled('finance');
    $finStyle = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-blue-50 text-blue-700', 'paid_off' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700', 'cancelled' => 'bg-slate-100 text-slate-500'];

    $steps = [
        [__('العقد اتعمل'), true, Carbon::parse($contract->contract_date)->format('Y-m-d')],
        [__('التوقيعات'), $contract->customer_signature && $contract->seller_signature, $contract->customer_signature && $contract->seller_signature ? __('تمام') : __('ناقص')],
        [__('التسليم'), (bool) $contract->delivery_date, $contract->delivery_date ? Carbon::parse($contract->delivery_date)->format('Y-m-d') : __('لسه')],
        [__('نقل الملكية'), (bool) $contract->ownership_transferred_at, $contract->ownership_transferred_at ? Carbon::parse($contract->ownership_transferred_at)->format('Y-m-d') : __('لسه')],
        [__('السداد الكامل'), (float) $contract->remaining_amount <= 0 && ! $cancelled, $pct.'%'],
    ];

    $reminder = null;
    if ($wa && $overdue->isNotEmpty()) {
        $reminder = "أهلاً {$contract->customer_name}\nنفكّرك إن عليك ".$overdue->count().__(' قسط متأخر بإجمالي ').Money::format($overdueAmount)
            ." على عقد العربية {$contract->brand_name} {$contract->model_name} (عقد {$contract->contract_number}).\nياريت تتواصل معانا للسداد. شكراً لك.";
    }
@endphp

@section('actions')
    @if ($canPay)
        <button type="button" data-open="payModal" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            {{ __('إضافة دفعة') }}
        </button>
    @endif

    <details class="relative" data-actions>
        <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">
            {{ __('الإجراءات') }}
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="absolute end-0 z-30 mt-2 max-h-[75vh] w-72 overflow-y-auto rounded-2xl bg-white py-2 text-sm shadow-xl ring-1 ring-slate-200">
            @if ($signLink && $open)
                @php $waMsg = "أهلاً {$contract->customer_name}\nده رابط توقيع عقد شراء عربيتك {$contract->brand_name} {$contract->model_name} (عقد رقم {$contract->contract_number}):\n{$signLink}\nالرابط صالح لمدة 7 أيام."; @endphp
                @if ($wa)
                    <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($waMsg) }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-emerald-700 hover:bg-emerald-50">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-emerald-50">✍️</span> {{ __('ابعت رابط توقيع للمشتري') }}
                    </a>
                @endif
                <button type="button" data-copy="{{ $signLink }}" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🔗</span> <span data-copy-label>{{ __('نسخ رابط التوقيع') }}</span>
                </button>
            @endif
            <a href="#signatures" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🖊️</span> {{ __('توقيع المشتري / البائع') }}
            </a>
            @if ($open)
                <div class="my-1 border-t border-slate-100"></div>
                <button type="button" data-open="deliverModal" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50">🔑</span> {{ $contract->delivery_date ? __('تسليم تاني / تعديل') : __('تسليم العربية للعميل') }}
                </button>
                <button type="button" data-open="transferModal" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-violet-50">📄</span> {{ $contract->ownership_transferred_at ? __('تعديل نقل الملكية') : __('نقل الملكية') }}
                </button>
                @if ($financeEnabled && $canPay)
                    @if ($financeCompanies->isNotEmpty())
                        <button type="button" data-open="financeModal" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-slate-700 hover:bg-slate-50">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-emerald-50">🏦</span> {{ __('طلب تمويل') }}
                        </button>
                    @else
                        <a href="{{ route('admin.finance-companies.index') }}" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-400 hover:bg-slate-50">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🏦</span> {{ __('طلب تمويل (ضيف شركة الأول)') }}
                        </a>
                    @endif
                @endif
            @endif
            <div class="my-1 border-t border-slate-100"></div>
            <a href="{{ route('admin.sales-contracts.print', $model) }}" target="_blank" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🖨️</span> {{ __('طباعة العقد (عربي)') }}
            </a>
            <a href="{{ route('admin.sales-contracts.print', [$model, 'lang' => 'en']) }}" target="_blank" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🌐</span> Print (English)
            </a>
            @if ($open)
                <a href="{{ route('admin.sales-contracts.edit', $model) }}" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">✏️</span> {{ __('تعديل العقد') }}
                </a>
            @endif
            <a href="{{ route('admin.customers.show', $contract->customer_id) }}" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">👤</span> {{ __('ملف العميل') }}
            </a>
            @if ($reminder)
                <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($reminder) }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-rose-700 hover:bg-rose-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-rose-50">⏰</span> {{ __('تذكير بالمتأخرات واتساب') }}
                </a>
            @endif
            @if ($open && ! $pendingCancel)
                <div class="my-1 border-t border-slate-100"></div>
                <button type="button" data-open="cancelModal" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-amber-700 hover:bg-amber-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-amber-50">⏸️</span> {{ $isAdmin ? __('إلغاء العقد') : __('طلب إلغاء') }}
                </button>
            @endif
            @if ($isAdmin && $payments->where('id', '!=', $downId)->isEmpty())
                <form method="POST" action="{{ route('admin.sales-contracts.destroy', $model) }}" onsubmit="{{ __('return confirm(\'مسح العقد نهائياً؟ المقدم هيتمسح والعربية هترجع متاحة.\')') }}">
                    @csrf @method('DELETE')
                    <button class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-rose-600 hover:bg-rose-50"><span class="grid h-7 w-7 place-items-center rounded-lg bg-rose-50">🗑️</span> {{ __('حذف العقد') }}</button>
                </form>
            @endif
        </div>
    </details>
@endsection

@section('content')

    @if (session('receipt_url'))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-emerald-50 px-5 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
            <span class="font-bold">{{ __('اتسجلت الدفعة. اطبع إيصال استلام للعميل.') }}</span>
            <a href="{{ session('receipt_url') }}" target="_blank" class="rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white hover:bg-emerald-700">{{ __('طباعة الإيصال') }}</a>
        </div>
    @endif

    @if ($cancelled)
        <div class="mb-4 rounded-2xl bg-slate-800 px-5 py-4 text-sm text-white">
            <span class="font-extrabold">{{ __('العقد ده ملغي.') }}</span>
            @if ($cancellation)
                {{ __('السبب:') }} {{ \App\Support\Lang::pick($cancellation, 'reason') }} {{ __('· المسترد للعميل:') }} <b>{{ Money::format($cancellation->net_refund) }}</b>
                @if ((float) $cancellation->penalty_amount > 0) {{ __('(بعد غرامة') }} {{ Money::format($cancellation->penalty_amount, false) }}) @endif
            @endif
        </div>
    @endif

    @if ($pendingCancel)
        <div class="mb-4 rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="font-extrabold">{{ __('طلب إلغاء مستني موافقة الإدارة') }}</div>
                    <div class="mt-1">{{ __('السبب:') }} {{ \App\Support\Lang::pick($pendingCancel, 'reason') }} {{ __('· طلبه') }} {{ $pendingCancel->user_name ?? '—' }} {{ __('يوم') }} {{ Carbon::parse($pendingCancel->created_at)->format('Y-m-d') }}</div>
                    <div class="mt-1 text-xs">{{ __('المدفوع') }} {{ Money::format($pendingCancel->refund_amount) }} {{ __('− غرامة') }} {{ Money::format($pendingCancel->penalty_amount, false) }} {{ __('= يرجع للعميل') }} <b>{{ Money::format($pendingCancel->net_refund) }}</b></div>
                </div>
                @if ($isAdmin)
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.sales-contracts.cancellations.decide', [$model, $pendingCancel->id, 'approve']) }}" onsubmit="{{ __('return confirm(\'موافقة على الإلغاء؟ العربية هترجع متاحة.\')') }}">
                            @csrf @method('PATCH')
                            <button class="rounded-xl bg-rose-600 px-4 py-2 font-bold text-white hover:bg-rose-700">{{ __('موافقة وإلغاء') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.sales-contracts.cancellations.decide', [$model, $pendingCancel->id, 'reject']) }}">
                            @csrf @method('PATCH')
                            <button class="rounded-xl bg-white px-4 py-2 font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('رفض') }}</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($overdue->isNotEmpty() && $open)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800 ring-1 ring-rose-200">
            <span><b>{{ $overdue->count() }} {{ __('قسط متأخر') }}</b> {{ __('بإجمالي') }} <b>{{ Money::format($overdueAmount) }}</b> {{ __('— أقدم واحد من') }} {{ (int) Carbon::parse($overdue->first()->due_date)->diffInDays($today) }} {{ __('يوم.') }}</span>
            @if ($reminder)
                <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($reminder) }}" target="_blank" rel="noopener" class="rounded-lg bg-rose-600 px-4 py-2 font-bold text-white hover:bg-rose-700">{{ __('ابعت تذكير') }}</a>
            @endif
        </div>
    @endif

    {{-- ═══ مراحل العقد ═══ --}}
    <div class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-5">
        @foreach ($steps as $i => [$label, $done, $sub])
            <div class="flex items-center gap-3 rounded-2xl p-3 ring-1 {{ $done ? 'bg-emerald-50 ring-emerald-200' : 'bg-white ring-slate-200' }}">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm font-extrabold {{ $done ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400' }}">{{ $done ? '✓' : $i + 1 }}</span>
                <div class="min-w-0">
                    <div class="truncate text-sm font-bold {{ $done ? 'text-emerald-800' : 'text-slate-700' }}">{{ $label }}</div>
                    <div class="text-[11px] {{ $done ? 'text-emerald-600' : 'text-slate-400' }}">{{ $sub }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- الملخص المالي --}}
        <section class="rounded-2xl bg-slate-900 p-6 text-white shadow-lg lg:col-span-2">
            <div class="flex items-center justify-between">
                <span class="font-mono text-sm text-slate-400">{{ $contract->contract_number }}</span>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold">{{ $contract->payment_type === 'installments' ? __('تقسيط') : __('كاش') }}</span>
                    <span class="rounded-full {{ $statusStyle[$contract->status_code] ?? 'bg-slate-500' }} px-3 py-1 text-xs font-bold">{{ $contract->status_name }}</span>
                </div>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div><div class="text-xs text-slate-400">{{ __('إجمالي العقد') }}</div><div class="mt-1 text-2xl font-extrabold">{{ Money::format($contract->total_amount) }}</div></div>
                <div><div class="text-xs text-slate-400">{{ __('اتدفع') }}</div><div class="mt-1 text-2xl font-extrabold text-emerald-300">{{ Money::format($contract->paid_amount) }}</div></div>
                <div><div class="text-xs text-slate-400">{{ __('الباقي') }}</div><div class="mt-1 text-2xl font-extrabold {{ (float) $contract->remaining_amount > 0 ? 'text-amber-300' : 'text-slate-400' }}">{{ Money::format($contract->remaining_amount) }}</div></div>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-emerald-400" style="width: {{ $pct }}%"></div></div>
            <div class="mt-5 grid grid-cols-2 gap-3 border-t border-white/10 pt-5 text-sm sm:grid-cols-5">
                <div><div class="text-[11px] text-slate-400">{{ __('سعر البيع') }}</div><div class="font-bold">{{ Money::format($contract->sale_price, false) }}</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('الخصم') }}</div><div class="font-bold text-rose-300">{{ Money::format($contract->discount_amount, false) }}</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('الضريبة (') }}{{ (float) $contract->tax_percent }}%)</div><div class="font-bold">{{ Money::format($contract->tax_amount, false) }}</div></div>
                <div><div class="text-[11px] text-slate-400">{{ $contract->payment_type === 'installments' ? __('المقدم') : __('عند التعاقد') }}</div><div class="font-bold">{{ Money::format($contract->down_payment, false) }}</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('التاريخ') }}</div><div class="font-bold">{{ Carbon::parse($contract->contract_date)->format('Y-m-d') }}</div></div>
            </div>
            @if ($next && $open)
                @php $nd = Carbon::parse($next->due_date); @endphp
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 {{ $nd->lt($today) ? 'bg-rose-500/20' : 'bg-white/10' }}">
                    <div class="text-sm">
                        <span class="text-slate-300">{{ $nd->lt($today) ? __('أقدم قسط متأخر') : __('القسط الجاي') }}:</span>
                        <b>{{ __('رقم') }} {{ $next->installment_sequence }}</b> · {{ $nd->format('Y-m-d') }}
                        <span class="text-slate-300">({{ $nd->lt($today) ? __('متأخر ').(int) $nd->diffInDays($today).__(' يوم') : ($nd->isSameDay($today) ? __('النهارده') : __('بعد ').(int) $today->diffInDays($nd).__(' يوم')) }})</span>
                    </div>
                    <div class="text-lg font-extrabold">{{ Money::format((float) $next->amount - (float) $next->paid_amount) }}</div>
                </div>
            @endif
        </section>

        {{-- العربية --}}
        <a href="{{ route('admin.vehicles.show', $contract->vehicle_id) }}" class="group overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:shadow-lg">
            <div class="aspect-[16/9] bg-slate-100">@if ($img) <img src="{{ $img }}" alt="" class="h-full w-full object-cover transition group-hover:scale-105"> @endif</div>
            <div class="p-5">
                <div class="font-extrabold text-slate-900 group-hover:text-blue-700">{{ $contract->brand_name }} {{ $contract->model_name }} {{ $contract->manufacturing_year }}</div>
                <dl class="mt-3 space-y-1.5 text-xs">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الكود') }}</dt><dd class="font-mono font-bold">{{ $contract->vehicle_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('اللوحة') }}</dt><dd class="font-bold">{{ $contract->plate_number ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الشاسيه') }}</dt><dd class="font-mono font-bold">{{ $contract->chassis_number ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('اللون') }}</dt><dd class="font-bold">{{ $contract->color ?: '—' }}</dd></div>
                    @if ($contract->branch_name) <div class="flex justify-between"><dt class="text-slate-500">{{ __('الفرع') }}</dt><dd class="font-bold">{{ $contract->branch_name }}</dd></div> @endif
                </dl>
            </div>
        </a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- ═══ الأقساط ═══ --}}
            @if ($installments->isNotEmpty())
                <section id="installments" class="scroll-mt-24 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h2 class="text-base font-extrabold text-slate-900">{{ __('جدول الأقساط') }}</h2>
                        <span class="text-xs text-slate-500">{{ $installments->where('is_paid', true)->count() }} {{ __('مدفوع من') }} {{ $installments->count() }}
                            · {{ \App\Http\Controllers\Admin\SalesContractController::INTERVALS[(int) $contract->installment_interval] ?? '' }}</span>
                    </div>
                    <div class="max-h-[480px] overflow-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('القسط') }}</th><th class="px-3 py-3 text-start">{{ __('الاستحقاق') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('المدفوع') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($installments as $ins)
                                    @php
                                        $due = Carbon::parse($ins->due_date);
                                        $late = ! $ins->is_paid && $due->lt($today);
                                        $paidLate = $ins->is_paid && $ins->actual_payment_date && Carbon::parse($ins->actual_payment_date)->gt($due);
                                        $isNext = $next && $next->id === $ins->id;
                                    @endphp
                                    <tr class="{{ $late ? 'bg-rose-50/60' : ($isNext ? 'bg-blue-50/50' : '') }} {{ $ins->is_paid ? 'text-slate-400' : '' }}">
                                        <td class="px-6 py-2.5 font-bold">{{ $ins->installment_sequence }} <span class="font-normal text-slate-400">/ {{ $ins->total_installments }}</span>
                                            @if ($chq = $chequeByInst[$ins->id] ?? null)<div class="text-[10px] font-bold {{ $chq->status === 'bounced' ? 'text-rose-600' : 'text-slate-400' }}">🧾 {{ $chq->cheque_number }} · {{ __(\App\Support\Cheques::STATUSES[$chq->status][0] ?? '') }}</div>@endif
                                        </td>
                                        <td class="px-3 py-2.5">{{ $due->format('Y-m-d') }}</td>
                                        <td class="px-3 py-2.5 font-semibold">{{ Money::format($ins->amount, false) }}</td>
                                        <td class="px-3 py-2.5">
                                            @if ((float) $ins->paid_amount > 0)
                                                {{ Money::format($ins->paid_amount, false) }}
                                                @if (! $ins->is_paid) <div class="text-[11px] text-amber-600">{{ __('باقي') }} {{ Money::format((float) $ins->amount - (float) $ins->paid_amount, false) }}</div> @endif
                                            @else — @endif
                                        </td>
                                        <td class="px-3 py-2.5">
                                            @if ($ins->status === 'cancelled') <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">{{ __('اتقفل مع الإلغاء') }}</span>
                                            @elseif ($ins->is_paid && $paidLate) <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ __('اتدفع متأخر') }} {{ (int) $due->diffInDays(Carbon::parse($ins->actual_payment_date)) }} {{ __('يوم') }}</span>
                                            @elseif ($ins->is_paid) <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ __('مدفوع ✓') }}</span>
                                            @elseif ($late) <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700">{{ __('متأخر') }} {{ (int) $due->diffInDays($today) }} {{ __('يوم') }}</span>
                                            @elseif ($isNext) <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-700">{{ __('الجاي') }} {{ $due->isSameDay($today) ? __('(النهارده)') : '' }}</span>
                                            @else <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ __('بعد') }} {{ (int) $today->diffInDays($due) }} {{ __('يوم') }}</span> @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- ═══ الشيكات المؤجلة ═══ --}}
            @if (\App\Support\Cheques::table() && ($cheques->isNotEmpty() || ($canCheque && (float) $contract->remaining_amount > 0)))
                <section id="cheques" class="scroll-mt-24 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-4">
                        <h2 class="text-base font-extrabold text-slate-900">🧾 {{ __('الشيكات') }} <span class="text-sm font-normal text-slate-400">({{ $cheques->count() }})</span></h2>
                        @if ($canCheque && (float) $contract->remaining_amount > 0 && ! $isCancelled)
                            <button type="button" data-open="chequeModal" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">＋ {{ __('تسجيل شيكات') }}</button>
                        @endif
                    </div>
                    @if ($cheques->isEmpty())
                        <p class="px-6 py-6 text-center text-sm text-slate-400">{{ __('العميل بيدفع بشيكات؟ سجّلها هنا وتابع ميعاد إيداع كل شيك — ولما يتصرف بيتسجل دفعة على القسط أوتوماتيك.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('رقم الشيك') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th><th class="px-5 py-3"></th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($cheques as $c)
                                        @php $cLate = $c->status === 'received' && Carbon::parse($c->due_date)->lt($today); @endphp
                                        <tr class="{{ $cLate ? 'bg-rose-50/50' : '' }}">
                                            <td class="whitespace-nowrap px-6 py-2.5 {{ $cLate ? 'font-bold text-rose-700' : '' }}">{{ Carbon::parse($c->due_date)->format('Y-m-d') }}</td>
                                            <td class="px-3 py-2.5"><span class="font-mono font-bold">{{ $c->cheque_number }}</span> <span class="text-xs text-slate-400">{{ $c->bank_name }}</span></td>
                                            <td class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ Money::format($c->amount, false) }}</td>
                                            <td class="px-3 py-2.5"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ \App\Support\Cheques::STATUSES[$c->status][1] ?? '' }}">{{ __(\App\Support\Cheques::STATUSES[$c->status][0] ?? $c->status) }}</span></td>
                                            <td class="px-5 py-2.5 text-end">@if ($canCheque) @include('admin.cheques.actions', ['c' => $c]) @endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            {{-- ═══ الدفعات ═══ --}}
            <section id="payments" class="scroll-mt-24 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-base font-extrabold text-slate-900">{{ __('الدفعات') }} <span class="text-sm font-normal text-slate-400">({{ $payments->count() }})</span></h2>
                    @if ($canPay) <button type="button" data-open="payModal" class="text-sm font-bold text-emerald-700 hover:underline">{{ __('+ دفعة') }}</button> @endif
                </div>
                @if ($payments->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-slate-400">{{ __('لسه مفيش دفعات.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('الطريقة') }}</th><th class="px-3 py-3 text-start">{{ __('غطّت') }}</th><th class="px-3 py-3 text-start">{{ __('بواسطة') }}</th><th class="px-5 py-3"></th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($payments as $p)
                                    <tr>
                                        <td class="whitespace-nowrap px-6 py-3"><div class="font-semibold text-slate-800">{{ Carbon::parse($p->payment_date)->format('Y-m-d') }}</div><div class="font-mono text-[11px] text-slate-400">{{ $p->payment_reference }}</div></td>
                                        <td class="whitespace-nowrap px-3 py-3 font-bold text-emerald-700">{{ Money::format($p->amount, false) }}</td>
                                        <td class="px-3 py-3"><span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $p->method_name }}</span></td>
                                        <td class="px-3 py-3 text-xs text-slate-500">
                                            @if ((int) $p->id === $downId) <span class="rounded-md bg-violet-50 px-2 py-0.5 font-bold text-violet-700">{{ $contract->payment_type === 'installments' ? __('المقدم') : __('عند التعاقد') }}</span>
                                            @elseif ($p->covers) {{ __('قسط') }} {{ str_replace(',', __('، '), $p->covers) }}
                                            @else — @endif
                                        </td>
                                        <td class="px-3 py-3 text-xs text-slate-500">{{ $p->user_name }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-end">
                                            <a href="{{ route('admin.sale-payments.receipt', $p) }}" target="_blank" title="{{ __('إيصال') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7z"/></svg>
                                            </a>
                                            @if ($isAdmin && $open && (int) $p->id !== $downId)
                                                <form method="POST" action="{{ route('admin.sale-payments.destroy', $p) }}" class="inline" onsubmit="{{ __('return confirm(\'مسح الدفعة دي؟ الأقساط هتتظبط تاني.\')') }}">
                                                    @csrf @method('DELETE')
                                                    <button title="{{ __('مسح') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-rose-500 hover:bg-rose-50">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            {{-- المشتري --}}
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('المشتري') }}</h2>
                <a href="{{ route('admin.customers.show', $contract->customer_id) }}" class="mt-3 block text-lg font-bold text-slate-800 hover:text-blue-700">{{ $contract->customer_name }}</a>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('التليفون') }}</dt><dd class="font-semibold" dir="ltr">{{ $contract->customer_phone ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الرقم المدني') }}</dt><dd class="font-semibold">{{ $contract->customer_national_id ?: '—' }}</dd></div>
                    @if ($contract->customer_nationality) <div class="flex justify-between"><dt class="text-slate-500">{{ __('الجنسية') }}</dt><dd class="font-semibold">{{ $contract->customer_nationality }}</dd></div> @endif
                </dl>
                @if ($wa)
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="rounded-xl bg-emerald-500 py-2 text-center text-sm font-bold text-white hover:bg-emerald-600">{{ __('واتساب') }}</a>
                        <a href="tel:{{ $contract->customer_phone }}" class="rounded-xl bg-slate-100 py-2 text-center text-sm font-bold text-slate-700 hover:bg-slate-200">{{ __('اتصال') }}</a>
                    </div>
                @endif
            </section>

            {{-- الضامن --}}
            @if ($contract->has_guarantor)
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-base font-extrabold text-slate-900">{{ __('الضامن') }}</h2>
                    <div class="mt-3 font-bold text-slate-800">{{ $contract->guarantor_name }}</div>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('التليفون') }}</dt><dd class="font-semibold" dir="ltr">{{ $contract->guarantor_phone ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('الرقم المدني') }}</dt><dd class="font-semibold">{{ $contract->guarantor_national_id ?: '—' }}</dd></div>
                        @if ($contract->guarantor_address) <div class="flex justify-between"><dt class="text-slate-500">{{ __('العنوان') }}</dt><dd class="font-semibold">{{ $contract->guarantor_address }}</dd></div> @endif
                    </dl>
                    @if ($gwa = preg_replace('/\D/', '', (string) $contract->guarantor_phone))
                        <a href="https://wa.me/{{ $gwa }}" target="_blank" rel="noopener" class="mt-4 block rounded-xl bg-slate-100 py-2 text-center text-sm font-bold text-slate-700 hover:bg-slate-200">{{ __('واتساب الضامن') }}</a>
                    @endif
                </section>
            @endif

            {{-- التمويل --}}
            @if ($financeRequests->isNotEmpty())
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-extrabold text-slate-900">{{ __('التمويل') }}</h2>
                        <a href="{{ route('admin.finance-requests.index', ['q' => $contract->contract_number]) }}" class="text-xs font-bold text-blue-600 hover:underline">{{ __('إدارة') }}</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach ($financeRequests as $fr)
                            <div class="rounded-xl bg-slate-50 p-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800">{{ $fr->company_name }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $finStyle[$fr->status] ?? '' }}">{{ \App\Http\Controllers\Admin\FinanceRequestController::STATUSES[$fr->status] ?? $fr->status }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ __('مطلوب') }} {{ Money::format($fr->requested_amount, false) }}@if ($fr->approved_amount) {{ __('· موافق') }} {{ Money::format($fr->approved_amount, false) }}@endif · {{ Carbon::parse($fr->request_date)->format('Y-m-d') }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- التسليم ونقل الملكية --}}
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('التسليم والملكية') }}</h2>
                <dl class="mt-4 space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('التسليم') }}</dt><dd class="font-bold {{ $contract->delivery_date ? 'text-emerald-700' : 'text-slate-400' }}">{{ $contract->delivery_date ? Carbon::parse($contract->delivery_date)->format('Y-m-d') : __('لسه') }}</dd></div>
                    @if ($delivery)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('استلمها') }}</dt><dd class="font-semibold">{{ $delivery->received_by }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('سلّمها') }}</dt><dd class="font-semibold">{{ $delivery->delivered_by }}</dd></div>
                        @if ($delivery->delivery_notes) <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $delivery->delivery_notes }}</div> @endif
                    @endif
                    <div class="flex justify-between border-t border-slate-100 pt-2.5"><dt class="text-slate-500">{{ __('نقل الملكية') }}</dt><dd class="font-bold {{ $contract->ownership_transferred_at ? 'text-emerald-700' : 'text-slate-400' }}">{{ $contract->ownership_transferred_at ? Carbon::parse($contract->ownership_transferred_at)->format('Y-m-d') : __('لسه') }}</dd></div>
                    @if ($contract->inspection_due_date)
                        @php $insp = Carbon::parse($contract->inspection_due_date); @endphp
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('ميعاد الفحص') }}</dt><dd class="font-bold {{ $insp->lt($today->copy()->addDays(30)) ? 'text-rose-600' : '' }}">{{ $insp->format('Y-m-d') }}</dd></div>
                    @endif
                    @if ($contract->ownership_notes) <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $contract->ownership_notes }}</div> @endif
                </dl>
                @if ($open && (float) $contract->remaining_amount > 0 && ! $contract->ownership_transferred_at)
                    <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('الملكية عادةً مابتتنقلش غير بعد السداد الكامل أو بموافقة الإدارة (رهن لصالح المعرض).') }}</p>
                @endif
                <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-400">@if (! empty($contract->rep_name))<div class="mb-1 font-bold text-slate-600">🤝 {{ __('مندوب المبيعات:') }} {{ $contract->rep_name }}</div>@endif{{ __('اتعمل بواسطة') }} {{ $contract->user_name ?? '—' }}</div>
            </section>
        </div>
    </div>

    {{-- ═══ الشروط والتوقيعات ═══ --}}
    <section id="signatures" class="mt-6 scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-base font-extrabold text-slate-900">{{ __('الشروط والتوقيعات') }}</h2>
        @if ($contract->terms_ar || $contract->terms_en)
            <div class="mt-4 grid gap-4 {{ \App\Support\Lang::pick($contract, 'terms') && $contract->terms_en ? 'md:grid-cols-2' : '' }}">
                @if ($contract->terms_ar) <div class="rounded-xl bg-slate-50 p-4 text-sm leading-loose text-slate-700">{{ \App\Support\RichText::render(\App\Support\Lang::pick($contract, 'terms'), 'terms') }}</div> @endif
                @if ($contract->terms_en) <div class="rounded-xl bg-slate-50 p-4 text-sm leading-loose text-slate-700" dir="ltr">{{ \App\Support\RichText::render($contract->terms_en, 'terms') }}</div> @endif
            </div>
        @endif
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            @foreach (['customer' => __('توقيع المشتري'), 'seller' => __('توقيع البائع (المعرض)')] as $party => $label)
                @php $sig = Media::url($contract->{$party.'_signature'}); @endphp
                <div class="rounded-2xl p-4 ring-1 {{ $sig ? 'ring-emerald-200' : 'ring-amber-200' }}">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-bold text-slate-800">{{ $label }}</span>
                        <span class="text-xs font-bold {{ $sig ? 'text-emerald-600' : 'text-amber-600' }}">{{ $sig ? __('تم التوقيع ✓') : __('مستني التوقيع') }}</span>
                    </div>
                    @if ($sig) <img src="{{ $sig }}" alt="" class="h-32 w-full rounded-xl bg-slate-50 object-contain"> @endif
                    @if ($open)
                        <details class="mt-3" @if (! $sig) open @endif>
                            <summary class="cursor-pointer text-xs font-bold text-blue-600">{{ $sig ? __('إعادة التوقيع') : __('وقّع دلوقتي') }}</summary>
                            <form method="POST" action="{{ route('admin.sales-contracts.sign', [$model, $party]) }}" class="mt-3 space-y-3">
                                @csrf
                                @include('admin.partials.signature-pad', ['name' => 'signature_data', 'label' => $label])
                                <button class="w-full rounded-xl bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('حفظ التوقيع') }}</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    @if ($contract->notes)
        <p class="mt-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900"><span class="font-bold">{{ __('ملاحظات:') }}</span> {{ $contract->notes }}</p>
    @endif

    {{-- ═══ سجل العقد ═══ --}}
    @if ($history->isNotEmpty())
        @php
            $dots = ['sale_created' => 'bg-blue-500', 'sale_cancelled' => 'bg-slate-500', 'sale_cancel_requested' => 'bg-amber-500', 'sale_cancel_rejected' => 'bg-slate-400',
                     'sale_payment_deleted' => 'bg-rose-500', 'sale_payment_updated' => 'bg-amber-500', 'sale_delivered' => 'bg-emerald-500', 'sale_ownership_transferred' => 'bg-violet-500'];
        @endphp
        <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('سجل العقد') }}</h2>
            <ol class="relative space-y-4 border-s-2 border-slate-100 ps-6">
                @foreach ($history as $h)
                    @php $p = json_decode($h->properties ?? '{}', true) ?: []; @endphp
                    <li class="relative">
                        <span class="absolute -start-[31px] top-1 h-3 w-3 rounded-full ring-4 ring-white {{ $dots[$h->action] ?? 'bg-emerald-500' }}"></span>
                        <div class="text-sm font-bold text-slate-800">
                            {{ $h->description ?: $h->action }}
                            @if ($h->action === 'sale_payment_deleted' || $h->action === 'sale_payment_updated')
                                <span class="font-normal text-slate-500">({{ $p['reference'] ?? '' }} · {{ Money::format($p['amount'] ?? 0, false) }}@isset($p['new_amount']) ← {{ Money::format($p['new_amount'], false) }}@endisset)</span>
                            @elseif ($h->action === 'sale_cancel_requested' && ! empty($p['reason']))
                                <span class="font-normal text-slate-500">— {{ $p['reason'] }}</span>
                            @elseif ($h->action === 'sale_ownership_transferred' && ! empty($p['plate']))
                                <span class="font-normal text-slate-500">{{ __('— لوحة جديدة') }} {{ $p['plate'] }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-400">{{ Carbon::parse($h->created_at)->format('Y-m-d H:i') }} · {{ $h->user_name ?? __('العميل (رابط)') }}</div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- ═══════════════ المودالات ═══════════════ --}}
    @if (\App\Support\Cheques::table() && $canCheque && ! $isCancelled && (float) $contract->remaining_amount > 0)
        @php
            $covered = $cheques->whereIn('status', ['received', 'deposited'])->pluck('installment_id')->filter()->all();
            $chequeRows = $installments->where('is_paid', false)->reject(fn ($i) => in_array($i->id, $covered) || $i->status === 'cancelled')->values();
        @endphp
        <dialog id="chequeModal" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
            <form method="POST" action="{{ route('admin.sales-contracts.cheques.store', $model) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">{{ __('تسجيل شيكات مؤجلة') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('اكتب رقم أول شيك وهيكمّل الباقي بالترتيب — عدّل أي سطر أو امسح رقمه عشان يتخطّى.') }}</p>
                    </div>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    @error('cheques') <p class="rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700">{{ $message }}</p> @enderror
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">{{ __('البنك') }}</span><input name="bank_name" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400" placeholder="{{ __('مثلاً: بنك مسقط') }}"></label>
                        <label class="block"><span class="mb-1 block text-sm font-semibold text-slate-700">{{ __('رقم أول شيك') }}</span><input id="firstCheque" inputmode="numeric" dir="ltr" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-mono text-sm outline-none focus:border-blue-400" placeholder="000101"></label>
                    </div>
                    <div class="max-h-80 overflow-auto rounded-xl ring-1 ring-slate-200">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-slate-50 text-xs text-slate-500"><tr><th class="px-3 py-2 text-start">{{ __('القسط') }}</th><th class="px-2 py-2 text-start">{{ __('رقم الشيك') }}</th><th class="px-2 py-2 text-start">{{ __('تاريخ الشيك') }}</th><th class="px-3 py-2 text-start">{{ __('المبلغ') }}</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($chequeRows as $k => $ins)
                                    <tr>
                                        <td class="px-3 py-1.5 font-bold text-slate-600">{{ $ins->installment_sequence }}<input type="hidden" name="cheques[{{ $k }}][installment_id]" value="{{ $ins->id }}"></td>
                                        <td class="px-2 py-1.5"><input name="cheques[{{ $k }}][number]" data-chq-no dir="ltr" class="w-32 rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-sm"></td>
                                        <td class="px-2 py-1.5"><input type="date" name="cheques[{{ $k }}][due_date]" value="{{ Carbon::parse($ins->due_date)->format('Y-m-d') }}" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></td>
                                        <td class="px-3 py-1.5"><input type="number" step="0.001" min="0" name="cheques[{{ $k }}][amount]" value="{{ number_format((float) $ins->amount - (float) $ins->paid_amount, Money::decimals(), '.', '') }}" class="w-28 rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-3 py-1.5 text-slate-400">—</td>
                                        <td class="px-2 py-1.5"><input name="cheques[0][number]" data-chq-no dir="ltr" class="w-32 rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-sm"></td>
                                        <td class="px-2 py-1.5"><input type="date" name="cheques[0][due_date]" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></td>
                                        <td class="px-3 py-1.5"><input type="number" step="0.001" min="0" name="cheques[0][amount]" value="{{ number_format((float) $contract->remaining_amount, Money::decimals(), '.', '') }}" class="w-28 rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-60">{{ __('حفظ الشيكات') }}</button>
                </div>
            </form>
        </dialog>
        <script>
            // رقم أول شيك ← الباقي بالترتيب (مع الحفاظ على الأصفار في الأول)
            document.getElementById('firstCheque')?.addEventListener('input', (e) => {
                const v = e.target.value.trim(), m = v.match(/^(.*?)(\d+)$/);
                document.querySelectorAll('[data-chq-no]').forEach((el, i) => {
                    if (!v) { el.value = ''; return; }
                    el.value = m ? m[1] + String(BigInt(m[2]) + BigInt(i)).padStart(m[2].length, '0') : (i ? '' : v);
                });
            });
            @if ($errors->has('cheques')) document.getElementById('chequeModal')?.showModal(); @endif
        </script>
    @endif

    @php $dlg = 'm-auto w-[calc(100%-2rem)] rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm'; @endphp

    @if ($canPay)
        <dialog id="payModal" class="{{ $dlg }} max-w-lg">
            <form method="POST" action="{{ route('admin.sales-contracts.payments.store', $model) }}"
                  onsubmit="{{ __('const b = this.querySelector(\'[type=submit]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
                @csrf
                <input type="hidden" name="_modal" value="pay">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">{{ __('دفعة من العميل') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('الباقي:') }} {{ Money::format($contract->remaining_amount) }}</p>
                    </div>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('المبلغ') }} <span class="text-rose-500">*</span></label>
                        <input name="amount" type="number" step="0.001" min="0.001" max="{{ (float) $contract->remaining_amount }}" required value="{{ old('amount') }}"
                               class="w-full rounded-xl border px-3 py-3 text-lg font-bold outline-none focus:ring-4 {{ $errors->has('amount') ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-400 focus:ring-blue-50' }}">
                        @error('amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                            @if ($next)
                                <button type="button" data-fill="{{ number_format((float) $next->amount - (float) $next->paid_amount, Money::decimals(), '.', '') }}" class="rounded-full bg-blue-50 px-2.5 py-1 font-bold text-blue-700 hover:bg-blue-100">{{ __('قسط واحد') }}</button>
                            @endif
                            @if ($overdue->count() > 1)
                                <button type="button" data-fill="{{ number_format($overdueAmount, Money::decimals(), '.', '') }}" class="rounded-full bg-rose-50 px-2.5 py-1 font-bold text-rose-700 hover:bg-rose-100">{{ __('كل المتأخر (') }}{{ $overdue->count() }})</button>
                            @endif
                            <button type="button" data-fill="{{ number_format((float) $contract->remaining_amount, Money::decimals(), '.', '') }}" class="rounded-full bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700 hover:bg-emerald-100">{{ __('الباقي كله (سداد مبكر)') }}</button>
                        </div>
                        @if ($unpaid->isNotEmpty())
                            <p data-covers class="mt-2 hidden rounded-lg bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600"></p>
                        @endif
                    </div>
                    <div>
                        <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('طريقة الدفع') }} <span class="text-rose-500">*</span></div>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($paymentMethods as $id => $name)
                                <label class="cursor-pointer rounded-xl border border-slate-200 px-2 py-2.5 text-center text-sm font-bold text-slate-600 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                                    <input type="radio" name="payment_method_id" value="{{ $id }}" class="sr-only" required @checked((string) old('payment_method_id', $paymentMethods->keys()->first()) === (string) $id)>
                                    {{ $name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @include('admin.partials.field', ['model' => null, 'name' => 'payment_date', 'label' => __('تاريخ الدفع'), 'type' => 'date', 'required' => true, 'value' => old('payment_date', today()->format('Y-m-d'))])
                        @include('admin.partials.field', ['model' => null, 'name' => 'reference', 'label' => __('رقم التحويل / الشيك'), 'dir' => 'ltr'])
                    </div>
                    @include('admin.partials.field', ['model' => null, 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{{ __('تأكيد الدفع') }}</button>
                </div>
            </form>
        </dialog>
    @endif

    @if ($open)
        <dialog id="deliverModal" class="{{ $dlg }} max-w-md">
            <form method="POST" action="{{ route('admin.sales-contracts.deliver', $model) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf
                <input type="hidden" name="_modal" value="deliver">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('تسليم العربية') }}</h2>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    @if ((float) $contract->paid_amount <= 0)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('⚠️ العميل لسه مادفعش أي حاجة.') }}</p>
                    @endif
                    @if (! $contract->customer_signature)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('⚠️ العقد لسه مش متوقّع من المشتري.') }}</p>
                    @endif
                    @include('admin.partials.field', ['model' => null, 'name' => 'actual_delivery', 'label' => __('وقت التسليم'), 'type' => 'datetime-local', 'required' => true, 'value' => old('actual_delivery', now()->format('Y-m-d\TH:i'))])
                    @include('admin.partials.field', ['model' => null, 'name' => 'received_by', 'label' => __('المستلم'), 'required' => true, 'value' => old('received_by', $contract->customer_name)])
                    <div class="grid gap-4 sm:grid-cols-2">
                        @include('admin.partials.field', ['model' => null, 'name' => 'delivery_mileage', 'label' => __('العداد (كم)'), 'type' => 'number', 'min' => 0, 'value' => old('delivery_mileage', $contract->mileage)])
                        @include('admin.partials.field', ['model' => null, 'name' => 'delivery_location', 'label' => __('المكان'), 'value' => old('delivery_location', $contract->branch_name)])
                    </div>
                    @include('admin.partials.field', ['model' => null, 'name' => 'delivery_notes', 'label' => __('ملاحظات (حالة العربية، المفاتيح، الأوراق...)'), 'type' => 'textarea', 'rows' => 2])
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('تأكيد التسليم') }}</button>
                </div>
            </form>
        </dialog>

        <dialog id="transferModal" class="{{ $dlg }} max-w-md">
            <form method="POST" action="{{ route('admin.sales-contracts.transfer', $model) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf @method('PATCH')
                <input type="hidden" name="_modal" value="transfer">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('نقل الملكية') }}</h2>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    @if ((float) $contract->remaining_amount > 0)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('⚠️ لسه على العميل') }} {{ Money::format($contract->remaining_amount) }}{{ __('. اتأكد إن فيه رهن لصالح المعرض أو موافقة الإدارة.') }}</p>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        @include('admin.partials.field', ['model' => null, 'name' => 'ownership_transferred_at', 'label' => __('تاريخ النقل'), 'type' => 'date', 'required' => true, 'value' => old('ownership_transferred_at', $contract->ownership_transferred_at ? Carbon::parse($contract->ownership_transferred_at)->format('Y-m-d') : today()->format('Y-m-d'))])
                        @include('admin.partials.field', ['model' => null, 'name' => 'inspection_due_date', 'label' => __('ميعاد الفحص الجاي'), 'type' => 'date', 'value' => old('inspection_due_date', $contract->inspection_due_date ? Carbon::parse($contract->inspection_due_date)->format('Y-m-d') : today()->addYear()->format('Y-m-d'))])
                    </div>
                    @include('admin.partials.field', ['model' => null, 'name' => 'new_plate_number', 'label' => __('رقم اللوحة الجديد (لو اتغير)'), 'placeholder' => $contract->plate_number])
                    @include('admin.partials.field', ['model' => null, 'name' => 'ownership_notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2, 'value' => old('ownership_notes', $contract->ownership_notes)])
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-60">{{ __('حفظ') }}</button>
                </div>
            </form>
        </dialog>

        @if ($financeEnabled && $canPay && $financeCompanies->isNotEmpty())
            <dialog id="financeModal" class="{{ $dlg }} max-w-md">
                <form method="POST" action="{{ route('admin.finance-requests.store', $model) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                    @csrf
                    <input type="hidden" name="_modal" value="finance">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900">{{ __('طلب تمويل') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('البنك بيدفع الباقي على العميل (') }}{{ Money::format($contract->remaining_amount) }})</p>
                        </div>
                        <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        @include('admin.partials.field', ['model' => null, 'name' => 'finance_company_id', 'label' => __('شركة التمويل'), 'type' => 'select', 'options' => $financeCompanies, 'empty' => __('— اختار —'), 'required' => true])
                        <div class="grid gap-4 sm:grid-cols-2">
                            @include('admin.partials.field', ['model' => null, 'name' => 'requested_amount', 'label' => __('المبلغ المطلوب'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true, 'value' => old('requested_amount', number_format((float) $contract->remaining_amount, Money::decimals(), '.', ''))])
                            @include('admin.partials.field', ['model' => null, 'name' => 'installment_period', 'label' => __('المدة (شهور)'), 'type' => 'number', 'min' => 1, 'placeholder' => '60'])
                        </div>
                        @include('admin.partials.field', ['model' => null, 'name' => 'notes', 'label' => __('ملاحظات (راتب العميل، جهة العمل...)'), 'type' => 'textarea', 'rows' => 2])
                    </div>
                    <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                        <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{{ __('إرسال الطلب') }}</button>
                    </div>
                </form>
            </dialog>
        @endif

        @unless ($pendingCancel)
            <dialog id="cancelModal" class="{{ $dlg }} max-w-md">
                <form method="POST" action="{{ route('admin.sales-contracts.cancel', $model) }}" onsubmit="return confirm('{{ $isAdmin ? __('تأكيد إلغاء العقد؟') : __('إرسال طلب الإلغاء؟') }}')">
                    @csrf
                    <input type="hidden" name="_modal" value="cancel">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900">{{ $isAdmin ? __('إلغاء العقد') : __('طلب إلغاء العقد') }}</h2>
                            <p class="text-xs text-slate-500">{{ $isAdmin ? __('العربية هترجع متاحة والأقساط اللي مااتدفعتش هتتشال.') : __('الطلب هيروح للإدارة توافق عليه.') }}</p>
                        </div>
                        <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        @include('admin.partials.field', ['model' => null, 'name' => 'reason_ar', 'label' => __('السبب'), 'required' => true, 'list' => 'cancelReasons'])
                        <datalist id="cancelReasons">
                            <option value="العميل اتراجع عن الشراء"><option value="التمويل اترفض"><option value="العميل مش قادر يكمل الأقساط"><option value="عيب في العربية"><option value="اتفاق بين الطرفين">
                        </datalist>
                        <div class="rounded-xl bg-slate-50 p-4 text-sm">
                            <div class="flex justify-between"><span class="text-slate-500">{{ __('المدفوع من العميل') }}</span><b>{{ Money::format($contract->paid_amount) }}</b></div>
                        </div>
                        @if ((float) $contract->paid_amount > 0)
                            @include('admin.partials.field', ['model' => null, 'name' => 'penalty_amount', 'label' => __('غرامة / مصاريف بتتخصم'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'hint' => __('اللي هيرجع للعميل = المدفوع − الغرامة')])
                        @endif
                        @include('admin.partials.field', ['model' => null, 'name' => 'additional_notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])
                    </div>
                    <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                        <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('رجوع') }}</button>
                        <button type="submit" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700">{{ $isAdmin ? __('إلغاء العقد') : __('إرسال الطلب') }}</button>
                    </div>
                </form>
            </dialog>
        @endunless
    @endif

    @php
        $unpaidJs = $unpaid->values()->map(fn ($i) => ['n' => $i->installment_sequence, 'left' => round((float) $i->amount - (float) $i->paid_amount, 3)]);
        $openModal = in_array(request('open'), ['payModal', 'deliverModal', 'transferModal', 'cancelModal', 'financeModal'], true) ? request('open') : null;
        $errorModal = $errors->any() ? (['pay' => 'payModal', 'deliver' => 'deliverModal', 'transfer' => 'transferModal', 'cancel' => 'cancelModal', 'finance' => 'financeModal'][$modal] ?? null) : null;
    @endphp
    <script>
        (function () {
            document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => {
                b.closest('details')?.removeAttribute('open');
                document.getElementById(b.dataset.open)?.showModal();
            }));
            document.querySelectorAll('dialog').forEach((d) => {
                d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
                d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
            });
            document.addEventListener('click', (e) => {
                document.querySelectorAll('details[data-actions][open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
            });
            document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(b.dataset.copy); } catch (e) { return; }
                const l = b.querySelector('[data-copy-label]'); const t = l.textContent; l.textContent = @js(__('اتنسخ ✓')); setTimeout(() => { l.textContent = t; }, 1500);
            }));

            // معاينة: الدفعة دي هتغطي أنهي أقساط
            const pay = document.querySelector('#payModal [name=amount]'), covers = document.querySelector('[data-covers]');
            const unpaid = @json($unpaidJs);
            const preview = () => {
                if (!covers) return;
                let left = Number(pay.value || 0); const full = [], part = [];
                for (const i of unpaid) { if (left <= 0.0005) break; if (left + 0.0005 >= i.left) { full.push(i.n); left -= i.left; } else { part.push(i.n); left = 0; } }
                if (!full.length && !part.length) { covers.classList.add('hidden'); return; }
                covers.textContent = (full.length ? @js(__('هتقفل قسط ')) + full.join(@js(__('، '))) : '') + (part.length ? (full.length ? ' + ' : '') + @js(__('جزء من قسط ')) + part[0] : '');
                covers.classList.remove('hidden');
            };
            pay?.addEventListener('input', preview);
            document.querySelectorAll('[data-fill]').forEach((b) => b.addEventListener('click', () => { pay.value = b.dataset.fill; preview(); }));

            @if ($errorModal) document.getElementById(@json($errorModal))?.showModal(); @endif
            @if (! $errors->any() && $openModal) document.getElementById(@json($openModal))?.showModal(); @endif
        })();
    </script>
@endsection
