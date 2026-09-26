@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use App\Support\Media;
    use App\Http\Controllers\Admin\SalesContractController as SC;
    $editing = $contract->exists;
    $f = fn (array $o) => array_merge(['model' => $contract], $o);
    $dec = Money::decimals();
    $downPayment = $downPayment ?? null;
    $type = old('payment_type', $contract->payment_type ?? 'cash');
    $hasGuarantor = (bool) old('has_guarantor', $contract->has_guarantor);
    $scoreTone = ['emerald' => 'bg-emerald-50 text-emerald-700', 'blue' => 'bg-blue-50 text-blue-700', 'amber' => 'bg-amber-50 text-amber-700', 'rose' => 'bg-rose-50 text-rose-700', 'slate' => 'bg-slate-100 text-slate-500'];
    $methodDefault = old('down_payment_method_id', $downPayment->payment_method_id ?? $paymentMethods->keys()->first());
    $vehiclesJson = $vehicles->mapWithKeys(fn ($v) => [$v->id => [
        'title' => trim($v->brand_name.' '.$v->model_name.' '.$v->manufacturing_year),
        'code'  => $v->vehicle_code,
        'color' => $v->color,
        'plate' => $v->plate_number,
        'branch'=> $v->branch_name,
        'status'=> $v->status,
        'price' => (float) $v->selling_price,
        'offer' => (float) $v->discount_price,
        'img'   => Media::url(Media::list($v->images)[0] ?? null),
    ]]);
@endphp

@section('title', $editing ? __('تعديل عقد البيع ').$contract->contract_number : __('عقد بيع جديد'))
@section('subtitle', __('بيع عربية لعميل — كاش أو تقسيط'))

@section('content')
<form method="POST" action="{{ $editing ? route('admin.sales-contracts.update', $contract) : route('admin.sales-contracts.store') }}"
      class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-3" id="saleForm"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
            </div>
        @endif

        @unless ($editableMoney)
            <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                <b>{{ __('العقد عليه دفعات بعد المقدم') }}</b>{{ __('، فالعميل والعربية والمبالغ والأقساط مقفولة عشان الحسابات ماتبوظش. تقدر تعدّل التاريخ والضامن والشروط والتوقيعات. لو محتاج تغيّر المبالغ امسح الدفعات من صفحة المدفوعات الأول.') }}
            </div>
        @endunless

        {{-- ═══ 1) العميل والعربية ═══ --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">1</span> {{ __('العميل والعربية') }}</h2>

            @if ($editableMoney)
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="customer_id" class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                            <span>{{ __('المشتري') }} <span class="text-rose-500">*</span></span>
                            <button type="button" data-open="customerModal" class="text-xs font-bold text-blue-600 hover:underline">{{ __('+ عميل جديد') }}</button>
                        </label>
                        <select id="customer_id" name="customer_id" required class="w-full rounded-xl border {{ $errors->has('customer_id') ? 'border-rose-300' : 'border-slate-200' }} bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                            <option value="">{{ __('— اختار العميل —') }}</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" data-score="{{ $c->score['label'] }}{{ $c->score['score'] !== null ? ' ('.$c->score['score'].')' : '' }}" data-tone="{{ $scoreTone[$c->score['tone']] }}" data-hint="{{ $c->score['hint'] }}"
                                        @selected((string) old('customer_id', $contract->customer_id) === (string) $c->id)>{{ \App\Support\Lang::pick($c, 'name') }} — {{ $c->phone }}{{ $c->national_id ? ' — '.$c->national_id : '' }}</option>
                            @endforeach
                        </select>
                        <div id="customerScore" class="mt-2 hidden items-center gap-2 text-xs">
                            <span data-badge class="rounded-full px-2.5 py-1 font-bold"></span>
                            <span data-hint class="text-slate-500"></span>
                        </div>
                    </div>
                    <div>
                        <label for="vehicle_id" class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                            <span>{{ __('العربية') }} <span class="text-rose-500">*</span></span>
                            <span class="text-xs font-normal text-slate-400">{{ __('المتاحة والمحجوزة بس') }}</span>
                        </label>
                        <select id="vehicle_id" name="vehicle_id" required class="w-full rounded-xl border {{ $errors->has('vehicle_id') ? 'border-rose-300' : 'border-slate-200' }} bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                            <option value="">{{ __('— اختار العربية —') }}</option>
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}" @selected((string) old('vehicle_id', $contract->vehicle_id) === (string) $v->id)>
                                    {{ $v->brand_name }} {{ $v->model_name }} {{ $v->manufacturing_year }} — {{ $v->vehicle_code }}{{ $v->status === 'reserved' ? __(' (محجوزة)') : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div id="vehicleCard" class="mt-5 hidden items-center gap-4 rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200">
                    <div class="h-16 w-24 shrink-0 overflow-hidden rounded-xl bg-slate-200"><img data-v="img" alt="" class="hidden h-full w-full object-cover"></div>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-extrabold text-slate-900" data-v="title"></div>
                        <div class="mt-0.5 text-xs text-slate-500"><span class="font-mono" data-v="code"></span> · <span data-v="color"></span> · <span data-v="branch"></span></div>
                    </div>
                    <div class="text-end">
                        <div class="text-[11px] text-slate-400">{{ __('سعر العرض') }}</div>
                        <div class="font-extrabold text-slate-900" data-v="price"></div>
                        <button type="button" id="usePrice" class="text-xs font-bold text-blue-600 hover:underline">{{ __('استخدم السعر') }}</button>
                    </div>
                </div>
            @else
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 px-4 py-3"><div class="text-xs text-slate-500">{{ __('المشتري') }}</div><div class="font-bold">{{ $customers->firstWhere('id', $contract->customer_id)?->name_ar }}</div></div>
                    @php $cv = $vehicles->firstWhere('id', $contract->vehicle_id); @endphp
                    <div class="rounded-xl bg-slate-50 px-4 py-3"><div class="text-xs text-slate-500">{{ __('العربية') }}</div><div class="font-bold">{{ $cv ? $cv->brand_name.' '.$cv->model_name.' '.$cv->manufacturing_year.' — '.$cv->vehicle_code : '—' }}</div></div>
                </div>
            @endif
        </section>

        {{-- ═══ 2) السعر ═══ --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">2</span> {{ __('السعر') }}</h2>
            @if ($editableMoney)
                <div class="grid gap-5 sm:grid-cols-3">
                    @include('admin.partials.field', $f(['name' => 'sale_price', 'label' => __('سعر البيع'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true]))
                    @include('admin.partials.field', $f(['name' => 'discount_amount', 'label' => __('الخصم'), 'type' => 'number', 'step' => '0.001', 'min' => 0]))
                    @include('admin.partials.field', $f(['name' => 'tax_percent', 'label' => __('الضريبة %'), 'type' => 'number', 'step' => '0.01', 'min' => 0]))
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="text-slate-400">{{ __('خصم سريع:') }}</span>
                    @foreach ([1, 2, 3, 5] as $p)
                        <button type="button" data-discount="{{ $p }}" class="rounded-full bg-slate-100 px-2.5 py-1 font-bold text-slate-600 hover:bg-slate-200">{{ $p }}%</button>
                    @endforeach
                    <button type="button" data-discount="0" class="rounded-full px-2.5 py-1 font-bold text-rose-600 hover:bg-rose-50">{{ __('بدون') }}</button>
                </div>
            @else
                <div class="grid gap-3 text-sm sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 px-4 py-3"><div class="text-xs text-slate-500">{{ __('السعر') }}</div><div class="font-bold">{{ Money::format($contract->sale_price, false) }}</div></div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3"><div class="text-xs text-slate-500">{{ __('الخصم') }}</div><div class="font-bold">{{ Money::format($contract->discount_amount, false) }}</div></div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3"><div class="text-xs text-slate-500">{{ __('الضريبة') }}</div><div class="font-bold">{{ Money::format($contract->tax_amount, false) }}</div></div>
                    <div class="rounded-xl bg-slate-900 px-4 py-3 text-white"><div class="text-xs text-slate-400">{{ __('الإجمالي') }}</div><div class="font-extrabold">{{ Money::format($contract->total_amount, false) }}</div></div>
                </div>
            @endif
        </section>

        {{-- ═══ 3) طريقة السداد ═══ --}}
        @if ($editableMoney)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">3</span> {{ __('طريقة السداد') }}</h2>

                <div class="grid grid-cols-2 gap-3">
                    @foreach (['cash' => [__('كاش'), __('العميل بيدفع الكل (مرة واحدة أو على دفعات من غير جدول)')], 'installments' => [__('تقسيط'), __('مقدم + أقساط شهرية بتتولّد لوحدها')]] as $k => [$l, $d])
                        <label class="cursor-pointer rounded-2xl border-2 border-slate-200 p-4 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/60">
                            <input type="radio" name="payment_type" value="{{ $k }}" class="sr-only" @checked($type === $k)>
                            <div class="text-base font-extrabold text-slate-900">{{ $l }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $d }}</div>
                        </label>
                    @endforeach
                </div>

                <div class="mt-6 rounded-2xl bg-slate-50 p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="text-sm font-extrabold text-slate-800" data-down-label>{{ $type === 'installments' ? __('المقدم') : __('المدفوع وقت العقد') }}</div>
                        <div class="flex gap-1.5 text-xs" data-down-chips>
                            @foreach ([10, 20, 30, 50] as $p)
                                <button type="button" data-down="{{ $p }}" class="rounded-full bg-white px-2.5 py-1 font-bold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100">{{ $p }}%</button>
                            @endforeach
                            <button type="button" data-down="100" data-cash-only class="rounded-full bg-white px-2.5 py-1 font-bold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50">{{ __('الكل') }}</button>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        @include('admin.partials.field', ['model' => null, 'name' => 'down_payment', 'label' => __('المبلغ'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'value' => old('down_payment', (float) $contract->down_payment ?: '')])
                        @include('admin.partials.field', ['model' => null, 'name' => 'down_payment_date', 'label' => __('تاريخ الدفع'), 'type' => 'date', 'value' => old('down_payment_date', isset($downPayment->payment_date) ? \Illuminate\Support\Carbon::parse($downPayment->payment_date)->format('Y-m-d') : today()->format('Y-m-d'))])
                        @include('admin.partials.field', ['model' => null, 'name' => 'down_payment_reference', 'label' => __('رقم التحويل / الشيك'), 'dir' => 'ltr', 'value' => old('down_payment_reference', $downPayment->bank_reference ?? '')])
                    </div>
                    <div class="mt-4">
                        <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('طريقة الدفع') }}</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($paymentMethods as $id => $name)
                                <label class="cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                                    <input type="radio" name="down_payment_method_id" value="{{ $id }}" class="sr-only" @checked((string) $methodDefault === (string) $id)>
                                    {{ $name }}
                                </label>
                            @endforeach
                        </div>
                        @error('down_payment_method_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div data-installments class="mt-6 {{ $type === 'installments' ? '' : 'hidden' }}">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            @include('admin.partials.field', $f(['name' => 'installments_count', 'label' => __('عدد الأقساط'), 'type' => 'number', 'min' => 1, 'step' => '1']))
                            <div class="mt-2 flex flex-wrap gap-1 text-xs">
                                @foreach ([6, 12, 24, 36, 48, 60] as $n)
                                    <button type="button" data-count="{{ $n }}" class="rounded-md bg-slate-100 px-2 py-0.5 font-bold text-slate-600 hover:bg-slate-200">{{ $n }}</button>
                                @endforeach
                            </div>
                        </div>
                        @include('admin.partials.field', $f(['name' => 'installment_interval', 'label' => __('القسط'), 'type' => 'select', 'options' => SC::INTERVALS]))
                        @include('admin.partials.field', $f(['name' => 'first_due_date', 'label' => __('أول قسط يوم'), 'type' => 'date']))
                    </div>

                    <div class="mt-5 overflow-hidden rounded-2xl ring-1 ring-slate-200">
                        <div class="flex items-center justify-between bg-slate-50 px-4 py-3 text-sm">
                            <span class="font-extrabold text-slate-800">{{ __('معاينة جدول الأقساط') }}</span>
                            <span class="text-xs text-slate-500" data-out="schedule-summary"></span>
                        </div>
                        <table class="w-full text-sm">
                            <tbody data-out="schedule" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        {{-- ═══ 4) الضامن ═══ --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">{{ $editableMoney ? 4 : 3 }}</span> {{ __('الضامن') }}</h2>
                <label class="relative inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                    <input type="hidden" name="has_guarantor" value="0">
                    <input type="checkbox" name="has_guarantor" value="1" class="peer sr-only" @checked($hasGuarantor)>
                    <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-blue-600"></span>
                    <span class="absolute start-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:-translate-x-5"></span>
                    {{ __('فيه ضامن') }}
                </label>
            </div>
            <p data-guarantor-tip class="mt-2 hidden text-xs font-bold text-amber-600">{{ __('⚠️ تقييم العميل ضعيف — يُفضّل يكون فيه ضامن.') }}</p>
            <div data-guarantor class="mt-5 grid gap-4 sm:grid-cols-2 {{ $hasGuarantor ? '' : 'hidden' }}">
                @include('admin.partials.field', $f(['name' => 'guarantor_name', 'label' => __('اسم الضامن *')]))
                @include('admin.partials.field', $f(['name' => 'guarantor_phone', 'label' => __('تليفون الضامن *'), 'dir' => 'ltr']))
                @include('admin.partials.field', $f(['name' => 'guarantor_national_id', 'label' => __('الرقم المدني')]))
                @include('admin.partials.field', $f(['name' => 'guarantor_address', 'label' => __('العنوان')]))
            </div>
        </section>

        {{-- ═══ 5) الشروط ═══ --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">{{ $editableMoney ? 5 : 4 }}</span> {{ __('الشروط والأحكام') }}</h2>
                <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-bold" data-tabs>
                    <button type="button" data-tab="ar" class="rounded-lg bg-white px-3 py-1.5 text-slate-900 shadow-sm">{{ __('عربي') }}</button>
                    <button type="button" data-tab="en" class="rounded-lg px-3 py-1.5 text-slate-500">English</button>
                </div>
            </div>
            <div data-pane="ar">@include('admin.partials.field', $f(['name' => 'terms_ar', 'label' => __('الشروط بالعربي'), 'type' => 'rich']))</div>
            <div data-pane="en" class="hidden" dir="ltr">@include('admin.partials.field', $f(['name' => 'terms_en', 'label' => 'Terms (English)', 'type' => 'rich', 'dir' => 'ltr']))</div>
            <div class="mt-4">@include('admin.partials.field', $f(['name' => 'notes', 'label' => __('ملاحظات داخلية'), 'type' => 'textarea', 'rows' => 2]))</div>
        </section>

        {{-- ═══ 6) التوقيعات ═══ --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-1 flex items-center gap-2 text-base font-extrabold text-slate-900"><span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">{{ $editableMoney ? 6 : 5 }}</span> {{ __('التوقيعات') }}</h2>
            <p class="mb-5 text-xs text-slate-500">{{ __('اختياري دلوقتي — تقدر توقّع بعدين أو تبعت للعميل رابط يوقّع من موبايله.') }}</p>
            <div class="grid gap-5 sm:grid-cols-2">
                @include('admin.partials.signature-pad', ['name' => 'customer_signature_data', 'label' => __('توقيع المشتري'), 'existing' => Media::url($contract->customer_signature)])
                @include('admin.partials.signature-pad', ['name' => 'seller_signature_data', 'label' => __('توقيع البائع (المعرض)'), 'existing' => Media::url($contract->seller_signature)])
            </div>
        </section>
    </div>

    {{-- ═══ الجنب: بيانات العقد + الملخص ═══ --}}
    <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-base font-extrabold text-slate-900">{{ __('بيانات العقد') }}</h2>
            <div class="space-y-4">
                @include('admin.partials.field', $f(['name' => 'contract_number', 'label' => __('رقم العقد'), 'required' => true, 'dir' => 'ltr']))
                @include('admin.partials.field', $f(['name' => 'contract_date', 'label' => __('تاريخ العقد'), 'type' => 'date', 'required' => true]))
                @if (($reps ?? collect())->isNotEmpty())
                    @include('admin.partials.field', ['model' => null, 'name' => 'sales_rep_id', 'label' => __('مندوب المبيعات'), 'type' => 'select', 'options' => $reps,
                        'empty' => __('— اللي بيسجّل العقد —'), 'value' => old('sales_rep_id', $contract->sales_rep_id ?? ($contract->exists ? null : auth()->id())),
                        'hint' => __('عمولة البيعة بتتحسب للمندوب ده في المرتبات')])
                @endif
            </div>
        </section>

        @if ($editableMoney)
            <section class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('السعر') }}</dt><dd class="font-bold" data-out="price">—</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('− الخصم') }}</dt><dd class="font-bold text-rose-300" data-out="discount">—</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('+ الضريبة') }}</dt><dd class="font-bold" data-out="tax">—</dd></div>
                    <div class="flex justify-between border-t border-white/10 pt-3"><dt class="font-bold">{{ __('الإجمالي') }}</dt><dd class="text-xl font-extrabold" data-out="total">—</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400" data-down-label>{{ __('المدفوع وقت العقد') }}</dt><dd class="font-bold text-emerald-300" data-out="down">—</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('الباقي على العميل') }}</dt><dd class="font-bold text-amber-300" data-out="rest">—</dd></div>
                </dl>
                <div data-installments class="mt-4 rounded-xl bg-white/10 p-3 text-center {{ $type === 'installments' ? '' : 'hidden' }}">
                    <div class="text-xs text-slate-300">{{ __('القسط') }}</div>
                    <div class="text-2xl font-extrabold text-white" data-out="installment">—</div>
                    <div class="text-[11px] text-slate-400" data-out="installment-note"></div>
                </div>
                <p data-out="warn" class="mt-3 hidden rounded-lg bg-rose-500/20 px-3 py-2 text-xs font-bold text-rose-200"></p>
            </section>
        @endif

        <div class="flex gap-2">
            <a href="{{ $editing ? route('admin.sales-contracts.show', $contract) : route('admin.sales-contracts.index') }}" class="flex-1 rounded-xl bg-white py-3 text-center text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('إلغاء') }}</a>
            <button type="submit" data-save class="flex-[2] rounded-xl bg-blue-600 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">{{ $editing ? __('حفظ التعديلات') : __('إنشاء العقد') }}</button>
        </div>
        @unless ($editing)
            <p class="text-center text-xs text-slate-400">{{ __('العربية هتتعلّم "مباعة" وهتتشال من المتجر أوتوماتيك.') }}</p>
        @endunless
    </aside>
</form>

{{-- ═══ مودال عميل جديد ═══ --}}
@if ($editableMoney)
    <dialog id="customerModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form id="quickCustomer" data-url="{{ route('admin.sales-contracts.quick-customer') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900">{{ __('عميل جديد') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('البيانات الأساسية بس — تكمّل الباقي من ملفه بعدين.') }}</p>
                </div>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'name' => 'name_ar', 'label' => __('الاسم'), 'required' => true, 'idPrefix' => 'qc_', 'value' => ''])
                @include('admin.partials.field', ['model' => null, 'name' => 'phone', 'label' => __('التليفون'), 'required' => true, 'dir' => 'ltr', 'idPrefix' => 'qc_', 'value' => ''])
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'name' => 'national_id', 'label' => __('الرقم المدني'), 'idPrefix' => 'qc_', 'value' => ''])
                    @include('admin.partials.field', ['model' => null, 'name' => 'nationality', 'label' => __('الجنسية'), 'idPrefix' => 'qc_', 'value' => ''])
                </div>
                <p data-qc-error class="hidden rounded-xl bg-rose-50 px-3 py-2 text-sm font-bold text-rose-700"></p>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('إضافة واختياره') }}</button>
            </div>
        </form>
    </dialog>
@endif

<script>
(function () {
    const form = document.getElementById('saleForm');
    const q = (n) => form.querySelector(`[name="${n}"]`);
    const all = (s) => document.querySelectorAll(s);

    // ─── الشروط عربي/إنجليزي ───
    all('[data-tab]').forEach((b) => b.addEventListener('click', () => {
        all('[data-tab]').forEach((x) => { x.classList.toggle('bg-white', x === b); x.classList.toggle('shadow-sm', x === b); x.classList.toggle('text-slate-900', x === b); x.classList.toggle('text-slate-500', x !== b); });
        all('[data-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.pane !== b.dataset.tab));
    }));

    // ─── الضامن ───
    const gToggle = form.querySelector('input[type=checkbox][name=has_guarantor]');
    gToggle.addEventListener('change', () => form.querySelector('[data-guarantor]').classList.toggle('hidden', !gToggle.checked));

    @if ($editableMoney)
    const dec = {{ $dec }};
    const unit = 10 ** dec;
    const fmt = (n) => Number(n || 0).toLocaleString('en', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const round = (n) => Math.round(n * unit) / unit;
    const vehicles = @json($vehiclesJson);
    const out = (k) => document.querySelector(`[data-out="${k}"]`);

    // ─── تقييم العميل ───
    const customer = q('customer_id'), scoreBox = document.getElementById('customerScore'), gTip = document.querySelector('[data-guarantor-tip]');
    const showScore = () => {
        const o = customer.selectedOptions[0];
        if (!o || !o.value) { scoreBox.classList.add('hidden'); gTip.classList.add('hidden'); return; }
        const badge = scoreBox.querySelector('[data-badge]');
        badge.className = 'rounded-full px-2.5 py-1 font-bold ' + o.dataset.tone;
        badge.textContent = @js(__('التقييم: ')) + o.dataset.score;
        scoreBox.querySelector('[data-hint]').textContent = o.dataset.hint;
        scoreBox.classList.remove('hidden'); scoreBox.classList.add('flex');
        gTip.classList.toggle('hidden', !/rose|amber/.test(o.dataset.tone) || gToggle.checked);
    };
    customer.addEventListener('change', showScore);
    gToggle.addEventListener('change', showScore);
    showScore();

    // ─── العربية ───
    const vehicle = q('vehicle_id'), card = document.getElementById('vehicleCard');
    const showVehicle = (fill) => {
        const v = vehicles[vehicle.value];
        card.classList.toggle('hidden', !v); card.classList.toggle('flex', !!v);
        if (!v) return;
        card.querySelector('[data-v=title]').textContent = v.title;
        card.querySelector('[data-v=code]').textContent = v.code;
        card.querySelector('[data-v=color]').textContent = v.color || '—';
        card.querySelector('[data-v=branch]').textContent = v.branch || '—';
        card.querySelector('[data-v=price]').textContent = v.price ? fmt(v.price) : @js(__('مش متسعّرة'));
        const img = card.querySelector('[data-v=img]');
        img.classList.toggle('hidden', !v.img); if (v.img) img.src = v.img;
        if (fill && v.price) applyPrice(v);
    };
    const applyPrice = (v) => {
        q('sale_price').value = v.price;
        q('discount_amount').value = v.offer && v.offer < v.price ? round(v.price - v.offer) : 0;
        calc();
    };
    vehicle.addEventListener('change', () => showVehicle(true));
    document.getElementById('usePrice').addEventListener('click', () => { const v = vehicles[vehicle.value]; if (v) applyPrice(v); });
    showVehicle(false);

    // ─── الحسابات ───
    const types = form.querySelectorAll('[name=payment_type]');
    const isInst = () => form.querySelector('[name=payment_type]:checked')?.value === 'installments';
    const totals = () => {
        const price = Number(q('sale_price').value || 0), discount = Number(q('discount_amount').value || 0);
        const tax = round((price - discount) * Number(q('tax_percent').value || 0) / 100);
        return { price, discount, tax, total: round(price - discount + tax) };
    };
    const addMonths = (d, m) => { const r = new Date(d); const day = r.getDate(); r.setDate(1); r.setMonth(r.getMonth() + m); r.setDate(Math.min(day, new Date(r.getFullYear(), r.getMonth() + 1, 0).getDate())); return r; };
    const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

    const calc = () => {
        const t = totals(), down = Number(q('down_payment').value || 0), rest = round(t.total - down), inst = isInst();
        out('price').textContent = fmt(t.price); out('discount').textContent = fmt(t.discount);
        out('tax').textContent = fmt(t.tax); out('total').textContent = fmt(t.total);
        out('down').textContent = fmt(down); out('rest').textContent = fmt(Math.max(rest, 0));
        all('[data-installments]').forEach((e) => e.classList.toggle('hidden', !inst));
        all('[data-down-label]').forEach((e) => e.textContent = inst ? @js(__('المقدم')) : @js(__('المدفوع وقت العقد')));
        form.querySelectorAll('[data-cash-only]').forEach((e) => e.classList.toggle('hidden', inst));

        const warn = out('warn'); let msg = '';
        if (t.discount > t.price) msg = @js(__('الخصم أكبر من السعر'));
        else if (down > t.total) msg = @js(__('المدفوع أكبر من الإجمالي'));
        else if (inst && rest <= 0) msg = @js(__('المقدم مغطي العقد كله — اختار كاش'));
        warn.textContent = msg; warn.classList.toggle('hidden', !msg);

        if (!inst) return;
        const n = Math.max(1, parseInt(q('installments_count').value || 1, 10)), every = parseInt(q('installment_interval').value || 1, 10);
        const units = Math.max(0, Math.round(rest * unit)), base = Math.floor(units / n), last = units - base * (n - 1);
        out('installment').textContent = fmt(base / unit);
        out('installment-note').textContent = n + @js(__(' قسط')) + (last !== base ? @js(__(' · الأخير ')) + fmt(last / unit) : '');

        const first = q('first_due_date').value ? new Date(q('first_due_date').value + 'T00:00:00') : null;
        const rows = [];
        const show = n <= 6 ? [...Array(n).keys()] : [0, 1, 2, -1, n - 1];
        show.forEach((i) => {
            if (i === -1) { rows.push(`<tr><td colspan="3" class="px-4 py-1.5 text-center text-xs text-slate-400">… ${n - 4} قسط كمان …</td></tr>`); return; }
            const d = first ? ymd(addMonths(first, i * every)) : '—';
            rows.push(`<tr><td class="px-4 py-2 font-bold text-slate-700">${i + 1}</td><td class="px-4 py-2 text-slate-500">${d}</td><td class="px-4 py-2 text-end font-bold">${fmt((i === n - 1 ? last : base) / unit)}</td></tr>`);
        });
        out('schedule').innerHTML = rows.join('');
        const end = first ? ymd(addMonths(first, (n - 1) * every)) : '—';
        out('schedule-summary').textContent = `${fmt(rest)} على ${n} قسط · آخر قسط ${end}`;
    };

    ['sale_price', 'discount_amount', 'tax_percent', 'down_payment', 'installments_count', 'installment_interval', 'first_due_date']
        .forEach((n) => { q(n)?.addEventListener('input', calc); q(n)?.addEventListener('change', calc); });
    types.forEach((r) => r.addEventListener('change', calc));

    all('[data-discount]').forEach((b) => b.addEventListener('click', () => {
        q('discount_amount').value = round(Number(q('sale_price').value || 0) * Number(b.dataset.discount) / 100); calc();
    }));
    all('[data-down]').forEach((b) => b.addEventListener('click', () => {
        q('down_payment').value = round(totals().total * Number(b.dataset.down) / 100); calc();
    }));
    all('[data-count]').forEach((b) => b.addEventListener('click', () => { q('installments_count').value = b.dataset.count; calc(); }));
    q('contract_date').addEventListener('change', () => {
        if (!q('first_due_date').value && q('contract_date').value) { q('first_due_date').value = ymd(addMonths(new Date(q('contract_date').value + 'T00:00:00'), 1)); calc(); }
    });
    calc();

    // ─── مودال العميل الجديد ───
    const modal = document.getElementById('customerModal');
    all('[data-open]').forEach((b) => b.addEventListener('click', () => document.getElementById(b.dataset.open)?.showModal()));
    modal.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.close(); });

    const qc = document.getElementById('quickCustomer'), qcErr = qc.querySelector('[data-qc-error]');
    qc.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = qc.querySelector('[type=submit]'); btn.disabled = true; qcErr.classList.add('hidden');
        try {
            const res = await fetch(qc.dataset.url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: new FormData(qc),
            });
            const data = await res.json();
            if (!res.ok) { qcErr.textContent = Object.values(data.errors || {}).flat()[0] || data.message || @js(__('حصلت مشكلة')); qcErr.classList.remove('hidden'); return; }
            const opt = new Option(data.label, data.id, true, true);
            opt.dataset.score = @js(__('جديد')); opt.dataset.tone = 'bg-slate-100 text-slate-500'; opt.dataset.hint = @js(__('عميل لسه متضاف'));
            customer.add(opt); customer.value = data.id; customer.dispatchEvent(new Event('change'));
            qc.reset(); modal.close();
        } catch (err) {
            qcErr.textContent = @js(__('مفيش اتصال، جرّب تاني')); qcErr.classList.remove('hidden');
        } finally { btn.disabled = false; }
    });
    @endif
})();
</script>
@endsection
