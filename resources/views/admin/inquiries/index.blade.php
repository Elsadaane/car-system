@extends('admin.layouts.app')

@php
    use App\Http\Controllers\Admin\InquiryController as CRM;
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $input = 'rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400';
    // Tailwind لازم يشوف أسماء الكلاسات كاملة
    $tone = [
        'amber'   => ['bg-amber-50', 'text-amber-700', 'bg-amber-500'],
        'sky'     => ['bg-sky-50', 'text-sky-700', 'bg-sky-500'],
        'violet'  => ['bg-violet-50', 'text-violet-700', 'bg-violet-500'],
        'emerald' => ['bg-emerald-50', 'text-emerald-700', 'bg-emerald-500'],
        'slate'   => ['bg-slate-100', 'text-slate-600', 'bg-slate-400'],
        'rose'    => ['bg-rose-50', 'text-rose-700', 'bg-rose-500'],
    ];
    $srcLabel = fn ($s) => CRM::SOURCES[strtolower((string) $s)] ?? ($s ?: __('🌐 الموقع'));
    $tabs = ['board' => __('🗂️ مراحل البيع'), 'list' => __('📋 قائمة'), 'campaigns' => __('📈 أداء الإعلانات')];
    $keep = array_filter($filters);
    $digits = fn ($p) => preg_replace('/\D/', '', (string) $p);
@endphp

@section('title', __('طلبات الموقع'))
@section('subtitle', __('كل عميل كلّمك من الموقع أو الإعلانات — اسحبه من مرحلة لمرحلة لحد ما يشتري'))

@section('content')
    {{-- الأرقام المهمة --}}
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @php
            $resp = $stats['response'];
            $respText = $resp === null ? '—' : ($resp < 60 ? $resp.__(' دقيقة') : ($resp < 1440 ? round($resp / 60, 1).__(' ساعة') : round($resp / 1440, 1).__(' يوم')));
        @endphp
        @foreach ([
            [__('🆕 جديد مستني رد'), $stats['new'], $stats['new'] ? 'bg-amber-500 text-white' : 'bg-white text-slate-900', ['view' => 'list', 'status' => 'new']],
            [__('⏰ متأخر أكتر من يوم'), $stats['late'], $stats['late'] ? 'bg-rose-600 text-white' : 'bg-white text-slate-900', ['view' => 'list', 'status' => 'new']],
            [__('🔄 شغالين عليه'), $stats['working'], 'bg-white text-sky-700', ['view' => 'board']],
            [__('✅ اتباع الشهر ده'), $stats['won_month'], 'bg-white text-emerald-600', ['view' => 'list', 'status' => 'converted']],
            [__('🎯 نسبة التحويل (90 يوم)'), $stats['conversion'].'%', 'bg-white text-violet-700', ['view' => 'campaigns', 'period' => '90']],
            [__('⚡ متوسط سرعة الرد'), $respText, $resp !== null && $resp > 120 ? 'bg-white text-rose-600' : 'bg-white text-slate-900', null],
        ] as [$l, $n, $cls, $link])
            <a href="{{ $link ? route('admin.inquiries.index', $link) : '#' }}" class="rounded-2xl p-4 shadow-sm ring-1 ring-slate-200 {{ $cls }}">
                <div class="text-xs opacity-75">{{ $l }}</div>
                <div class="mt-1 text-2xl font-black">{{ $n }}</div>
            </a>
        @endforeach
    </div>

    @if ($stats['unassigned'])
        <a href="{{ route('admin.inquiries.index', ['view' => $view === 'campaigns' ? 'board' : $view, 'assigned' => 'none']) }}" class="mb-4 flex items-center gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 ring-1 ring-amber-200">
            {{ __('👀 فيه') }} {{ $stats['unassigned'] }} {{ __('طلب مفتوح محدش ماسكه — دوس هنا ووزّعهم') }}
        </a>
    @endif

    {{-- التابات --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($tabs as $k => $l)
            <a href="{{ route('admin.inquiries.index', ['view' => $k] + ($k === 'campaigns' ? [] : $keep)) }}"
               class="rounded-xl px-4 py-2.5 text-sm font-bold {{ $view === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $l }}</a>
        @endforeach
    </div>

    @if ($view !== 'campaigns')
        <form class="mb-4 flex flex-wrap gap-2 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('🔍 اسم، موبايل، رقم الطلب...') }}" class="{{ $input }} min-w-[200px] flex-1">
            <select name="type" class="{{ $input }}" data-no-search><option value="">{{ __('كل الأنواع') }}</option>@foreach (CRM::TYPES as $k => $l)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $l }}</option>@endforeach</select>
            <select name="source" class="{{ $input }}" data-no-search><option value="">{{ __('كل المصادر') }}</option>@foreach ($sources as $s)<option value="{{ $s }}" @selected($filters['source'] === $s)>{{ $srcLabel($s) }}</option>@endforeach</select>
            <select name="assigned" class="{{ $input }}"><option value="">{{ __('كل الموظفين') }}</option><option value="me" @selected($filters['assigned'] === 'me')>{{ __('👤 طلباتي أنا') }}</option><option value="none" @selected($filters['assigned'] === 'none')>{{ __('⚠️ محدش ماسكها') }}</option>@foreach ($staff as $id => $n)<option value="{{ $id }}" @selected((string) $filters['assigned'] === (string) $id)>{{ $n }}</option>@endforeach</select>
            @if ($view === 'list')
                <select name="status" class="{{ $input }}" data-no-search><option value="">{{ __('كل المراحل') }}</option>@foreach (CRM::STAGES as $k => [$l])<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option>@endforeach</select>
            @endif
            <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">{{ __('فلترة') }}</button>
            @if ($keep)<a href="{{ route('admin.inquiries.index', ['view' => $view]) }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-600">{{ __('مسح') }}</a>@endif
        </form>
    @endif

    {{-- ===================== لوحة المراحل ===================== --}}
    @if ($view === 'board')
        <p class="mb-3 text-xs text-slate-500">{{ __('💡 اسحب الكارت وحطه في المرحلة الجديدة — ودوس عليه عشان تكتب ملاحظة أو تحوّله لموظف.') }}</p>
        <div class="flex gap-3 overflow-x-auto pb-4" id="crm-board">
            @foreach ($columns as $st => $col)
                @php [$label, $color] = CRM::STAGES[$st]; [$bg, $txt, $dot] = $tone[$color]; @endphp
                <section class="flex w-64 shrink-0 flex-col rounded-2xl {{ $bg }} p-2" data-stage="{{ $st }}">
                    <header class="mb-2 flex items-center justify-between px-2 pt-1">
                        <span class="text-sm font-extrabold {{ $txt }}">{{ $label }}</span>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-black {{ $txt }}" data-count>{{ $col['count'] }}</span>
                    </header>
                    @if (in_array($st, ['converted', 'closed'], true))<div class="mb-2 px-2 text-[11px] {{ $txt }} opacity-70">{{ __('آخر 30 يوم') }}</div>@endif
                    <div class="flex min-h-[120px] flex-1 flex-col gap-2 rounded-xl p-1 transition" data-drop>
                        @foreach ($col['rows'] as $i)
                            @include('admin.inquiries.card', ['i' => $i])
                        @endforeach
                        @if ($col['count'] > $col['rows']->count())
                            <a href="{{ route('admin.inquiries.index', ['view' => 'list', 'status' => $st] + $keep) }}" class="rounded-xl bg-white/70 p-2 text-center text-xs font-bold {{ $txt }}">+ {{ $col['count'] - $col['rows']->count() }} {{ __('كمان — شوفهم في القائمة') }}</a>
                        @endif
                    </div>
                </section>
            @endforeach
            {{-- منطقة السبام --}}
            <section class="flex w-40 shrink-0 flex-col rounded-2xl border-2 border-dashed border-rose-200 p-2" data-stage="spam">
                <header class="mb-2 px-2 pt-1 text-sm font-extrabold text-rose-600">{{ __('🚫 سبام') }}</header>
                <div class="flex min-h-[120px] flex-1 items-center justify-center rounded-xl p-1 text-center text-xs text-rose-400 transition" data-drop>{{ __('اسحب هنا أي طلب وهمي') }}</div>
                <a href="{{ route('admin.inquiries.index', ['view' => 'list', 'status' => 'spam']) }}" class="mt-2 text-center text-[11px] font-bold text-rose-500">{{ __('عرض السبام') }}</a>
            </section>
        </div>
    @endif

    {{-- ===================== القائمة ===================== --}}
    @if ($view === 'list')
        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-xs font-bold text-slate-500"><tr>
                    <th class="p-3 text-start">{{ __('العميل') }}</th><th class="p-3 text-start">{{ __('الطلب') }}</th><th class="p-3 text-start">{{ __('العربية') }}</th><th class="p-3 text-start">{{ __('المصدر') }}</th><th class="p-3 text-start">{{ __('المرحلة') }}</th><th class="p-3 text-start">{{ __('مع مين') }}</th><th class="p-3 text-start">{{ __('من إمتى') }}</th><th class="p-3"></th>
                </tr></thead>
                <tbody>
                    @forelse ($rows as $i)
                        @php [$label, $color] = CRM::STAGES[$i->status] ?? [$i->status, 'slate']; @endphp
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="p-3"><div class="font-bold text-slate-800">{{ $i->name }}</div><div class="font-mono text-xs text-slate-400" dir="ltr">{{ $i->phone }}</div></td>
                            <td class="p-3"><div class="font-semibold">{{ CRM::TYPES[$i->type] ?? $i->type }}</div><div class="font-mono text-[11px] text-slate-400">{{ $i->inquiry_number }}</div></td>
                            <td class="p-3 text-slate-600">{{ $i->brand ? trim($i->brand.' '.$i->model.' '.$i->year) : '—' }}</td>
                            <td class="p-3 text-xs"><div>{{ $srcLabel($i->source) }}</div>@if ($i->utm_campaign)<div class="text-slate-400">{{ $i->utm_campaign }}</div>@endif</td>
                            <td class="p-3"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-bold {{ $tone[$color][0] }} {{ $tone[$color][1] }}">{{ $label }}</span></td>
                            <td class="p-3 text-slate-600">{{ $i->assignee ?? '—' }}</td>
                            <td class="whitespace-nowrap p-3 text-xs text-slate-500">{{ Carbon::parse($i->created_at)->diffForHumans() }}</td>
                            <td class="whitespace-nowrap p-3 text-end">
                                <a href="https://wa.me/{{ $digits($i->phone) }}" target="_blank" class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ __('واتساب') }}</a>
                                <button type="button" class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700" data-inq="{{ json_encode(['id' => $i->id, 'name' => $i->name, 'phone' => $i->phone, 'email' => $i->email, 'number' => $i->inquiry_number, 'type' => CRM::TYPES[$i->type] ?? $i->type, 'message' => $i->message, 'car' => $i->brand ? trim($i->brand.' '.$i->model.' '.$i->year) : null, 'date' => $i->preferred_date, 'source' => $srcLabel($i->source), 'campaign' => $i->utm_campaign, 'status' => $i->status, 'assigned' => $i->assigned_to, 'notes' => $i->staff_notes, 'created' => Carbon::parse($i->created_at)->format('Y-m-d H:i')], JSON_UNESCAPED_UNICODE) }}">{{ __('تفاصيل') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-12 text-center text-slate-400">{{ __('مفيش طلبات مطابقة') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif

    {{-- ===================== أداء الإعلانات ===================== --}}
    @if ($view === 'campaigns')
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach (['month' => __('الشهر ده'), 'last_month' => __('الشهر اللي فات'), '30' => __('آخر 30 يوم'), '90' => __('آخر 90 يوم')] as $k => $l)
                <a href="{{ route('admin.inquiries.index', ['view' => 'campaigns', 'period' => $k]) }}" class="rounded-xl px-4 py-2 text-sm font-bold {{ $period === $k ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">{{ $l }}</a>
            @endforeach
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach ([
                [__('📥 طلبات'), number_format($totals['leads']), 'text-slate-900'],
                [__('🤝 مبيعات فعلية'), number_format($totals['sales']), 'text-emerald-600'],
                [__('💰 إيراد المبيعات'), Money::format($totals['revenue'], false), 'text-emerald-700'],
                [__('📣 صرف الإعلانات'), $canSpend ? Money::format($totals['spend'], false) : '—', 'text-rose-600'],
                ['📈 ROAS', $totals['roas'] !== null ? $totals['roas'].'x' : '—', $totals['roas'] !== null && $totals['roas'] < 1 ? 'text-rose-600' : 'text-violet-700'],
            ] as [$l, $n, $c])
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><div class="text-xs text-slate-500">{{ $l }}</div><div class="mt-1 truncate text-2xl font-black {{ $c }}">{{ $n }}</div></div>
            @endforeach
        </div>

        <div class="mb-6 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-xs font-bold text-slate-500"><tr>
                    <th class="p-3 text-start">{{ __('المصدر / الحملة') }}</th><th class="p-3 text-center">{{ __('طلبات') }}</th><th class="p-3 text-center">{{ __('مهتم') }}</th><th class="p-3 text-center">{{ __('اتباع') }}</th><th class="p-3 text-center">{{ __('نسبة التحويل') }}</th><th class="p-3 text-center">{{ __('المبيعات') }}</th><th class="p-3 text-center">{{ __('الإيراد') }}</th>
                    @if ($canSpend)<th class="p-3 text-center">{{ __('الصرف') }}</th><th class="p-3 text-center">CPL</th><th class="p-3 text-center">{{ __('تكلفة البيعة') }}</th><th class="p-3 text-center">ROAS</th>@endif
                </tr></thead>
                <tbody>
                    @forelse ($campaignRows as $r)
                        <tr class="border-t border-slate-100">
                            <td class="p-3"><div class="font-bold text-slate-800">{{ $srcLabel($r['source']) }}</div><div class="text-xs text-slate-400">{{ $r['campaign'] ?: __('بدون اسم حملة') }}</div></td>
                            <td class="p-3 text-center font-bold">{{ $r['leads'] }}</td>
                            <td class="p-3 text-center">{{ $r['qualified'] }}</td>
                            <td class="p-3 text-center">{{ $r['won'] }}</td>
                            <td class="p-3 text-center">{{ $r['rate'] }}%</td>
                            <td class="p-3 text-center font-bold text-emerald-600">{{ $r['sales'] }}</td>
                            <td class="p-3 text-center font-bold">{{ Money::format($r['revenue'], false) }}</td>
                            @if ($canSpend)
                                <td class="p-3 text-center text-rose-600">{{ $r['spend'] ? Money::format($r['spend'], false) : '—' }}</td>
                                <td class="p-3 text-center">{{ $r['cpl'] !== null ? Money::format($r['cpl'], false) : '—' }}</td>
                                <td class="p-3 text-center">{{ $r['cps'] !== null ? Money::format($r['cps'], false) : '—' }}</td>
                                <td class="p-3 text-center">
                                    @if ($r['roas'] !== null)
                                        <span class="rounded-full px-2 py-0.5 text-xs font-black {{ $r['roas'] >= 3 ? 'bg-emerald-100 text-emerald-700' : ($r['roas'] >= 1 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">{{ $r['roas'] }}x</span>
                                    @else — @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="11" class="p-12 text-center text-slate-400">{{ __('مفيش طلبات في الفترة دي') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mb-6 rounded-2xl bg-blue-50 p-4 text-xs leading-6 text-blue-900 ring-1 ring-blue-100">
            <b>{{ __('إزاي الأرقام دي بتتحسب؟') }}</b><br>
            {{ __('• المبيعة بتتحسب للحملة لو العميل اشترى (عقد بيع)') }} <b>{{ __('بعد') }}</b> {{ __('ما بعت الطلب — بنربطهم بالعميل أو بآخر 8 أرقام من الموبايل، حتى لو الموظف نسي يحرّك الكارت.') }}<br>
            {{ __('• عشان اسم الحملة يظهر، حط في رابط الإعلان:') }} <code dir="ltr" class="rounded bg-white px-1">{{ __('?utm_source=facebook&amp;utm_campaign=اسم-الحملة') }}</code><br>
            {{ __('• ROAS = إيراد المبيعات ÷ صرف الإعلان.') }} <span class="font-bold text-emerald-700">{{ __('3x أو أكتر ممتاز') }}</span> · <span class="font-bold text-amber-700">{{ __('1x–3x محتاج متابعة') }}</span> · <span class="font-bold text-rose-700">{{ __('أقل من 1x خسران') }}</span>
        </div>

        @if ($canSpend)
            <div class="grid gap-5 lg:grid-cols-2">
                <form method="POST" action="{{ route('admin.inquiries.spend.store') }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    @csrf
                    <h3 class="mb-1 font-extrabold text-slate-900">{{ __('➕ سجّل صرف إعلان') }}</h3>
                    <p class="mb-4 text-xs text-slate-500">{{ __('اكتب اللي اتصرف على كل حملة في الشهر (من Ads Manager) — اسم الحملة لازم يكون نفس utm_campaign.') }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm font-bold text-slate-700">{{ __('المنصة') }}
                            <select name="source" class="{{ $input }} mt-1 w-full" data-no-search required>@foreach (CRM::SOURCES as $k => $l)<option value="{{ $k }}" @selected(old('source') === $k)>{{ $l }}</option>@endforeach</select>
                        </label>
                        <label class="text-sm font-bold text-slate-700">{{ __('الشهر') }}
                            <input type="month" name="month" value="{{ old('month', now()->format('Y-m')) }}" class="{{ $input }} mt-1 w-full" required>
                        </label>
                        <label class="text-sm font-bold text-slate-700 sm:col-span-2">{{ __('اسم الحملة') }} <span class="font-normal text-slate-400">{{ __('(اختياري)') }}</span>
                            <input name="campaign" value="{{ old('campaign') }}" list="crm-campaigns" class="{{ $input }} mt-1 w-full" dir="auto" placeholder="{{ __('مثلاً: ramadan-offers') }}">
                            <datalist id="crm-campaigns">@foreach ($campaignNames as $c)<option value="{{ $c }}">@endforeach</datalist>
                        </label>
                        <label class="text-sm font-bold text-slate-700">{{ __('المبلغ') }} @if (Money::currency())({{ Money::currency() }})@endif
                            <input type="number" step="0.001" min="0.001" name="amount" value="{{ old('amount') }}" class="{{ $input }} mt-1 w-full" required>
                        </label>
                        <label class="text-sm font-bold text-slate-700">{{ __('ملاحظة') }}
                            <input name="notes" value="{{ old('notes') }}" class="{{ $input }} mt-1 w-full">
                        </label>
                    </div>
                    @if ($errors->any())<div class="mt-3 rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-700">{{ $errors->first() }}</div>@endif
                    <button class="mt-4 w-full rounded-xl bg-blue-600 py-3 text-sm font-bold text-white hover:bg-blue-700">{{ __('حفظ') }}</button>
                </form>

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h3 class="mb-3 font-extrabold text-slate-900">{{ __('📣 الصرف المسجّل في الفترة') }}</h3>
                    <div class="divide-y divide-slate-100">
                        @forelse ($spends as $sp)
                            <div class="flex items-center justify-between gap-2 py-2 text-sm">
                                <div class="min-w-0"><div class="truncate font-bold text-slate-800">{{ $srcLabel($sp->source) }} @if ($sp->campaign)<span class="font-normal text-slate-400">· {{ $sp->campaign }}</span>@endif</div><div class="text-xs text-slate-400">{{ $sp->month }}@if ($sp->notes) · {{ $sp->notes }}@endif</div></div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="font-extrabold text-rose-600">{{ Money::format($sp->amount, false) }}</span>
                                    <form method="POST" action="{{ route('admin.inquiries.spend.destroy', $sp->id) }}" onsubmit="{{ __('return confirm(\'تمسح السطر ده؟\')') }}">@csrf @method('DELETE')<button class="rounded-lg px-2 py-1 text-xs text-slate-400 hover:bg-rose-50 hover:text-rose-600">✕</button></form>
                                </div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-slate-400">{{ __('لسه مفيش صرف متسجّل — من غيره مش هنقدر نحسب ROAS') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ===================== نافذة التفاصيل ===================== --}}
    <dialog id="crm-dialog" class="m-auto max-h-[92vh] w-[calc(100%-2rem)] max-w-lg rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/50">
        <form method="POST" id="crm-form" class="p-5">
            @csrf @method('PATCH')
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate text-lg font-extrabold text-slate-900" data-f="name"></h3>
                    <div class="text-xs text-slate-400"><span data-f="number" class="font-mono"></span> · <span data-f="created"></span></div>
                </div>
                <button type="button" class="rounded-lg px-2 text-xl text-slate-400 hover:bg-slate-100" data-close>✕</button>
            </div>

            <div class="mb-4 grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-xl bg-slate-50 p-2"><div class="text-[11px] text-slate-400">{{ __('الموبايل') }}</div><div class="font-mono font-bold" dir="ltr" data-f="phone"></div></div>
                <div class="rounded-xl bg-slate-50 p-2"><div class="text-[11px] text-slate-400">{{ __('نوع الطلب') }}</div><div class="font-bold" data-f="type"></div></div>
                <div class="rounded-xl bg-slate-50 p-2"><div class="text-[11px] text-slate-400">{{ __('العربية') }}</div><div class="font-bold" data-f="car"></div></div>
                <div class="rounded-xl bg-slate-50 p-2"><div class="text-[11px] text-slate-400">{{ __('المصدر') }}</div><div class="font-bold"><span data-f="source"></span> <span class="text-xs font-normal text-slate-400" data-f="campaign"></span></div></div>
                <div class="col-span-2 hidden rounded-xl bg-slate-50 p-2" data-wrap="date"><div class="text-[11px] text-slate-400">{{ __('الميعاد المفضّل') }}</div><div class="font-bold" data-f="date"></div></div>
                <div class="col-span-2 hidden rounded-xl bg-amber-50 p-2" data-wrap="message"><div class="text-[11px] text-amber-700">{{ __('رسالة العميل') }}</div><div class="whitespace-pre-line text-slate-800" data-f="message"></div></div>
            </div>

            <div class="mb-4 flex gap-2">
                <a data-wa target="_blank" class="flex-1 rounded-xl bg-emerald-500 py-2.5 text-center text-sm font-bold text-white hover:bg-emerald-600">{{ __('💬 واتساب') }}</a>
                <a data-tel class="flex-1 rounded-xl bg-sky-500 py-2.5 text-center text-sm font-bold text-white hover:bg-sky-600">{{ __('📞 اتصال') }}</a>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-bold text-slate-700">{{ __('المرحلة') }}
                    <select name="status" class="{{ $input }} mt-1 w-full" data-no-search>@foreach (CRM::STAGES as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-bold text-slate-700">{{ __('مع مين') }}
                    <select name="assigned_to" class="{{ $input }} mt-1 w-full" data-no-search><option value="">{{ __('— محدش —') }}</option>@foreach ($staff as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-bold text-slate-700 sm:col-span-2">{{ __('ملاحظات المتابعة') }}
                    <textarea name="staff_notes" rows="4" class="{{ $input }} mt-1 w-full" placeholder="{{ __('اتكلمنا يوم كذا، عايز يشوف العربية الخميس، ميزانيته...') }}"></textarea>
                </label>
            </div>
            <button class="mt-4 w-full rounded-xl bg-slate-900 py-3 text-sm font-bold text-white hover:bg-slate-800">{{ __('حفظ') }}</button>
        </form>
    </dialog>

    <script>
    (() => {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const statusUrl = @js(route('admin.inquiries.status', 0));
        const moveUrl = @js(route('admin.inquiries.move', 0));
        const withId = (url, id) => url.replace(/\/0\/(status|move)$/, `/${id}/$1`);

        // ---------- نافذة التفاصيل ----------
        const dlg = document.getElementById('crm-dialog');
        const form = document.getElementById('crm-form');
        const openDetails = (d) => {
            form.action = withId(statusUrl, d.id);
            dlg.querySelectorAll('[data-f]').forEach(el => el.textContent = d[el.dataset.f] || '—');
            ['date', 'message'].forEach(k => dlg.querySelector(`[data-wrap="${k}"]`).classList.toggle('hidden', !d[k]));
            dlg.querySelector('[data-f="campaign"]').textContent = d.campaign || '';
            const digits = String(d.phone || '').replace(/\D/g, '');
            dlg.querySelector('[data-wa]').href = 'https://wa.me/' + digits;
            dlg.querySelector('[data-tel]').href = 'tel:' + (d.phone || '');
            form.status.value = d.status;
            form.assigned_to.value = d.assigned || '';
            form.staff_notes.value = d.notes || '';
            dlg.showModal();
        };
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-inq]');
            if (btn && !e.target.closest('a')) openDetails(JSON.parse(btn.dataset.inq));
            if (e.target.closest('[data-close]') || e.target === dlg) dlg.close();
        });

        // ---------- السحب والإفلات ----------
        const board = document.getElementById('crm-board');
        if (!board) return;
        let dragged = null, from = null;
        board.addEventListener('dragstart', (e) => {
            const card = e.target.closest('[data-card]');
            if (!card) return;
            dragged = card; from = card.closest('[data-stage]');
            e.dataTransfer.effectAllowed = 'move';
            requestAnimationFrame(() => card.classList.add('opacity-40'));
        });
        board.addEventListener('dragend', () => { dragged?.classList.remove('opacity-40'); board.querySelectorAll('[data-drop]').forEach(z => z.classList.remove('ring-2', 'ring-blue-400', 'bg-white/60')); });
        board.querySelectorAll('[data-drop]').forEach(zone => {
            zone.addEventListener('dragover', (e) => { if (!dragged) return; e.preventDefault(); zone.classList.add('ring-2', 'ring-blue-400', 'bg-white/60'); });
            zone.addEventListener('dragleave', () => zone.classList.remove('ring-2', 'ring-blue-400', 'bg-white/60'));
            zone.addEventListener('drop', async (e) => {
                e.preventDefault();
                const col = zone.closest('[data-stage]');
                const card = dragged; dragged = null;
                if (!card || col === from) return;
                const stage = col.dataset.stage, prev = from, next = card.nextSibling;
                const bump = (c, n) => { const el = c.querySelector('[data-count]'); if (el) el.textContent = Math.max(0, +el.textContent + n); };
                if (stage === 'spam') card.remove(); else zone.prepend(card);
                bump(prev, -1); bump(col, 1);
                try {
                    const res = await fetch(withId(moveUrl, card.dataset.card), {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ status: stage }),
                    });
                    if (!res.ok) throw new Error(res.status);
                    const d = JSON.parse(card.dataset.inq); d.status = stage; card.dataset.inq = JSON.stringify(d);
                    card.querySelector('[data-sla]')?.remove();
                } catch (err) {
                    prev.querySelector('[data-drop]').insertBefore(card, next && next.parentNode ? next : null);
                    bump(prev, 1); bump(col, -1);
                    alert(@js(__('ماقدرناش نحرّك الطلب — جرّب تاني أو اعمل تحديث للصفحة.')));
                }
            });
        });
    })();
    </script>
@endsection
