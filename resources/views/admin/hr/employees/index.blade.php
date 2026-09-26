@extends('admin.layouts.app')

@section('title', __('الموظفين'))
@section('subtitle', __('فريق المعرض — نوع التوظيف، المرتبات، المستندات اللي قربت تخلص'))

@php
    use App\Support\Money;
    use App\Support\Media;
    use App\Support\Payroll;
    use Illuminate\Support\Carbon;
    $typeStyle = ['sponsored' => 'bg-blue-50 text-blue-700', 'contract' => 'bg-emerald-50 text-emerald-700', 'freelance' => 'bg-amber-50 text-amber-700'];
    $typeShort = ['sponsored' => __('كفالة'), 'contract' => __('عقد'), 'freelance' => __('بدون عقد')];
    $statusStyle = ['active' => 'bg-emerald-500', 'on_leave' => 'bg-amber-500', 'suspended' => 'bg-rose-500', 'terminated' => 'bg-slate-400'];
@endphp

@section('actions')
    <a href="{{ route('admin.hr.employees.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-violet-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('موظف جديد') }}
    </a>
@endsection

@section('content')
    @include('admin.hr.partials-tabs')

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 p-5 text-white shadow-lg">
            <div class="text-sm text-violet-100">{{ __('على رأس العمل') }}</div>
            <div class="mt-1 text-3xl font-extrabold">{{ $stats['count'] }}</div>
            <div class="mt-1 text-xs text-violet-100">{{ $onLeaveToday }} {{ __('في إجازة النهارده') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('المرتبات الثابتة شهرياً') }}</div>
            <div class="mt-1 truncate text-2xl font-extrabold">{{ Money::format($stats['payroll'], false) }}</div>
            <div class="text-xs text-slate-400">{{ __('أساسي + بدلات (من غير اليومية والأوفر تايم)') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('نوع التوظيف') }}</div>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($typeShort as $k => $l) <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $typeStyle[$k] }}">{{ $l }} {{ (int) ($stats['by_type'][$k] ?? 0) }}</span> @endforeach
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="text-sm text-slate-500">{{ __('طريقة القبض') }}</div>
            <div class="mt-1 text-lg font-extrabold">🏦 {{ $stats['bank'] }} {{ __('تحويل · 💵') }} {{ $stats['count'] - $stats['bank'] }} {{ __('كاش') }}</div>
        </div>
    </div>

    @if ($expiring->isNotEmpty() || $contractsEnding->isNotEmpty())
        <div class="mb-6 rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
            <div class="mb-3 font-extrabold text-amber-900">{{ __('⚠️ محتاجين تجديد خلال 30 يوم') }}</div>
            <div class="flex flex-wrap gap-2">
                @foreach ($expiring as $d)
                    @php $left = (int) today()->diffInDays(Carbon::parse($d->expiry_date), false); @endphp
                    <a href="{{ route('admin.hr.employees.show', $d->employee_id) }}#documents" class="rounded-xl bg-white px-3 py-2 text-xs ring-1 {{ $left < 0 ? 'ring-rose-300' : 'ring-amber-200' }}">
                        <b>{{ \App\Support\Lang::pick($d, 'full_name') }}</b> — {{ Payroll::DOCUMENTS[$d->document_type] ?? $d->document_type }}
                        <span class="font-bold {{ $left < 0 ? 'text-rose-600' : 'text-amber-700' }}">{{ $left < 0 ? __('منتهية من ').abs($left).__(' يوم') : __('باقي ').$left.__(' يوم') }}</span>
                    </a>
                @endforeach
                @foreach ($contractsEnding as $c)
                    <a href="{{ route('admin.hr.employees.show', $c->id) }}" class="rounded-xl bg-white px-3 py-2 text-xs ring-1 ring-amber-200"><b>{{ \App\Support\Lang::pick($c, 'full_name') }}</b> {{ __('— العقد بيخلص') }} {{ $c->contract_end_date }}</a>
                @endforeach
            </div>
        </div>
    @endif

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('الاسم، الكود، التليفون، الرقم المدني، الوظيفة...') }}" class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-50">
        <select name="branch" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل الفروع') }}</option>@foreach ($branches as $id => $n) <option value="{{ $id }}" @selected($filters['branch'] === $id)>{{ $n }}</option> @endforeach</select>
        <select name="department" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل الأقسام') }}</option>@foreach ($departments as $id => $n) <option value="{{ $id }}" @selected($filters['department'] === $id)>{{ $n }}</option> @endforeach</select>
        <select name="type" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل الأنواع') }}</option>@foreach (Payroll::EMPLOYMENT as $k => $n) <option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $n }}</option> @endforeach</select>
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="all">{{ __('كل الحالات') }}</option>@foreach (Payroll::STATUSES as $k => $n) <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $n }}</option> @endforeach</select>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">{{ __('بحث') }}</button>
    </form>

    @if ($employees->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200">
            <div class="text-4xl">👥</div>
            <p class="mt-2 font-bold text-slate-600">{{ __('مفيش موظفين') }}</p>
            <a href="{{ route('admin.hr.employees.create') }}" class="mt-2 inline-block text-sm font-bold text-violet-600 hover:underline">{{ __('ضيف أول موظف') }}</a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($employees as $e)
                @php
                    $img = Media::url($e->profile_image);
                    $exp = $e->next_expiry ? (int) today()->diffInDays(Carbon::parse($e->next_expiry), false) : null;
                @endphp
                <a href="{{ route('admin.hr.employees.show', $e->id) }}" class="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="flex items-start gap-4">
                        <div class="relative">
                            @if ($img) <img src="{{ $img }}" alt="" class="h-14 w-14 rounded-2xl object-cover">
                            @else <span class="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-xl font-extrabold text-white">{{ mb_substr(\App\Support\Lang::pick($e, 'full_name'), 0, 1) }}</span> @endif
                            <span class="absolute -bottom-1 -end-1 h-4 w-4 rounded-full border-2 border-white {{ $statusStyle[$e->status] }}" title="{{ Payroll::STATUSES[$e->status] }}"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-extrabold text-slate-900 group-hover:text-violet-700">{{ \App\Support\Lang::pick($e, 'full_name') }}</div>
                            <div class="truncate text-xs text-slate-500">{{ $e->job_title }}{{ $e->department_name ? ' · '.$e->department_name : '' }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold {{ $typeStyle[$e->employment_type] }}">{{ $typeShort[$e->employment_type] }}</span>
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ $e->payment_method === 'bank' ? __('🏦 تحويل') : __('💵 كاش') }}</span>
                                @if ($e->overtime_eligible) <span class="rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-700">{{ __('أوفر تايم') }}</span> @endif
                                @if ($e->commission_type !== 'none') <span class="rounded-md bg-fuchsia-50 px-1.5 py-0.5 text-[10px] font-bold text-fuchsia-700">{{ __('عمولة') }}</span> @endif
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-end justify-between border-t border-slate-100 pt-3">
                        <div>
                            <div class="text-[11px] text-slate-400">{{ $e->pay_basis === 'daily' ? __('اليومية') : __('المرتب الأساسي') }}</div>
                            <div class="font-extrabold text-slate-900">{{ Money::format($e->basic_salary, false) }}</div>
                        </div>
                        <div class="text-end text-[11px]">
                            <div class="text-slate-400">{{ $e->branch_name ?? '—' }} · {{ $e->employee_code }}</div>
                            @if ($exp !== null && $exp <= 30) <div class="font-bold {{ $exp < 0 ? 'text-rose-600' : 'text-amber-600' }}">{{ __('📄 مستند') }} {{ $exp < 0 ? __('منتهي') : __('بيخلص بعد ').$exp.__(' يوم') }}</div> @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
