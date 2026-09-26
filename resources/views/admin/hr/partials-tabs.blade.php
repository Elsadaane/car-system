{{-- تنقل سريع بين صفحات الـ HR --}}
@php
    $hrTabs = ['employees.index' => __('👥 الموظفين'), 'attendance.index' => __('🕘 الحضور'), 'leaves.index' => __('🌴 الإجازات'), 'finance.index' => __('💵 السلف والحوافز'), 'payroll.index' => __('🧾 المرتبات'), 'settings.index' => __('⚙️ الإعدادات')];
@endphp
<nav class="mb-5 flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200 print:hidden">
    @foreach ($hrTabs as $r => $l)
        @php $on = request()->routeIs('admin.hr.'.\Illuminate\Support\Str::beforeLast($r, '.').'.*'); @endphp
        <a href="{{ route('admin.hr.'.$r) }}" class="shrink-0 rounded-xl px-3.5 py-2 text-sm font-bold transition {{ $on ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $l }}</a>
    @endforeach
</nav>
