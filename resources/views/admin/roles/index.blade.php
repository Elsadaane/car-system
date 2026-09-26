@extends('admin.layouts.app')

@php use App\Support\Access; @endphp

@section('title', __('الأدوار والصلاحيات'))
@section('subtitle', __('كل دور = مجموعة صلاحيات — وبتدّي الموظف دور واحد'))

@section('actions')
    @perm('users.view')<a href="{{ route('admin.users.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('👥 المستخدمين') }}</a>@endperm
    @perm('roles.create')<a href="{{ route('admin.roles.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ __('＋ دور جديد') }}</a>@endperm
@endsection

@section('content')
    @if (session('error')) <div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 ring-1 ring-rose-200">{{ session('error') }}</div> @endif
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $r)
            @php $super = $r->name === Access::SUPER_ROLE; $pct = $super ? 100 : ($total ? round($r->permissions_count / $total * 100) : 0); @endphp
            <div class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 {{ $super ? 'ring-amber-300' : 'ring-slate-200' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-black text-slate-900">{{ $super ? '👑 ' : '🛡️ ' }}{{ $r->name }}</h3>
                        <div class="mt-0.5 text-xs font-bold text-slate-400">{{ $r->users_count }} {{ __('موظف') }}</div>
                    </div>
                    @if ($super)<span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">{{ __('أساسي (محمي)') }}</span>@endif
                </div>
                <div class="mt-4">
                    <div class="mb-1 flex justify-between text-xs font-bold text-slate-500"><span>{{ __('الصلاحيات') }}</span><span>{{ $super ? __('كل حاجة') : $r->permissions_count.' / '.$total }}</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $super ? 'bg-amber-400' : 'bg-blue-500' }}" style="width: {{ $pct }}%"></div></div>
                </div>
                <div class="mt-auto flex gap-1.5 pt-5">
                    @unless ($super)
                        @perm('roles.edit')<a href="{{ route('admin.roles.edit', $r->id) }}" class="flex-1 rounded-xl bg-slate-900 py-2 text-center text-sm font-bold text-white hover:bg-slate-700">{{ __('✏️ الصلاحيات') }}</a>@endperm
                    @endunless
                    @perm('roles.create')<a href="{{ route('admin.roles.create', ['copy' => $r->id]) }}" class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-200" title="{{ __('دور جديد بنفس الصلاحيات') }}">{{ __('📋 نسخ') }}</a>@endperm
                    @unless ($super)
                        @perm('roles.delete')<form method="POST" action="{{ route('admin.roles.destroy', $r->id) }}" onsubmit="{{ __('return confirm(\'مسح الدور؟\')') }}">@csrf @method('DELETE')<button class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-bold text-rose-600 hover:bg-rose-100">🗑️</button></form>@endperm
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
@endsection
