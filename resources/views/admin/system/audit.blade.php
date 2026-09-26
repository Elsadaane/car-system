@extends('admin.layouts.app')

@php
    use App\Support\Audit;
    use Illuminate\Support\Carbon;
    $input = 'rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400';
@endphp

@section('title', __('سجل النشاط'))
@section('subtitle', __('مين عمل إيه وإمتى ومن أنهي جهاز — بيتسجل أوتوماتيك ومحدش يقدر يعدّله'))

@section('content')
    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([[__('عمليات آخر 24 ساعة'), $stats['today'], 'bg-white text-slate-900', null], [__('مسح آخر 24 ساعة'), $stats['deletes'], 'bg-white text-rose-600', 'delete'], [__('دخول غلط آخر 24 ساعة'), $stats['failed'], $stats['failed'] >= 5 ? 'bg-amber-500 text-white' : 'bg-white text-amber-600', 'login_failed'], [__('محاولات بدون صلاحية'), $stats['denied'], $stats['denied'] ? 'bg-rose-600 text-white' : 'bg-white text-slate-900', 'denied']] as [$l, $n, $tone, $ev])
            <a href="{{ $ev ? route('admin.audit.index', ['event' => $ev]) : '#' }}" class="rounded-2xl p-5 shadow-sm ring-1 ring-slate-200 {{ $tone }}"><div class="text-sm opacity-70">{{ $l }}</div><div class="mt-1 text-3xl font-black">{{ $n }}</div></a>
        @endforeach
    </div>

    <form class="mb-4 flex flex-wrap gap-2 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('🔍 بحث: رقم، كلمة، IP...') }}" class="{{ $input }} min-w-[200px] flex-1">
        <select name="user" class="{{ $input }}"><option value="">{{ __('كل الموظفين') }}</option>@foreach ($users as $id => $n)<option value="{{ $id }}" @selected($filters['user'] === $id)>{{ $n }}</option>@endforeach</select>
        <select name="event" class="{{ $input }}" data-no-search><option value="">{{ __('كل الأنواع') }}</option>@foreach (Audit::EVENTS as $k => [$l])<option value="{{ $k }}" @selected($filters['event'] === $k)>{{ $l }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="{{ $input }}">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="{{ $input }}">
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">{{ __('فلترة') }}</button>
        @if (array_filter($filters))<a href="{{ route('admin.audit.index') }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-600">{{ __('مسح') }}</a>@endif
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full min-w-[860px] text-sm">
            <thead class="bg-slate-50 text-xs font-bold text-slate-500"><tr><th class="p-3 text-start">{{ __('الوقت') }}</th><th class="p-3 text-start">{{ __('الموظف') }}</th><th class="p-3 text-start">{{ __('النوع') }}</th><th class="p-3 text-start">{{ __('العملية') }}</th><th class="p-3 text-start">{{ __('الجهاز') }}</th><th class="p-3"></th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    @php [$label, $tone] = Audit::EVENTS[$log->event] ?? [$log->event, 'bg-slate-100 text-slate-600']; $data = $log->data ? json_decode($log->data, true) : null; $t = Carbon::parse($log->created_at); @endphp
                    <tr class="border-t border-slate-100 align-top">
                        <td class="whitespace-nowrap p-3"><div class="font-bold text-slate-800">{{ $t->format('Y-m-d H:i') }}</div><div class="text-[11px] text-slate-400">{{ $t->diffForHumans() }}</div></td>
                        <td class="p-3 font-bold text-slate-700">{{ $log->user_name ?? '—' }}</td>
                        <td class="p-3"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-bold {{ $tone }}">{{ $label }}</span></td>
                        <td class="p-3">
                            <div class="font-semibold text-slate-800">{{ app()->getLocale() === 'en' && $log->route && in_array($log->event, ['create', 'update', 'delete', 'action', 'denied'], true) ? Audit::describe($log->route, $log->method) : ($log->description ?: Audit::describe($log->route, $log->method)) }}@if ($log->subject_id)<span class="ms-1 font-mono text-xs text-slate-400">#{{ $log->subject_id }}</span>@endif</div>
                            @if ($data)
                                <details class="mt-1"><summary class="cursor-pointer text-xs font-bold text-blue-600">{{ __('البيانات اللي اتبعتت') }}</summary>
                                    <pre class="mt-1 max-h-60 overflow-auto rounded-lg bg-slate-50 p-2 text-[11px] leading-relaxed text-slate-600" dir="ltr">{{ json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td class="p-3 text-xs text-slate-500"><div dir="ltr" class="font-mono">{{ $log->ip }}</div><div class="max-w-[180px] truncate" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit((string) $log->user_agent, 40) }}</div></td>
                        <td class="p-3">@if ($log->user_id)<a href="{{ route('admin.audit.index', ['user' => $log->user_id]) }}" class="text-xs font-bold text-slate-400 hover:text-blue-600" title="{{ __('كل نشاط الموظف ده') }}">👤</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-12 text-center text-slate-400">{{ __('مفيش نشاط مطابق') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
    <p class="mt-3 text-xs text-slate-400">{{ __('🔒 السجل بيتحفظ سنة، وبعدها بيتمسح لوحده. كلمات السر والتوقيعات عمرها ما بتتسجل.') }}</p>
@endsection
