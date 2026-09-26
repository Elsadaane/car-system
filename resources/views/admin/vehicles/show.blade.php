@extends('admin.layouts.app')

@section('title', $car->brand_name.' '.$car->model_name.' '.$car->manufacturing_year)
@section('subtitle', $car->vehicle_code.($car->plate_number ? __(' · لوحة ').$car->plate_number : ''))

@php
    $user = auth()->user();
    $isAdmin = $user->canSeeVehicleCost();
    $dec = (int) config('laroville.decimals', 3);
    $currency = config('laroville.currency');
    $money = fn ($v) => number_format((float) $v, $dec).' '.$currency;
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d') : '—';

    $statusStyle = [
        'available' => 'bg-emerald-500', 'reserved' => 'bg-amber-500', 'sold' => 'bg-blue-600',
        'maintenance' => 'bg-orange-500', 'damaged' => 'bg-rose-600', 'returned' => 'bg-slate-500',
    ];
    $license = $vehicle->license_expiry_date ? \Illuminate\Support\Carbon::parse($vehicle->license_expiry_date) : null;
    $salePrice = $vehicle->discount_price ?: $vehicle->selling_price;

    $sections = collect([
        'details' => __('البيانات'),
        'dealer'  => $dealerContracts->isNotEmpty() ? __('عقد التفويض') : null,
        'sales'   => $salesContracts->isNotEmpty() ? __('عقد البيع') : null,
        'drives'  => __('تجارب القيادة'),
        'history' => ($transfers->isNotEmpty() || $returns->isNotEmpty() || $invoice) ? __('السجل') : null,
        'finance' => __('الملخص المالي'),
    ])->filter();
@endphp

@section('actions')
    @if (in_array($vehicle->status, ['available', 'reserved'], true))
        <a href="{{ route('admin.sales-contracts.create', ['vehicle' => $vehicle->id]) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 print:hidden">{{ __('بيع العربية') }}</a>
    @endif
    <a href="{{ route('admin.vehicles.edit', $vehicle) }}"
       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
        {{ __('تعديل') }}
    </a>
    @unless (in_array($vehicle->status, ['sold', 'returned'], true))
        <form method="POST" action="{{ route('admin.vehicles.publish', $vehicle) }}">
            @csrf @method('PATCH')
            <button class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold ring-1 {{ $vehicle->is_published ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50' }}">
                <span class="h-2 w-2 rounded-full {{ $vehicle->is_published ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                {{ $vehicle->is_published ? __('منشورة في المتجر') : __('انشر في المتجر') }}
            </button>
        </form>
    @endunless
    @if ($vehicle->is_published && \Illuminate\Support\Facades\Route::has('storefront.vehicles.show'))
        <a href="{{ route('storefront.vehicles.show', $vehicle) }}" target="_blank" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('عرض في المتجر ↗') }}</a>
    @endif
    <button type="button" onclick="window.print()" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 print:hidden">{{ __('طباعة') }}</button>
@endsection

@section('content')

    {{-- ═══════ تنقّل سريع ═══════ --}}
    <nav class="sticky top-16 z-10 -mx-4 mb-5 flex gap-1 overflow-x-auto bg-slate-100/90 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6 print:hidden">
        @foreach ($sections as $id => $label)
            <a href="#{{ $id }}" class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-white hover:text-slate-900">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- ═══════ الصور + الملخص ═══════ --}}
    <div id="details" class="grid scroll-mt-32 gap-6 lg:grid-cols-5">
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-3">
            <div class="relative aspect-[16/10] bg-slate-100">
                @if ($images)
                    <img id="mainImage" src="{{ $images[0] }}" alt="" class="h-full w-full object-cover">
                @else
                    <div class="grid h-full place-items-center text-slate-300">
                        <div class="text-center">
                            <svg class="mx-auto h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17V12l1.8-4.6A2 2 0 0 1 8.7 6h6.6a2 2 0 0 1 1.9 1.4L19 12v5"/><path d="M3 17h18v2.5H3z"/></svg>
                            <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="mt-2 inline-block text-sm font-bold text-blue-600 hover:underline">{{ __('ضيف صور') }}</a>
                        </div>
                    </div>
                @endif
                <span class="absolute start-4 top-4 rounded-full {{ $statusStyle[$vehicle->status] ?? 'bg-slate-500' }} px-3 py-1 text-xs font-bold text-white shadow">{{ $statuses[$vehicle->status] ?? $vehicle->status }}</span>
                @if ($vehicle->is_featured)
                    <span class="absolute end-4 top-4 rounded-full bg-amber-400 px-3 py-1 text-xs font-bold text-amber-950 shadow">{{ __('⭐ مميزة') }}</span>
                @endif
            </div>
            @if (count($images) > 1)
                <div class="flex gap-2 overflow-x-auto p-3">
                    @foreach ($images as $i => $img)
                        <button type="button" data-thumb="{{ $img }}"
                                class="h-16 w-24 shrink-0 overflow-hidden rounded-lg ring-2 transition {{ $i === 0 ? 'ring-blue-600' : 'ring-transparent opacity-70 hover:opacity-100' }}">
                            <img src="{{ $img }}" alt="" class="h-full w-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="flex flex-col rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <div class="flex items-center gap-3">
                @if ($logo = \App\Support\Media::url($car->brand_logo))
                    <img src="{{ $logo }}" alt="" class="h-10 w-10 rounded-xl bg-slate-50 object-contain p-1 ring-1 ring-slate-100">
                @endif
                <div>
                    <div class="text-lg font-extrabold text-slate-900">{{ $car->brand_name }} {{ $car->model_name }}</div>
                    <div class="text-sm text-slate-500">{{ $vehicle->manufacturing_year }} · {{ $vehicle->color }}</div>
                </div>
            </div>

            <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                <div class="text-xs text-slate-500">{{ __('سعر البيع') }}</div>
                @if ($vehicle->price_on_request)
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ __('عند الطلب') }}</div>
                @elseif ($salePrice)
                    <div class="mt-1 text-3xl font-extrabold text-slate-900">{{ $money($salePrice) }}</div>
                    @if ($vehicle->discount_price)
                        <div class="text-sm text-slate-400 line-through">{{ $money($vehicle->selling_price) }}</div>
                    @endif
                @else
                    <div class="mt-1 text-sm font-bold text-amber-600">{{ __('لسه متحددش سعر') }}</div>
                @endif
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                @foreach ([
                    [__('العداد'), $vehicle->mileage ? number_format($vehicle->mileage).__(' كم') : __('0 كم')],
                    [__('ناقل الحركة'), $specs['transmission'] ?? '—'],
                    [__('الوقود'), $specs['fuel'] ?? '—'],
                ] as [$l, $v])
                    <div class="rounded-xl bg-slate-50 px-2 py-3">
                        <div class="truncate text-sm font-extrabold text-slate-800">{{ $v }}</div>
                        <div class="text-[11px] text-slate-500">{{ $l }}</div>
                    </div>
                @endforeach
            </div>

            <dl class="mt-5 space-y-2.5 text-sm">
                @if ($car->branch_name)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الفرع') }}</dt><dd class="font-bold text-slate-800">{{ $car->branch_name }}</dd></div>
                @endif
                @if ($car->source_name)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('جهة الاستيراد') }}</dt><dd class="font-bold text-slate-800">{{ $car->source_name }}</dd></div>
                @endif
                @if ($isAdmin && $car->supplier_name)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('المورد') }}</dt><dd><a href="{{ route('admin.suppliers.show', $vehicle->supplier_id) }}" class="font-bold text-blue-700 hover:underline">{{ $car->supplier_name }}</a></dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('في المتجر') }}</dt>
                    <dd class="font-bold {{ $vehicle->is_published ? 'text-emerald-600' : 'text-slate-400' }}">{{ $vehicle->is_published ? __('منشورة · ').number_format((int) $vehicle->views_count).__(' مشاهدة') : __('مش منشورة') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('اتسجلت') }}</dt><dd class="font-bold text-slate-800">{{ $vehicle->created_at?->format('Y-m-d') }}</dd></div>
            </dl>

            @if ($license && $license->lte(today()->addDays(30)))
                <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-bold text-amber-800 ring-1 ring-amber-200">
                    {{ __('الرخصة') }} {{ $license->isPast() ? __('انتهت في') : __('هتنتهي في') }} {{ $license->format('Y-m-d') }}
                </div>
            @endif
        </section>
    </div>

    {{-- ═══════ البيانات التفصيلية ═══════ --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('معلومات السيارة') }}</h2>
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-3">
                @foreach ([
                    __('كود السيارة')      => $vehicle->vehicle_code,
                    __('رقم اللوحة')       => $vehicle->plate_number ?: __('بدون لوحة'),
                    __('رقم الشاسيه')      => $vehicle->chassis_number,
                    __('رقم المحرك')       => $vehicle->engine_number,
                    __('سنة الصنع')        => $vehicle->manufacturing_year,
                    __('اللون')            => $vehicle->color,
                    __('عدد السلندرات')    => $vehicle->cylinders,
                    __('سعة المحرك')       => $specs['engine'] ?? null,
                    __('نظام الدفع')       => $specs['drive'] ?? null,
                    __('المقاعد')          => $specs['seats'] ?? null,
                    __('الأبواب')          => $specs['doors'] ?? null,
                    'الكيلومتر الحالي' => number_format((int) $vehicle->mileage).__(' كم'),
                    __('الأميال')          => number_format((int) $vehicle->mileage * 0.621371).__(' ميل'),
                    __('انتهاء الرخصة')    => $license?->format('Y-m-d'),
                    __('نوع الهيكل')       => \App\Http\Controllers\Admin\CatalogController::BODY_TYPES[$car->body_type] ?? null,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 {{ in_array($label, [__('رقم الشاسيه'), __('رقم المحرك'), __('كود السيارة')], true) ? 'font-mono text-sm' : '' }}">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($vehicle->description_ar)
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <div class="text-xs text-slate-500">{{ __('الوصف') }}</div>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ \App\Support\Lang::pick($vehicle, 'description') }}</p>
                </div>
            @endif
            @if ($vehicle->notes)
                <div class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><span class="font-bold">{{ __('ملاحظات داخلية:') }}</span> {{ $vehicle->notes }}</div>
            @endif
        </section>

        @if ($isAdmin)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-5 flex items-center justify-between text-base font-extrabold text-slate-900">
                    {{ __('التكلفة والربح') }} <span class="rounded-md bg-amber-50 px-1.5 text-[10px] font-bold text-amber-700">{{ __('للأدمن') }}</span>
                </h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('سعر الشراء') }}</dt><dd class="font-bold">{{ $money($vehicle->purchase_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الضريبة (') }}{{ (float) $vehicle->tax_percent }}%)</dt><dd class="font-bold">{{ $money($vehicle->tax_amount) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="font-bold text-slate-700">{{ __('إجمالي التكلفة') }}</dt><dd class="font-extrabold text-slate-900">{{ $money($finance['cost']) }}</dd></div>
                </dl>
                @php $profit = $finance['profit'] ?? $finance['expected_profit']; @endphp
                @if ($profit !== null)
                    <div class="mt-5 rounded-2xl p-4 {{ $profit >= 0 ? 'bg-emerald-50' : 'bg-rose-50' }}">
                        <div class="text-xs {{ $profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $finance['profit'] !== null ? __('الربح الفعلي من البيعة') : __('الربح المتوقع بالسعر الحالي') }}</div>
                        <div class="mt-1 text-2xl font-extrabold {{ $profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $money($profit) }}</div>
                        @if ($finance['cost'] > 0)
                            <div class="text-xs {{ $profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ round($profit / $finance['cost'] * 100, 1) }}{{ __('% من التكلفة') }}</div>
                        @endif
                    </div>
                @endif
                @if ($invoice)
                    <a href="{{ route('admin.suppliers.show', $vehicle->supplier_id) }}#invoices" class="mt-4 flex justify-between rounded-xl bg-slate-50 px-3 py-2.5 text-xs hover:bg-slate-100">
                        <span class="text-slate-500">{{ __('فاتورة الشراء') }}</span>
                        <span class="font-bold text-slate-800">{{ $invoice->invoice_number }} · {{ \App\Models\SupplierInvoice::STATUSES[$invoice->status] }}</span>
                    </a>
                @endif
            </section>
        @endif
    </div>

    {{-- ═══════ الفحوصات والشركاء ═══════ --}}
    @php
        $IC = \App\Http\Controllers\Admin\InspectionController::class;
        $canEditVehicle = \App\Support\Access::can('vehicles.edit');
        $canPartners = \App\Support\Partners::ready() && \App\Support\Access::can('partners.edit');
        $soldAlready = ($vehicle->status ?? null) === 'sold';
    @endphp
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @if (\Illuminate\Support\Facades\Schema::hasTable('vehicle_inspections'))
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-base font-extrabold text-slate-900">🔍 {{ __('الفحوصات') }} ({{ $inspections->count() }})</h2>
                    @if ($canEditVehicle)<a href="{{ route('admin.inspections.create', $vehicle->id) }}" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">＋ {{ __('فحص جديد') }}</a>@endif
                </div>
                @forelse ($inspections as $ins)
                    <a href="{{ route('admin.inspections.show', $ins->id) }}" class="flex items-center justify-between gap-3 border-b border-slate-50 px-6 py-3 text-sm hover:bg-slate-50">
                        <div><div class="font-bold text-slate-800">{{ __($IC::TYPES[$ins->type] ?? $ins->type) }}</div><div class="text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($ins->inspected_at)->format('Y-m-d') }} · {{ $ins->inspector ?: '—' }}@if ($ins->mileage !== null) · {{ number_format($ins->mileage) }} {{ __('كم') }}@endif</div></div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $IC::STATES[$ins->result][1] ?? '' }}">{{ __($IC::STATES[$ins->result][0] ?? $ins->result) }}</span>
                    </a>
                @empty
                    <p class="px-6 py-6 text-center text-sm text-slate-400">{{ __('اعمل فحص استلام أول ما العربية تدخل المعرض — بيحميك لو حصل خلاف على حالتها.') }}</p>
                @endforelse
            </section>
        @endif

        @if (\App\Support\Partners::ready())
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-base font-extrabold text-slate-900">🤝 {{ __('الشركاء في العربية') }}</h2>
                    @if ($canPartners && ! $soldAlready)<button type="button" onclick="document.getElementById('vpModal').showModal()" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">＋ {{ __('إضافة شريك') }}</button>@endif
                </div>
                @forelse ($vehiclePartners as $vp)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-50 px-6 py-3 text-sm">
                        <div><a href="{{ route('admin.partners.show', $vp->partner_id) }}" class="font-bold text-slate-800 hover:text-blue-700">{{ $vp->name }}</a><div class="text-xs text-slate-400">{{ __('رأس مال') }} {{ \App\Support\Money::format($vp->capital, false) }}@if ($vp->notes) · {{ $vp->notes }}@endif</div></div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-extrabold text-violet-700">{{ (float) $vp->profit_percent }}% {{ __('من الربح') }}</span>
                            @if ($canPartners && ! $soldAlready)
                                <form method="POST" action="{{ route('admin.vehicles.partners.destroy', [$vehicle->id, $vp->partner_id]) }}" onsubmit="return confirm(@js(__('شيل الشريك من العربية؟')))">@csrf @method('DELETE')<button class="text-xs text-rose-500">✕</button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-6 text-center text-sm text-slate-400">{{ __('العربية مملوكة للمعرض بالكامل. لو فيه شريك داخل فيها بفلوس، ضيفه هنا بنسبته من الربح.') }}</p>
                @endforelse
                @if ($vehiclePartners->isNotEmpty())
                    <div class="bg-slate-50 px-6 py-2 text-xs text-slate-500">{{ __('نصيب المعرض من الربح:') }} <b>{{ 100 - (float) $vehiclePartners->sum('profit_percent') }}%</b></div>
                @endif
            </section>
        @endif
    </div>

    @if (\App\Support\Partners::ready() && $canPartners && ! $soldAlready)
        <dialog id="vpModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
            <form method="POST" action="{{ route('admin.vehicles.partners.store', $vehicle->id) }}" class="space-y-4 p-6">
                @csrf
                <input type="hidden" name="_modal" value="vp">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('شريك في العربية') }}</h2>
                @if ($allPartners->isNotEmpty())
                    @include('admin.partials.field', ['model' => null, 'name' => 'partner_id', 'label' => __('الشريك'), 'type' => 'select', 'options' => $allPartners, 'empty' => __('— شريك جديد (اكتب اسمه تحت) —')])
                @endif
                <div class="grid gap-3 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'name' => 'new_name', 'label' => __('اسم شريك جديد')])
                    @include('admin.partials.field', ['model' => null, 'name' => 'new_phone', 'label' => __('تليفونه'), 'dir' => 'ltr'])
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'name' => 'capital', 'label' => __('رأس ماله في العربية'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                    @include('admin.partials.field', ['model' => null, 'name' => 'profit_percent', 'label' => __('نسبته من الربح %'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true])
                </div>
                @include('admin.partials.field', ['model' => null, 'name' => 'notes', 'label' => __('ملاحظات')])
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button>
                    <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button>
                </div>
            </form>
        </dialog>
        @if ($errors->any() && old('_modal') === 'vp') <script>document.getElementById('vpModal').showModal();</script> @endif
    @endif

    {{-- ═══════ الرخصة وحالة العربية ═══════ --}}
    @php
        $docs = collect(['license_front' => __('الرخصة من الأمام'), 'license_back' => __('الرخصة من الخلف')])
            ->map(fn ($l, $c) => [$l, \App\Support\Media::url($vehicle->{$c} ?? null)])->filter(fn ($x) => $x[1]);
        $cond = collect(['before_images' => __('حالتها السابقة (وقت الاستلام)'), 'after_images' => __('حالتها الحالية')])
            ->map(fn ($l, $c) => [$l, array_map([\App\Support\Media::class, 'url'], \App\Support\Media::list($vehicle->{$c} ?? null))])->filter(fn ($x) => $x[1]);
    @endphp
    @if ($docs->isNotEmpty() || $cond->isNotEmpty())
        <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-base font-extrabold text-slate-900">🪪 {{ __('الرخصة وحالة العربية') }}</h2>
            <div class="grid gap-6 lg:grid-cols-3">
                @if ($docs->isNotEmpty())
                    <div>
                        <div class="mb-2 text-xs font-bold text-slate-500">{{ __('الرخصة') }}</div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($docs as [$l, $u])
                                <a href="{{ $u }}" target="_blank" title="{{ $l }}"><img src="{{ $u }}" alt="{{ $l }}" class="aspect-[4/3] w-full rounded-xl object-cover ring-1 ring-slate-200"></a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @foreach ($cond as [$l, $urls])
                    <div>
                        <div class="mb-2 text-xs font-bold text-slate-500">{{ $l }}</div>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($urls as $u)
                                <a href="{{ $u }}" target="_blank"><img src="{{ $u }}" alt="" class="aspect-square w-full rounded-lg object-cover ring-1 ring-slate-200"></a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══════ عقود التفويض ═══════ --}}
    @foreach ($dealerContracts as $i => $dc)
        @php $pays = $dealerPayments[$dc->id] ?? collect(); @endphp
        <section @if ($i === 0) id="dealer" @endif class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">{{ __('عقد تفويض بيع المركبة') }}</h2>
                    <div class="text-xs text-slate-500"><span class="font-mono">{{ $dc->contract_number }}</span> · {{ $date($dc->contract_date) }} {{ __('· بواسطة') }} {{ $dc->user_name ?? '—' }}</div>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $dc->status_name ?? '—' }}</span>
            </div>
            <div class="grid gap-6 p-6 lg:grid-cols-3">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('المورد') }}</dt><dd class="font-bold">{{ $dc->supplier_name }} <span class="block text-end text-xs font-normal text-slate-400" dir="ltr">{{ $dc->supplier_phone }}</span></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('السعر المتفق عليه') }}</dt><dd class="font-bold">{{ $money($dc->agreed_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('العمولة') }}</dt><dd class="font-bold">{{ $money($dc->commission_amount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الضريبة (') }}{{ (float) $dc->tax_percent }}%)</dt><dd class="font-bold">{{ $money($dc->tax_amount) }}</dd></div>
                </dl>
                <div class="lg:col-span-2">
                    @include('admin.vehicles.partials.payments', ['total' => $dc->total_amount, 'paid' => $dc->paid_amount, 'remaining' => $dc->remaining_amount, 'payments' => $pays, 'money' => $money, 'date' => $date, 'emptyText' => __('لا توجد مدفوعات لعقد التفويض')])
                </div>
            </div>
        </section>
    @endforeach

    {{-- ═══════ عقود البيع ═══════ --}}
    @foreach ($salesContracts as $i => $sc)
        @php $pays = $salesPayments[$sc->id] ?? collect(); @endphp
        <section @if ($i === 0) id="sales" @endif class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">{{ __('عقد البيع') }}</h2>
                    <div class="text-xs text-slate-500"><span class="font-mono">{{ $sc->contract_number }}</span> · {{ $date($sc->contract_date) }} {{ __('· بواسطة') }} {{ $sc->user_name ?? '—' }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $sc->status_name ?? '—' }}</span>
                    <a href="{{ route('admin.sales-contracts.show', $sc->id) }}" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 print:hidden">{{ __('فتح العقد') }}</a>
                </div>
            </div>
            <div class="grid gap-6 p-6 lg:grid-cols-3">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('العميل') }}</dt><dd class="font-bold">{{ $sc->customer_name }} <span class="block text-end text-xs font-normal text-slate-400" dir="ltr">{{ $sc->customer_phone }}</span></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('سعر البيع') }}</dt><dd class="font-bold">{{ $money($sc->sale_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الضريبة (') }}{{ (float) $sc->tax_percent }}%)</dt><dd class="font-bold">{{ $money($sc->tax_amount) }}</dd></div>
                    @if ($sc->delivery_date)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('التسليم') }}</dt><dd class="font-bold">{{ $date($sc->delivery_date) }}</dd></div>
                    @endif
                    @if ($sc->customer_phone)
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $sc->customer_phone) }}" target="_blank" rel="noopener" class="block rounded-xl bg-emerald-500 py-2 text-center text-sm font-bold text-white hover:bg-emerald-600 print:hidden">{{ __('واتساب العميل') }}</a>
                    @endif
                </dl>
                <div class="lg:col-span-2">
                    @include('admin.vehicles.partials.payments', ['total' => $sc->total_amount, 'paid' => $sc->paid_amount, 'remaining' => $sc->remaining_amount, 'payments' => $pays, 'money' => $money, 'date' => $date, 'emptyText' => __('لا توجد مدفوعات لعقد البيع')])
                </div>
            </div>
        </section>
    @endforeach

    {{-- ═══════ تجارب القيادة ═══════ --}}
    <section id="drives" class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('تجارب القيادة') }} <span class="text-sm font-normal text-slate-400">({{ $testDrives->count() }})</span></h2>
        </div>
        @if ($testDrives->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-slate-400">{{ __('لا توجد تجارب قيادة لهذه المركبة') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr><th class="px-6 py-3 text-start">{{ __('العميل') }}</th><th class="px-3 py-3 text-start">{{ __('الميعاد') }}</th><th class="px-3 py-3 text-start">{{ __('المسافة') }}</th><th class="px-3 py-3 text-start">{{ __('مهتم؟') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($testDrives as $td)
                            <tr>
                                <td class="px-6 py-3"><div class="font-bold text-slate-800">{{ $td->customer_name }}</div><div class="text-xs text-slate-400" dir="ltr" data-page-align>{{ $td->customer_phone }}</div></td>
                                <td class="px-3 py-3 text-slate-600">{{ \Illuminate\Support\Carbon::parse($td->start_time)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ $td->distance_km ? $td->distance_km.__(' كم') : '—' }}</td>
                                <td class="px-3 py-3">{!! $td->is_interested === null ? '<span class="text-slate-400">—</span>' : ($td->is_interested ? __('<span class="font-bold text-emerald-600">مهتم</span>') : __('<span class="text-slate-500">مش مهتم</span>')) !!}</td>
                                <td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ ['scheduled' => __('محجوزة'), 'completed' => __('تمت'), 'cancelled' => __('ملغاة')][$td->status] ?? $td->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ═══════ السجل ═══════ --}}
    @if ($sections->has('history'))
        <section id="history" class="mt-6 scroll-mt-32 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('سجل العربية') }}</h2>
            <ol class="relative space-y-5 border-s-2 border-slate-100 ps-6">
                @foreach ($returns as $ret)
                    <li class="relative">
                        <span class="absolute -start-[31px] top-1 h-3 w-3 rounded-full bg-rose-500 ring-4 ring-white"></span>
                        <div class="text-sm font-bold text-slate-800">{{ __('مرتجع للمورد ·') }} {{ \App\Models\SupplierReturn::STATUSES[$ret->status] }}</div>
                        <div class="text-xs text-slate-500">{{ $ret->return_date->format('Y-m-d') }} · {{ \App\Models\SupplierReturn::REASONS[$ret->reason] ?? '' }} @if ($isAdmin) · {{ $money($ret->refund_amount) }} @endif</div>
                    </li>
                @endforeach
                @foreach ($transfers as $t)
                    @php $p = json_decode($t->properties ?? '{}', true) ?: []; @endphp
                    <li class="relative">
                        <span class="absolute -start-[31px] top-1 h-3 w-3 rounded-full bg-blue-500 ring-4 ring-white"></span>
                        <div class="text-sm font-bold text-slate-800">{{ __('نقل من') }} {{ $branchNames[$p['from'] ?? 0] ?? '—' }} {{ __('إلى') }} {{ $branchNames[$p['to'] ?? 0] ?? '—' }}</div>
                        <div class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($t->created_at)->format('Y-m-d H:i') }} · {{ $t->user_name ?? '—' }}</div>
                    </li>
                @endforeach
                @if ($invoice && $isAdmin)
                    <li class="relative">
                        <span class="absolute -start-[31px] top-1 h-3 w-3 rounded-full bg-slate-400 ring-4 ring-white"></span>
                        <div class="text-sm font-bold text-slate-800">{{ __('اتشترت بفاتورة') }} {{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-slate-500">{{ $invoice->invoice_date->format('Y-m-d') }}</div>
                    </li>
                @endif
            </ol>
        </section>
    @endif

    {{-- ═══════ الملخص المالي ═══════ --}}
    <section id="finance" class="mt-6 scroll-mt-32 rounded-2xl bg-slate-900 p-6 text-white shadow-lg">
        <h2 class="text-base font-extrabold">{{ __('الملخص المالي') }}</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 {{ $isAdmin ? 'lg:grid-cols-3' : '' }}">
            @if ($isAdmin)
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                    <div class="text-xs text-slate-400">{{ __('المتبقي للمورد') }}</div>
                    <div class="mt-1 text-2xl font-extrabold {{ $finance['supplier_remaining'] > 0 ? 'text-amber-300' : 'text-emerald-300' }}">{{ $money($finance['supplier_remaining']) }}</div>
                    <div class="text-xs text-slate-500">{{ __('عقود التفويض + فاتورة الشراء') }}</div>
                </div>
            @endif
            <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                <div class="text-xs text-slate-400">{{ __('المتبقي على العميل') }}</div>
                <div class="mt-1 text-2xl font-extrabold {{ $finance['customer_remaining'] > 0 ? 'text-amber-300' : 'text-emerald-300' }}">{{ $money($finance['customer_remaining']) }}</div>
                <div class="text-xs text-slate-500">{{ __('عقود البيع') }}</div>
            </div>
            @if ($isAdmin)
                @php $p = $finance['profit'] ?? $finance['expected_profit']; @endphp
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                    <div class="text-xs text-slate-400">{{ $finance['profit'] !== null ? __('صافي الربح') : __('الربح المتوقع') }}</div>
                    <div class="mt-1 text-2xl font-extrabold {{ ($p ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $p !== null ? $money($p) : '—' }}</div>
                    <div class="text-xs text-slate-500">{{ __('سعر البيع − إجمالي التكلفة') }}</div>
                </div>
            @endif
        </div>
        <p class="mt-4 text-xs text-slate-500">{{ __('التقرير ده بيوضح الوضع المالي الكامل للمركبة، شامل كل المبالغ المتبقية على العقود المختلفة.') }}</p>
    </section>

    {{-- ═══════ منطقة الخطر ═══════ --}}
    @if (\App\Support\Access::can('vehicles.delete') && $dealerContracts->isEmpty() && $salesContracts->isEmpty() && $testDrives->isEmpty())
        <form method="POST" action="{{ route('admin.vehicles.destroy', $vehicle) }}" onsubmit="{{ __('return confirm(\'متأكد إنك عايز تمسح العربية دي؟\')') }}" class="mt-6 text-end print:hidden">
            @csrf @method('DELETE')
            <button class="text-sm font-semibold text-rose-600 hover:underline">{{ __('مسح المركبة') }}</button>
        </form>
    @endif

    <script>
        document.querySelectorAll('[data-thumb]').forEach((b) => b.addEventListener('click', () => {
            document.getElementById('mainImage').src = b.dataset.thumb;
            document.querySelectorAll('[data-thumb]').forEach((x) => {
                x.classList.toggle('ring-blue-600', x === b);
                x.classList.toggle('ring-transparent', x !== b);
                x.classList.toggle('opacity-70', x !== b);
            });
        }));
    </script>
@endsection
