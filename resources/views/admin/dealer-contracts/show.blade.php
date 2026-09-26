@extends('admin.layouts.app')

@section('title', __('عقد تفويض ').$contract->contract_number)
@section('subtitle', $contract->brand_name.' '.$contract->model_name.' '.$contract->manufacturing_year.' · '.$contract->supplier_name)

@php
    use App\Support\Money;
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $isAdmin = \App\Support\Access::can('dealer_contracts.delete');
    $active = $contract->status_code === 'active';
    $pct = (float) $contract->total_amount > 0 ? min(100, round((float) $contract->paid_amount / (float) $contract->total_amount * 100)) : 0;
    $img = Media::url(Media::list($contract->vehicle_images)[0] ?? null);
    $wa = preg_replace('/\D/', '', (string) $contract->supplier_phone);
    $statusStyle = ['active' => 'bg-emerald-500', 'completed' => 'bg-blue-600', 'cancelled' => 'bg-slate-400'];
    $expiry = $contract->expiry_date ? Carbon::parse($contract->expiry_date) : null;
    $modal = old('_modal');
@endphp

@section('actions')
    @if ($isAdmin && $active && (float) $contract->remaining_amount > 0)
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
        <div class="absolute end-0 z-30 mt-2 w-64 overflow-hidden rounded-2xl bg-white py-2 text-sm shadow-xl ring-1 ring-slate-200">
            @if ($signLink)
                @php $waMsg = "أهلاً {$contract->supplier_name}\nده رابط توقيع عقد تفويض بيع عربيتك {$contract->brand_name} {$contract->model_name} (عقد رقم {$contract->contract_number}):\n{$signLink}\nالرابط صالح لمدة 7 أيام."; @endphp
                <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($waMsg) }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-emerald-700 hover:bg-emerald-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-emerald-50">✍️</span> {{ __('ابعت رابط توقيع للمالك') }}
                </a>
                <button type="button" data-copy="{{ $signLink }}" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🔗</span> <span data-copy-label>{{ __('نسخ رابط التوقيع') }}</span>
                </button>
            @endif
            @if ($showroomLink ?? null)
                <button type="button" data-copy="{{ $showroomLink }}" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-50">🏢</span> <span data-copy-label>{{ __('نسخ رابط توقيع المفوّض (المعرض)') }}</span>
                </button>
            @endif
            <a href="#signatures" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🖊️</span> {{ __('التوقيع هنا في المعرض') }}
            </a>
            @if ($active && ! $vehicleSold)
                <button type="button" data-open="priceModal" class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-amber-50">💲</span> {{ __('تغيير السعر المتفق عليه') }}
                </button>
            @endif
            <a href="{{ route('admin.dealer-contracts.print', $model) }}" target="_blank" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🖨️</span> {{ __('طباعة العقد (عربي + إنجليزي)') }}
            </a>
            @if ($contract->status_code !== 'cancelled')
                <a href="{{ route('admin.dealer-contracts.edit', $model) }}" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">✏️</span> {{ __('تعديل العقد') }}
                </a>
            @endif
            @if (\App\Support\Access::can('vehicles.edit'))
                <a href="{{ route('admin.vehicles.edit', $contract->vehicle_id) }}" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">🚗</span> {{ __('تعديل المركبة') }}
                </a>
            @endif
            <a href="{{ route('admin.dealer-contracts.print', [$model, 'lang' => 'ar']) }}" target="_blank" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">📄</span> {{ __('طباعة بالعربي بس') }}
            </a>
            @if ($wa)
                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-slate-100">💬</span> {{ __('واتساب المالك') }}
                </a>
            @endif
            @if ($active && (float) $contract->paid_amount == 0)
                <div class="my-1 border-t border-slate-100"></div>
                <form method="POST" action="{{ route('admin.dealer-contracts.cancel', $model) }}" onsubmit="{{ __('return confirm(\'إلغاء العقد؟ العربية هترجع متاحة لعقد جديد.\')') }}">
                    @csrf @method('PATCH')
                    <button class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-amber-700 hover:bg-amber-50"><span class="grid h-7 w-7 place-items-center rounded-lg bg-amber-50">⏸️</span> {{ __('إلغاء العقد') }}</button>
                </form>
                @if ($isAdmin)
                    <form method="POST" action="{{ route('admin.dealer-contracts.destroy', $model) }}" onsubmit="{{ __('return confirm(\'مسح العقد نهائياً؟\')') }}">
                        @csrf @method('DELETE')
                        <button class="flex w-full items-center gap-3 px-4 py-2.5 text-start font-semibold text-rose-600 hover:bg-rose-50"><span class="grid h-7 w-7 place-items-center rounded-lg bg-rose-50">🗑️</span> {{ __('حذف') }}</button>
                    </form>
                @endif
            @endif
        </div>
    </details>
@endsection

@section('content')

    @if (session('receipt_url'))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-emerald-50 px-5 py-4 text-sm text-emerald-800 ring-1 ring-emerald-200">
            <span class="font-bold">{{ __('اتسجلت الدفعة. اطبع إيصال للمالك يوقّع عليه.') }}</span>
            <a href="{{ session('receipt_url') }}" target="_blank" class="rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white hover:bg-emerald-700">{{ __('طباعة الإيصال') }}</a>
        </div>
    @endif

    @if (session('price_link'))
        @php $priceMsg = "أهلاً {$contract->supplier_name}\nالمعرض بيطلب موافقتك على تغيير سعر بيع عربيتك {$contract->brand_name} {$contract->model_name} (عقد رقم {$contract->contract_number}).\nشوف السعر الجديد ووافق أو ارفض من هنا:\n".session('price_link'); @endphp
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-amber-50 px-5 py-4 text-sm text-amber-900 ring-1 ring-amber-200">
            <span class="font-bold">{{ __('طلب تغيير السعر مستني موافقة المالك. ابعتله الرابط:') }}</span>
            <div class="flex gap-2">
                @if ($wa)<a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($priceMsg) }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white hover:bg-emerald-700">{{ __('ابعت واتساب') }}</a>@endif
                <button type="button" data-copy="{{ session('price_link') }}" class="rounded-lg bg-white px-4 py-2 font-bold text-slate-700 ring-1 ring-slate-200"><span data-copy-label>{{ __('نسخ الرابط') }}</span></button>
            </div>
        </div>
    @endif

    @if (! $contract->owner_signature || ! $contract->showroom_signature)
        <div class="mb-4 rounded-2xl bg-amber-50 px-5 py-4 text-sm text-amber-800 ring-1 ring-amber-200">
            {{ __('العقد ناقصه') }} <span class="font-bold">{{ collect([! $contract->owner_signature ? __('توقيع المالك') : null, ! $contract->showroom_signature ? __('توقيع المعرض') : null])->filter()->implode(__(' و ')) }}</span>{{ __('. وقّع من تحت.') }}
        </div>
    @endif

    @if ($expiry && $active && $expiry->lte(today()->addDays(7)))
        <div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 ring-1 ring-rose-200">
            {{ __('العقد') }} {{ $expiry->isPast() ? __('انتهى في') : __('بينتهي في') }} {{ $expiry->format('Y-m-d') }}{{ __('. جدّده مع المالك أو اقفله.') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- الملخص المالي --}}
        <section class="rounded-2xl bg-slate-900 p-6 text-white shadow-lg lg:col-span-2">
            <div class="flex items-center justify-between">
                <span class="font-mono text-sm text-slate-400">{{ $contract->contract_number }}</span>
                <span class="rounded-full {{ $statusStyle[$contract->status_code] ?? 'bg-slate-500' }} px-3 py-1 text-xs font-bold">{{ $contract->status_name }}</span>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div><div class="text-xs text-slate-400">{{ __('المستحق للمالك') }}</div><div class="mt-1 text-2xl font-extrabold">{{ Money::format($contract->total_amount) }}</div></div>
                <div><div class="text-xs text-slate-400">{{ __('اتدفع') }}</div><div class="mt-1 text-2xl font-extrabold text-emerald-300">{{ Money::format($contract->paid_amount) }}</div></div>
                <div><div class="text-xs text-slate-400">{{ __('الباقي') }}</div><div class="mt-1 text-2xl font-extrabold {{ (float) $contract->remaining_amount > 0 ? 'text-amber-300' : 'text-slate-400' }}">{{ Money::format($contract->remaining_amount) }}</div></div>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-emerald-400" style="width: {{ $pct }}%"></div></div>
            <div class="mt-5 grid grid-cols-2 gap-3 border-t border-white/10 pt-5 text-sm sm:grid-cols-4">
                <div><div class="text-[11px] text-slate-400">{{ __('السعر المتفق عليه') }}</div><div class="font-bold">{{ Money::format($contract->agreed_price, false) }}</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('الضريبة (') }}{{ (float) $contract->tax_percent }}%)</div><div class="font-bold">{{ Money::format($contract->tax_amount, false) }}</div></div>
                <div><div class="text-[11px] text-slate-400">{{ __('عمولة المعرض') }}@if (($contract->commission_type ?? null) === 'percent') ({{ (float) $contract->commission_rate }}%)@endif</div><div class="font-bold text-violet-300">{{ Money::format($contract->commission_amount, false) }}</div>@if (! empty($contract->min_price))<div class="text-[11px] text-amber-300">{{ __('أقل سعر:') }} {{ Money::format($contract->min_price, false) }}</div>@endif</div>
                <div><div class="text-[11px] text-slate-400">{{ __('التاريخ') }}</div><div class="font-bold">{{ Carbon::parse($contract->contract_date)->format('Y-m-d') }}</div></div>
            </div>
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
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('الحالة') }}</dt><dd class="font-bold">{{ \App\Http\Controllers\Admin\VehicleController::STATUSES[$contract->vehicle_status] ?? '—' }}</dd></div>
                </dl>
            </div>
        </a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- المدفوعات --}}
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('الدفعات للمالك') }} <span class="text-sm font-normal text-slate-400">({{ $payments->count() }})</span></h2>
            </div>
            @if ($payments->isEmpty())
                <p class="px-6 py-10 text-center text-sm text-slate-400">{{ __('لسه مفيش دفعات.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('الطريقة') }}</th><th class="px-3 py-3 text-start">{{ __('المرجع') }}</th><th class="px-3 py-3 text-start">{{ __('بواسطة') }}</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($payments as $p)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-3"><div class="font-semibold text-slate-800">{{ Carbon::parse($p->payment_date)->format('Y-m-d') }}</div><div class="font-mono text-[11px] text-slate-400">{{ $p->payment_reference }}</div></td>
                                    <td class="whitespace-nowrap px-3 py-3 font-bold text-emerald-700">{{ Money::format($p->amount, false) }}</td>
                                    <td class="px-3 py-3"><span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $p->method_name }}</span></td>
                                    <td class="px-3 py-3 text-xs text-slate-500" dir="ltr" data-page-align>{{ $p->bank_reference ?: '—' }}</td>
                                    <td class="px-3 py-3 text-xs text-slate-500">{{ $p->user_name }}</td>
                                    <td class="px-5 py-3 text-end">
                                        <a href="{{ route('admin.dealer-payments.receipt', $p) }}" target="_blank" title="{{ __('إيصال') }}" class="inline-grid h-8 w-8 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7z"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- المالك --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('المالك') }}</h2>
            <div class="mt-3 text-lg font-bold text-slate-800">{{ $contract->supplier_name }}</div>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('التليفون') }}</dt><dd class="font-semibold" dir="ltr">{{ $contract->supplier_phone ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('البطاقة') }}</dt><dd class="font-semibold">{{ $contract->supplier_national_id ?: '—' }}</dd></div>
                @if ($contract->bank_name) <div class="flex justify-between"><dt class="text-slate-500">{{ __('البنك') }}</dt><dd class="font-semibold">{{ $contract->bank_name }}</dd></div> @endif
            </dl>
            @if ($contract->iban_number)
                <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700" dir="ltr">{{ $contract->iban_number }}</div>
            @endif
            @if ($isAdmin)
                <a href="{{ route('admin.suppliers.show', $contract->supplier_id) }}" class="mt-4 block rounded-xl bg-slate-100 py-2.5 text-center text-sm font-bold text-slate-700 hover:bg-slate-200">{{ __('حساب المالك كامل') }}</a>
            @endif
            <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-400">{{ __('اتعمل بواسطة') }} {{ $contract->user_name ?? '—' }}</div>
        </section>
    </div>

    {{-- الشروط والتوقيعات --}}
    <section id="signatures" class="mt-6 scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-base font-extrabold text-slate-900">{{ __('الشروط والتوقيعات') }}</h2>
        @if ($contract->terms_ar || ! empty($contract->terms_en))
            <div class="mt-4 grid gap-4 {{ $contract->terms_ar && ! empty($contract->terms_en) ? 'lg:grid-cols-2' : '' }}">
                @if ($contract->terms_ar)
                    <div class="rounded-xl bg-slate-50 p-4 text-sm leading-loose text-slate-700" dir="rtl">{{ \App\Support\RichText::render($contract->terms_ar, 'terms') }}</div>
                @endif
                @if (! empty($contract->terms_en))
                    <div class="rounded-xl bg-slate-50 p-4 text-sm leading-loose text-slate-700" dir="ltr">{{ \App\Support\RichText::render($contract->terms_en, 'terms') }}</div>
                @endif
            </div>
        @endif
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            @foreach (['owner' => __('توقيع المالك'), 'showroom' => __('توقيع المعرض')] as $party => $label)
                @php $sig = Media::url($contract->{$party.'_signature'}); @endphp
                <div class="rounded-2xl p-4 ring-1 {{ $sig ? 'ring-emerald-200' : 'ring-amber-200' }}">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-bold text-slate-800">{{ $label }}</span>
                        <span class="text-xs font-bold {{ $sig ? 'text-emerald-600' : 'text-amber-600' }}">{{ $sig ? __('تم التوقيع ✓') : __('مستني التوقيع') }}@if ($sig && ! empty($contract->{$party.'_signed_at'})) <span class="font-normal text-slate-400">· {{ Carbon::parse($contract->{$party.'_signed_at'})->format('Y-m-d H:i') }}</span>@endif</span>
                    </div>
                    @if ($sig)
                        <img src="{{ $sig }}" alt="" class="h-32 w-full rounded-xl bg-slate-50 object-contain">
                    @endif
                    @if ($contract->status_code !== 'cancelled')
                        <details class="mt-3" @if (! $sig) open @endif>
                            <summary class="cursor-pointer text-xs font-bold text-blue-600">{{ $sig ? __('إعادة التوقيع') : __('وقّع دلوقتي') }}</summary>
                            <form method="POST" action="{{ route('admin.dealer-contracts.sign', [$model, $party]) }}" class="mt-3 space-y-3">
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



    {{-- ═══ طلبات تغيير السعر ═══ --}}
    @if ($priceRequests->isNotEmpty() || $vehicleSold)
        @php $reqStates = ['pending' => [__('مستني المالك'), 'bg-amber-100 text-amber-800'], 'approved' => [__('المالك وافق'), 'bg-emerald-100 text-emerald-700'], 'rejected' => [__('المالك رفض'), 'bg-rose-100 text-rose-700'], 'cancelled' => [__('اتلغى'), 'bg-slate-100 text-slate-500']]; @endphp
        <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-extrabold text-slate-900">{{ __('طلبات تغيير سعر البيع') }}</h2></div>
            @if ($vehicleSold)
                <p class="bg-amber-50 px-6 py-3 text-sm font-bold text-amber-800">{{ __('لا يمكن إنشاء طلب تغيير سعر: العربية عليها عقد بيع بالفعل.') }}</p>
            @endif
            @if ($priceRequests->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('السعر القديم') }}</th><th class="px-3 py-3 text-start">{{ __('السعر الجديد') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th><th class="px-3 py-3 text-start">{{ __('بواسطة') }}</th><th class="px-5 py-3 text-end">{{ __('الرابط') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($priceRequests as $r)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-3 text-slate-700">{{ Carbon::parse($r->created_at)->format('Y-m-d H:i') }}@if ($r->reason)<div class="text-xs text-slate-400">{{ $r->reason }}</div>@endif</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ Money::format($r->old_price, false) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 font-bold text-slate-900">{{ Money::format($r->new_price, false) }}</td>
                                    <td class="px-3 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $reqStates[$r->status][1] ?? '' }}">{{ $reqStates[$r->status][0] ?? $r->status }}</span>@if ($r->owner_note)<div class="mt-1 text-xs text-slate-500">💬 {{ $r->owner_note }}</div>@endif</td>
                                    <td class="px-3 py-3 text-xs text-slate-500">{{ $r->user_name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end">
                                        @if ($r->link)
                                            <button type="button" data-copy="{{ $r->link }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200"><span data-copy-label>{{ __('نسخ الرابط') }}</span></button>
                                            <form method="POST" action="{{ route('admin.dealer-contracts.price-requests.cancel', [$model, $r->id]) }}" class="inline">
                                                @csrf @method('PATCH')
                                                <button class="rounded-lg px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('إلغاء') }}</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- ═══ سجل العقد ═══ --}}
    @if ($history->isNotEmpty())
        <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-5 text-base font-extrabold text-slate-900">{{ __('سجل العقد') }}</h2>
            <ol class="relative space-y-5 border-s-2 border-slate-100 ps-6">
                @foreach ($history as $h)
                    @php $p = json_decode($h->properties ?? '{}', true) ?: []; @endphp
                    <li class="relative">
                        <span class="absolute -start-[31px] top-1 h-3 w-3 rounded-full ring-4 ring-white {{ $h->action === 'dealer_price_changed' ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                        @if ($h->action === 'dealer_price_changed')
                            <div class="text-sm font-bold text-slate-800">{{ __('تغيير السعر:') }} {{ Money::format($p['old'] ?? 0, false) }} ← {{ Money::format($p['new'] ?? 0, false) }}</div>
                            @if (($p['via'] ?? null) === 'owner') <div class="text-xs font-bold text-emerald-600">{{ __('✓ بموافقة المالك من الرابط') }}</div> @endif
                            @if (! empty($p['reason'])) <div class="text-xs text-slate-600">{{ __('السبب:') }} {{ $p['reason'] }}</div> @endif
                        @elseif ($h->action === 'dealer_price_requested')
                            <div class="text-sm font-bold text-slate-800">{{ __('طلب تغيير السعر:') }} {{ Money::format($p['old'] ?? 0, false) }} ← {{ Money::format($p['new'] ?? 0, false) }} <span class="font-normal text-amber-600">({{ __('مستني المالك') }})</span></div>
                        @elseif ($h->action === 'dealer_price_rejected')
                            <div class="text-sm font-bold text-rose-700">{{ __('المالك رفض السعر الجديد') }} {{ Money::format($p['new'] ?? 0, false) }}</div>
                            @if (! empty($p['note'])) <div class="text-xs text-slate-600">💬 {{ $p['note'] }}</div> @endif
                        @elseif ($h->action === 'dealer_signed_remotely')
                            <div class="text-sm font-bold text-slate-800">{{ ($p['party'] ?? 'owner') === 'showroom' ? __('المفوّض (المعرض) وقّع عن طريق الرابط') : __('المالك وقّع عن طريق الرابط') }}</div>
                        @else
                            <div class="text-sm font-bold text-slate-800">{{ $h->action }}</div>
                        @endif
                        <div class="text-xs text-slate-400">{{ Carbon::parse($h->created_at)->format('Y-m-d H:i') }} · {{ $h->user_name ?? __('عن طريق الرابط') }}</div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- ═══ مودال تغيير السعر ═══ --}}
    @if ($active && ! $vehicleSold)
        <dialog id="priceModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
            <form method="POST" action="{{ route('admin.dealer-contracts.price', $model) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf @method('PATCH')
                <input type="hidden" name="_modal" value="price">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">{{ __('تغيير السعر المتفق عليه') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('الحالي:') }} {{ Money::format($contract->agreed_price) }}</p>
                    </div>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    @include('admin.partials.field', ['model' => null, 'name' => 'new_price', 'label' => __('السعر الجديد'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                    <div id="priceDiff" class="hidden rounded-xl px-3 py-2 text-sm font-bold"></div>
                    @include('admin.partials.field', ['model' => null, 'name' => 'reason', 'label' => __('السبب'), 'placeholder' => __('مثلاً: المالك وافق ينزل السعر بعد 30 يوم عرض')])
                    <div class="grid gap-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/60">
                            <input type="radio" name="mode" value="request" checked class="mt-1 accent-amber-500">
                            <span><span class="block text-sm font-bold text-slate-800">{{ __('ابعت للمالك يوافق (مُستحسن)') }}</span><span class="text-xs text-slate-500">{{ __('بيطلع رابط تبعته للمالك واتساب، والسعر مايتغيرش غير لما يوافق.') }}</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/60">
                            <input type="radio" name="mode" value="direct" class="mt-1 accent-amber-500">
                            <span><span class="block text-sm font-bold text-slate-800">{{ __('طبّق على طول') }}</span><span class="text-xs text-slate-500">{{ __('لو المالك وافق قدامك أو بالتليفون — التغيير بيتسجل باسمك.') }}</span></span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-600 disabled:opacity-60">{{ __('تأكيد') }}</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- ═══ مودال الدفع ═══ --}}
    @if ($isAdmin)
        <dialog id="payModal" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
            <form method="POST" action="{{ route('admin.dealer-contracts.payments.store', $model) }}"
                  onsubmit="{{ __('const b = this.querySelector(\'[type=submit]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
                @csrf
                <input type="hidden" name="_modal" value="pay">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">{{ __('دفعة للمالك') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('الباقي:') }} {{ Money::format($contract->remaining_amount) }}</p>
                    </div>
                    <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="mb-1.5 flex justify-between text-sm font-semibold text-slate-700">
                            <span>{{ __('المبلغ') }} <span class="text-rose-500">*</span></span>
                            <button type="button" onclick="this.closest('form').amount.value = '{{ number_format((float) $contract->remaining_amount, Money::decimals(), '.', '') }}'" class="text-xs font-bold text-blue-600 hover:underline">{{ __('الباقي كله') }}</button>
                        </label>
                        <input name="amount" type="number" step="0.001" min="0.001" max="{{ (float) $contract->remaining_amount }}" required value="{{ old('amount') }}"
                               class="w-full rounded-xl border px-3 py-3 text-lg font-bold outline-none focus:ring-4 {{ $errors->has('amount') ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-400 focus:ring-blue-50' }}">
                        @error('amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
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

            const np = document.querySelector('[name=new_price]'), diff = document.getElementById('priceDiff');
            np?.addEventListener('input', () => {
                const cur = {{ (float) $contract->agreed_price }}, v = Number(np.value || 0);
                if (!v) { diff.classList.add('hidden'); return; }
                const d = v - cur;
                diff.className = 'rounded-xl px-3 py-2 text-sm font-bold ' + (d < 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700');
                diff.textContent = (d < 0 ? @js(__('نزول ')) : @js(__('زيادة '))) + Math.abs(d).toLocaleString('en') + ' (' + (d / cur * 100).toFixed(1) + '%)';
            });

            @if ($errors->any() && $modal === 'pay') document.getElementById('payModal')?.showModal(); @endif
            @if ($errors->any() && $modal === 'price') document.getElementById('priceModal')?.showModal(); @endif
            @if (! $errors->any() && in_array(request('open'), ['payModal', 'priceModal'], true))
                document.getElementById(@json(request('open')))?.showModal();
            @endif
        })();
    </script>
@endsection
