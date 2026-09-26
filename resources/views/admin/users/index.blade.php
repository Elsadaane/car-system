@extends('admin.layouts.app')

@php use App\Support\Access; $me = auth()->user(); @endphp

@section('title', __('المستخدمين'))
@section('subtitle', __('حسابات الموظفين، أدوارهم، وكل واحد يشوف أنهي فرع'))

@section('actions')
    @perm('roles.view')<a href="{{ route('admin.roles.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('🛡️ الأدوار والصلاحيات') }}</a>@endperm
    @perm('users.create')<a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ __('＋ مستخدم جديد') }}</a>@endperm
@endsection

@section('content')
    @if (session('error')) <div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 ring-1 ring-rose-200">{{ session('error') }}</div> @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-slate-900 p-5 text-white"><div class="text-sm text-slate-400">{{ __('الحسابات') }}</div><div class="mt-1 text-3xl font-black">{{ $counts['all'] }}</div><div class="text-xs text-slate-400">{{ $counts['active'] }} {{ __('شغال') }}</div></div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:col-span-1 lg:col-span-3">
            <div class="mb-2 text-xs font-bold text-slate-500">{{ __('فلترة بالدور') }}</div>
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('admin.users.index', array_filter(['q' => request('q')])) }}" class="rounded-lg px-3 py-1.5 text-xs font-bold {{ ! request('role') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }}">{{ __('الكل') }}</a>
                @foreach ($roles as $r)
                    <a href="{{ route('admin.users.index', array_filter(['role' => $r->name, 'q' => request('q')])) }}" class="rounded-lg px-3 py-1.5 text-xs font-bold {{ request('role') === $r->name ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $r->name }} <span class="opacity-60">{{ $r->users_count }}</span></a>
                @endforeach
            </div>
        </div>
    </div>

    <form class="mb-4"><input type="hidden" name="role" value="{{ request('role') }}"><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('🔍 الاسم، الإيميل، الموبايل...') }}" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50"></form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full min-w-[760px] text-sm">
            <thead class="bg-slate-50 text-xs font-bold text-slate-500">
                <tr><th class="p-3 text-start">{{ __('الموظف') }}</th><th class="p-3 text-start">{{ __('الدور') }}</th><th class="p-3 text-start">{{ __('الفروع') }}</th><th class="p-3 text-start">{{ __('آخر دخول') }}</th><th class="p-3 text-start">{{ __('الحالة') }}</th><th class="p-3"></th></tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    @php
                        $isFounder = $u->id === $founder;
                        $isMe = $u->id === $me->id;
                        $locked = ($isFounder && ! $isMe) || (Access::isSuper($u) && ! Access::isSuper($me));
                        $roleName = $u->roles->first()?->name ?? ($u->role === 'admin' ? Access::SUPER_ROLE : null);
                    @endphp
                    <tr class="border-t border-slate-100 {{ $u->is_active ? '' : 'bg-slate-50 opacity-70' }}">
                        <td class="p-3">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full {{ $roleName === Access::SUPER_ROLE ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-700' }} font-black">{{ mb_substr($u->name, 0, 1) }}</span>
                                <div>
                                    <div class="font-extrabold text-slate-900">{{ $u->name }} @if ($isMe)<span class="text-xs font-bold text-blue-600">{{ __('(إنت)') }}</span>@endif</div>
                                    <div class="text-xs text-slate-400" dir="ltr">{{ $u->email }}</div>
                                    @if ($isFounder)<div class="text-[11px] font-bold text-amber-600">{{ __('👑 المؤسس (محمي)') }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td class="p-3">@if ($roleName)<span class="rounded-lg px-2.5 py-1 text-xs font-bold {{ $roleName === Access::SUPER_ROLE ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100' }}">🛡️ {{ $roleName }}</span>@else<span class="text-xs text-rose-500">{{ __('من غير دور') }}</span>@endif</td>
                        <td class="p-3 text-xs font-bold">{!! Access::branchIds($u) === null ? __('<span class="text-emerald-600">🌍 كل الفروع</span>') : '<span class="text-slate-600">📍 '.e($u->branch?->name_ar ?? '—').__(' بس</span>') !!}</td>
                        <td class="p-3 text-xs text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td class="p-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $u->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $u->is_active ? __('● شغال') : __('⏸ موقوف') }}</span>
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1">
                                @if (! $locked)
                                    @perm('users.edit')
                                        <a href="{{ route('admin.users.edit', $u->id) }}" class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-amber-50" title="{{ __('تعديل') }}">✏️</a>
                                        @unless ($isMe)
                                            <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" onsubmit="return confirm('{{ $u->is_active ? __('إيقاف الحساب؟ هيخرج من كل الأجهزة فوراً.') : __('تفعيل الحساب؟') }}')">@csrf @method('PATCH')<button class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" title="{{ $u->is_active ? __('إيقاف') : __('تفعيل') }}">{{ $u->is_active ? '⏸' : '▶️' }}</button></form>
                                        @endunless
                                    @endperm
                                    @perm('users.delete')
                                        @unless ($isMe)
                                            <form method="POST" action="{{ route('admin.users.destroy', $u->id) }}" onsubmit="{{ __('return confirm(\'مسح الحساب نهائياً؟ (لو مربوط بعمليات هيتوقف بدل ما يتمسح)\')') }}">@csrf @method('DELETE')<button class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-rose-50" title="{{ __('مسح') }}">🗑️</button></form>
                                        @endunless
                                    @endperm
                                @else
                                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-400">{{ __('🔒 محمي') }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-12 text-center text-slate-400">{{ __('مفيش مستخدمين مطابقين') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
