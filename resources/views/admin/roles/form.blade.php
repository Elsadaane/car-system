@extends('admin.layouts.app')

@php
    use App\Support\Access;
    $checked = old('permissions', $checked);
    $groups = Access::grouped();
    $icons = ['المبيعات' => '🛒', 'المخزون' => '🚗', 'المالية' => '💰', 'العملاء' => '👥', 'الموارد البشرية' => '🧑‍💼', 'التقارير' => '📊', 'الموقع الإلكتروني' => '🌐', 'النظام' => '⚙️'];
    $mine = Access::isSuper(auth()->user());
@endphp

@section('title', $role ? __('صلاحيات: ').$role->name : __('دور جديد'))
@section('subtitle', $copyFrom ? __('منسوخ من "').$copyFrom.__('" — عدّل اللي محتاجه') : __('علّم الصفحات والعمليات المسموحة للدور ده'))

@section('actions')
    <a href="{{ route('admin.roles.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('→ الأدوار') }}</a>
@endsection

@section('content')
<form method="POST" action="{{ $role ? route('admin.roles.update', $role->id) : route('admin.roles.store') }}" onsubmit="this.querySelector('[type=submit]').disabled = true">
    @csrf @if ($role) @method('PUT') @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
    @endif

    <div class="sticky top-16 z-20 mb-5 flex flex-wrap items-center gap-3 rounded-2xl bg-white/95 p-4 shadow-sm ring-1 ring-slate-200 backdrop-blur">
        <input name="name" value="{{ old('name', $role->name ?? '') }}" required placeholder="{{ __('اسم الدور (مثلاً: مدير مبيعات)') }}" class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-base font-bold outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <label class="flex cursor-pointer items-center gap-2 rounded-xl bg-blue-50 px-4 py-2.5 text-sm font-extrabold text-blue-700"><input type="checkbox" id="selectAll" class="h-5 w-5 accent-blue-600"> {{ __('تحديد الكل') }}</label>
        <span class="text-sm font-bold text-slate-500"><span id="count">0</span> {{ __('صلاحية') }}</span>
        <button type="submit" class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('💾 حفظ') }}</button>
    </div>

    <p class="mb-5 rounded-xl bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-500">{{ __('💡 "عرض" هي اللي بتظهر الصفحة في السايدبار. لو شلت "عرض" من قسم، باقي صلاحياته مالهاش لازمة.') }} @unless ($mine)<b class="text-amber-700">{{ __('مش هتقدر تدّي صلاحية إنت نفسك معندكش.') }}</b>@endunless</p>

    <div class="space-y-6">
        @foreach ($groups as $group => $modules)
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-base font-black text-slate-900">{{ $icons[$group] ?? '📁' }} {{ $group }}</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($modules as $key => $m)
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200" data-group>
                            <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-3">
                                <span class="font-extrabold text-slate-800">{{ $m['label'] }}</span>
                                <label class="flex cursor-pointer items-center gap-1.5 text-xs font-bold text-slate-400"><input type="checkbox" data-group-all class="h-4 w-4 accent-blue-600"> {{ __('الكل') }}</label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($m['perms'] as $name => $label)
                                    @php $disabled = ! $mine && ! Access::can($name); @endphp
                                    <label class="cursor-pointer {{ $disabled ? 'pointer-events-none opacity-40' : '' }}">
                                        <input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, $checked, true)) @disabled($disabled) class="peer sr-only" data-perm data-verb="{{ \Illuminate\Support\Str::afterLast($name, '.') }}">
                                        <span class="block rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-500 transition peer-checked:border-blue-500 peer-checked:bg-blue-600 peer-checked:text-white">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</form>
<script>
(function () {
    const all = document.getElementById('selectAll'), perms = [...document.querySelectorAll('[data-perm]:not(:disabled)')];
    const sync = () => {
        document.querySelectorAll('[data-group]').forEach((g) => { const b = [...g.querySelectorAll('[data-perm]:not(:disabled)')]; g.querySelector('[data-group-all]').checked = b.length && b.every((x) => x.checked); });
        all.checked = perms.length && perms.every((x) => x.checked);
        document.getElementById('count').textContent = document.querySelectorAll('[data-perm]:checked').length;
    };
    all.addEventListener('change', () => { perms.forEach((p) => { p.checked = all.checked; }); sync(); });
    document.querySelectorAll('[data-group-all]').forEach((ga) => ga.addEventListener('change', () => { ga.closest('[data-group]').querySelectorAll('[data-perm]:not(:disabled)').forEach((p) => { p.checked = ga.checked; }); sync(); }));
    // أي صلاحية غير "عرض" بتعلّم "عرض" معاها، وشيل "عرض" بيشيل الباقي
    perms.forEach((p) => p.addEventListener('change', () => {
        const box = p.closest('[data-group]'), view = box.querySelector('[data-verb=view]');
        if (p.checked && p !== view && view && !view.disabled) view.checked = true;
        if (!p.checked && p === view) box.querySelectorAll('[data-perm]:not(:disabled)').forEach((x) => { x.checked = false; });
        sync();
    }));
    sync();
})();
</script>
@endsection
