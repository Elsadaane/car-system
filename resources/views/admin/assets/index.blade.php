@extends('admin.layouts.app')

@section('title', __('الأصول الثابتة'))
@section('subtitle', __('أثاث، أجهزة، عربيات الشركة — وقيمتها بتقل كل شهر (الإهلاك) لوحدها'))

@php
    use App\Support\Money;
    use App\Support\Media;
    use App\Http\Controllers\Admin\AssetController as AC;
    use Illuminate\Support\Carbon;
    $reopen = $errors->any() && old('_modal') === 'asset' ? (old('_edit_url') ?: 'new') : '';
    $style = ['in_use' => 'bg-emerald-50 text-emerald-700', 'maintenance' => 'bg-amber-50 text-amber-700', 'disposed' => 'bg-slate-100 text-slate-500', 'sold' => 'bg-blue-50 text-blue-700'];
@endphp

@section('actions')
    <a href="{{ route('admin.finance-categories.index', 'asset') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('📁 التصنيفات') }}</a>
    <button type="button" data-crud-new="assetModal" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ __('+ أصل جديد') }}</button>
@endsection

@section('content')
    @if (session('warning')) <div class="mb-4 rounded-2xl bg-amber-50 px-5 py-4 text-sm font-bold text-amber-800 ring-1 ring-amber-200">{{ session('warning') }}</div> @endif
    @if ($alerts['overdue'] || $alerts['soon'] || $debt > 0)
        <a href="{{ route('admin.asset-installments.index', ['tab' => $alerts['overdue'] ? 'overdue' : 'upcoming']) }}"
           class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl px-5 py-4 text-sm ring-1 {{ $alerts['overdue'] ? 'bg-rose-50 text-rose-800 ring-rose-200' : 'bg-violet-50 text-violet-800 ring-violet-200' }}">
            <span class="font-bold">
                @if ($alerts['overdue'])🔴 {{ $alerts['overdue'] }} {{ __('قسط متأخر (') }}{{ Money::format($alerts['overdue_amount']) }}) @endif
                @if ($alerts['soon'])⏰ {{ $alerts['soon'] }} {{ __('قسط مستحق خلال أسبوع (') }}{{ Money::format($alerts['soon_amount']) }}) @endif
                @if (! $alerts['overdue'] && ! $alerts['soon']){{ __('📅 مديونية أقساط الأصول:') }} {{ Money::format($debt) }} @endif
            </span>
            <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-extrabold">{{ __('عرض الأقساط ←') }}</span>
        </a>
    @endif
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('أصول شغالة') }}</div><div class="mt-1 text-2xl font-extrabold">{{ $stats['count'] }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><div class="text-sm text-slate-500">{{ __('تكلفة الشراء') }}</div><div class="mt-1 truncate text-2xl font-extrabold">{{ Money::format($stats['cost'], false) }}</div></div>
        <div class="rounded-2xl bg-slate-900 p-5 text-white shadow-lg"><div class="text-sm text-slate-400">{{ __('القيمة الدفترية دلوقتي') }}</div><div class="mt-1 truncate text-2xl font-extrabold">{{ Money::format($stats['book'], false) }}</div></div>
        <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200"><div class="text-sm text-amber-700">{{ __('الإهلاك الشهري') }}</div><div class="mt-1 truncate text-2xl font-extrabold text-amber-800">{{ Money::format($stats['monthly'], false) }}</div></div>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('الاسم، الكود، السيريال، العهدة مع...') }}" class="min-w-[200px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-50">
        <select name="category" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل التصنيفات') }}</option>@foreach ($mainCats as $id => $n) <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $n }}</option> @endforeach</select>
        <select name="branch" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل الفروع') }}</option>@foreach ($branches as $id => $n) <option value="{{ $id }}" @selected($filters['branch'] === $id)>{{ $n }}</option> @endforeach</select>
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ __('كل الحالات') }}</option>@foreach (AC::STATUSES as $k => $n) <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $n }}</option> @endforeach</select>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">{{ __('بحث') }}</button>
    </form>

    @if ($assets->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-slate-200"><div class="text-4xl">🪑</div><p class="mt-2 font-bold text-slate-600">{{ __('مفيش أصول مسجلة') }}</p></div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($assets as $a)
                @php $img = Media::url($a->image); $d = $a->dep; $active = in_array($a->status, ['in_use', 'maintenance'], true); @endphp
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 {{ $active ? '' : 'opacity-70' }}">
                    <div class="flex gap-4 p-5">
                        <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-100 text-2xl">@if ($img) <img src="{{ $img }}" alt="" class="h-full w-full object-cover"> @else 🪑 @endif</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="truncate font-extrabold text-slate-900">{{ \App\Support\Lang::pick($a, 'name') }}</h3>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $style[$a->status] }}">{{ AC::STATUSES[$a->status] }}</span>
                            </div>
                            <div class="text-xs text-slate-400"><span class="font-mono">{{ $a->asset_code }}</span> · {{ $a->category_name }}{{ $a->sub_category_name ? ' / '.$a->sub_category_name : '' }}</div>
                            <div class="text-xs text-slate-400">{{ $a->branch_name ?? __('عام') }}{{ $a->location ? ' · '.$a->location : '' }}{{ $a->custodian ? __(' · عهدة: ').$a->custodian : '' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 border-t border-slate-100 px-5 py-3 text-center text-xs">
                        <div><div class="text-slate-400">{{ __('التكلفة') }}</div><div class="font-bold">{{ Money::format($a->purchase_cost, false) }}</div></div>
                        <div><div class="text-slate-400">{{ __('اتهلك') }}</div><div class="font-bold text-amber-600">{{ Money::format($d['accumulated'], false) }}</div></div>
                        <div><div class="text-slate-400">{{ __('قيمته دلوقتي') }}</div><div class="font-extrabold text-slate-900">{{ Money::format($d['book_value'], false) }}</div></div>
                    </div>
                    <div class="px-5 pb-4">
                        <div class="flex justify-between text-[11px] text-slate-400"><span>{{ __('اشتريناه') }} {{ Carbon::parse($a->purchase_date)->format('Y-m') }}</span><span>{{ $d['months'] }} / {{ $a->useful_life_months }} {{ __('شهر') }}</span></div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $d['pct'] >= 100 ? 'bg-slate-400' : 'bg-amber-500' }}" style="width: {{ min(100, $d['pct']) }}%"></div></div>
                        @if ($a->fin)
                            @php $fin = $a->fin; $pctPaid = $fin['count'] ? round($fin['paid'] / $fin['count'] * 100) : 0; @endphp
                            <a href="{{ route('admin.asset-installments.index', ['asset' => $a->id, 'tab' => 'all']) }}" class="mt-3 block rounded-xl bg-violet-50 p-3 ring-1 ring-violet-100 hover:ring-violet-300">
                                <div class="flex items-center justify-between text-xs font-bold text-violet-800">
                                    <span>{{ __('📅 تقسيط') }} {{ $a->financier_type === 'bank' ? '— '.($a->lender_name ?: __('بنك')) : __('مع المورد') }}</span>
                                    <span>{{ $fin['paid'] }} / {{ $fin['count'] }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-violet-100"><div class="h-full rounded-full bg-violet-500" style="width: {{ $pctPaid }}%"></div></div>
                                <div class="mt-1.5 flex justify-between text-[11px] text-violet-700">
                                    <span>{{ __('باقي') }} {{ Money::format($fin['remaining'], false) }}</span>
                                    @if ($fin['overdue'])<span class="rounded-full bg-rose-500 px-1.5 font-bold text-white">{{ $fin['overdue'] }} {{ __('متأخر') }}</span>
                                    @elseif ($fin['next'])<span>{{ __('الجاي') }} {{ Carbon::parse($fin['next']->due_date)->format('m/d') }}</span>@else<span class="font-bold text-emerald-600">{{ __('✅ خلص') }}</span>@endif
                                </div>
                            </a>
                        @endif
                        @if ($a->status === 'sold') <div class="mt-2 text-xs font-bold {{ (float) $a->disposal_amount >= $d['book_value'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ __('اتباع بـ') }} {{ Money::format($a->disposal_amount, false) }} ({{ (float) $a->disposal_amount >= $d['book_value'] ? __('مكسب') : __('خسارة') }} {{ Money::format(abs((float) $a->disposal_amount - $d['book_value']), false) }})</div> @endif
                    </div>
                    <div class="flex gap-1 border-t border-slate-100 bg-slate-50/60 px-3 py-2">
                        <button type="button" data-crud-edit="assetModal" class="flex-1 rounded-lg py-1.5 text-xs font-bold text-slate-600 hover:bg-white"
                                data-values="{{ json_encode(['url' => route('admin.assets.update', $a->id), 'name_ar' => $a->name_ar, 'name_en' => $a->name_en, 'category_id' => $a->category_id, 'sub_category_id' => $a->sub_category_id, 'branch_id' => $a->branch_id, 'supplier_id' => $a->supplier_id, 'financial_account_id' => $a->financial_account_id, 'serial_number' => $a->serial_number, 'location' => $a->location, 'custodian' => $a->custodian, 'purchase_date' => $a->purchase_date, 'purchase_cost' => (float) $a->purchase_cost, 'salvage_value' => (float) $a->salvage_value, 'useful_life_months' => $a->useful_life_months, 'status' => $a->status, 'notes' => $a->notes, '_image' => $img,
                                    'payment_type' => $a->payment_type ?? 'cash', 'financier_type' => $a->financier_type ?? 'supplier', 'lender_name' => $a->lender_name, 'down_payment' => (float) ($a->down_payment ?? 0), 'interest_rate' => (float) ($a->interest_rate ?? 0), 'installments_count' => $a->installments_count, 'first_due_date' => $a->first_due_date, '_locked' => $a->fin && $a->fin['paid'] > 0], JSON_UNESCAPED_UNICODE) }}">{{ __('✏️ تعديل') }}</button>
                        @if ($active)
                            <button type="button" data-dispose="{{ route('admin.assets.dispose', $a->id) }}" data-name="{{ $a->name_ar }}" data-book="{{ $d['book_value'] }}" class="flex-1 rounded-lg py-1.5 text-xs font-bold text-amber-700 hover:bg-white">{{ __('♻️ بيع / تكهين') }}</button>
                        @endif
                        <form method="POST" action="{{ route('admin.assets.destroy', $a->id) }}" onsubmit="{{ __('return confirm(\'مسح الأصل؟ (لو اتباع أو اتكهّن استخدم الزرار التاني)\')') }}">@csrf @method('DELETE')<button class="rounded-lg px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-white">🗑️</button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <dialog id="assetModal" data-crud data-reopen="{{ $reopen }}" data-store-url="{{ route('admin.assets.store') }}" data-title-new="{{ __('أصل جديد') }}" data-title-edit="{{ __('تعديل الأصل') }}"
            class="m-auto w-[calc(100%-2rem)] max-w-3xl rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.assets.store') }}" enctype="multipart/form-data">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="asset"><input type="hidden" name="_edit_url" value="{{ old('_edit_url') }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 data-crud-title class="text-lg font-extrabold text-slate-900">{{ __('أصل جديد') }}</h2>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="grid max-h-[70vh] gap-4 overflow-y-auto px-6 py-5 sm:grid-cols-3">
                <div class="sm:col-span-2">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'name_ar', 'label' => __('اسم الأصل'), 'required' => true, 'placeholder' => __('لابتوب ديل للمحاسب')])</div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'name_en', 'label' => __('بالإنجليزي'), 'dir' => 'ltr'])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'category_id', 'label' => __('التصنيف'), 'type' => 'select', 'options' => $mainCats, 'empty' => __('— اختار —'), 'required' => true])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'sub_category_id', 'label' => __('الفرعي'), 'type' => 'select', 'options' => [], 'empty' => '—'])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'branch_id', 'label' => __('الفرع'), 'type' => 'select', 'options' => $branches, 'empty' => __('عام')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'purchase_date', 'label' => __('تاريخ الشراء'), 'type' => 'date', 'required' => true])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'purchase_cost', 'label' => __('التكلفة'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'required' => true])
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200 sm:col-span-3">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="as_payment_type" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('طريقة الشراء') }} <span class="text-rose-500">*</span></label>
                            <select id="as_payment_type" name="payment_type" data-default="cash" data-no-search class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold outline-none focus:border-blue-400">
                                <option value="cash" @selected(old('payment_type', 'cash') === 'cash')>{{ __('💵 كاش') }}</option>
                                <option value="installments" @selected(old('payment_type') === 'installments')>{{ __('📅 تقسيط') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="as_financial_account_id" class="mb-1.5 block text-sm font-semibold text-slate-700" data-acc-label>{{ __('اتدفع من') }}</label>
                            <select id="as_financial_account_id" name="financial_account_id" data-no-search class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400">
                                <option value="">{{ __('مش هيتخصم من خزنة') }}</option>
                                @foreach ($accounts as $id => $n)<option value="{{ $id }}" @selected((string) old('financial_account_id') === (string) $id)>{{ $n }}</option>@endforeach
                            </select>
                        </div>
                        <div data-inst class="hidden">
                            <label for="as_financier_type" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('التقسيط مع') }}</label>
                            <select id="as_financier_type" name="financier_type" data-default="supplier" data-no-search class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400">
                                @foreach (\App\Support\AssetFinance::FINANCIERS as $k => $l)<option value="{{ $k }}" @selected(old('financier_type', 'supplier') === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div data-inst class="mt-4 hidden">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div data-bank class="hidden"><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('اسم البنك / الشركة') }} <span class="text-rose-500">*</span></label><input name="lender_name" value="{{ old('lender_name') }}" placeholder="{{ __('بنك مسقط') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400"></div>
                            <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('المقدم') }}</label><input type="number" step="0.001" min="0" name="down_payment" value="{{ old('down_payment') }}" data-default="0" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400"></div>
                            <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('الفايدة السنوية %') }}</label><input type="number" step="0.001" min="0" max="100" name="interest_rate" value="{{ old('interest_rate') }}" data-default="0" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400"></div>
                            <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('عدد الأقساط (شهري)') }} <span class="text-rose-500">*</span></label><input type="number" min="1" max="360" name="installments_count" value="{{ old('installments_count') }}" data-default="12" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400"></div>
                            <div><label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('أول قسط يوم') }} <span class="text-rose-500">*</span></label><input type="date" name="first_due_date" value="{{ old('first_due_date') }}" data-default="{{ today()->addMonthNoOverflow()->toDateString() }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400"></div>
                        </div>
                        <div data-preview class="mt-4 grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4"></div>
                        <p data-lock class="mt-3 hidden rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('🔒 فيه أقساط اتدفعت — تغيير التكلفة أو التمويل مش هيتحفظ. ألغي دفع الأقساط الأول لو محتاج.') }}</p>
                    </div>
                </div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'useful_life_months', 'label' => __('العمر الافتراضي (شهور)'), 'type' => 'number', 'min' => 1, 'required' => true, 'value' => old('useful_life_months', 60)])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'salvage_value', 'label' => __('قيمته في آخر عمره'), 'type' => 'number', 'step' => '0.001', 'min' => 0])
                <div><div class="mb-1.5 text-sm font-semibold text-slate-700">{{ __('الإهلاك الشهري') }}</div><div data-monthly class="rounded-xl bg-amber-50 px-3 py-2.5 font-extrabold text-amber-800 ring-1 ring-amber-200">—</div></div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'supplier_id', 'label' => __('المورد'), 'type' => 'select', 'options' => $suppliers, 'empty' => '—'])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'serial_number', 'label' => __('السيريال'), 'dir' => 'ltr'])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'location', 'label' => __('المكان'), 'placeholder' => __('مكتب المبيعات')])
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'custodian', 'label' => __('العهدة مع')])
                <div data-edit-only class="hidden">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'status', 'label' => __('الحالة'), 'type' => 'select', 'options' => ['in_use' => __('شغال'), 'maintenance' => __('صيانة')]])</div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">{{ __('صورة') }}</label>
                    <input type="file" name="image" accept="image/*" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-3">@include('admin.partials.field', ['model' => null, 'idPrefix' => 'as_', 'name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2])</div>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                <button type="submit" class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('حفظ') }}</button>
            </div>
        </form>
    </dialog>

    <dialog id="disposeModal" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm">
        <form method="POST" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            @csrf @method('PATCH')
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div><h2 class="text-lg font-extrabold text-slate-900">{{ __('بيع / تكهين') }}</h2><p class="text-xs text-slate-500" data-info></p></div>
                <button type="button" data-close class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['sold' => __('💰 اتباع'), 'disposed' => __('♻️ اتكهّن')] as $k => $l)
                        <label class="cursor-pointer rounded-xl border-2 border-slate-200 p-3 text-center font-bold has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50"><input type="radio" name="status" value="{{ $k }}" class="sr-only" @checked($k === 'sold')> {{ $l }}</label>
                    @endforeach
                </div>
                @include('admin.partials.field', ['model' => null, 'idPrefix' => 'd_', 'name' => 'disposal_date', 'label' => __('التاريخ'), 'type' => 'date', 'required' => true, 'value' => today()->format('Y-m-d')])
                <div data-sold>@include('admin.partials.field', ['model' => null, 'idPrefix' => 'd_', 'name' => 'disposal_amount', 'label' => __('اتباع بكام'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'hint' => __('المبلغ هيدخل نفس الخزنة اللي اتدفع منها التمن')])</div>
            </div>
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" data-close class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white">{{ __('إغلاق') }}</button>
                <button type="submit" class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-60">{{ __('تأكيد') }}</button>
            </div>
        </form>
    </dialog>

    @php $subsJson = $subCats->map(fn ($s) => ['id' => $s->id, 'p' => $s->parent_id, 'n' => $s->name_ar])->values(); @endphp
    <script>
        (function () {
            const f = document.querySelector('#assetModal form'), subs = @json($subsJson);
            const fill = () => {
                const keep = f.sub_category_id.value;
                f.sub_category_id.innerHTML = '<option value="">—</option>' + subs.filter((s) => String(s.p) === f.category_id.value).map((s) => `<option value="${s.id}">${s.n}</option>`).join('');
                f.sub_category_id.value = keep;
            };
            f.category_id.addEventListener('change', fill); fill();
            const monthly = () => {
                const c = Number(f.purchase_cost.value || 0), s = Number(f.salvage_value.value || 0), m = Number(f.useful_life_months.value || 0);
                f.querySelector('[data-monthly]').textContent = m ? ((c - s) / m).toLocaleString('en', { maximumFractionDigits: 3 }) : '—';
            };
            ['purchase_cost', 'salvage_value', 'useful_life_months'].forEach((n) => { f[n].addEventListener('input', monthly); f[n].addEventListener('change', monthly); });
            monthly();

            // التقسيط: إظهار الحقول + معاينة الجدول (نفس معادلة السيرفر)
            const fmt = (n) => Number(n).toLocaleString('en', { maximumFractionDigits: 3 });
            const inst = () => {
                const on = f.payment_type.value === 'installments';
                f.querySelectorAll('[data-inst]').forEach((el) => el.classList.toggle('hidden', !on));
                f.querySelector('[data-bank]').classList.toggle('hidden', !on || f.financier_type.value !== 'bank');
                f.querySelector('[data-acc-label]').textContent = on ? @js(__('المقدم اتدفع من')) : @js(__('اتدفع من'));
                if (!on) return;
                const cost = Number(f.purchase_cost.value || 0), down = Number(f.down_payment.value || 0), rate = Number(f.interest_rate.value || 0), n = Math.max(1, parseInt(f.installments_count.value || 1, 10));
                const financed = Math.max(0, cost - down), interest = financed * rate / 100 * n / 12, each = (financed + interest) / n;
                let last = '—';
                if (f.first_due_date.value) { const d = new Date(f.first_due_date.value + 'T00:00:00'); d.setMonth(d.getMonth() + n - 1); last = d.toISOString().slice(0, 7); }
                const box = (l, v, c) => `<div class="rounded-xl ${c} p-2"><div class="text-slate-500">${l}</div><div class="text-sm font-extrabold">${v}</div></div>`;
                f.querySelector('[data-preview]').innerHTML = box(@js(__('القسط الشهري')), fmt(each), 'bg-violet-100 text-violet-900') + box(@js(__('المبلغ المقسّط')), fmt(financed), 'bg-white') + box(@js(__('إجمالي الفوايد')), fmt(interest), 'bg-white') + box(@js(__('آخر قسط')), last, 'bg-white');
            };
            ['payment_type', 'financier_type', 'purchase_cost', 'down_payment', 'interest_rate', 'installments_count', 'first_due_date'].forEach((n) => { f[n].addEventListener('input', inst); f[n].addEventListener('change', inst); });
            inst();
            document.querySelectorAll('[data-crud-edit="assetModal"]').forEach((b) => b.addEventListener('click', () => {
                f.querySelector('[data-lock]').classList.toggle('hidden', !JSON.parse(b.dataset.values)._locked);
            }));
            document.querySelectorAll('[data-crud-new="assetModal"]').forEach((b) => b.addEventListener('click', () => f.querySelector('[data-lock]').classList.add('hidden')));

            const d = document.getElementById('disposeModal'), df = d.querySelector('form');
            document.querySelectorAll('[data-dispose]').forEach((b) => b.addEventListener('click', () => {
                df.action = b.dataset.dispose;
                d.querySelector('[data-info]').textContent = b.dataset.name + @js(__(' — قيمته الدفترية ')) + Number(b.dataset.book).toLocaleString('en');
                df.disposal_amount.value = b.dataset.book; d.showModal();
            }));
            df.querySelectorAll('[name=status]').forEach((r) => r.addEventListener('change', () => d.querySelector('[data-sold]').classList.toggle('hidden', df.status.value !== 'sold')));
            d.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => d.close()));
            d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
        })();
    </script>

    {{-- زرار إضافة عائم: دايماً ظاهر --}}
    <button type="button" data-crud-new="assetModal" class="fixed bottom-6 end-6 z-30 inline-flex items-center gap-2 rounded-full bg-blue-600 hover:bg-blue-700 px-6 py-4 text-base font-extrabold text-white shadow-2xl transition hover:scale-105">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        {{ __('أصل جديد') }}
    </button>
    @include('admin.partials.crud-modal-script')
@endsection
