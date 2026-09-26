@extends('admin.layouts.app')

@section('title', $branch->name_ar)
@section('subtitle', $branch->branch_code.($branch->city ? ' · '.$branch->city : '').($branch->is_main ? __(' · الفرع الرئيسي') : ''))

@php
    $decimals = (int) config('laroville.decimals', 3);
    $money = fn ($v) => number_format((float) $v, $decimals).' '.config('laroville.currency');
    $canManage = auth()->user()->canManageSettings();

    $statusStyle = [
        'available'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'reserved'    => 'bg-amber-50 text-amber-700 ring-amber-200',
        'sold'        => 'bg-blue-50 text-blue-700 ring-blue-200',
        'maintenance' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'damaged'     => 'bg-rose-50 text-rose-700 ring-rose-200',
        'returned'    => 'bg-slate-100 text-slate-500 ring-slate-200',
    ];
@endphp

@section('actions')
    <a href="{{ route('admin.branches.index') }}"
       class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>
        {{ __('كل الفروع') }}
    </a>

    @if ($canManage)
        <a href="{{ route('admin.branches.edit', $branch) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>
            {{ __('تعديل الفرع') }}
        </a>
    @endif

    @if ($branch->location_url)
        <a href="{{ $branch->location_url }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6 7-11.5a7 7 0 0 0-14 0C5 15 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            {{ __('الموقع على الخريطة') }}
        </a>
    @endif
@endsection

@section('content')

    {{-- ═══════ الأرقام ═══════ --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $cards = [
                ['label' => __('عربيات متاحة'),  'value' => $stats['available'], 'hint' => __('من ').$stats['vehicles'].__(' عربية'), 'dot' => 'bg-emerald-500', 'filter' => 'available'],
                ['label' => __('محجوزة'),         'value' => $stats['reserved'],  'hint' => __('في انتظار إتمام البيع'),        'dot' => 'bg-amber-400',   'filter' => 'reserved'],
                ['label' => __('مبيعات الشهر'),   'value' => $stats['month_sales_count'].__(' عقد'), 'hint' => $money($stats['month_sales_total']), 'dot' => 'bg-blue-500', 'filter' => null],
                ['label' => __('قيمة المعروض'),   'value' => number_format($stats['stock_value'], 0), 'hint' => $stats['stock_cost'] !== null ? __('التكلفة: ').number_format($stats['stock_cost'], 0) : config('laroville.currency'), 'dot' => 'bg-violet-500', 'filter' => null],
            ];
        @endphp
        @foreach ($cards as $card)
            @php $tag = $card['filter'] ? 'a' : 'div'; @endphp
            <{{ $tag }} @if ($card['filter']) href="{{ route('admin.branches.show', [$branch, 'status' => $card['filter']]) }}#vehicles" @endif
                class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 {{ $card['filter'] ? 'transition hover:-translate-y-0.5 hover:shadow-md' : '' }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">{{ $card['label'] }}</span>
                    <span class="h-2.5 w-2.5 rounded-full {{ $card['dot'] }}"></span>
                </div>
                <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $card['value'] }}</div>
                <div class="mt-1 truncate text-xs text-slate-400">{{ $card['hint'] }}</div>
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">

        {{-- ═══════ العربيات ═══════ --}}
        <section id="vehicles" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('عربيات الفرع') }}</h2>

                {{-- تبويبات الحالة --}}
                <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                    <a href="{{ route('admin.branches.show', [$branch, 'q' => $filters['q'] ?: null, 'brand' => $filters['brand']]) }}#vehicles"
                       class="rounded-lg px-3 py-1.5 {{ ! $filters['status'] ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                        {{ __('الكل') }}
                    </a>
                    @foreach ($statuses as $key => $label)
                        <a href="{{ route('admin.branches.show', [$branch, 'status' => $key, 'q' => $filters['q'] ?: null, 'brand' => $filters['brand']]) }}#vehicles"
                           class="rounded-lg px-3 py-1.5 {{ $filters['status'] === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <form method="GET" action="{{ route('admin.branches.show', $branch) }}#vehicles" class="flex flex-wrap gap-3 border-b border-slate-100 p-4">
                @if ($filters['status'])
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('الكود، اللوحة، الشاسيه، الماركة...') }}"
                       class="min-w-[200px] flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
                @if ($brands->isNotEmpty())
                    <select name="brand" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400">
                        <option value="">{{ __('كل الماركات') }}</option>
                        @foreach ($brands as $id => $name)
                            <option value="{{ $id }}" @selected($filters['brand'] === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">{{ __('بحث') }}</button>
            </form>

            @if ($vehicles->isEmpty())
                <div class="px-6 py-14 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v2H3z"/></svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-600">{{ __('مفيش عربيات هنا') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">{{ __('العربية') }}</th>
                                <th class="px-3 py-3 text-start font-semibold">{{ __('اللوحة / الشاسيه') }}</th>
                                <th class="px-3 py-3 text-start font-semibold">{{ __('السعر') }}</th>
                                <th class="px-3 py-3 text-start font-semibold">{{ __('الحالة') }}</th>
                                @if ($otherBranches->isNotEmpty())
                                    <th class="px-5 py-3 text-end font-semibold">{{ __('نقل') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($vehicles as $vehicle)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-slate-900">
                                            {{ $vehicle->brand_name }} {{ $vehicle->model_name }}
                                            <span class="font-normal text-slate-500">{{ $vehicle->manufacturing_year }}</span>
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-400">
                                            <span>{{ $vehicle->vehicle_code }}</span>
                                            <span>·</span>
                                            <span>{{ $vehicle->color }}</span>
                                            @if ($vehicle->is_published)
                                                <span class="rounded bg-sky-50 px-1.5 text-[10px] font-bold text-sky-700">{{ __('منشورة') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-xs text-slate-600" dir="ltr" style="text-align: right">
                                        <div>{{ $vehicle->plate_number ?: __('بدون لوحة') }}</div>
                                        <div class="text-slate-400">{{ $vehicle->chassis_number ?: '—' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5">
                                        @if ($vehicle->price_on_request)
                                            <span class="text-xs text-slate-500">{{ __('عند الطلب') }}</span>
                                        @elseif ($vehicle->discount_price)
                                            <div class="font-bold text-emerald-700">{{ number_format((float) $vehicle->discount_price, 0) }}</div>
                                            <div class="text-xs text-slate-400 line-through">{{ number_format((float) $vehicle->selling_price, 0) }}</div>
                                        @elseif ($vehicle->selling_price)
                                            <div class="font-bold text-slate-900">{{ number_format((float) $vehicle->selling_price, 0) }}</div>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $statusStyle[$vehicle->status] ?? 'bg-slate-50 text-slate-600 ring-slate-200' }}">
                                            {{ $statuses[$vehicle->status] ?? $vehicle->status }}
                                        </span>
                                    </td>
                                    @if ($otherBranches->isNotEmpty())
                                        <td class="px-5 py-3.5 text-end">
                                            @if ($vehicle->status !== 'sold')
                                                <details class="relative inline-block text-start">
                                                    <summary class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg text-slate-400 transition hover:bg-blue-50 hover:text-blue-600" title="{{ __('نقل لفرع تاني') }}">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h13l-3-3M20 16H7l3 3"/></svg>
                                                    </summary>
                                                    <form method="POST" action="{{ route('admin.branches.vehicles.transfer', [$branch, $vehicle]) }}"
                                                          class="absolute end-0 z-10 mt-2 w-60 space-y-3 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-slate-200"
                                                          onsubmit="{{ __('return confirm(\'متأكد إنك عايز تنقل العربية دي؟\')') }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="block text-xs font-bold text-slate-600">{{ __('نقل') }} {{ $vehicle->vehicle_code }} {{ __('إلى:') }}</label>
                                                        <select name="to_branch_id" required class="w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                                                            @foreach ($otherBranches as $id => $name)
                                                                <option value="{{ $id }}">{{ $name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button class="w-full rounded-lg bg-blue-600 py-2 text-sm font-bold text-white hover:bg-blue-700">{{ __('نقل') }}</button>
                                                    </form>
                                                </details>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($vehicles->hasPages())
                    <div class="border-t border-slate-100 p-4">{{ $vehicles->fragment('vehicles')->links() }}</div>
                @endif
            @endif
        </section>

        {{-- ═══════ الجنب ═══════ --}}
        <aside class="space-y-6">

            {{-- بيانات الفرع --}}
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-extrabold text-slate-900">{{ __('بيانات الفرع') }}</h2>
                    <span class="flex items-center gap-1.5 text-xs font-semibold {{ $branch->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">
                        <span class="h-2 w-2 rounded-full {{ $branch->status === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                        {{ $branch->status === 'active' ? __('نشط') : __('غير نشط') }}
                    </span>
                </div>

                <dl class="space-y-3 text-sm">
                    @foreach ([
                        __('المدير')          => $manager?->name,
                        __('التليفون')        => $branch->phone,
                        __('الموبايل')        => $branch->mobile,
                        __('البريد')          => $branch->email,
                        __('العنوان')         => collect([$branch->address, $branch->city, $branch->region])->filter()->implode(__('، ')),
                        'السجل التجاري'   => $branch->commercial_register,
                        'الرقم الضريبي'   => $branch->tax_number,
                        'تاريخ الافتتاح'  => $branch->opening_date ? \Illuminate\Support\Carbon::parse($branch->opening_date)->format('Y-m-d') : null,
                    ] as $label => $value)
                        @if ($value)
                            <div class="flex justify-between gap-4">
                                <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                                <dd class="text-end font-semibold text-slate-800">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                @if ($branch->mobile || $branch->phone)
                    @php $wa = preg_replace('/\D/', '', $branch->mobile ?: $branch->phone); @endphp
                    <div class="mt-5 grid grid-cols-2 gap-2">
                        <a href="tel:{{ $branch->mobile ?: $branch->phone }}"
                           class="rounded-xl bg-slate-100 py-2.5 text-center text-sm font-bold text-slate-700 hover:bg-slate-200">{{ __('اتصال') }}</a>
                        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                           class="rounded-xl bg-emerald-500 py-2.5 text-center text-sm font-bold text-white hover:bg-emerald-600">{{ __('واتساب') }}</a>
                    </div>
                @endif

                @if ($branch->notes)
                    <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">{{ $branch->notes }}</p>
                @endif
            </section>

            {{-- الموظفين --}}
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-4 text-base font-extrabold text-slate-900">
                    {{ __('الموظفين') }} <span class="text-sm font-normal text-slate-400">({{ $staff->count() }})</span>
                </h2>
                @forelse ($staff as $member)
                    <div class="flex items-center gap-3 py-2">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $member->is_active ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-400' }} text-sm font-bold">
                            {{ mb_substr($member->name, 0, 1) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-bold {{ $member->is_active ? 'text-slate-800' : 'text-slate-400 line-through' }}">
                                {{ $member->name }}
                                @if ($manager && $manager->id === $member->id)
                                    <span class="rounded bg-amber-50 px-1.5 text-[10px] font-bold text-amber-700 no-underline">{{ __('مدير') }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-400">
                                {{ $member->role === 'admin' ? __('أدمن') : __('موظف') }}
                                {{ __('· آخر دخول') }} {{ $member->last_login_at?->locale('ar')->diffForHumans() ?? __('لم يدخل بعد') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('مفيش موظفين متسجلين على الفرع ده.') }}</p>
                @endforelse
            </section>

            {{-- آخر المبيعات --}}
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-4 text-base font-extrabold text-slate-900">{{ __('آخر المبيعات') }}</h2>
                @forelse ($latestSales as $sale)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2.5 last:border-0">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-800">{{ $sale->customer_name ?? '—' }}</div>
                            <div class="text-xs text-slate-400">{{ $sale->contract_number }} · {{ \Illuminate\Support\Carbon::parse($sale->contract_date)->format('Y-m-d') }}</div>
                        </div>
                        <div class="text-end">
                            <div class="whitespace-nowrap text-sm font-bold text-slate-900">{{ number_format((float) $sale->total_amount, 0) }}</div>
                            @if ((float) $sale->remaining_amount > 0)
                                <div class="text-[11px] text-amber-600">{{ __('متبقي') }} {{ number_format((float) $sale->remaining_amount, 0) }}</div>
                            @else
                                <div class="text-[11px] text-emerald-600">{{ __('مدفوع') }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('لسه مفيش مبيعات في الفرع ده.') }}</p>
                @endforelse

                @if ($stats['new_inquiries'])
                    <a href="{{ route('admin.inquiries.index') }}"
                       class="mt-4 flex items-center justify-between rounded-xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 hover:bg-rose-100">
                        <span>{{ __('استفسارات جديدة مستنية رد') }}</span>
                        <span class="rounded-full bg-rose-500 px-2 text-xs leading-5 text-white">{{ $stats['new_inquiries'] }}</span>
                    </a>
                @endif
            </section>

            {{-- منطقة الخطر --}}
            @if ($canManage && ! $branch->is_main)
                <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}"
                      onsubmit="{{ __('return confirm(\'متأكد إنك عايز تمسح الفرع ده؟\')') }}"
                      class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-rose-100">
                    @csrf
                    @method('DELETE')
                    <h2 class="text-sm font-extrabold text-rose-700">{{ __('مسح الفرع') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('متاح بس لو الفرع مفيهوش عربيات ولا عقود.') }}</p>
                    <button class="mt-3 w-full rounded-xl bg-rose-50 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100">{{ __('مسح الفرع') }}</button>
                </form>
            @endif
        </aside>
    </div>
@endsection
