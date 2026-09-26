@extends('admin.layouts.app')

@php
    use App\Support\Importer;
@endphp

@section('title', __('استيراد وتصدير Excel'))
@section('subtitle', __('دخّل بياناتك القديمة مرة واحدة من Excel، أو نزّل أي بيانات من السيستم كملف Excel'))

@section('content')
    {{-- نتيجة آخر استيراد --}}
    @if ($result)
        <section class="mb-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 {{ $result['failed'] ? 'ring-amber-300' : 'ring-emerald-300' }}">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 {{ $result['failed'] ? 'bg-amber-50' : 'bg-emerald-50' }}">
                <div>
                    <div class="font-extrabold text-slate-900">{{ $result['failed'] ? __('⚠️ الاستيراد خلص — وفيه صفوف محتاجة تصليح') : __('✅ الاستيراد خلص بنجاح') }}</div>
                    <div class="text-xs text-slate-500">{{ Importer::TYPES[$result['type']]['label'] }} · {{ $result['file'] }}</div>
                </div>
                @if ($result['token'])
                    <a href="{{ route('admin.data.errors', $result['token']) }}" class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-600">{{ __('⬇️ نزّل الصفوف اللي فيها مشكلة') }}</a>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4">
                @foreach ([[__('اتضاف جديد'), $result['created'], 'text-emerald-600'], [__('اتحدّث'), $result['updated'], 'text-sky-600'], [__('اتخطّى (موجود قبل كده)'), $result['skipped'], 'text-slate-500'], [__('فيه مشكلة'), $result['failed'], $result['failed'] ? 'text-rose-600' : 'text-slate-400']] as [$l, $n, $c])
                    <div class="rounded-xl bg-slate-50 p-3 text-center"><div class="text-2xl font-black {{ $c }}">{{ $n }}</div><div class="text-xs text-slate-500">{{ $l }}</div></div>
                @endforeach
            </div>
            @if (array_filter($result['extra']))
                <p class="px-5 pb-4 text-xs text-slate-500">
                    {{ __('واتعمل كمان أوتوماتيك:') }}
                    @if ($result['extra']['brands']) {{ $result['extra']['brands'] }} {{ __('ماركة جديدة ·') }} @endif
                    @if ($result['extra']['models']) {{ $result['extra']['models'] }} {{ __('موديل جديد ·') }} @endif
                    @if ($result['extra']['customers']) {{ $result['extra']['customers'] }} {{ __('عميل جديد') }} @endif
                </p>
            @endif
            @if ($result['errors'])
                <div class="max-h-64 overflow-y-auto border-t border-slate-100">
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($result['errors'] as $e)
                                <tr class="border-b border-slate-50"><td class="w-20 whitespace-nowrap px-5 py-2 text-xs font-bold text-slate-400">{{ __('صف') }} {{ $e['n'] }}</td><td class="px-2 py-2 text-rose-700">{{ implode(' — ', $e['errors']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">{{ __('💡 نزّل ملف الصفوف اللي فيها مشكلة، صلّحها في Excel، وارفعه تاني — الصفوف اللي دخلت مش هتتكرر.') }}</p>
            @endif
        </section>
    @endif

    {{-- الاستيراد --}}
    @if ($imports->isNotEmpty())
        <h2 class="mb-3 text-lg font-extrabold text-slate-900">{{ __('⬆️ استيراد') }}</h2>
        <div class="mb-8 grid gap-4 lg:grid-cols-3">
            @foreach ($imports as $key => $t)
                <form method="POST" action="{{ route('admin.data.upload') }}" enctype="multipart/form-data" class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    @csrf
                    <input type="hidden" name="type" value="{{ $key }}">
                    <div class="flex items-center gap-3">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-blue-50 text-2xl">{{ $t['icon'] }}</span>
                        <h3 class="text-base font-extrabold text-slate-900">{{ $t['label'] }}</h3>
                    </div>
                    <p class="mt-3 flex-1 text-xs leading-6 text-slate-500">{{ $t['hint'] }}</p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach ($t['fields'] as $f)
                            <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold {{ $f[1] ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500' }}">{{ $f[0] }}{{ $f[1] ? ' *' : '' }}</span>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.data.template', $key) }}" class="mt-4 text-center text-xs font-bold text-blue-600 hover:underline">{{ __('⬇️ نزّل نموذج Excel جاهز بالأعمدة دي') }}</a>
                    <label class="mt-3 block cursor-pointer rounded-xl border-2 border-dashed border-slate-200 p-4 text-center text-sm text-slate-500 hover:border-blue-400 hover:bg-blue-50/40">
                        <input type="file" name="file" accept=".xlsx,.csv" required class="sr-only" onchange="{{ __('this.closest(\'label\').querySelector(\'span\').textContent = this.files[0]?.name || \'اختار الملف\'') }}">
                        📄 <span>{{ __('اختار ملف Excel أو CSV') }}</span>
                    </label>
                    <button class="mt-3 rounded-xl bg-slate-900 py-2.5 text-sm font-bold text-white hover:bg-slate-800">{{ __('التالي: مراجعة الأعمدة ←') }}</button>
                </form>
            @endforeach
        </div>
        @if ($errors->any())<div class="-mt-5 mb-6 rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>@endif
    @endif

    {{-- التصدير --}}
    @if ($exports->isNotEmpty())
        <h2 class="mb-3 text-lg font-extrabold text-slate-900">{{ __('⬇️ تصدير لـ Excel') }}</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($exports as $key => [$label, $icon])
                <a href="{{ route('admin.data.export', $key) }}" class="flex flex-col items-center gap-2 rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="text-3xl">{{ $icon }}</span>
                    <span class="text-sm font-extrabold text-slate-800">{{ $label }}</span>
                    <span class="text-[11px] text-slate-400">{{ __('ملف Excel') }}</span>
                </a>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-400">{{ __('🔒 التصدير بيطلّع بس البيانات اللي من صلاحياتك وفرعك — وسعر الشراء مش بيظهر غير للي عنده صلاحية التكلفة.') }}</p>
    @endif
@endsection
