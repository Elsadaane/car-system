{{--
    حقل فورم موحّد:
    @include('admin.partials.field', ['model' => $x, 'name' => 'name_ar', 'label' => 'الاسم', 'required' => true])
    خيارات: type (text|email|number|date|url|textarea|select), options (للـ select), dir, placeholder, step, hint, list
--}}
@php
    $type = $type ?? 'text';
    $raw = $model?->{$name} ?? null;
    if ($raw instanceof \DateTimeInterface) {
        $raw = $raw->format('Y-m-d');
    }
    $value = $value ?? old($name, $raw);
    $invalid = $errors->has($name);
    $classes = 'w-full rounded-xl border bg-white px-3 py-2.5 text-sm outline-none transition focus:ring-4 '
        .($invalid ? 'border-rose-300 focus:ring-rose-50' : 'border-slate-200 focus:border-blue-400 focus:ring-blue-50');
    $fieldId = ($idPrefix ?? '').$name;
@endphp

<div>
    <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
        {{ $label }} @if (! empty($required)) <span class="text-rose-500">*</span> @endif
    </label>

    @if ($type === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" rows="{{ $rows ?? 3 }}"
                  @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
                  class="{{ $classes }}">{{ $value }}</textarea>
    @elseif ($type === 'rich')
        @include('admin.partials.rich-editor', ['name' => $name, 'value' => $value, 'mode' => $mode ?? 'terms', 'dir' => $dir ?? 'rtl', 'id' => $fieldId])
    @elseif ($type === 'select')
        <select id="{{ $fieldId }}" name="{{ $name }}" @if (! empty($required)) required @endif class="{{ $classes }}">
            @if (isset($empty)) <option value="">{{ $empty }}</option> @endif
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
               @if (! empty($required)) required @endif
               @if (! empty($dir)) dir="{{ $dir }}" @endif
               @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
               @if (! empty($step)) step="{{ $step }}" @endif
               @if (isset($min)) min="{{ $min }}" @endif
               @if (! empty($list)) list="{{ $list }}" @endif
               class="{{ $classes }}">
    @endif

    @if (! empty($hint) && ! $invalid)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
    @error($name) <p data-error class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
