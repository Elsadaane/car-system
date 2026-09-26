@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use App\Support\Media;
    use App\Support\Payroll;
    use App\Http\Controllers\Admin\Hr\PayrollController as PC;
    use Illuminate\Support\Carbon;
    $img = Media::url($e->profile_image);
    $wa = preg_replace('/\D/', '', (string) $e->phone);
    $terminated = $e->status === 'terminated';
    $years = Carbon::parse($e->join_date)->diff($terminated && $e->termination_date ? Carbon::parse($e->termination_date) : today());
    $daily = Payroll::dailyRate($e);
    $hourly = $daily / max(1, (float) $e->working_hours_per_day);
    $allowSum = $allowances->where('type', 'allowance')->sum('amount');
    $dedSum = $allowances->where('type', 'deduction')->sum('amount');
    $att = fn ($k) => (int) ($attendance[$k]->c ?? 0);
    $otMonth = round($attendance->sum('ot') / 60, 1);
    $lateMonth = (int) $attendance->sum('late');
    $typeStyle = ['sponsored' => 'bg-blue-500/20 text-blue-100', 'contract' => 'bg-emerald-500/20 text-emerald-100', 'freelance' => 'bg-amber-500/20 text-amber-100'];
    $dlg = 'm-auto w-[calc(100%-2rem)] rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm';
@endphp

@section('title', $e->full_name_ar)
@section('subtitle', $e->job_title.($e->department_name ? ' · '.$e->department_name : '').($e->branch_name ? ' · '.$e->branch_name : ''))

@section('actions')
    @unless ($terminated)
        <a href="{{ route('admin.hr.employees.edit', $e->id) }}" class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">{{ __('✏️ تعديل') }}</a>
        <button type="button" data-open="eosModal" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50">{{ __('إنهاء الخدمة') }}</button>
    @endunless
    @if ($eos) <a href="{{ route('admin.hr.employees.eos-print', $e->id) }}" target="_blank" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">{{ __('🖨️ المخالصة') }}</a> @endif
    @if ($wa) <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-600">{{ __('واتساب') }}</a> @endif
@endsection

@section('content')
    @include('admin.hr.partials-tabs')

    {{-- ═══ الهيدر ═══ --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-violet-950 to-indigo-900 p-6 text-white shadow-xl">
        <div class="absolute -end-16 -top-16 h-56 w-56 rounded-full bg-violet-500/20 blur-2xl"></div>
        <div class="relative flex flex-wrap items-center gap-5">
            @if ($img) <img src="{{ $img }}" alt="" class="h-24 w-24 rounded-3xl object-cover ring-4 ring-white/10">
            @else <span class="grid h-24 w-24 place-items-center rounded-3xl bg-white/10 text-4xl font-black">{{ mb_substr(\App\Support\Lang::pick($e, 'full_name'), 0, 1) }}</span> @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-extrabold">{{ \App\Support\Lang::pick($e, 'full_name') }}</h2>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $typeStyle[$e->employment_type] }}">{{ Payroll::EMPLOYMENT[$e->employment_type] }}</span>
                    <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-bold">{{ Payroll::STATUSES[$e->status] }}</span>
                </div>
                @if ($e->full_name_en) <div class="text-sm text-white/60" dir="ltr" data-page-align>{{ $e->full_name_en }}</div> @endif
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-white/80">
                    <span>🪪 {{ $e->employee_code }}</span>
                    @if ($e->phone) <span dir="ltr">📞 {{ $e->phone }}</span> @endif
                    @if ($e->nationality) <span>🌍 {{ $e->nationality }}</span> @endif
                    <span>{{ __('📅 من') }} {{ Carbon::parse($e->join_date)->format('Y-m-d') }} ({{ $years->y ? $years->y.__(' سنة ') : '' }}{{ $years->m }} {{ __('شهر)') }}</span>
                    @if ($e->user_name) <span>{{ __('👤 حساب:') }} {{ $e->user_name }}</span> @endif
                    @if ($e->shift_name) <span>🕘 {{ $e->shift_name }} {{ substr($e->shift_start, 0, 5) }}-{{ substr($e->shift_end, 0, 5) }}</span> @endif
                </div>
            </div>
            <div class="rounded-2xl bg-white/10 px-5 py-4 text-center backdrop-blur">
                <div class="text-xs text-white/70">{{ $e->pay_basis === 'daily' ? __('اليومية') : __('المرتب الأساسي') }}</div>
                <div class="text-2xl font-extrabold">{{ Money::format($e->basic_salary, false) }}</div>
                <div class="text-[11px] text-white/60">{{ $e->payment_method === 'bank' ? __('🏦 تحويل') : __('💵 كاش') }}</div>
            </div>
        </div>
        @if ($e->contract_end_date)
            @php $cl = (int) today()->diffInDays(Carbon::parse($e->contract_end_date), false); @endphp
            <div class="relative mt-4 inline-block rounded-xl px-3 py-1.5 text-xs font-bold {{ $cl < 0 ? 'bg-rose-500/30' : ($cl <= 30 ? 'bg-amber-500/30' : 'bg-white/10') }}">{{ __('📝 العقد:') }} {{ $e->contract_start_date }} ← {{ $e->contract_end_date }} ({{ $cl < 0 ? __('منتهي') : __('باقي ').$cl.__(' يوم') }})</div>
        @endif
    </section>

    @if ($terminated && $eos)
        <div class="mt-4 rounded-2xl bg-slate-800 p-4 text-sm text-white">{{ __('انتهت خدمته يوم') }} <b>{{ $eos->termination_date }}</b> — {{ $eos->reason }} {{ __('— صافي المخالصة') }} <b>{{ Money::format($eos->net_payout) }}</b> {{ $eos->paid_date ? __('(اتصرفت)') : __('(لسه ماتصرفتش)') }}</div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- هيكل المرتب --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 font-extrabold text-slate-900">{{ __('💼 هيكل المرتب') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ $e->pay_basis === 'daily' ? __('اليومية') : __('الأساسي') }}</dt><dd class="font-bold">{{ Money::format($e->basic_salary, false) }}</dd></div>
                @foreach ($allowances as $a)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ $a->type === 'allowance' ? '+' : '−' }} {{ \App\Support\Lang::pick($a, 'name') }}</dt><dd class="font-bold {{ $a->type === 'deduction' ? 'text-rose-600' : 'text-emerald-600' }}">{{ Money::format($a->amount, false) }}</dd></div>
                @endforeach
                @if ($e->is_insured) <div class="flex justify-between"><dt class="text-slate-500">{{ __('− تأمينات') }} {{ (float) $e->insurance_percent }}%</dt><dd class="font-bold text-rose-600">{{ Money::format(($e->pay_basis === 'daily' ? $daily * $e->working_days_per_month : $e->basic_salary + $allowSum) * $e->insurance_percent / 100, false) }}</dd></div> @endif
                <div class="flex justify-between border-t border-slate-100 pt-2 text-base"><dt class="font-bold">{{ __('الثابت شهرياً') }}</dt><dd class="font-extrabold text-violet-700">{{ Money::format(($e->pay_basis === 'daily' ? $daily * $e->working_days_per_month : $e->basic_salary) + $allowSum - $dedSum, false) }}</dd></div>
            </dl>
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-xl bg-slate-50 p-2.5">{{ __('أجر اليوم') }}<b class="block text-sm">{{ Money::format($daily, false) }}</b></div>
                <div class="rounded-xl bg-slate-50 p-2.5">{{ __('أجر الساعة') }}<b class="block text-sm">{{ Money::format($hourly, false) }}</b></div>
                <div class="rounded-xl p-2.5 {{ $e->overtime_eligible ? 'bg-sky-50 text-sky-800' : 'bg-slate-50 text-slate-400' }}">{{ __('⏱️ أوفر تايم') }}<b class="block text-sm">{{ $e->overtime_eligible ? '×'.(float) $e->overtime_rate.' = '.Money::format($hourly * $e->overtime_rate, false) : __('مش مفعّل') }}</b></div>
                <div class="rounded-xl p-2.5 {{ $e->commission_type !== 'none' ? 'bg-fuchsia-50 text-fuchsia-800' : 'bg-slate-50 text-slate-400' }}">{{ __('💰 عمولة') }}<b class="block text-sm">{{ $e->commission_type === 'none' ? __('مفيش') : ((float) $e->commission_value.($e->commission_type === 'per_car' ? __(' للعربية') : '% '.($e->commission_type === 'profit_percent' ? __('من الربح') : __('من البيع')))) }}</b></div>
            </div>
            @if ($e->payment_method === 'bank') <div class="mt-3 rounded-xl bg-slate-50 p-3 text-xs"><div class="font-bold">{{ $e->bank_name }}</div><div class="font-mono" dir="ltr">{{ $e->iban }}</div></div> @endif
        </section>

        {{-- الحضور + الإجازات --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold text-slate-900">{{ __('🕘 الشهر ده') }}</h2><a href="{{ route('admin.hr.attendance.monthly') }}" class="text-xs font-bold text-violet-600">{{ __('الشيت الشهري ←') }}</a></div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-emerald-50 p-3"><div class="text-2xl font-extrabold text-emerald-700">{{ $att('present') + $att('late') }}</div><div class="text-[11px] text-emerald-700">{{ __('حضور') }}</div></div>
                <div class="rounded-xl bg-rose-50 p-3"><div class="text-2xl font-extrabold text-rose-700">{{ $att('absent') }}</div><div class="text-[11px] text-rose-700">{{ __('غياب') }}</div></div>
                <div class="rounded-xl bg-amber-50 p-3"><div class="text-2xl font-extrabold text-amber-700">{{ $att('late') }}</div><div class="text-[11px] text-amber-700">{{ __('تأخير (') }}{{ $lateMonth }} {{ __('د)') }}</div></div>
            </div>
            @if ($e->overtime_eligible) <div class="mt-2 rounded-xl bg-sky-50 p-2.5 text-center text-sm text-sky-800">{{ __('⏱️ أوفر تايم:') }} <b>{{ $otMonth }} {{ __('ساعة') }}</b> ≈ {{ Money::format($otMonth * $hourly * $e->overtime_rate, false) }}</div> @endif

            <h3 class="mb-2 mt-5 text-sm font-extrabold text-slate-800">{{ __('🌴 رصيد الإجازات') }} {{ now()->year }}</h3>
            @foreach ($balances as $b)
                <div class="mb-2">
                    <div class="flex justify-between text-xs"><span class="font-bold">{{ \App\Support\Lang::pick($b, 'name') }}</span><span class="text-slate-500">{{ $b->remaining !== null ? __('باقي ').$b->remaining.__(' من ').$b->days_per_year : __('اتاخد ').$b->used.__(' يوم') }}</span></div>
                    @if ($b->days_per_year > 0) <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-500" style="width: {{ min(100, $b->used / $b->days_per_year * 100) }}%"></div></div> @endif
                </div>
            @endforeach
            <a href="{{ route('admin.hr.leaves.index', ['employee' => $e->id]) }}" class="mt-2 block text-center text-xs font-bold text-violet-600">{{ __('إجازاته ←') }}</a>
        </section>

        {{-- السلف والحوافز --}}
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center justify-between"><h2 class="font-extrabold text-slate-900">{{ __('💵 السلف والحوافز') }}</h2><a href="{{ route('admin.hr.finance.index') }}" class="text-xs font-bold text-violet-600">{{ __('إدارة ←') }}</a></div>
            @forelse ($loans->where('status', 'active') as $l)
                @php $pct = (float) $l->amount > 0 ? $l->paid_amount / $l->amount * 100 : 0; @endphp
                <div class="mb-3 rounded-xl bg-amber-50 p-3 text-xs">
                    <div class="flex justify-between"><b>{{ __('سلفة') }} {{ Money::format($l->amount, false) }}</b><span>{{ __('قسط') }} {{ Money::format($l->monthly_installment, false) }}</span></div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-amber-500" style="width: {{ $pct }}%"></div></div>
                    <div class="mt-1 text-amber-800">{{ __('باقي') }} {{ Money::format($l->amount - $l->paid_amount, false) }}</div>
                </div>
            @empty <p class="mb-3 text-xs text-slate-400">{{ __('مفيش سلف مفتوحة') }}</p> @endforelse
            <div class="space-y-1.5">
                @foreach ($adjustments->take(6) as $a)
                    <div class="flex justify-between rounded-lg px-2 py-1.5 text-xs {{ $a->type === 'bonus' ? 'bg-emerald-50' : 'bg-rose-50' }}">
                        <span>{{ $a->type === 'bonus' ? '🎁' : '⚠️' }} {{ $a->reason }} <span class="text-slate-400">{{ $a->date }}</span></span>
                        <b class="{{ $a->type === 'bonus' ? 'text-emerald-700' : 'text-rose-700' }}">{{ Money::format($a->amount, false) }}</b>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- المستندات --}}
    <section id="documents" class="mt-6 scroll-mt-24 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="font-extrabold text-slate-900">{{ __('📄 المستندات') }}</h2>
            <button type="button" data-open="docModal" class="rounded-xl bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 hover:bg-violet-100">{{ __('+ مستند') }}</button>
        </div>
        <div class="grid gap-3 p-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($documents as $d)
                @php $left = $d->expiry_date ? (int) today()->diffInDays(Carbon::parse($d->expiry_date), false) : null; @endphp
                <div class="rounded-2xl p-4 ring-1 {{ $left === null ? 'ring-slate-200' : ($left < 0 ? 'bg-rose-50 ring-rose-200' : ($left <= 30 ? 'bg-amber-50 ring-amber-200' : 'ring-slate-200')) }}">
                    <div class="flex items-start justify-between">
                        <div><div class="font-bold text-slate-800">{{ Payroll::DOCUMENTS[$d->document_type] ?? $d->document_type }}</div><div class="font-mono text-xs text-slate-500">{{ $d->document_number }}</div></div>
                        <form method="POST" action="{{ route('admin.hr.documents.destroy', $d->id) }}" onsubmit="{{ __('return confirm(\'مسح المستند؟\')') }}">@csrf @method('DELETE')<button class="text-xs text-rose-500">✕</button></form>
                    </div>
                    <div class="mt-2 text-xs">
                        @if ($left !== null) <span class="font-bold {{ $left < 0 ? 'text-rose-600' : ($left <= 30 ? 'text-amber-700' : 'text-slate-500') }}">{{ $left < 0 ? __('منتهي من ').abs($left).__(' يوم') : __('ينتهي ').$d->expiry_date.__(' (باقي ').$left.__(' يوم)') }}</span> @else <span class="text-slate-400">{{ __('من غير تاريخ انتهاء') }}</span> @endif
                    </div>
                    @if ($d->attachment) <a href="{{ Media::url($d->attachment) }}" target="_blank" class="mt-2 inline-block text-xs font-bold text-violet-600">{{ __('📎 فتح المرفق') }}</a> @endif
                </div>
            @empty
                <p class="col-span-full py-6 text-center text-sm text-slate-400">{{ __('ضيف البطاقة والجواز والإقامة والرخصة بتواريخ انتهائها — والسيستم هينبهك قبلها بشهر.') }}</p>
            @endforelse
        </div>
    </section>

    {{-- المرتبات --}}
    <section class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <h2 class="border-b border-slate-100 px-6 py-4 font-extrabold text-slate-900">{{ __('🧾 كشوف المرتبات') }}</h2>
        @if ($payslips->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-slate-400">{{ __('لسه مفيش مرتبات محسوبة') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-3 text-start">{{ __('الشهر') }}</th><th class="px-3 py-3 text-start">{{ __('الإجمالي') }}</th><th class="px-3 py-3 text-start">{{ __('أوفر تايم') }}</th><th class="px-3 py-3 text-start">{{ __('عمولة') }}</th><th class="px-3 py-3 text-start">{{ __('الخصومات') }}</th><th class="px-3 py-3 text-start">{{ __('الصافي') }}</th><th class="px-6 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($payslips as $p)
                            <tr>
                                <td class="px-6 py-2.5 font-bold">{{ PC::MONTHS[$p->month] }} {{ $p->year }} <span class="text-xs font-normal text-slate-400">{{ PC::RUN_STATUS[$p->run_status] }}</span></td>
                                <td class="px-3 py-2.5">{{ Money::format($p->gross, false) }}</td>
                                <td class="px-3 py-2.5 text-sky-700">{{ (float) $p->overtime_amount ? Money::format($p->overtime_amount, false) : '—' }}</td>
                                <td class="px-3 py-2.5 text-fuchsia-700">{{ (float) $p->commission ? Money::format($p->commission, false) : '—' }}</td>
                                <td class="px-3 py-2.5 text-rose-600">{{ Money::format($p->total_deductions, false) }}</td>
                                <td class="px-3 py-2.5 font-extrabold">{{ Money::format($p->net, false) }}</td>
                                <td class="px-6 py-2.5 text-end"><a href="{{ route('admin.hr.payroll.payslip', $p->id) }}" target="_blank" class="rounded-lg bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700">{{ __('القسيمة') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($e->notes) <p class="mt-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900"><b>{{ __('ملاحظات:') }}</b> {{ $e->notes }}</p> @endif

    {{-- مودال مستند --}}
    <dialog id="docModal" class="{{ $dlg }} max-w-md">
        <form method="POST" action="{{ route('admin.hr.employees.documents.store', $e->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><h2 class="text-lg font-extrabold">{{ __('مستند جديد') }}</h2><button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button></div>
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'name' => 'document_type', 'label' => __('النوع'), 'type' => 'select', 'options' => Payroll::DOCUMENTS, 'required' => true])
                @include('admin.partials.field', ['model' => null, 'name' => 'document_number', 'label' => __('الرقم'), 'dir' => 'ltr'])
                <div class="grid grid-cols-2 gap-4">
                    @include('admin.partials.field', ['model' => null, 'name' => 'issue_date', 'label' => __('تاريخ الإصدار'), 'type' => 'date'])
                    @include('admin.partials.field', ['model' => null, 'name' => 'expiry_date', 'label' => __('تاريخ الانتهاء'), 'type' => 'date'])
                </div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('صورة / PDF') }}</label><input type="file" name="attachment" accept="image/*,.pdf" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></div>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button><button class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button></div>
        </form>
    </dialog>

    {{-- مودال نهاية الخدمة --}}
    @if ($eosPreview)
        <dialog id="eosModal" class="{{ $dlg }} max-w-lg">
            <form method="POST" action="{{ route('admin.hr.employees.end-service', $e->id) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('تأكيد إنهاء خدمة :name؟', ['name' => \App\Support\Lang::pick($e, 'full_name')])) }})">
                @csrf
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div><h2 class="text-lg font-extrabold">{{ __('إنهاء الخدمة والمخالصة') }}</h2><p class="text-xs text-slate-500">{{ __('مدة الخدمة لحد النهارده:') }} {{ $eosPreview['service_years'] }} {{ __('سنة') }}</p></div>
                    <button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button>
                </div>
                <div class="max-h-[65vh] space-y-4 overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-2 gap-4">
                        @include('admin.partials.field', ['model' => null, 'name' => 'termination_date', 'label' => __('آخر يوم شغل'), 'type' => 'date', 'required' => true, 'value' => today()->format('Y-m-d')])
                        @include('admin.partials.field', ['model' => null, 'name' => 'reason', 'label' => __('السبب'), 'required' => true, 'list' => 'eosReasons'])
                    </div>
                    <datalist id="eosReasons"><option value="استقالة"><option value="انتهاء العقد"><option value="فصل"><option value="اتفاق الطرفين"><option value="سفر نهائي"></datalist>
                    @include('admin.partials.field', ['model' => null, 'name' => 'gratuity_days_per_year', 'label' => __('أيام المكافأة عن كل سنة'), 'type' => 'number', 'step' => '0.5', 'min' => 0, 'required' => true, 'value' => 30, 'hint' => __('حسب العقد / القانون')])
                    <div class="rounded-xl bg-slate-50 p-4 text-sm" data-eos>
                        <div class="flex justify-between"><span>{{ __('مكافأة نهاية الخدمة') }}</span><b data-g>{{ Money::format($eosPreview['gratuity_amount'], false) }}</b></div>
                        <div class="flex justify-between"><span>{{ __('رصيد إجازة (') }}{{ $eosPreview['leave_days'] }} {{ __('يوم)') }}</span><b>{{ Money::format($eosPreview['leave_encashment'], false) }}</b></div>
                        @if ($eosPreview['loan_deduction'] > 0)
                            <label class="mt-1 flex cursor-pointer justify-between text-rose-700"><span><input type="checkbox" name="deduct_loans" value="1" checked class="accent-rose-600"> {{ __('خصم باقي السلف') }}</span><b>− {{ Money::format($eosPreview['loan_deduction'], false) }}</b></label>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        @include('admin.partials.field', ['model' => null, 'name' => 'other_additions', 'label' => __('إضافات (مرتب آخر شهر...)'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                        @include('admin.partials.field', ['model' => null, 'name' => 'other_deductions', 'label' => __('خصومات أخرى'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                    </div>
                    @include('admin.partials.field', ['model' => null, 'name' => 'financial_account_id', 'label' => __('اصرف دلوقتي من'), 'type' => 'select', 'options' => $accounts, 'empty' => __('لسه مش هصرف')])
                    @include('admin.partials.field', ['model' => null, 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('رجوع') }}</button><button class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('إنهاء الخدمة') }}</button></div>
            </form>
        </dialog>
    @endif

    <script>
        (function () {
            document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => document.getElementById(b.dataset.open)?.showModal()));
            document.querySelectorAll('dialog').forEach((d) => {
                d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
                d.addEventListener('click', (ev) => { if (ev.target === d) d.close(); });
            });
            const g = document.querySelector('#eosModal [name=gratuity_days_per_year]');
            g?.addEventListener('input', () => {
                const years = {{ $eosPreview['service_years'] ?? 0 }}, daily = {{ $eosPreview['daily_rate'] ?? 0 }};
                document.querySelector('[data-g]').textContent = (years * Number(g.value || 0) * daily).toLocaleString('en', { maximumFractionDigits: 3 });
            });
        })();
    </script>
@endsection
