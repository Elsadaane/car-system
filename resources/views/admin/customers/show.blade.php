@extends('admin.layouts.app')

@section('title', $customer->name_ar)
@section('subtitle', $customer->customer_code.($customer->phone ? ' · '.$customer->phone : ''))

@php
    use App\Support\Money;
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $wa = preg_replace('/\D/', '', (string) $customer->phone);
    $paidPct = $summary['purchases'] > 0 ? min(100, round($summary['paid'] / $summary['purchases'] * 100)) : 0;
    $statusStyle = ['active' => 'bg-emerald-50 text-emerald-700', 'inactive' => 'bg-slate-100 text-slate-500', 'blocked' => 'bg-rose-50 text-rose-700'];
    $tone = [
        'emerald' => ['ring' => 'stroke-emerald-500', 'text' => 'text-emerald-600', 'bg' => 'bg-emerald-50'],
        'blue'    => ['ring' => 'stroke-blue-500',    'text' => 'text-blue-600',    'bg' => 'bg-blue-50'],
        'amber'   => ['ring' => 'stroke-amber-500',   'text' => 'text-amber-600',   'bg' => 'bg-amber-50'],
        'rose'    => ['ring' => 'stroke-rose-500',    'text' => 'text-rose-600',    'bg' => 'bg-rose-50'],
        'slate'   => ['ring' => 'stroke-slate-300',   'text' => 'text-slate-500',   'bg' => 'bg-slate-50'],
    ][$score['tone']];
    $circ = 2 * M_PI * 42;
    $upcomingFollowUps = $notes->map(fn ($n) => [$n, json_decode($n->properties ?? '{}', true)['follow_up'] ?? null])
        ->filter(fn ($x) => $x[1] && Carbon::parse($x[1])->gte(today()));
    $sections = collect([
        'overview' => __('نظرة عامة'),
        'cars' => __('العربيات (').$contracts->count().')',
        'installments' => $installments->isNotEmpty() ? __('الأقساط (').$installments->count().')' : null,
        'payments' => __('المدفوعات (').$payments->count().')',
        'drives' => __('تجارب القيادة (').$drives->count().')',
        'inquiries' => $inquiries->isNotEmpty() ? __('طلبات الموقع (').$inquiries->count().')' : null,
        'attachments' => __('المرفقات (').$attachments->count().')',
        'notes' => __('المتابعة (').$notes->count().')',
    ])->filter();
@endphp

@section('actions')
    @if ($customer->status !== 'blocked')
        <a href="{{ route('admin.sales-contracts.create', ['customer' => $customer->id]) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">{{ __('عقد بيع') }}</a>
    @endif
    <a href="{{ route('admin.test-drives.create', ['customer' => $customer->id]) }}"
       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ __('تجربة قيادة') }}</a>
    <a href="{{ route('admin.customers.statement', $customer) }}" target="_blank" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('🧾 كشف حساب') }}</a>
    <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('تعديل') }}</a>
    @if ($wa)
        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-600">{{ __('واتساب') }}</a>
        <a href="tel:{{ $customer->phone }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('اتصال') }}</a>
    @endif
@endsection

@section('content')

    <nav class="sticky top-16 z-10 -mx-4 mb-5 flex gap-1 overflow-x-auto bg-slate-100/90 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6">
        @foreach ($sections as $id => $label)
            <a href="#{{ $id }}" class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-white hover:text-slate-900">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($customer->status === 'blocked')
        <div class="mb-4 rounded-2xl bg-rose-600 px-5 py-4 text-sm text-white"><span class="font-extrabold">{{ __('عميل محظور:') }}</span> {{ $customer->block_reason ?: __('بدون سبب مكتوب') }}</div>
    @endif

    @if ($summary['overdue_count'])
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-800 ring-1 ring-rose-200">
            <span><span class="font-extrabold">{{ $summary['overdue_count'] }} {{ __('قسط متأخر') }}</span> {{ __('بإجمالي') }} {{ Money::format($summary['overdue']) }}</span>
            @if ($wa)
                @php $remind = "أهلاً {$customer->name_ar}، ده تذكير ودّي إن عليك أقساط متأخرة بإجمالي ".Money::format($summary['overdue']).__('. ياريت تتواصل معانا. شكراً'); @endphp
                <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($remind) }}" target="_blank" rel="noopener" class="rounded-lg bg-rose-600 px-4 py-2 font-bold text-white hover:bg-rose-700">{{ __('ابعت تذكير واتساب') }}</a>
            @endif
        </div>
    @endif

    @foreach ($upcomingFollowUps as [$n, $date])
        <div class="mb-4 rounded-2xl bg-blue-50 px-5 py-3 text-sm text-blue-800 ring-1 ring-blue-200">
            {{ __('📅 متابعة') }} {{ Carbon::parse($date)->isToday() ? __('النهارده') : __('يوم ').Carbon::parse($date)->format('Y-m-d') }}: <span class="font-bold">{{ $n->description }}</span>
        </div>
    @endforeach

    @if (session('portal_password'))
        <div class="mb-4 rounded-2xl bg-violet-600 p-5 text-white shadow-lg">
            <div class="text-sm font-bold">{{ __('بيانات دخول العميل — ابعتهاله دلوقتي، مش هتظهر تاني') }}</div>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <code class="rounded-lg bg-white/15 px-3 py-2 text-sm" dir="ltr">{{ $portalUser?->email }}</code>
                <code class="rounded-lg bg-white/15 px-3 py-2 text-lg font-bold tracking-widest" dir="ltr">{{ session('portal_password') }}</code>
                @if ($wa && \Illuminate\Support\Facades\Route::has('customer.login'))
                    @php $msg = "أهلاً {$customer->name_ar}\nتقدر تتابع عربيتك وأقساطك من هنا:\n".route('customer.login')."\nالإيميل: {$portalUser?->email}\nكلمة المرور: ".session('portal_password'); @endphp
                    <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-bold hover:bg-emerald-600">{{ __('ابعتها واتساب') }}</a>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════ نظرة عامة ═══════ --}}
    <div id="overview" class="grid scroll-mt-32 gap-6 lg:grid-cols-3">

        {{-- كارت العميل --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center gap-4">
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-2xl font-extrabold text-white">{{ mb_substr(\App\Support\Lang::pick($customer, 'name'), 0, 1) }}</span>
                <div class="min-w-0">
                    <div class="truncate text-lg font-extrabold text-slate-900">{{ \App\Support\Lang::pick($customer, 'name') }}</div>
                    @if ($customer->name_en) <div class="truncate text-sm text-slate-500" dir="ltr" data-page-align>{{ $customer->name_en }}</div> @endif
                    <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-bold {{ $statusStyle[$customer->status] ?? '' }}">{{ $statuses[$customer->status] ?? $customer->status }}</span>
                </div>
            </div>
            <dl class="mt-6 space-y-3 text-sm">
                @foreach ([
                    'الهاتف' => $customer->phone, 'الإيميل' => $customer->email, 'الرقم المدني' => $customer->national_id,
                    'الجنسية' => $customer->nationality, 'الوظيفة' => $customer->job_title, 'العنوان' => $customer->address,
                    'حد الائتمان' => $customer->credit_limit ? Money::format($customer->credit_limit) : null,
                    'عميل من' => $customer->created_at?->format('Y-m-d'),
                ] as $l => $v)
                    @if ($v)
                        <div class="flex justify-between gap-4"><dt class="shrink-0 text-slate-500">{{ $l }}</dt><dd class="text-end font-semibold text-slate-800">{{ $v }}</dd></div>
                    @endif
                @endforeach
            </dl>
            @if ($customer->notes)
                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-900">{{ $customer->notes }}</p>
            @endif
        </section>

        {{-- التقييم --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('التقييم الائتماني') }}</h2>
            <div class="mt-4 flex items-center gap-5">
                <div class="relative h-28 w-28 shrink-0">
                    <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                        <circle cx="50" cy="50" r="42" fill="none" stroke-width="10" class="stroke-slate-100"/>
                        <circle cx="50" cy="50" r="42" fill="none" stroke-width="10" stroke-linecap="round" class="{{ $tone['ring'] }}"
                                stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ * (1 - ($score['score'] ?? 0) / 100) }}"/>
                    </svg>
                    <div class="absolute inset-0 grid place-items-center text-center">
                        <div>
                            <div class="text-2xl font-extrabold text-slate-900">{{ $score['score'] ?? '—' }}</div>
                            @if ($score['score'] !== null) <div class="text-[10px] text-slate-400">{{ __('من 100') }}</div> @endif
                        </div>
                    </div>
                </div>
                <div>
                    <div class="inline-block rounded-full px-3 py-1 text-sm font-extrabold {{ $tone['bg'] }} {{ $tone['text'] }}">{{ $score['label'] }}</div>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $score['hint'] }}</p>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-slate-50 py-2.5"><div class="font-extrabold text-emerald-600">{{ $summary['paid_count'] - $summary['late_count'] }}</div><div class="text-[10px] text-slate-500">{{ __('في ميعاده') }}</div></div>
                <div class="rounded-xl bg-slate-50 py-2.5"><div class="font-extrabold text-amber-600">{{ $summary['late_count'] }}</div><div class="text-[10px] text-slate-500">{{ __('اتدفع متأخر') }}</div></div>
                <div class="rounded-xl bg-slate-50 py-2.5"><div class="font-extrabold {{ $summary['overdue_count'] ? 'text-rose-600' : 'text-slate-900' }}">{{ $summary['overdue_count'] }}</div><div class="text-[10px] text-slate-500">{{ __('متأخر دلوقتي') }}</div></div>
            </div>
            @if ($summary['drives'])
                <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    {{ __('جرّب') }} {{ $summary['drives'] }} {{ __('عربية، وكان مهتم بـ') }} {{ $summary['interested'] }} {{ __('منهم') }}
                </div>
            @endif
        </section>

        {{-- الحساب + البوابة --}}
        <div class="space-y-6">
            <section class="rounded-2xl bg-slate-900 p-6 text-white shadow-lg">
                <div class="text-xs text-slate-400">{{ __('إجمالي المشتريات') }}</div>
                <div class="text-3xl font-extrabold">{{ Money::format($summary['purchases']) }}</div>
                <div class="mt-4 flex justify-between text-xs"><span class="font-bold text-emerald-300">{{ __('دفع') }} {{ Money::format($summary['paid'], false) }}</span><span class="text-slate-400">{{ $paidPct }}%</span></div>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-emerald-400" style="width: {{ $paidPct }}%"></div></div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><div class="text-[11px] text-slate-400">{{ __('المتبقي') }}</div><div class="font-extrabold">{{ Money::format($summary['remaining'], false) }}</div></div>
                    <div><div class="text-[11px] text-slate-400">{{ __('متأخر') }}</div><div class="font-extrabold {{ $summary['overdue'] > 0 ? 'text-rose-300' : '' }}">{{ Money::format($summary['overdue'], false) }}</div></div>
                </div>
                @if ($summary['next'])
                    @php $due = Carbon::parse($summary['next']->due_date); $days = (int) today()->diffInDays($due, false); @endphp
                    <div class="mt-4 rounded-xl bg-white/10 p-3 text-sm">
                        <div class="text-[11px] text-slate-300">{{ __('القسط الجاي') }}</div>
                        <div class="font-extrabold">{{ Money::format((float) $summary['next']->amount - (float) $summary['next']->paid_amount) }}</div>
                        <div class="text-xs text-slate-300">{{ $due->format('Y-m-d') }} · {{ $days === 0 ? __('النهارده') : __('بعد ').$days.__(' يوم') }}</div>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-sm font-extrabold text-slate-900">{{ __('بوابة العملاء') }}</h2>
                @if ($portalUser)
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 font-bold {{ $portalUser->is_active ? 'text-emerald-700' : 'text-rose-700' }}">
                            <span class="h-2 w-2 rounded-full {{ $portalUser->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>{{ $portalUser->is_active ? __('شغال') : __('موقوف') }}
                        </span>
                        <span class="text-xs text-slate-400">{{ __('آخر دخول:') }} {{ $portalUser->last_login_at?->locale('ar')->diffForHumans() ?? __('لسه') }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.customers.reset-password', $customer) }}" class="mt-3" onsubmit="{{ __('return confirm(\'كلمة مرور جديدة؟ القديمة هتبطل.\')') }}">
                        @csrf
                        <button class="w-full rounded-xl bg-slate-100 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200">{{ __('كلمة مرور جديدة') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.customers.account', $customer) }}" class="mt-3 space-y-2">
                        @csrf
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}" required dir="ltr" placeholder="email@example.com"
                               class="w-full rounded-xl border {{ $errors->has('email') ? 'border-rose-300' : 'border-slate-200' }} px-3 py-2 text-sm outline-none focus:border-violet-400">
                        @error('email') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                        <button class="w-full rounded-xl bg-violet-600 py-2 text-sm font-bold text-white hover:bg-violet-700">{{ __('إنشاء حساب') }}</button>
                    </form>
                @endif
            </section>
        </div>
    </div>

    {{-- ═══════ العربيات ═══════ --}}
    <section id="cars" class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-extrabold text-slate-900">{{ __('العربيات اللي اشتراها') }}</h2></div>
        @if ($contracts->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-slate-400">{{ __('لسه مااشتراش عربية.') }}</p>
        @else
            <div class="grid gap-4 p-6 md:grid-cols-2">
                @foreach ($contracts as $sc)
                    @php $img = Media::url(Media::list($sc->images)[0] ?? null); $pct = (float) $sc->total_amount > 0 ? min(100, round((float) $sc->paid_amount / (float) $sc->total_amount * 100)) : 0; @endphp
                    <a href="{{ route('admin.sales-contracts.show', $sc->id) }}" class="flex gap-4 rounded-2xl p-3 ring-1 ring-slate-200 transition hover:shadow-md">
                        <div class="h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-slate-100">@if ($img) <img src="{{ $img }}" alt="" class="h-full w-full object-cover"> @endif</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-bold text-slate-900">{{ $sc->brand_name }} {{ $sc->model_name }} {{ $sc->manufacturing_year }}</div>
                            <div class="text-xs text-slate-400"><span class="font-mono">{{ $sc->contract_number }}</span> · {{ Carbon::parse($sc->contract_date)->format('Y-m-d') }} · {{ $sc->status_name }}</div>
                            <div class="mt-2 flex justify-between text-xs"><span class="font-bold text-slate-800">{{ Money::format($sc->total_amount) }}</span><span class="{{ (float) $sc->remaining_amount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ (float) $sc->remaining_amount > 0 ? __('متبقي ').Money::format($sc->remaining_amount, false) : __('مدفوع بالكامل ✓') }}</span></div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div></div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══════ الأقساط ═══════ --}}
    @if ($installments->isNotEmpty())
        <section id="installments" class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('جدول الأقساط') }}</h2>
                <span class="text-xs text-slate-500">{{ $summary['paid_count'] }} {{ __('مدفوع من') }} {{ $installments->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('القسط') }}</th><th class="px-3 py-3 text-start">{{ __('الاستحقاق') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('المدفوع') }}</th><th class="px-3 py-3 text-start">{{ __('اتدفع يوم') }}</th><th class="px-3 py-3 text-start">{{ __('الحالة') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($installments as $ins)
                            @php
                                $due = Carbon::parse($ins->due_date);
                                $overdue = ! $ins->is_paid && $due->lt(today());
                                $paidLate = $ins->is_paid && $ins->actual_payment_date && Carbon::parse($ins->actual_payment_date)->gt($due);
                            @endphp
                            <tr class="{{ $overdue ? 'bg-rose-50/50' : '' }} {{ $ins->is_paid ? 'text-slate-400' : '' }}">
                                <td class="px-6 py-3 font-bold">{{ $ins->installment_sequence }} / {{ $ins->total_installments }}</td>
                                <td class="px-3 py-3">{{ $due->format('Y-m-d') }}</td>
                                <td class="px-3 py-3 font-semibold">{{ Money::format($ins->amount, false) }}</td>
                                <td class="px-3 py-3">{{ (float) $ins->paid_amount > 0 ? Money::format($ins->paid_amount, false) : '—' }}</td>
                                <td class="px-3 py-3 text-xs">{{ $ins->actual_payment_date ? Carbon::parse($ins->actual_payment_date)->format('Y-m-d') : '—' }}</td>
                                <td class="px-3 py-3">
                                    @if ($ins->is_paid && $paidLate) <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ __('اتدفع متأخر') }} {{ (int) $due->diffInDays(Carbon::parse($ins->actual_payment_date)) }} {{ __('يوم') }}</span>
                                    @elseif ($ins->is_paid) <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ __('في ميعاده ✓') }}</span>
                                    @elseif ($overdue) <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700">{{ __('متأخر') }} {{ (int) $due->diffInDays(today()) }} {{ __('يوم') }}</span>
                                    @else <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ __('بعد') }} {{ (int) today()->diffInDays($due) }} {{ __('يوم') }}</span> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- ═══════ المدفوعات ═══════ --}}
    <section id="payments" class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('المدفوعات') }}</h2>
            <span class="text-sm font-extrabold text-emerald-600">{{ Money::format($payments->sum('amount')) }}</span>
        </div>
        @if ($payments->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-slate-400">{{ __('مفيش مدفوعات مسجلة.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('التاريخ') }}</th><th class="px-3 py-3 text-start">{{ __('العقد') }}</th><th class="px-3 py-3 text-start">{{ __('المبلغ') }}</th><th class="px-3 py-3 text-start">{{ __('الطريقة') }}</th><th class="px-3 py-3 text-start">{{ __('المرجع') }}</th><th class="px-3 py-3 text-start">{{ __('استلمها') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payments as $p)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-3"><div class="font-semibold text-slate-800">{{ Carbon::parse($p->payment_date)->format('Y-m-d') }}</div><div class="font-mono text-[11px] text-slate-400">{{ $p->payment_reference }}</div></td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-600">{{ $p->contract_number }}</td>
                                <td class="whitespace-nowrap px-3 py-3 font-bold text-emerald-700">{{ Money::format($p->amount, false) }}</td>
                                <td class="px-3 py-3"><span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $p->method_name ?? '—' }}</span></td>
                                <td class="px-3 py-3 text-xs text-slate-500" dir="ltr" data-page-align>{{ $p->bank_reference ?: '—' }}</td>
                                <td class="px-3 py-3 text-xs text-slate-500">{{ $p->user_name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ═══════ تجارب القيادة ═══════ --}}
    <section id="drives" class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('تجارب القيادة') }}</h2>
            <a href="{{ route('admin.test-drives.create', ['customer' => $customer->id]) }}" class="text-sm font-bold text-blue-600 hover:underline">{{ __('+ تجربة جديدة') }}</a>
        </div>
        @forelse ($drives as $d)
            <a href="{{ route('admin.test-drives.show', $d) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-3 last:border-0 hover:bg-slate-50">
                <div>
                    <div class="font-bold text-slate-800">{{ $d->brand_name }} {{ $d->model_name }} <span class="font-mono text-xs font-normal text-slate-400">{{ $d->vehicle_code }}</span></div>
                    <div class="text-xs text-slate-400">{{ Carbon::parse($d->start_time)->format('Y-m-d H:i') }}@if ($d->feedback_ar) · "{{ \Illuminate\Support\Str::limit(\App\Support\Lang::pick($d, 'feedback'), 60) }}"@endif</div>
                </div>
                <span class="text-xs font-bold {{ $d->is_interested ? 'text-emerald-600' : 'text-slate-400' }}">
                    {{ $d->status === 'scheduled' ? __('جارية الآن') : ($d->is_interested === null ? '—' : ($d->is_interested ? __('مهتم 👍') : __('مش مهتم'))) }}
                </span>
            </a>
        @empty
            <p class="px-6 py-8 text-center text-sm text-slate-400">{{ __('مفيش تجارب قيادة.') }}</p>
        @endforelse
    </section>

    {{-- ═══════ طلبات الموقع ═══════ --}}
    @if ($inquiries->isNotEmpty())
        @php $stages = \App\Http\Controllers\Admin\InquiryController::STAGES; $types = \App\Http\Controllers\Admin\InquiryController::TYPES; @endphp
        <section id="inquiries" class="mt-6 scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-extrabold text-slate-900">{{ __('طلباته من الموقع والإعلانات') }}</h2>
                <a href="{{ route('admin.inquiries.index', ['view' => 'list', 'q' => $customer->phone]) }}" class="text-sm font-bold text-blue-600 hover:underline">{{ __('فتح في طلبات الموقع') }}</a>
            </div>
            @foreach ($inquiries as $q)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-3 last:border-0">
                    <div class="min-w-0">
                        <div class="font-bold text-slate-800">{{ $types[$q->type] ?? $q->type }}@if ($q->brand_name) — {{ $q->brand_name }} {{ $q->model_name }}@endif</div>
                        <div class="text-xs text-slate-400">{{ Carbon::parse($q->created_at)->format('Y-m-d') }} · {{ $q->source ?: 'website' }}@if ($q->utm_campaign) · {{ $q->utm_campaign }}@endif @if ($q->assignee) {{ __('· مع') }} {{ $q->assignee }}@endif</div>
                        @if ($q->message)<div class="mt-1 truncate text-xs text-slate-500">"{{ \Illuminate\Support\Str::limit($q->message, 90) }}"</div>@endif
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $stages[$q->status][0] ?? $q->status }}</span>
                </div>
            @endforeach
        </section>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- ═══════ المرفقات ═══════ --}}
        <section id="attachments" class="scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-extrabold text-slate-900">{{ __('المرفقات') }}</h2></div>
            <form method="POST" action="{{ route('admin.attachments.store', ['customer', $customer->id]) }}" enctype="multipart/form-data" class="space-y-3 border-b border-slate-100 p-5"
                  onsubmit="this.querySelector('[type=submit]').disabled = true;">
                @csrf
                <div class="flex flex-wrap gap-2">
                    @foreach ([__('صورة البطاقة'), __('رخصة القيادة'), __('كشف حساب بنكي'), __('خطاب راتب'), __('عقد موقّع')] as $preset)
                        <button type="button" onclick="this.form.name.value = '{{ $preset }}'" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200">{{ $preset }}</button>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-3">
                    <input name="name" value="{{ old('name') }}" required placeholder="{{ __('اسم المرفق') }}" class="min-w-[140px] flex-1 rounded-xl border {{ $errors->has('name') ? 'border-rose-300' : 'border-slate-200' }} px-3 py-2 text-sm outline-none focus:border-blue-400">
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                           class="min-w-[180px] flex-1 text-sm text-slate-500 file:me-2 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-blue-700">
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('رفع') }}</button>
                </div>
                @if ($errors->has('file') || $errors->has('name')) <p class="text-xs text-rose-600">{{ $errors->first('file') ?: $errors->first('name') }}</p> @endif
                <p class="text-[11px] text-slate-400">{{ __('PDF أو صورة أو Word/Excel — لحد 10 ميجا. الملفات محمية ومش متاحة على النت.') }}</p>
            </form>
            @forelse ($attachments as $a)
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $a->isImage() ? 'bg-violet-50 text-violet-600' : 'bg-blue-50 text-blue-600' }} text-[10px] font-extrabold uppercase">
                        {{ $a->isImage() ? 'IMG' : strtoupper(pathinfo($a->path, PATHINFO_EXTENSION)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-bold text-slate-800">{{ $a->name }}</div>
                        <div class="text-[11px] text-slate-400">{{ $a->humanSize() }} · {{ $a->created_at->format('Y-m-d') }} · {{ $a->user?->name }}</div>
                    </div>
                    @if ($a->isImage() || $a->mime_type === 'application/pdf')
                        <a href="{{ route('admin.attachments.download', [$a, 'inline' => 1]) }}" target="_blank" class="rounded-lg px-2 py-1 text-xs font-bold text-blue-600 hover:bg-blue-50">{{ __('عرض') }}</a>
                    @endif
                    <a href="{{ route('admin.attachments.download', $a) }}" class="rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100">{{ __('تنزيل') }}</a>
                    @if (auth()->user()->isAdmin() || (int) $a->user_id === (int) auth()->id())
                        <form method="POST" action="{{ route('admin.attachments.destroy', $a) }}" onsubmit="{{ __('return confirm(\'مسح المرفق؟\')') }}">
                            @csrf @method('DELETE')
                            <button class="rounded-lg px-2 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('مسح') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-6 py-8 text-center text-sm text-slate-400">{{ __('مفيش مرفقات.') }}</p>
            @endforelse
        </section>

        {{-- ═══════ المتابعة ═══════ --}}
        <section id="notes" class="scroll-mt-32 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-extrabold text-slate-900">{{ __('سجل المتابعة') }}</h2></div>
            <form method="POST" action="{{ route('admin.customers.notes', $customer) }}" class="space-y-3 border-b border-slate-100 p-5">
                @csrf
                <textarea name="note" rows="2" required placeholder="{{ __('مثلاً: كلمته ووعد يدفع القسط يوم الخميس') }}"
                          class="w-full rounded-xl border {{ $errors->has('note') ? 'border-rose-300' : 'border-slate-200' }} px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">{{ old('note') }}</textarea>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        {{ __('فكّرني يوم') }}
                        <input type="date" name="follow_up" value="{{ old('follow_up') }}" min="{{ today()->format('Y-m-d') }}" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs outline-none">
                    </label>
                    <button class="ms-auto rounded-xl bg-slate-900 px-5 py-2 text-sm font-bold text-white hover:bg-slate-800">{{ __('حفظ') }}</button>
                </div>
                @error('follow_up') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </form>
            <ol class="max-h-96 space-y-4 overflow-y-auto p-5">
                @forelse ($notes as $n)
                    @php $fu = json_decode($n->properties ?? '{}', true)['follow_up'] ?? null; @endphp
                    <li class="flex gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">{{ mb_substr($n->user_name ?? __('؟'), 0, 1) }}</span>
                        <div class="min-w-0 flex-1 rounded-2xl rounded-ss-sm bg-slate-50 px-4 py-2.5">
                            <p class="whitespace-pre-line text-sm text-slate-800">{{ $n->description }}</p>
                            <div class="mt-1 flex flex-wrap gap-2 text-[11px] text-slate-400">
                                <span>{{ $n->user_name }} · {{ Carbon::parse($n->created_at)->locale('ar')->diffForHumans() }}</span>
                                @if ($fu) <span class="rounded bg-blue-50 px-1.5 font-bold text-blue-700">{{ __('📅 متابعة') }} {{ Carbon::parse($fu)->format('Y-m-d') }}</span> @endif
                            </div>
                        </div>
                    </li>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">{{ __('لسه مفيش ملاحظات.') }}</p>
                @endforelse
            </ol>
        </section>
    </div>

    @if (\App\Support\Access::can('customers.delete') && $contracts->isEmpty())
        <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" class="mt-6 text-end" onsubmit="{{ __('return confirm(\'مسح العميل وحسابه وتجارب القيادة بتاعته؟\')') }}">
            @csrf @method('DELETE')
            <button class="text-sm font-semibold text-rose-600 hover:underline">{{ __('مسح العميل') }}</button>
        </form>
    @endif
@endsection
