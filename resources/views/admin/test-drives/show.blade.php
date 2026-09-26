@extends('admin.layouts.app')

@section('title', __('تجربة قيادة — ').$drive->customer_name)
@section('subtitle', $drive->brand_name.' '.$drive->model_name.' '.$drive->manufacturing_year.' · '.$drive->vehicle_code)

@php
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $start = Carbon::parse($drive->start_time);
    $end = $drive->end_time ? Carbon::parse($drive->end_time) : null;
    $ongoing = $drive->status === 'scheduled';
    $minutes = (int) $start->diffInMinutes($end ?? now());
    $img = Media::url(Media::list($drive->vehicle_images)[0] ?? null);
    $wa = preg_replace('/\D/', '', (string) $drive->customer_phone);
@endphp

@section('actions')
    @if ($drive->status === 'completed')
        <a href="{{ route('admin.sales-contracts.create', ['customer' => $drive->customer_id, 'vehicle' => $drive->vehicle_id]) }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">{{ __('حوّلها لبيعة') }}</a>
    @endif
    <a href="{{ route('admin.test-drives.print', $model) }}" target="_blank" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('طباعة') }}</a>
    @if ($wa) <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-600">{{ __('واتساب العميل') }}</a> @endif
    <a href="{{ route('admin.customers.show', $drive->customer_id) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('ملف العميل') }}</a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- الحالة --}}
            <section class="overflow-hidden rounded-2xl shadow-sm ring-1 {{ $ongoing ? 'bg-blue-600 text-white ring-blue-600' : 'bg-white ring-slate-200' }}">
                <div class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div>
                        <div class="text-sm {{ $ongoing ? 'text-blue-100' : 'text-slate-500' }}">{{ $ongoing ? __('العربية مع العميل من') : __('مدة التجربة') }}</div>
                        <div class="mt-1 text-3xl font-extrabold" @if ($ongoing) data-since="{{ $start->timestamp }}" @endif>{{ intdiv($minutes, 60) ? intdiv($minutes, 60).__(' س ') : '' }}{{ $minutes % 60 }} {{ __('د') }}</div>
                        <div class="mt-1 text-xs {{ $ongoing ? 'text-blue-100' : 'text-slate-400' }}">{{ __('خرجت') }} {{ $start->format('Y-m-d H:i') }}@if ($end) {{ __('· رجعت') }} {{ $end->format('H:i') }}@endif</div>
                    </div>
                    <div class="text-end">
                        @if ($ongoing)
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-sm font-bold"><span class="h-2 w-2 animate-pulse rounded-full bg-white"></span> {{ __('جارية') }}</span>
                        @elseif ($drive->status === 'cancelled')
                            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-bold text-slate-600">{{ __('ملغاة') }}</span>
                        @else
                            <span class="rounded-full px-3 py-1.5 text-sm font-bold {{ $drive->is_interested ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $drive->is_interested ? __('العميل مهتم 👍') : __('مش مهتم') }}</span>
                            @if ($drive->distance_km !== null) <div class="mt-2 text-sm font-bold text-slate-700">{{ $drive->distance_km }} {{ __('كم') }}</div> @endif
                        @endif
                    </div>
                </div>

                @if ($ongoing)
                    {{-- قفل التجربة --}}
                    <form method="POST" action="{{ route('admin.test-drives.complete', $model) }}" class="space-y-4 bg-white p-6 text-slate-800">
                        @csrf @method('PATCH')
                        <div class="text-base font-extrabold text-slate-900">{{ __('العربية رجعت؟ اقفل التجربة') }}</div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @include('admin.partials.field', ['model' => null, 'name' => 'end_mileage', 'label' => __('قراءة العداد دلوقتي (كم)'), 'type' => 'number', 'min' => (int) $drive->start_mileage, 'hint' => $drive->start_mileage !== null ? __('وقت الخروج كان ').number_format($drive->start_mileage) : null])
                            <div>
                                <div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('العميل مهتم يشتري؟') }} <span class="text-rose-500">*</span></div>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="cursor-pointer rounded-xl border border-slate-200 py-2.5 text-center text-sm font-bold text-slate-600 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-700">
                                        <input type="radio" name="is_interested" value="1" class="sr-only" required @checked(old('is_interested') === '1')> {{ __('مهتم 👍') }}
                                    </label>
                                    <label class="cursor-pointer rounded-xl border border-slate-200 py-2.5 text-center text-sm font-bold text-slate-600 has-[:checked]:border-slate-500 has-[:checked]:bg-slate-100">
                                        <input type="radio" name="is_interested" value="0" class="sr-only" @checked(old('is_interested') === '0')> {{ __('مش مهتم') }}
                                    </label>
                                </div>
                                @error('is_interested') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        @include('admin.partials.field', ['model' => null, 'name' => 'feedback_ar', 'label' => __('رأي العميل / ملاحظات'), 'type' => 'textarea', 'rows' => 2, 'placeholder' => __('مثلاً: عجبه الموتور بس شايف السعر عالي')])
                        <div class="flex items-center justify-between">
                            <button type="submit" form="cancelDrive" class="text-sm font-semibold text-slate-500 hover:text-rose-600">{{ __('إلغاء التجربة') }}</button>
                            <button class="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('قفل التجربة') }}</button>
                        </div>
                    </form>
                    <form id="cancelDrive" method="POST" action="{{ route('admin.test-drives.cancel', $model) }}" onsubmit="{{ __('return confirm(\'إلغاء التجربة؟\')') }}">@csrf @method('PATCH')</form>
                @endif
            </section>

            @if ($drive->feedback_ar)
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm font-bold text-slate-500">{{ __('رأي العميل') }}</div>
                    <p class="mt-2 text-slate-800">{{ \App\Support\Lang::pick($drive, 'feedback') }}</p>
                    @if ($drive->is_interested)
                        <div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ __('العميل مهتم. تابعه خلال 24 ساعة، فرص البيع بتقل كل يوم بيعدّي.') }}</div>
                    @endif
                </section>
            @endif

            {{-- التوقيعات --}}
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('الإقرار والتوقيعات') }}</h2>
                @if ($drive->terms_ar)
                    <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-loose text-slate-700">{{ \App\Support\RichText::render(\App\Support\Lang::pick($drive, 'terms'), 'terms') }}</div>
                @endif
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    @foreach (['customer' => __('توقيع العميل'), 'showroom' => __('توقيع الموظف')] as $party => $label)
                        @php $sig = Media::url($drive->{$party.'_signature'}); @endphp
                        <div class="rounded-2xl p-4 ring-1 {{ $sig ? 'ring-emerald-200' : 'ring-amber-200' }}">
                            <div class="mb-2 flex justify-between text-sm"><span class="font-bold text-slate-800">{{ $label }}</span><span class="text-xs font-bold {{ $sig ? 'text-emerald-600' : 'text-amber-600' }}">{{ $sig ? __('تم ✓') : __('مستني') }}</span></div>
                            @if ($sig) <img src="{{ $sig }}" alt="" class="h-28 w-full rounded-xl bg-slate-50 object-contain"> @endif
                            <details class="mt-3" @if (! $sig) open @endif>
                                <summary class="cursor-pointer text-xs font-bold text-blue-600">{{ $sig ? __('إعادة التوقيع') : __('وقّع دلوقتي') }}</summary>
                                <form method="POST" action="{{ route('admin.test-drives.sign', [$model, $party]) }}" class="mt-3 space-y-3">
                                    @csrf
                                    @include('admin.partials.signature-pad', ['name' => 'signature_data', 'label' => $label])
                                    <button class="w-full rounded-xl bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('حفظ التوقيع') }}</button>
                                </form>
                            </details>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <a href="{{ route('admin.vehicles.show', $drive->vehicle_id) }}" class="group block overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 hover:shadow-lg">
                <div class="aspect-[16/10] bg-slate-100">@if ($img) <img src="{{ $img }}" alt="" class="h-full w-full object-cover transition group-hover:scale-105"> @endif</div>
                <div class="p-5">
                    <div class="font-extrabold text-slate-900 group-hover:text-blue-700">{{ $drive->brand_name }} {{ $drive->model_name }} {{ $drive->manufacturing_year }}</div>
                    <div class="mt-1 text-xs text-slate-500"><span class="font-mono">{{ $drive->vehicle_code }}</span> {{ __('· لوحة') }} {{ $drive->plate_number ?: '—' }}</div>
                    @if ($drive->start_mileage !== null)
                        <div class="mt-3 text-xs text-slate-500">{{ __('العداد:') }} {{ number_format($drive->start_mileage) }}@if ($drive->end_mileage) → {{ number_format($drive->end_mileage) }}@endif {{ __('كم') }}</div>
                    @endif
                </div>
            </a>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 font-bold text-white">{{ mb_substr($drive->customer_name, 0, 1) }}</span>
                    <div>
                        <div class="font-bold text-slate-900">{{ $drive->customer_name }}</div>
                        <div class="text-xs text-slate-500" dir="ltr" data-page-align>{{ $drive->customer_phone }}</div>
                    </div>
                </div>
                @if ($drive->license_number) <div class="mt-4 flex justify-between text-sm"><span class="text-slate-500">{{ __('رقم الرخصة') }}</span><span class="font-mono font-bold">{{ $drive->license_number }}</span></div> @endif
                <div class="mt-2 flex justify-between text-sm"><span class="text-slate-500">{{ __('الموظف') }}</span><span class="font-bold">{{ $drive->user_name ?? '—' }}</span></div>
            </section>

            @if (\App\Support\Access::can('test_drives.delete'))
                <form method="POST" action="{{ route('admin.test-drives.destroy', $model) }}" onsubmit="{{ __('return confirm(\'مسح التجربة نهائياً؟\')') }}" class="text-center">
                    @csrf @method('DELETE')
                    <button class="text-sm font-semibold text-rose-600 hover:underline">{{ __('مسح التجربة') }}</button>
                </form>
            @endif
        </aside>
    </div>

    @if ($ongoing)
        <script>
            (function () {
                const el = document.querySelector('[data-since]');
                const tick = () => {
                    const m = Math.floor((Date.now() / 1000 - Number(el.dataset.since)) / 60);
                    el.textContent = (Math.floor(m / 60) ? Math.floor(m / 60) + @js(__(' س ')) : '') + (m % 60) + @js(__(' د'));
                };
                setInterval(tick, 30000);
            })();
        </script>
    @endif
@endsection
