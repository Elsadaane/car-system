@extends('admin.layouts.app')

@php
    use App\Support\Access;
    $u = $user;
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-50';
    $role = old('role', $current ?? ($u && $u->role === 'admin' ? Access::SUPER_ROLE : null));
    $meRestricted = Access::branchIds() !== null;
@endphp

@section('title', $u ? __('تعديل: ').$u->name : __('مستخدم جديد'))
@section('subtitle', __('البيانات، الدور، والفروع اللي يشوفها'))

@section('actions')
    <a href="{{ route('admin.users.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('→ المستخدمين') }}</a>
@endsection

@section('content')
<form method="POST" action="{{ $u ? route('admin.users.update', $u->id) : route('admin.users.store') }}" class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_340px]" onsubmit="this.querySelector('[type=submit]').disabled = true">
    @csrf @if ($u) @method('PUT') @endif

    <div class="space-y-5">
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 font-extrabold text-slate-900">{{ __('👤 البيانات') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('الاسم *') }}</label><input name="name" value="{{ old('name', $u->name ?? '') }}" required class="{{ $input }}"></div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('الموبايل') }}</label><input name="phone" value="{{ old('phone', $u->phone ?? '') }}" dir="ltr" class="{{ $input }}"></div>
                <div class="sm:col-span-2"><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('الإيميل (بيدخل بيه) *') }}</label><input type="email" name="email" value="{{ old('email', $u->email ?? '') }}" required dir="ltr" autocomplete="off" class="{{ $input }}"></div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('كلمة السر') }} {{ $u ? __('(سيبها فاضية لو مش هتغيّرها)') : '*' }}</label>
                    <div class="relative"><input type="password" name="password" id="pw" {{ $u ? '' : 'required' }} autocomplete="new-password" dir="ltr" class="{{ $input }} pe-10"><button type="button" onclick="const i = document.getElementById('pw'); i.type = i.type === 'password' ? 'text' : 'password'" class="absolute end-2 top-1/2 -translate-y-1/2 text-slate-400">👁️</button></div>
                    <p class="mt-1 text-xs text-slate-400">{{ __('8 حروف على الأقل، فيها حروف وأرقام') }}</p>
                </div>
                <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('تأكيد كلمة السر') }}</label><input type="password" name="password_confirmation" autocomplete="new-password" dir="ltr" class="{{ $input }}"></div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-1 font-extrabold text-slate-900">{{ __('🛡️ الدور الوظيفي') }}</h2>
            <p class="mb-4 text-xs text-slate-400">{{ __('الدور بيحدد الصفحات والعمليات المسموحة. تقدر تعدّل صلاحيات أي دور من') }} <a href="{{ route('admin.roles.index') }}" class="font-bold text-blue-600">{{ __('الأدوار والصلاحيات') }}</a>.</p>
            @if ($isSelf)
                <div class="rounded-xl bg-amber-50 p-4 text-sm font-bold text-amber-800">{{ __('🔒 مينفعش تغيّر دورك إنت — عشان متقفلش على نفسك بالغلط. دورك:') }} {{ $role }}</div>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $r)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-slate-200 p-3 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <input type="radio" name="role" value="{{ $r->name }}" @checked($role === $r->name) required class="h-4 w-4 accent-blue-600" data-super="{{ $r->name === Access::SUPER_ROLE ? 1 : 0 }}">
                            <span class="font-bold text-slate-800">{{ $r->name === Access::SUPER_ROLE ? '👑 ' : '' }}{{ $r->name }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <aside class="space-y-5">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 font-extrabold text-slate-900">{{ __('📍 الفروع') }}</h2>
            <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('فرعه') }}</label>
            <select name="branch_id" class="{{ $input }}" data-no-search>
                <option value="">—</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((string) old('branch_id', $u->branch_id ?? '') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
            @unless ($meRestricted)
                <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl bg-emerald-50 p-4" id="allWrap">
                    <input type="hidden" name="all_branches" value="0">
                    <input type="checkbox" name="all_branches" value="1" @checked(old('all_branches', $u->all_branches ?? false)) class="mt-0.5 h-5 w-5 accent-emerald-600" id="allBranches">
                    <span><span class="block text-sm font-extrabold text-emerald-800">{{ __('🌍 يشوف كل الفروع') }}</span><span class="text-xs text-emerald-700">{{ __('لو مش متعلّمة: يشوف بيانات فرعه بس (العربيات، العقود، المدفوعات، التقارير...)') }}</span></span>
                </label>
                <p id="superNote" class="mt-3 hidden rounded-xl bg-amber-50 p-3 text-xs font-bold text-amber-800">{{ __('👑 المدير العام بيشوف كل الفروع دايماً.') }}</p>
            @endunless
        </div>

        @unless ($isSelf)
            <label class="flex cursor-pointer items-center justify-between rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <span><span class="block font-extrabold text-slate-900">{{ __('الحساب شغال') }}</span><span class="text-xs text-slate-400">{{ __('لو اتقفل مش هيقدر يدخل') }}</span></span>
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $u->is_active ?? true)) class="h-5 w-5 accent-blue-600">
            </label>
        @endunless

        <button type="submit" class="w-full rounded-xl bg-blue-600 py-3.5 font-bold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700 disabled:opacity-60">{{ __('💾 حفظ') }}</button>
    </aside>
</form>
<script>
    (function () {
        const note = document.getElementById('superNote'), wrap = document.getElementById('allWrap');
        const sync = () => { const s = document.querySelector('[name=role][data-super="1"]:checked'); if (note) { note.classList.toggle('hidden', !s); wrap.classList.toggle('hidden', !!s); } };
        document.querySelectorAll('[name=role]').forEach((r) => r.addEventListener('change', sync)); sync();
    })();
</script>
@endsection
