@extends('admin.layouts.app')

@php
    $editing = $vehicle->exists;
    $user = auth()->user();
    $f = fn (array $o) => array_merge(['model' => $vehicle], $o);
    $specs = is_array($vehicle->specifications) ? $vehicle->specifications : (json_decode((string) $vehicle->specifications, true) ?: []);
    $images = \App\Support\Media::list($vehicle->images);
    $lockedStatus = in_array($vehicle->status, ['sold', 'returned'], true);
    $currency = config('laroville.currency');
@endphp

@section('title', $editing ? __('تعديل ').$vehicle->vehicle_code : __('إضافة مركبة'))
@section('subtitle', $editing ? __('عدّل بيانات العربية وصورها') : __('سجّل عربية جديدة في المخزون'))

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $editing ? route('admin.vehicles.update', $vehicle) : route('admin.vehicles.store') }}"
      class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-3"
      onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                <div class="font-bold">{{ __('راجع الحقول المعلّمة بالأحمر:') }}</div>
                <ul class="mt-1 list-inside list-disc text-xs">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
            </div>
        @endif

        {{-- الهوية --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">1</span> {{ __('العربية') }}
            </h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="brand_id" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('الماركة') }} <span class="text-rose-500">*</span></label>
                    <select id="brand_id" name="brand_id" required class="w-full rounded-xl border {{ $errors->has('brand_id') ? 'border-rose-300' : 'border-slate-200' }} bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                        <option value="">{{ __('— اختار —') }}</option>
                        @foreach ($brands as $id => $name) <option value="{{ $id }}" @selected((string) old('brand_id', $vehicle->brand_id) === (string) $id)>{{ $name }}</option> @endforeach
                    </select>
                    @error('brand_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="model_id" class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                        <span>{{ __('الطراز') }} <span class="text-rose-500">*</span></span>
                        <a href="{{ route('admin.car-models.index') }}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">{{ __('+ طراز جديد') }}</a>
                    </label>
                    <select id="model_id" name="model_id" required class="w-full rounded-xl border {{ $errors->has('model_id') ? 'border-rose-300' : 'border-slate-200' }} bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                        <option value="">{{ __('— اختار الماركة الأول —') }}</option>
                        @foreach ($models as $m) <option value="{{ $m->id }}" data-brand="{{ $m->brand_id }}" @selected((string) old('model_id', $vehicle->model_id) === (string) $m->id)>{{ \App\Support\Lang::pick($m, 'name') }} {{ $m->year }}</option> @endforeach
                    </select>
                    @error('model_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                @include('admin.partials.field', $f(['name' => 'manufacturing_year', 'label' => __('سنة الصنع'), 'type' => 'number', 'min' => 1950, 'required' => true]))
                @include('admin.partials.field', $f(['name' => 'color', 'label' => __('اللون'), 'required' => true, 'list' => 'colorPresets']))
                <datalist id="colorPresets">
                    @foreach ([__('أبيض'), __('أسود'), __('فضي'), __('رصاصي'), __('أحمر'), __('أزرق'), __('بيج'), __('بني'), __('أخضر'), __('ذهبي')] as $c) <option value="{{ $c }}"></option> @endforeach
                </datalist>
                @include('admin.partials.field', $f(['name' => 'plate_number', 'label' => __('رقم اللوحة'), 'dir' => 'ltr', 'hint' => __('سيبه فاضي لو العربية لسه متسجلتش')]))
                @include('admin.partials.field', $f(['name' => 'vehicle_code', 'label' => __('كود المركبة'), 'dir' => 'ltr', 'hint' => __('بيتولّد لوحده لو سبته فاضي')]))
            </div>
        </section>

        {{-- البيانات الفنية --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">2</span> {{ __('البيانات الفنية') }}
            </h2>
            <div class="grid gap-5 sm:grid-cols-3">
                @include('admin.partials.field', $f(['name' => 'chassis_number', 'label' => __('رقم الشاسيه (VIN)'), 'dir' => 'ltr']))
                @include('admin.partials.field', $f(['name' => 'engine_number', 'label' => __('رقم المحرك'), 'dir' => 'ltr']))
                @include('admin.partials.field', $f(['name' => 'cylinders', 'label' => __('عدد السلندرات'), 'type' => 'number', 'min' => 1]))
                <div>
                    @include('admin.partials.field', $f(['name' => 'mileage', 'label' => __('العداد (كم)'), 'type' => 'number', 'min' => 0]))
                    <p class="mt-1 text-xs text-slate-400" id="milesHint"></p>
                </div>
                @include('admin.partials.field', $f(['name' => 'license_expiry_date', 'label' => __('انتهاء الرخصة'), 'type' => 'date']))
                @include('admin.partials.field', ['model' => null, 'name' => 'specs[engine]', 'label' => __('سعة المحرك'), 'placeholder' => '2.5L', 'dir' => 'ltr', 'value' => old('specs.engine', $specs['engine'] ?? '')])
                @include('admin.partials.field', ['model' => null, 'name' => 'specs[transmission]', 'label' => __('ناقل الحركة'), 'type' => 'select', 'empty' => '—', 'options' => ['أوتوماتيك' => __('أوتوماتيك'), 'عادي' => __('عادي (مانيوال)'), 'CVT' => 'CVT'], 'value' => old('specs.transmission', $specs['transmission'] ?? '')])
                @include('admin.partials.field', ['model' => null, 'name' => 'specs[drive]', 'label' => __('نظام الدفع'), 'type' => 'select', 'empty' => '—', 'options' => ['دفع أمامي' => __('دفع أمامي'), 'دفع خلفي' => __('دفع خلفي'), 'دفع رباعي' => __('دفع رباعي 4x4'), 'AWD' => 'AWD'], 'value' => old('specs.drive', $specs['drive'] ?? '')])
                @include('admin.partials.field', ['model' => null, 'name' => 'specs[fuel]', 'label' => __('الوقود'), 'type' => 'select', 'empty' => '—', 'options' => ['بنزين' => __('بنزين'), 'ديزل' => __('ديزل'), 'هايبرد' => __('هايبرد'), 'كهرباء' => __('كهرباء')], 'value' => old('specs.fuel', $specs['fuel'] ?? '')])
                @include('admin.partials.field', ['model' => null, 'name' => 'specs[seats]', 'label' => __('عدد المقاعد'), 'type' => 'number', 'min' => 1, 'value' => old('specs.seats', $specs['seats'] ?? '')])
                @include('admin.partials.field', ['model' => null, 'name' => 'specs[doors]', 'label' => __('عدد الأبواب'), 'type' => 'number', 'min' => 1, 'value' => old('specs.doors', $specs['doors'] ?? '')])
            </div>
        </section>

        {{-- الصور --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-1 flex items-center gap-2 text-base font-extrabold text-slate-900">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">3</span> {{ __('الصور') }}
            </h2>
            <p class="mb-5 text-xs text-slate-500">{{ __('أول صورة هي الغلاف اللي بيظهر في المتجر. اضغط ⭐ على أي صورة عشان تخليها الغلاف.') }}</p>

            <input type="hidden" name="cover_image" id="coverInput" value="{{ old('cover_image', $images[0] ?? '') }}">

            <div id="imageGrid" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($images as $i => $path)
                    <div class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-100 ring-2 ring-transparent" data-existing="{{ $path }}">
                        <img src="{{ \App\Support\Media::url($path) }}" alt="" class="h-full w-full object-cover">
                        <span data-cover-badge class="absolute start-2 top-2 hidden rounded-md bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ __('الغلاف') }}</span>
                        <div class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-gradient-to-t from-black/60 p-2 opacity-0 transition group-hover:opacity-100">
                            <button type="button" data-make-cover class="rounded-md bg-white/90 px-2 py-1 text-xs font-bold text-slate-800">{{ __('⭐ غلاف') }}</button>
                            <label class="cursor-pointer rounded-md bg-rose-600/90 px-2 py-1 text-xs font-bold text-white">
                                <input type="checkbox" name="remove_images[]" value="{{ $path }}" class="sr-only" data-remove> {{ __('مسح') }}
                            </label>
                        </div>
                        <div data-removed-overlay class="absolute inset-0 hidden place-items-center bg-rose-600/70 text-sm font-bold text-white">{{ __('هتتمسح ↺') }}</div>
                    </div>
                @endforeach

                <label class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 text-slate-400 transition hover:border-blue-400 hover:bg-blue-50/40 hover:text-blue-600">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                    <span class="text-xs font-bold">{{ __('ضيف صور') }}</span>
                    <input type="file" name="images[]" id="imagesInput" multiple accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
            </div>
            <div id="newPreviews" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6"></div>
            @error('images') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
            @error('images.*') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-slate-400">{{ __('JPG / PNG / WEBP — أقصى 5 ميجا للصورة، ولحد 15 صورة.') }}</p>
        </section>

        {{-- الرخصة وحالة العربية --}}
        @if (\Illuminate\Support\Facades\Schema::hasColumn('vehicles', 'license_front'))
            @php
                $pic = 'block aspect-[4/3] w-full overflow-hidden rounded-xl bg-slate-100 object-cover';
                $drop = 'flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-200 text-xs font-bold text-slate-400 hover:border-blue-400 hover:bg-blue-50/40 hover:text-blue-600';
            @endphp
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-1 text-base font-extrabold text-slate-900">🪪 {{ __('الرخصة وحالة العربية') }}</h2>
                <p class="mb-5 text-xs text-slate-500">{{ __('صور للتوثيق الداخلي بس — مش بتظهر في المتجر. بتحميك لو حصل خلاف مع المالك على حالة العربية.') }}</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['front' => __('الرخصة من الأمام'), 'back' => __('الرخصة من الخلف')] as $side => $label)
                        @php $cur = \App\Support\Media::url($vehicle->{'license_'.$side} ?? null); @endphp
                        <div>
                            <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ $label }}</div>
                            @if ($cur)
                                <a href="{{ $cur }}" target="_blank"><img src="{{ $cur }}" alt="" class="{{ $pic }}"></a>
                                <label class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-bold text-rose-600"><input type="checkbox" name="remove_license[]" value="{{ $side }}" class="accent-rose-600"> {{ __('مسح') }}</label>
                            @endif
                            <input type="file" name="license_{{ $side }}" accept="image/jpeg,image/png,image/webp" class="mt-1.5 block w-full text-xs text-slate-500 file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-slate-700">
                            @error('license_'.$side) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                @foreach (['before_images' => __('صور العربية في حالتها السابقة (وقت الاستلام)'), 'after_images' => __('صور العربية في حالتها الحالية')] as $col => $label)
                    @php $list = \App\Support\Media::list($vehicle->{$col} ?? null); @endphp
                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between text-sm font-semibold text-slate-700"><span>{{ $label }}</span><span class="text-xs text-slate-400">{{ count($list) }}/5</span></div>
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                            @foreach ($list as $path)
                                <div class="relative">
                                    <a href="{{ \App\Support\Media::url($path) }}" target="_blank"><img src="{{ \App\Support\Media::url($path) }}" alt="" class="{{ $pic }}"></a>
                                    <label class="absolute end-1 top-1 inline-flex cursor-pointer items-center gap-1 rounded-md bg-white/90 px-1.5 py-0.5 text-[10px] font-bold text-rose-600"><input type="checkbox" name="remove_condition[]" value="{{ $path }}" class="accent-rose-600"> {{ __('مسح') }}</label>
                                </div>
                            @endforeach
                            @if (count($list) < 5)
                                <label class="{{ $drop }}">
                                    <span class="text-lg">＋</span>{{ __('ضيف صور') }}
                                    <input type="file" name="{{ $col }}[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" data-max="{{ 5 - count($list) }}"
                                           onchange="const m = +this.dataset.max; if (this.files.length > m) { alert(@js(__('أقصى عدد هنا:')) + ' ' + m); this.value = ''; } this.closest('label').querySelector('span').textContent = this.files.length ? '✓ ' + this.files.length : '＋';">
                                </label>
                            @endif
                        </div>
                        @error($col) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @error($col.'.*') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </section>
        @endif

        {{-- الوصف --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50 text-xs text-blue-600">4</span> {{ __('الوصف والملاحظات') }}
            </h2>
            <div class="space-y-5">
                @include('admin.partials.field', $f(['name' => 'description_ar', 'label' => __('الوصف (بيظهر في المتجر)'), 'type' => 'textarea', 'rows' => 4, 'placeholder' => __('حالة العربية، المميزات، الصيانة...')]))
                @include('admin.partials.field', $f(['name' => 'notes', 'label' => __('ملاحظات داخلية (مش بتظهر للعملاء)'), 'type' => 'textarea', 'rows' => 2]))
            </div>
        </section>
    </div>

    {{-- ═══════ العمود الجانبي ═══════ --}}
    <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-base font-extrabold text-slate-900">{{ __('الحالة والمكان') }}</h2>
            <div class="space-y-4">
                @if ($lockedStatus)
                    <input type="hidden" name="status" value="{{ $vehicle->status }}">
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <span class="font-bold text-slate-800">{{ $statuses[$vehicle->status] }}</span>
                        <p class="mt-1 text-xs text-slate-500">{{ __('الحالة دي بيحطها السيستم من العقود، ومش بتتغير من هنا.') }}</p>
                    </div>
                @else
                    @include('admin.partials.field', $f(['name' => 'status', 'label' => __('الحالة'), 'type' => 'select', 'required' => true,
                        'options' => collect($statuses)->only(\App\Http\Controllers\Admin\VehicleController::EDITABLE_STATUSES)->all()]))
                @endif
                @if ($branches->count() > 1)
                    @include('admin.partials.field', $f(['name' => 'branch_id', 'label' => __('الفرع'), 'type' => 'select', 'required' => true, 'options' => $branches]))
                @else
                    <input type="hidden" name="branch_id" value="{{ old('branch_id', $vehicle->branch_id ?: $branches->keys()->first()) }}">
                @endif
                @include('admin.partials.field', $f(['name' => 'import_source_id', 'label' => __('جهة الاستيراد'), 'type' => 'select', 'options' => $sources, 'empty' => '—']))
                @include('admin.partials.field', $f(['name' => 'supplier_id', 'label' => __('المورد'), 'type' => 'select', 'options' => $suppliers, 'empty' => __('— بدون مورد —')]))
            </div>
        </section>

        @if ($user->canSeeVehicleCost())
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-1 flex items-center justify-between text-base font-extrabold text-slate-900">
                    {{ __('التكلفة') }} <span class="rounded-md bg-amber-50 px-1.5 text-[10px] font-bold text-amber-700">{{ __('للأدمن بس') }}</span>
                </h2>
                <p class="mb-4 text-xs text-slate-400">{{ __('مش بتظهر للموظفين ولا في المتجر') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    @include('admin.partials.field', $f(['name' => 'purchase_price', 'label' => __('سعر الشراء'), 'type' => 'number', 'step' => '0.001', 'min' => 0]))
                    @include('admin.partials.field', $f(['name' => 'tax_percent', 'label' => __('الضريبة %'), 'type' => 'number', 'step' => '0.01', 'min' => 0]))
                </div>
                <div class="mt-3 flex justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                    <span class="text-slate-500">{{ __('إجمالي التكلفة') }}</span>
                    <span class="font-extrabold text-slate-900" id="costTotal">—</span>
                </div>
            </section>
        @endif

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-base font-extrabold text-slate-900">{{ __('السعر والمتجر') }}</h2>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    @include('admin.partials.field', $f(['name' => 'selling_price', 'label' => __('سعر البيع'), 'type' => 'number', 'step' => '0.001', 'min' => 0]))
                    @include('admin.partials.field', $f(['name' => 'discount_price', 'label' => __('بعد الخصم'), 'type' => 'number', 'step' => '0.001', 'min' => 0]))
                </div>
                <div class="grid grid-cols-2 gap-3 rounded-xl bg-rose-50/60 p-3 ring-1 ring-rose-100">
                    @include('admin.partials.field', $f(['name' => 'offer_label', 'label' => __('🔥 اسم العرض'), 'placeholder' => __('عرض العيد'), 'hint' => __('بيظهر على الكارت')]))
                    @include('admin.partials.field', $f(['name' => 'offer_ends_at', 'label' => __('العرض بيخلص'), 'type' => 'date', 'hint' => __('عداد تنازلي في المتجر')]))
                </div>
                @if ($user->canSeeVehicleCost())
                    <div id="marginBox" class="hidden rounded-xl px-3 py-2 text-sm"></div>
                @endif

                @foreach ([
                    'price_on_request' => [__('السعر عند الطلب'), __('بدل ما السعر يظهر، بيظهر "اتصل للسعر"')],
                    'is_published'     => [__('تظهر في المتجر'), __('الزوار يقدروا يشوفوها ويطلبوها')],
                    'is_featured'      => [__('مميزة في الرئيسية'), __('بتظهر في أول الصفحة الرئيسية للمتجر')],
                ] as $key => [$optLabel, $optHint])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                        <input type="checkbox" name="{{ $key }}" value="1" class="mt-0.5 accent-blue-600" @checked(old($key, $vehicle->{$key}))>
                        <span>
                            <span class="block text-sm font-bold text-slate-800">{{ $optLabel }}</span>
                            <span class="block text-xs text-slate-500">{{ $optHint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <div class="flex gap-2">
            <a href="{{ $editing ? route('admin.vehicles.show', $vehicle) : route('admin.vehicles.index') }}"
               class="flex-1 rounded-xl bg-white py-3 text-center text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('إلغاء') }}</a>
            <button type="submit" data-save class="flex-[2] rounded-xl bg-blue-600 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60">
                {{ $editing ? __('حفظ التعديلات') : __('إضافة المركبة') }}
            </button>
        </div>
    </aside>
</form>

<script>
(function () {
    const dec = {{ (int) config('laroville.decimals', 3) }};
    const fmt = (n) => Number(n || 0).toLocaleString('en', { maximumFractionDigits: dec });
    const $ = (s) => document.querySelector(s);

    // ── الطراز حسب الماركة ──
    const brand = $('#brand_id'), model = $('#model_id');
    const syncModels = () => {
        [...model.options].forEach((o) => { if (o.dataset.brand) o.hidden = o.dataset.brand !== brand.value; });
        if (model.selectedOptions[0]?.hidden) model.value = '';
        model.options[0].textContent = brand.value ? @js(__('— اختار الطراز —')) : @js(__('— اختار الماركة الأول —'));
    };
    brand.addEventListener('change', syncModels);
    syncModels();

    // ── الكيلو ← ميل ──
    const km = document.querySelector('[name=mileage]'), hint = $('#milesHint');
    const miles = () => { hint.textContent = km.value ? '≈ ' + fmt(km.value * 0.621371) + @js(__(' ميل')) : ''; };
    km.addEventListener('input', miles); miles();

    // ── التكلفة والهامش ──
    const price = document.querySelector('[name=purchase_price]'), tax = document.querySelector('[name=tax_percent]');
    const sell = document.querySelector('[name=selling_price]'), disc = document.querySelector('[name=discount_price]');
    const margin = $('#marginBox'), total = $('#costTotal');
    const calc = () => {
        if (!price) return;
        const cost = Number(price.value || 0) * (1 + Number(tax.value || 0) / 100);
        total.textContent = cost ? fmt(cost) + ' {{ $currency }}' : '—';
        const sale = Number(disc.value || sell.value || 0);
        if (!margin) return;
        if (!cost || !sale) { margin.classList.add('hidden'); return; }
        const diff = sale - cost, pct = (diff / cost * 100).toFixed(1);
        margin.className = 'rounded-xl px-3 py-2 text-sm font-bold ' + (diff >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700');
        margin.textContent = (diff >= 0 ? @js(__('هامش الربح: ')) : @js(__('خسارة: '))) + fmt(Math.abs(diff)) + ' (' + pct + '%)';
    };
    [price, tax, sell, disc].forEach((el) => el && el.addEventListener('input', calc));
    calc();

    // ── الصور ──
    const cover = $('#coverInput');
    const markCover = () => document.querySelectorAll('[data-existing]').forEach((card) => {
        const on = card.dataset.existing === cover.value;
        card.classList.toggle('ring-blue-600', on);
        card.querySelector('[data-cover-badge]').classList.toggle('hidden', !on);
    });
    document.querySelectorAll('[data-make-cover]').forEach((b) => b.addEventListener('click', () => {
        cover.value = b.closest('[data-existing]').dataset.existing; markCover();
    }));
    document.querySelectorAll('[data-remove]').forEach((c) => c.addEventListener('change', () => {
        const card = c.closest('[data-existing]');
        card.querySelector('[data-removed-overlay]').classList.toggle('hidden', !c.checked);
        card.querySelector('[data-removed-overlay]').classList.toggle('grid', c.checked);
    }));
    document.querySelectorAll('[data-removed-overlay]').forEach((o) => o.addEventListener('click', () => {
        const c = o.closest('[data-existing]').querySelector('[data-remove]'); c.checked = false; c.dispatchEvent(new Event('change'));
    }));
    markCover();

    const input = $('#imagesInput'), previews = $('#newPreviews');
    input.addEventListener('change', () => {
        previews.innerHTML = '';
        [...input.files].forEach((file) => {
            const d = document.createElement('div');
            d.className = 'relative aspect-square overflow-hidden rounded-lg ring-2 ring-emerald-400';
            d.innerHTML = @js(__('<img class="h-full w-full object-cover"><span class="absolute bottom-1 start-1 rounded bg-emerald-500 px-1 text-[10px] font-bold text-white">جديدة</span>'));
            d.querySelector('img').src = URL.createObjectURL(file);
            previews.appendChild(d);
        });
    });
})();
</script>
@endsection
