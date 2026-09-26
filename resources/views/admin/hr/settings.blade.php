@extends('admin.layouts.app')

@section('title', __('إعدادات الموارد البشرية'))
@section('subtitle', $cfg['title'])

@php
    use App\Http\Controllers\Admin\Hr\HrSettingsController as S;
    $reopen = $errors->any() && old('_modal') === 'hrset' ? (old('_edit_url') ?: 'new') : '';
    $inputs = [];
    foreach ($cfg['fields'] as $name => [$label, $type, $req]) {
        $opts = str_starts_with($type, 'select:') ? collect(explode(',', substr($type, 7)))->mapWithKeys(fn ($o) => [explode('=', $o)[0] => explode('=', $o)[1]])->all() : null;
        $inputs[$name] = compact('label', 'type', 'req', 'opts');
    }
    $display = function ($row, $name, $i) {
        $v = $row->{$name};
        return match (true) {
            $i['type'] === 'bool' => $v ? '✓' : '—',
            $i['type'] === 'time' => substr((string) $v, 0, 5),
            (bool) $i['opts'] => $i['opts'][$v] ?? $v,
            default => $v,
        };
    };
@endphp

@section('actions')
    <button type="button" data-crud-new="hrModal" class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">{{ __('+ إضافة') }}</button>
@endsection

@section('content')
    @include('admin.hr.partials-tabs')

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach (S::TABS as $k => $t)
            <a href="{{ route('admin.hr.settings.index', $k) }}" class="rounded-full px-4 py-2 text-sm font-bold {{ $tab === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $t['title'] }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr>@foreach ($inputs as $i) <th class="px-5 py-3 text-start">{{ $i['label'] }}</th> @endforeach<th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php
                        $vals = ['url' => route('admin.hr.settings.update', [$tab, $row->id])];
                        foreach ($inputs as $n => $i) { $vals[$n] = $i['type'] === 'time' ? substr((string) $row->{$n}, 0, 5) : ($i['type'] === 'bool' ? (bool) $row->{$n} : $row->{$n}); }
                    @endphp
                    <tr class="hover:bg-slate-50/70">
                        @foreach ($inputs as $n => $i) <td class="px-5 py-3 {{ $loop->first ? 'font-bold text-slate-800' : '' }}">{{ $display($row, $n, $i) }}</td> @endforeach
                        <td class="whitespace-nowrap px-5 py-3 text-end">
                            <button type="button" data-crud-edit="hrModal" data-values="{{ json_encode($vals, JSON_UNESCAPED_UNICODE) }}" class="rounded-lg px-2 py-1 text-xs font-bold text-violet-600 hover:bg-violet-50">{{ __('تعديل') }}</button>
                            <form method="POST" action="{{ route('admin.hr.settings.destroy', [$tab, $row->id]) }}" class="inline" onsubmit="{{ __('return confirm(\'مسح؟\')') }}">@csrf @method('DELETE')<button class="rounded-lg px-2 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('مسح') }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($inputs) + 1 }}" class="px-5 py-12 text-center text-slate-400">{{ __('مفيش بيانات') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <dialog id="hrModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.hr.settings.store', $tab) }}" data-title-new="إضافة — {{ $cfg['title'] }}" data-title-edit="{{ __('تعديل') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.hr.settings.store', $tab) }}">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="hrset"><input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4"><h2 data-crud-title class="text-lg font-extrabold">{{ __('إضافة') }}</h2><button type="button" data-close class="h-9 w-9 rounded-full text-slate-400 hover:bg-slate-100">✕</button></div>
            <div class="space-y-4 px-6 py-5">
                @foreach ($inputs as $n => $i)
                    @if ($i['type'] === 'bool')
                        <input type="hidden" name="{{ $n }}" value="0">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/50">
                            <input type="checkbox" name="{{ $n }}" value="1" data-default="1" class="accent-violet-600" checked> <span class="text-sm font-bold">{{ $i['label'] }}</span>
                        </label>
                    @elseif ($i['opts'])
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'h_', 'name' => $n, 'label' => $i['label'], 'type' => 'select', 'options' => $i['opts'], 'required' => $i['req'] === 'required'])
                    @else
                        @include('admin.partials.field', ['model' => null, 'idPrefix' => 'h_', 'name' => $n, 'label' => $i['label'], 'type' => $i['type'], 'required' => $i['req'] === 'required'])
                    @endif
                @endforeach
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600">{{ __('إلغاء') }}</button><button type="submit" class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white">{{ __('حفظ') }}</button></div>
        </form>
    </dialog>
    @include('admin.partials.crud-modal-script')
@endsection
