{{-- حقول التواصل الأساسية + الحماية من البوتات --}}
@php $in = 'w-full rounded-xl border-0 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-900 ring-1 ring-transparent transition placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-[var(--accent)]'; @endphp
@csrf
<input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
<input type="hidden" name="_ts" value="">
<div class="grid gap-3 {{ ($cols ?? 2) === 2 ? 'sm:grid-cols-2' : '' }}">
    <div>
        <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الاسم *') }}</label>
        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" class="{{ $in }}">
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('رقم الموبايل *') }}</label>
        <input type="tel" name="phone" value="{{ old('phone') }}" required dir="ltr" autocomplete="tel" placeholder="9xxx xxxx" class="{{ $in }} text-start">
    </div>
</div>
@if ($email ?? true)
    <div class="mt-3">
        <label class="mb-1.5 block text-xs font-bold text-slate-600">{{ __('الإيميل') }} <span class="font-normal text-slate-400">{{ __('(اختياري)') }}</span></label>
        <input type="email" name="email" value="{{ old('email') }}" dir="ltr" autocomplete="email" class="{{ $in }} text-start">
    </div>
@endif
