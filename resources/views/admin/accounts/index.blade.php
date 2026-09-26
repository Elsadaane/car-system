@extends('admin.layouts.app')

@section('title', __('الخزائن والحسابات'))
@section('subtitle', __('فلوسك فين بالظبط — رصيد كل خزنة وحساب بنكي لحظة بلحظة'))

@php
    use App\Support\Money;
    use App\Http\Controllers\Admin\FinancialAccountController as FA;
    use Illuminate\Support\Carbon;
    $reopen = $errors->any() && old('_modal') === 'account' ? (old('_edit_url') ?: 'new') : '';
    $total = $accounts->where('is_active', true)->sum('balance');
    $typeIcon = ['cash' => '💵', 'bank' => '🏦', 'wallet' => '📱', 'other' => '💼'];
    $typeTone = ['cash' => 'from-emerald-500 to-teal-600', 'bank' => 'from-blue-600 to-indigo-700', 'wallet' => 'from-violet-500 to-fuchsia-600', 'other' => 'from-slate-600 to-slate-800'];
@endphp

@section('actions')
    <button type="button" data-open-dialog="transferModal" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('⇄ تحويل بين الخزائن') }}</button>
    <button type="button" data-crud-new="accountModal" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('حساب جديد') }}
    </button>
@endsection

@section('content')
    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl bg-slate-900 p-6 text-white shadow-lg lg:col-span-2">
            <div class="text-sm text-slate-400">{{ __('إجمالي السيولة في كل الحسابات') }}</div>
            <div class="mt-1 text-4xl font-extrabold tracking-tight">{{ Money::format($total, false) }} <span class="text-lg text-slate-400">{{ Money::currency() }}</span></div>
            <div class="mt-5 flex h-3 overflow-hidden rounded-full bg-white/10">
                @foreach ($accounts->where('is_active', true)->where('balance', '>', 0) as $a)
                    <div class="h-full rtl:bg-gradient-to-l ltr:bg-gradient-to-r {{ $typeTone[$a->type] ?? '' }} first:rounded-s-full last:rounded-e-full" style="width: {{ $total > 0 ? $a->balance / $total * 100 : 0 }}%" title="{{ \App\Support\Lang::pick($a, 'name') }}"></div>
                @endforeach
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200"><div class="text-sm text-emerald-700">{{ __('دخل النهارده') }}</div><div class="mt-1 truncate text-xl font-extrabold text-emerald-800">{{ Money::format($todayIn, false) }}</div></div>
            <div class="rounded-2xl bg-rose-50 p-5 ring-1 ring-rose-200"><div class="text-sm text-rose-700">{{ __('طلع النهارده') }}</div><div class="mt-1 truncate text-xl font-extrabold text-rose-800">{{ Money::format($todayOut, false) }}</div></div>
        </div>
    </div>

    @if ($methods->isNotEmpty() || ($unlinked['in'] + $unlinked['out']) > 0)
        <form method="POST" action="{{ route('admin.accounts.link-methods') }}" class="mb-6 rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200">
            @csrf @method('PATCH')
            <div class="font-extrabold">{{ __('⚠️ فيه طرق دفع مش مربوطة بخزنة') }}</div>
            <p class="mt-1 text-xs">{{ __('الدفعات اللي اتسجلت بيها (') }}{{ Money::format($unlinked['in'] + $unlinked['out']) }}{{ __(') مش داخلة في أي رصيد. اختار كل طريقة فلوسها بتروح فين:') }}</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach ($methods as $m)
                    <label class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-amber-200">
                        <span class="font-bold">{{ \App\Support\Lang::pick($m, 'name') }}</span> ←
                        <select name="methods[{{ $m->id }}]" data-no-search class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
                            <option value="">—</option>
                            @foreach ($accounts as $a) <option value="{{ $a->id }}">{{ \App\Support\Lang::pick($a, 'name') }}</option> @endforeach
                        </select>
                    </label>
                @endforeach
                <button class="rounded-xl bg-amber-600 px-4 py-2 font-bold text-white hover:bg-amber-700">{{ __('ربط') }}</button>
            </div>
        </form>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($accounts as $a)
            <div class="group relative overflow-hidden rounded-3xl bg-gradient-to-br {{ $typeTone[$a->type] ?? '' }} p-5 text-white shadow-lg {{ $a->is_active ? '' : 'opacity-50 grayscale' }}">
                <div class="absolute -end-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-10 end-10 h-28 w-28 rounded-full bg-white/5"></div>
                <div class="relative flex items-start justify-between">
                    <div>
                        <div class="text-xs font-semibold text-white/70">{{ $typeIcon[$a->type] ?? '' }} {{ FA::TYPES[$a->type] ?? '' }}{{ $a->branch_name ? ' · '.$a->branch_name : '' }}</div>
                        <div class="mt-1 text-lg font-extrabold">{{ \App\Support\Lang::pick($a, 'name') }}</div>
                    </div>
                    <button type="button" data-crud-edit="accountModal" title="{{ __('تعديل') }}"
                            data-values="{{ json_encode(['url' => route('admin.accounts.update', $a->id), 'name_ar' => $a->name_ar, 'name_en' => $a->name_en, 'type' => $a->type, 'branch_id' => $a->branch_id, 'bank_name' => $a->bank_name, 'account_number' => $a->account_number, 'iban' => $a->iban, 'opening_balance' => (float) $a->opening_balance, 'opening_date' => $a->opening_date, 'notes' => $a->notes, 'is_active' => (bool) $a->is_active]) }}"
                            class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 opacity-0 transition hover:bg-white/20 group-hover:opacity-100">✏️</button>
                </div>
                <div class="relative mt-5 text-3xl font-extrabold tracking-tight {{ $a->balance < 0 ? 'text-rose-200' : '' }}">{{ Money::format($a->balance, false) }}</div>
                @if ($a->iban) <div class="relative mt-1 font-mono text-[11px] tracking-wider text-white/60" dir="ltr">{{ $a->iban }}</div> @endif
                <div class="relative mt-4 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-xl bg-white/10 px-3 py-2">{{ __('↓ دخل') }} <b class="block text-sm">{{ Money::format($a->total_in, false) }}</b></div>
                    <div class="rounded-xl bg-white/10 px-3 py-2">{{ __('↑ طلع') }} <b class="block text-sm">{{ Money::format($a->total_out, false) }}</b></div>
                </div>
                @if ($a->methods->isNotEmpty()) <div class="relative mt-3 text-[11px] text-white/70">{{ __('بيستقبل:') }} {{ $a->methods->map(fn ($m) => __($m))->implode(__('، ')) }}</div> @endif
                <a href="{{ route('admin.accounts.show', $a->id) }}" class="relative mt-4 block rounded-xl bg-white py-2 text-center text-sm font-bold text-slate-800 hover:bg-slate-100">{{ __('كشف الحساب') }}</a>
            </div>
        @endforeach
    </div>

    @if ($transfers->isNotEmpty())
        <section class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <h2 class="border-b border-slate-100 px-6 py-4 text-base font-extrabold text-slate-900">{{ __('آخر التحويلات') }}</h2>
            <div class="divide-y divide-slate-100">
                @foreach ($transfers as $t)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3 text-sm">
                        <div><b>{{ $t->from_name }}</b> <span class="text-slate-400">←</span> <b>{{ $t->to_name }}</b> <span class="text-xs text-slate-400">· {{ Carbon::parse($t->transfer_date)->format('Y-m-d') }} · {{ $t->user_name }}{{ $t->notes ? ' · '.$t->notes : '' }}</span></div>
                        <div class="flex items-center gap-3">
                            <b class="text-slate-900">{{ Money::format($t->amount) }}</b>
                            <form method="POST" action="{{ route('admin.accounts.transfers.destroy', $t->id) }}" onsubmit="{{ __('return confirm(\'إلغاء التحويل؟\')') }}">@csrf @method('DELETE')<button class="text-xs font-bold text-rose-600 hover:underline">{{ __('إلغاء') }}</button></form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- مودال الحساب --}}
    <dialog id="accountModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.accounts.store') }}" data-title-new="{{ __('حساب / خزنة جديدة') }}" data-title-edit="{{ __('تعديل الحساب') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.accounts.store') }}">
            @csrf
            <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="account"><input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('حساب / خزنة جديدة') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'name_ar', 'label' => __('الاسم'), 'required' => true, 'placeholder' => __('خزنة الفرع الرئيسي / بنك مسقط')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'name_en', 'label' => __('بالإنجليزي'), 'dir' => 'ltr'])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'type', 'label' => __('النوع'), 'type' => 'select', 'options' => FA::TYPES])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'branch_id', 'label' => __('الفرع'), 'type' => 'select', 'options' => $branches, 'empty' => __('كل الفروع')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'bank_name', 'label' => __('اسم البنك')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'account_number', 'label' => __('رقم الحساب'), 'dir' => 'ltr'])
                <div class="sm:col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'iban', 'label' => 'IBAN', 'dir' => 'ltr'])</div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'opening_balance', 'label' => __('الرصيد الافتتاحي'), 'type' => 'number', 'step' => '0.001', 'hint' => __('الفلوس اللي كانت فيه قبل ما تبدأ تستخدم السيستم')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'opening_date', 'label' => __('تاريخ الرصيد الافتتاحي'), 'type' => 'date'])
                <div class="sm:col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'a_', 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])</div>
                <div class="sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/50">
                        <input type="checkbox" name="is_active" value="1" data-default="1" class="accent-blue-600" checked> <span class="text-sm font-bold text-slate-800">{{ __('مفعّل') }}</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-between gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <span></span>
                <div class="flex gap-2">
                    <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
                </div>
            </div>
        </form>
    </dialog>

    {{-- مودال التحويل --}}
    <dialog id="transferModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.accounts.transfers.store') }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            @csrf <input type="hidden" name="_modal" value="transfer">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('تحويل بين الخزائن') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            @php $accOpts = $accounts->where('is_active', true)->mapWithKeys(fn ($a) => [$a->id => $a->name_ar.' ('.Money::format($a->balance, false).')']); @endphp
            <div class="space-y-4 px-6 py-5">
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 't_', 'name' => 'from_account_id', 'label' => __('من'), 'type' => 'select', 'options' => $accOpts, 'empty' => __('— اختار —'), 'required' => true])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 't_', 'name' => 'to_account_id', 'label' => __('إلى'), 'type' => 'select', 'options' => $accOpts, 'empty' => __('— اختار —'), 'required' => true])
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 't_', 'name' => 'amount', 'label' => __('المبلغ'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                    @include('admin.partials.field', ['model' => null, 'idPrefix' => 't_', 'name' => 'transfer_date', 'label' => __('التاريخ'), 'type' => 'date', 'required' => true, 'value' => old('transfer_date', today()->format('Y-m-d'))])
                </div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 't_', 'name' => 'notes', 'label' => __('البيان'), 'placeholder' => __('مثلاً: إيداع كاش اليوم في البنك')])
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إلغاء') }}</button>
                <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-60">{{ __('تحويل') }}</button>
            </div>
        </form>
    </dialog>

    @include('admin.partials.crud-modal-script')
    <script>
        (function () {
            const t = document.getElementById('transferModal');
            document.querySelectorAll('[data-open-dialog]').forEach((b) => b.addEventListener('click', () => document.getElementById(b.dataset.openDialog).showModal()));
            t.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => t.close()));
            t.addEventListener('click', (e) => { if (e.target === t) t.close(); });
            @if ($errors->any() && old('_modal') === 'transfer') t.showModal(); @endif
        })();
    </script>
@endsection
