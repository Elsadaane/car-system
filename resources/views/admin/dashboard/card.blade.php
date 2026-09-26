{{-- كارت ويدجت: $key, $size, $w (تعريفه), $d (بياناته) --}}
@php $span = [1 => 'xl:col-span-1', 2 => 'md:col-span-2 xl:col-span-2', 3 => 'md:col-span-2 xl:col-span-3', 4 => 'md:col-span-2 xl:col-span-4'][$size] ?? ''; @endphp
<section data-widget="{{ $key }}" data-size="{{ $size }}" class="{{ $span }} flex min-w-0 flex-col rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <h3 class="flex min-w-0 items-center gap-2 text-sm font-extrabold text-slate-800"><span class="text-lg">{{ $w[1] }}</span><span class="truncate">{{ $w[0] }}</span></h3>
        <div data-edit-tools class="flex shrink-0 items-center gap-1">
            <span class="cursor-grab px-1 text-slate-300" title="{{ __('اسحب') }}">⋮⋮</span>
            @foreach ([1 => 'S', 2 => 'M', 3 => 'L', 4 => 'XL'] as $sz => $label)
                <button type="button" data-sz="{{ $sz }}" class="h-6 min-w-6 rounded-md px-1 text-[10px] font-black text-slate-500 ring-1 ring-slate-200">{{ $label }}</button>
            @endforeach
            <button type="button" data-remove class="grid h-6 w-6 place-items-center rounded-md text-xs text-rose-500 hover:bg-rose-50" title="{{ __('شيل') }}">✕</button>
        </div>
    </header>
    <div data-body class="flex-1 p-4">
        @if (! empty($d['_error']))
            <p class="py-6 text-center text-sm text-slate-400">⚠️ {{ $d['_error'] }}</p>
        @else
            @include('admin.dashboard.widgets.'.$key, ['d' => $d, 'size' => $size])
        @endif
    </div>
</section>
