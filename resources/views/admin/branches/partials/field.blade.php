@php
    $type = $type ?? 'text';
    $value = $value ?? old($name, $branch->{$name});
    $invalid = $errors->has($name);
    $classes = 'w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition focus:ring-4 '
        .($invalid ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-400 focus:ring-blue-50');
@endphp

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
        {{ $label }} @if (! empty($required)) <span class="text-rose-500">*</span> @endif
    </label>

    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" class="{{ $classes }}">{{ $value }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
               @if (! empty($required)) required @endif
               @if (! empty($dir)) dir="{{ $dir }}" @endif
               @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
               class="{{ $classes }}">
    @endif

    @error($name) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
