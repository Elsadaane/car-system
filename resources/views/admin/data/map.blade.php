@extends('admin.layouts.app')

@php
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400';
    $missing = collect($fields)->filter(fn ($f, $k) => $f[1] && ! isset($map[$k]));
    $mappedCols = array_flip($map);
@endphp

@section('title', __('استيراد ').$type['label'])
@section('subtitle', $meta['name'].' · '.$total.__(' صف'))

@section('content')
    {{-- الخطوات --}}
    <ol class="mb-5 flex flex-wrap items-center gap-2 text-xs font-bold">
        <li class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700">{{ __('✓ 1. رفع الملف') }}</li>
        <li class="text-slate-300">←</li>
        <li class="rounded-full bg-blue-600 px-3 py-1 text-white">{{ __('2. مراجعة الأعمدة') }}</li>
        <li class="text-slate-300">←</li>
        <li class="rounded-full bg-slate-100 px-3 py-1 text-slate-500">{{ __('3. استيراد') }}</li>
    </ol>

    <div class="mb-5 grid grid-cols-3 gap-3">
        <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200"><div class="text-2xl font-black text-emerald-600">{{ $stats['valid'] - $stats['existing'] }}</div><div class="text-xs text-slate-500">{{ __('جاهز يتضاف') }}</div></div>
        <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200"><div class="text-2xl font-black text-sky-600">{{ $stats['existing'] }}</div><div class="text-xs text-slate-500">{{ __('موجود قبل كده') }}</div></div>
        <div class="rounded-2xl p-4 text-center shadow-sm ring-1 {{ $stats['invalid'] ? 'bg-rose-50 ring-rose-200' : 'bg-white ring-slate-200' }}"><div class="text-2xl font-black {{ $stats['invalid'] ? 'text-rose-600' : 'text-slate-400' }}">{{ $stats['invalid'] }}</div><div class="text-xs text-slate-500">{{ __('فيه مشكلة (هيتخطّى)') }}</div></div>
    </div>

    @if ($topErrors->isNotEmpty())
        <div class="mb-5 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200">
            <div class="mb-1 font-extrabold">{{ __('أكتر المشاكل:') }}</div>
            <ul class="list-inside list-disc space-y-0.5 text-xs">
                @foreach ($topErrors as $msg => $count)<li>{{ $msg }} <b>({{ $count }} {{ __('صف)') }}</b></li>@endforeach
            </ul>
            <p class="mt-2 text-xs">{{ __('💡 لو المشكلة إن عمود مربوط غلط، غيّر الربط تحت ودوس «تحديث المعاينة».') }}</p>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.data.map', $token) }}" id="map-form">
        <section class="mb-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-extrabold text-slate-900">{{ __('كل خانة في السيستم ← أنهي عمود في ملفك؟') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('ربطنا اللي عرفناه أوتوماتيك. راجع وغيّر لو محتاج. الخانات اللي عليها * لازم تتربط.') }}</p>
                </div>
                <button class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200">{{ __('🔄 تحديث المعاينة') }}</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fields as $key => [$label, $required])
                    <label class="block rounded-xl p-2 {{ $required && ! isset($map[$key]) ? 'bg-rose-50 ring-1 ring-rose-200' : '' }}">
                        <span class="mb-1 flex items-center justify-between text-sm font-bold text-slate-700">
                            <span>{{ $label }}@if ($required)<span class="text-rose-500"> *</span>@endif</span>
                            @if (isset($map[$key]))<span class="text-[10px] font-bold text-emerald-600">{{ __('✓ مربوط') }}</span>@endif
                        </span>
                        <select name="map[{{ $key }}]" class="{{ $input }}" data-no-search>
                            <option value="">{{ __('— مش موجود في الملف —') }}</option>
                            @foreach ($headers as $i => $h)
                                <option value="{{ $i }}" @selected(($map[$key] ?? null) === $i)>{{ $h !== '' ? $h : __('عمود ').($i + 1) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
        </section>
    </form>

    {{-- المعاينة --}}
    <section class="mb-5 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-extrabold text-slate-900">{{ __('معاينة أول الصفوف') }}</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-xs">
                <thead class="bg-slate-50 font-bold text-slate-500">
                    <tr>
                        <th class="p-2 text-start">{{ __('صف') }}</th>
                        @foreach ($headers as $i => $h)
                            <th class="whitespace-nowrap p-2 text-start {{ isset($mappedCols[$i]) ? 'text-blue-700' : 'text-slate-300' }}">
                                {{ $h ?: __('عمود ').($i + 1) }}
                                @if (isset($mappedCols[$i]))<div class="text-[10px] font-normal text-blue-500">← {{ $fields[$mappedCols[$i]][0] }}</div>@endif
                            </th>
                        @endforeach
                        <th class="p-2 text-start">{{ __('النتيجة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview as $p)
                        <tr class="border-t border-slate-100 {{ $p['errors'] ? 'bg-rose-50/60' : '' }}">
                            <td class="p-2 font-bold text-slate-400">{{ $p['n'] }}</td>
                            @foreach ($headers as $i => $h)
                                <td class="max-w-[180px] truncate p-2 {{ isset($mappedCols[$i]) ? 'text-slate-800' : 'text-slate-300' }}">{{ $p['row'][$i] ?? '' }}</td>
                            @endforeach
                            <td class="p-2">
                                @if ($p['errors'])<span class="font-bold text-rose-700">{{ implode(' — ', $p['errors']) }}</span>
                                @elseif ($p['exists'])<span class="whitespace-nowrap rounded-full bg-sky-50 px-2 py-0.5 font-bold text-sky-700">{{ __('موجود قبل كده') }}</span>
                                @else<span class="whitespace-nowrap rounded-full bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700">{{ __('✓ جاهز') }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- التنفيذ --}}
    <form method="POST" action="{{ route('admin.data.run', $token) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200" onsubmit="{{ __('this.querySelector(\'button\').disabled = true; this.querySelector(\'button\').textContent = \'⏳ جاري الاستيراد… ماتقفلش الصفحة\';') }}">
        @csrf
        @foreach ($map as $k => $i)<input type="hidden" name="map[{{ $k }}]" value="{{ $i }}">@endforeach

        @if ($meta['type'] !== 'installments')
            <fieldset class="mb-4">
                <legend class="mb-2 text-sm font-extrabold text-slate-900">{{ __('لو الصف موجود قبل كده في السيستم:') }}</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-2.5"><input type="radio" name="mode" value="skip" checked> {{ __('اتخطّاه (سيب القديم زي ما هو)') }}</label>
                    <label class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-2.5"><input type="radio" name="mode" value="update"> {{ __('حدّث بياناته من الملف') }}</label>
                </div>
            </fieldset>
        @else
            <p class="mb-4 rounded-xl bg-blue-50 p-3 text-xs leading-6 text-blue-900">
                {{ __('📌 كل عقد هيتعمل بجدول أقساطه، والمبلغ المدفوع هيتوزّع على الأقساط من الأقدم (كأنها اندفعت في ميعادها). المبالغ دي بتتسجل كـ «رصيد افتتاحي» ومش بتدخل رصيد الخزنة. العقد اللي عربيته (نفس رقم الشاسيه) متسجلة مباعة قبل كده هيتخطّى عشان مايتكررش.') }}
            </p>
        @endif

        @if ($missing->isNotEmpty())
            <p class="mb-3 rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700">{{ __('لازم تربط:') }} {{ $missing->pluck(0)->implode(__('، ')) }}</p>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <button @disabled($missing->isNotEmpty() || ! $stats['valid']) class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                {{ __('✅ استورد :n صف', ['n' => $stats['valid']]) }}
            </button>
            <a href="{{ route('admin.data.index') }}" class="text-sm font-bold text-slate-500 hover:text-slate-800">{{ __('إلغاء') }}</a>
        </div>
    </form>

    <script>
        // أي تغيير في الربط يحدّث المعاينة على طول
        document.querySelectorAll('#map-form select').forEach(s => s.addEventListener('change', () => document.getElementById('map-form').submit()));
    </script>
@endsection
