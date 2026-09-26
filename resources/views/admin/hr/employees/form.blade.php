@extends('admin.layouts.app')

@php
    use App\Support\Money;
    use App\Support\Media;
    use App\Support\Payroll;
    $editing = (bool) $e->id;
    $f = fn (array $o) => array_merge(['model' => $e], $o);
    $v = fn (string $k, $d = null) => old($k, $e->{$k} ?? $d);
    $type = $v('employment_type', 'contract');
    $basis = $v('pay_basis', 'monthly');
    $method = $v('payment_method', 'cash');
    $img = Media::url($e->profile_image ?? null);
    $rows = old('allowance_type') ? collect(old('allowance_type'))->map(fn ($t, $i) => (object) ['allowance_type_id' => $t, 'amount' => old('allowance_amount')[$i] ?? '']) : $allowances;
    $card = 'rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200';
    $h2 = 'mb-5 flex items-center gap-2 text-base font-extrabold text-slate-900';
    $num = fn ($n) => '<span class="grid h-7 w-7 place-items-center rounded-lg bg-violet-50 text-xs text-violet-600">'.$n.'</span>';
    $typeCards = ['sponsored' => ['🛂', __('على كفالة المعرض'), __('عقد + إقامة على المعرض')], 'contract' => ['📝', __('بعقد من غير كفالة'), __('عقد عمل بس الكفالة برّه')], 'freelance' => ['🤝', __('من غير عقد'), __('يومية أو شغل حر')]];
@endphp

@section('title', $editing ? __('تعديل: ').$e->full_name_ar : __('موظف جديد'))
@section('subtitle', __('البيانات، نوع التوظيف، وهيكل المرتب'))

@section('content')
<form method="POST" action="{{ $editing ? route('admin.hr.employees.update', $e->id) : route('admin.hr.employees.store') }}" enctype="multipart/form-data" id="empForm"
      class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-3" onsubmit="{{ __('const b = this.querySelector(\'[data-save]\'); b.disabled = true; b.textContent = \'جاري الحفظ...\';') }}">
    @csrf @if ($editing) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul></div>
        @endif

        {{-- 1) الشخصية --}}
        <section class="{{ $card }}">
            <h2 class="{{ $h2 }}">{!! $num(1) !!} {{ __('البيانات الشخصية') }}</h2>
            <div class="flex flex-col gap-5 sm:flex-row">
                <label class="grid h-28 w-28 shrink-0 cursor-pointer place-items-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 hover:border-violet-300">
                    <img data-photo src="{{ $img }}" alt="" class="{{ $img ? '' : 'hidden' }} h-full w-full object-cover">
                    <span data-photo-ph class="{{ $img ? 'hidden' : '' }} text-center text-xs text-slate-400">📷<br>{{ __('صورة') }}</span>
                    <input type="file" name="profile_image" accept="image/*" class="sr-only" onchange="const f=this.files[0]; if(!f) return; const i=document.querySelector('[data-photo]'); i.src=URL.createObjectURL(f); i.classList.remove('hidden'); document.querySelector('[data-photo-ph]').classList.add('hidden');">
                </label>
                <div class="grid flex-1 gap-4 sm:grid-cols-2">
                    @include('admin.partials.field', $f(['name' => 'full_name_ar', 'label' => __('الاسم بالعربي'), 'required' => true]))
                    @include('admin.partials.field', $f(['name' => 'full_name_en', 'label' => __('الاسم بالإنجليزي (للبنك)'), 'dir' => 'ltr']))
                    @include('admin.partials.field', $f(['name' => 'phone', 'label' => __('التليفون'), 'dir' => 'ltr']))
                    @include('admin.partials.field', $f(['name' => 'email', 'label' => __('الإيميل'), 'type' => 'email', 'dir' => 'ltr']))
                </div>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @include('admin.partials.field', $f(['name' => 'national_id', 'label' => __('الرقم المدني / الإقامة')]))
                @include('admin.partials.field', $f(['name' => 'nationality', 'label' => __('الجنسية'), 'list' => 'nats']))
                @include('admin.partials.field', $f(['name' => 'gender', 'label' => __('النوع'), 'type' => 'select', 'options' => ['male' => __('ذكر'), 'female' => __('أنثى')], 'empty' => '—']))
                @include('admin.partials.field', $f(['name' => 'birth_date', 'label' => __('تاريخ الميلاد'), 'type' => 'date']))
                @include('admin.partials.field', $f(['name' => 'marital_status', 'label' => __('الحالة الاجتماعية'), 'type' => 'select', 'options' => ['أعزب' => __('أعزب'), 'متزوج' => __('متزوج'), 'مطلق' => __('مطلق'), 'أرمل' => __('أرمل')], 'empty' => '—']))
                @include('admin.partials.field', $f(['name' => 'emergency_contact', 'label' => __('رقم للطوارئ')]))
            </div>
            <datalist id="nats"><option value="عماني"><option value="مصري"><option value="هندي"><option value="باكستاني"><option value="بنجلاديشي"><option value="فلبيني"><option value="سوداني"><option value="أردني"></datalist>
        </section>

        {{-- 2) الوظيفة --}}
        <section class="{{ $card }}">
            <h2 class="{{ $h2 }}">{!! $num(2) !!} {{ __('الوظيفة') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                @include('admin.partials.field', $f(['name' => 'job_title', 'label' => __('المسمى الوظيفي'), 'required' => true, 'list' => 'jobs']))
                @include('admin.partials.field', $f(['name' => 'hr_department_id', 'label' => __('القسم'), 'type' => 'select', 'options' => $departments, 'empty' => '—']))
                @include('admin.partials.field', $f(['name' => 'branch_id', 'label' => __('الفرع'), 'type' => 'select', 'options' => $branches, 'empty' => '—']))
                @include('admin.partials.field', $f(['name' => 'hr_shift_id', 'label' => __('الوردية'), 'type' => 'select', 'options' => $shifts, 'empty' => __('من غير وردية'), 'hint' => __('منها بيتحسب التأخير والأوفر تايم')]))
                @include('admin.partials.field', $f(['name' => 'user_id', 'label' => __('حسابه على السيستم'), 'type' => 'select', 'options' => $users, 'empty' => __('مالوش حساب'), 'hint' => __('لازم عشان العمولة تتحسب من عقوده')]))
                @include('admin.partials.field', $f(['name' => 'status', 'label' => __('الحالة'), 'type' => 'select', 'options' => ['active' => __('على رأس العمل'), 'on_leave' => __('في إجازة'), 'suspended' => __('موقوف')]]))
            </div>
            <datalist id="jobs"><option value="مندوب مبيعات"><option value="مدير فرع"><option value="محاسب"><option value="كاشير"><option value="فني صيانة"><option value="منظف سيارات"><option value="سائق"><option value="مسوّق"></datalist>
        </section>

        {{-- 3) نوع التوظيف --}}
        <section class="{{ $card }}">
            <h2 class="{{ $h2 }}">{!! $num(3) !!} {{ __('نوع التوظيف') }}</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($typeCards as $k => [$icon, $l, $d])
                    <label class="cursor-pointer rounded-2xl border-2 border-slate-200 p-4 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/60">
                        <input type="radio" name="employment_type" value="{{ $k }}" class="sr-only" @checked($type === $k)>
                        <div class="text-2xl">{{ $icon }}</div>
                        <div class="mt-1 font-extrabold text-slate-900">{{ $l }}</div>
                        <div class="text-xs text-slate-500">{{ $d }}</div>
                    </label>
                @endforeach
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @include('admin.partials.field', $f(['name' => 'join_date', 'label' => __('تاريخ التعيين'), 'type' => 'date', 'required' => true]))
                <div data-contract class="contents">
                    @include('admin.partials.field', $f(['name' => 'contract_start_date', 'label' => __('بداية العقد'), 'type' => 'date']))
                    @include('admin.partials.field', $f(['name' => 'contract_end_date', 'label' => __('نهاية العقد'), 'type' => 'date', 'hint' => __('هيجيلك تنبيه قبلها بشهر')]))
                </div>
            </div>
            <p data-sponsor-tip class="mt-3 rounded-xl bg-blue-50 px-3 py-2 text-xs text-blue-800">{{ __('💡 ضيف الإقامة/التأشيرة في مستندات الموظف بتاريخ انتهائها، والسيستم هيفكّرك قبل ما تخلص.') }}</p>
        </section>

        {{-- 4) المرتب --}}
        <section class="{{ $card }}">
            <h2 class="{{ $h2 }}">{!! $num(4) !!} {{ __('المرتب وطريقة القبض') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (['monthly' => ['📅', __('مرتب شهري'), __('مبلغ ثابت كل شهر، والغياب بيتخصم')], 'daily' => ['☀️', __('يومية'), __('بياخد على عدد الأيام اللي حضرها')]] as $k => [$icon, $l, $d])
                    <label class="cursor-pointer rounded-2xl border-2 border-slate-200 p-4 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/60">
                        <input type="radio" name="pay_basis" value="{{ $k }}" class="sr-only" @checked($basis === $k)>
                        <div class="font-extrabold text-slate-900">{{ $icon }} {{ $l }}</div><div class="text-xs text-slate-500">{{ $d }}</div>
                    </label>
                @endforeach
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700"><span data-basic-label>{{ $basis === 'daily' ? __('اليومية') : __('المرتب الأساسي') }}</span> <span class="text-rose-500">*</span></label>
                    <input name="basic_salary" type="number" step="0.001" min="0" required value="{{ $v('basic_salary') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-lg font-bold outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-50">
                </div>
                @include('admin.partials.field', $f(['name' => 'working_days_per_month', 'label' => __('أيام الشغل في الشهر'), 'type' => 'number', 'min' => 1, 'required' => true, 'hint' => __('منها بيتحسب أجر اليوم')]))
                @include('admin.partials.field', $f(['name' => 'working_hours_per_day', 'label' => __('ساعات اليوم'), 'type' => 'number', 'step' => '0.5', 'min' => 1, 'required' => true]))
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach (['cash' => ['💵', __('كاش في الإيد'), __('بيستلم ويمضي على كشف المرتبات')], 'bank' => ['🏦', __('تحويل بنكي'), __('بيطلعلك ملف تحويل للبنك جاهز')]] as $k => [$icon, $l, $d])
                    <label class="cursor-pointer rounded-2xl border-2 border-slate-200 p-4 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/60">
                        <input type="radio" name="payment_method" value="{{ $k }}" class="sr-only" @checked($method === $k)>
                        <div class="font-extrabold text-slate-900">{{ $icon }} {{ $l }}</div><div class="text-xs text-slate-500">{{ $d }}</div>
                    </label>
                @endforeach
            </div>
            <div data-bank class="mt-4 grid gap-4 sm:grid-cols-2">
                @include('admin.partials.field', $f(['name' => 'bank_name', 'label' => __('البنك'), 'list' => 'banks']))
                @include('admin.partials.field', $f(['name' => 'iban', 'label' => __('IBAN / رقم الحساب'), 'dir' => 'ltr']))
                <datalist id="banks"><option value="بنك مسقط"><option value="البنك الوطني العُماني"><option value="بنك ظفار"><option value="بنك صحار الدولي"><option value="البنك الأهلي"><option value="بنك نزوى"></datalist>
            </div>
        </section>

        {{-- 5) البدلات --}}
        <section class="{{ $card }}">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-base font-extrabold text-slate-900">{!! $num(5) !!} {{ __('بدلات وخصومات ثابتة كل شهر') }}</h2>
                <button type="button" data-add-row class="rounded-xl bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 hover:bg-violet-100">{{ __('+ إضافة') }}</button>
            </div>
            <div data-rows class="space-y-2">
                @foreach ($rows as $row)
                    <div data-row class="flex gap-2">
                        <select name="allowance_type[]" data-no-search class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                            @foreach ($allowanceTypes as $t) <option value="{{ $t->id }}" data-type="{{ $t->type }}" @selected((string) $row->allowance_type_id === (string) $t->id)>{{ $t->type === 'allowance' ? '➕' : '➖' }} {{ \App\Support\Lang::pick($t, 'name') }}</option> @endforeach
                        </select>
                        <input name="allowance_amount[]" type="number" step="0.001" min="0" value="{{ $row->amount }}" placeholder="{{ __('المبلغ') }}" class="w-36 rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                        <button type="button" data-del-row class="grid w-10 place-items-center rounded-xl text-rose-500 hover:bg-rose-50">✕</button>
                    </div>
                @endforeach
            </div>
            <template data-row-tpl>
                <div data-row class="flex gap-2">
                    <select name="allowance_type[]" data-no-search class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                        @foreach ($allowanceTypes as $t) <option value="{{ $t->id }}" data-type="{{ $t->type }}">{{ $t->type === 'allowance' ? '➕' : '➖' }} {{ \App\Support\Lang::pick($t, 'name') }}</option> @endforeach
                    </select>
                    <input name="allowance_amount[]" type="number" step="0.001" min="0" placeholder="{{ __('المبلغ') }}" class="w-36 rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                    <button type="button" data-del-row class="grid w-10 place-items-center rounded-xl text-rose-500 hover:bg-rose-50">✕</button>
                </div>
            </template>
            <p class="mt-3 text-xs text-slate-400">{{ __('أنواع البدلات بتتضاف من') }} <a href="{{ route('admin.hr.settings.index', 'allowances') }}" class="font-bold text-violet-600">{{ __('إعدادات HR') }}</a>{{ __('. الحوافز والجزاءات اللي مرة واحدة بتتسجل من "السلف والحوافز".') }}</p>
        </section>

        {{-- 6) أوفر تايم وعمولة وتأمينات --}}
        <section class="{{ $card }}">
            <h2 class="{{ $h2 }}">{!! $num(6) !!} {{ __('أوفر تايم، عمولة، تأمينات') }}</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl bg-sky-50/60 p-4 ring-1 ring-sky-100">
                    <label class="flex cursor-pointer items-center justify-between font-bold text-slate-800">{{ __('⏱️ أوفر تايم') }}
                        <input type="checkbox" name="overtime_eligible" value="1" class="h-5 w-5 accent-sky-600" @checked($v('overtime_eligible'))></label>
                    <div data-ot class="mt-3">@include('admin.partials.field', $f(['name' => 'overtime_rate', 'label' => __('معامل الساعة'), 'type' => 'number', 'step' => '0.05', 'min' => 1, 'hint' => __('1.25 = الساعة بساعة وربع')]))</div>
                </div>
                <div class="rounded-2xl bg-fuchsia-50/60 p-4 ring-1 ring-fuchsia-100">
                    <div class="font-bold text-slate-800">{{ __('💰 عمولة') }}</div>
                    <div class="mt-3 space-y-3">
                        @include('admin.partials.field', $f(['name' => 'commission_type', 'label' => __('النوع'), 'type' => 'select', 'options' => Payroll::COMMISSION]))
                        <div data-comm>@include('admin.partials.field', $f(['name' => 'commission_value', 'label' => __('القيمة'), 'type' => 'number', 'step' => '0.001', 'min' => 0, 'hint' => __('% أو مبلغ لكل عربية')]))</div>
                    </div>
                </div>
                <div class="rounded-2xl bg-emerald-50/60 p-4 ring-1 ring-emerald-100">
                    <label class="flex cursor-pointer items-center justify-between font-bold text-slate-800">{{ __('🛡️ تأمينات اجتماعية') }}
                        <input type="checkbox" name="is_insured" value="1" class="h-5 w-5 accent-emerald-600" @checked($v('is_insured'))></label>
                    <div data-ins class="mt-3 space-y-3">
                        @include('admin.partials.field', $f(['name' => 'insurance_number', 'label' => __('رقم التأمين'), 'dir' => 'ltr']))
                        @include('admin.partials.field', $f(['name' => 'insurance_percent', 'label' => __('نسبة الخصم %'), 'type' => 'number', 'step' => '0.01', 'min' => 0]))
                    </div>
                </div>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @include('admin.partials.field', $f(['name' => 'annual_leave_days', 'label' => __('رصيد الإجازة السنوية (يوم)'), 'type' => 'number', 'min' => 0, 'required' => true]))
                @include('admin.partials.field', $f(['name' => 'notes', 'label' => __('ملاحظات'), 'type' => 'textarea', 'rows' => 2]))
            </div>
        </section>
    </div>

    {{-- الجنب --}}
    <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            @include('admin.partials.field', $f(['name' => 'employee_code', 'label' => __('كود الموظف'), 'required' => true, 'dir' => 'ltr']))
        </section>
        <section class="rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 p-5 text-white shadow-lg">
            <div class="text-sm text-violet-100">{{ __('المرتب المتوقع في الشهر') }}</div>
            <div class="mt-1 text-3xl font-extrabold" data-out="net">—</div>
            <div class="mt-1 text-[11px] text-violet-200" data-out="net-note"></div>
            <dl class="mt-4 space-y-1.5 border-t border-white/15 pt-3 text-sm">
                <div class="flex justify-between"><dt class="text-violet-100">{{ __('أجر اليوم') }}</dt><dd class="font-bold" data-out="day">—</dd></div>
                <div class="flex justify-between"><dt class="text-violet-100">{{ __('أجر الساعة') }}</dt><dd class="font-bold" data-out="hour">—</dd></div>
                <div class="flex justify-between"><dt class="text-violet-100">{{ __('ساعة الأوفر تايم') }}</dt><dd class="font-bold" data-out="ot">—</dd></div>
                <div class="flex justify-between"><dt class="text-violet-100">{{ __('+ بدلات') }}</dt><dd class="font-bold" data-out="allow">—</dd></div>
                <div class="flex justify-between"><dt class="text-violet-100">{{ __('− خصومات وتأمينات') }}</dt><dd class="font-bold" data-out="ded">—</dd></div>
            </dl>
            <p class="mt-3 text-[11px] text-violet-200">{{ __('من غير الأوفر تايم والعمولة والغياب — دول بيتحسبوا كل شهر من الحضور والمبيعات.') }}</p>
        </section>
        <div class="flex gap-2">
            <a href="{{ $editing ? route('admin.hr.employees.show', $e->id) : route('admin.hr.employees.index') }}" class="flex-1 rounded-xl bg-white py-3 text-center text-sm font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('إلغاء') }}</a>
            <button type="submit" data-save class="flex-[2] rounded-xl bg-violet-600 py-3 text-sm font-bold text-white shadow-sm hover:bg-violet-700 disabled:opacity-60">{{ $editing ? __('حفظ التعديلات') : __('إضافة الموظف') }}</button>
        </div>
    </aside>
</form>

<script>
(function () {
    const f = document.getElementById('empForm'), dec = {{ Money::decimals() }};
    const fmt = (n) => Number(n || 0).toLocaleString('en', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const val = (n) => f.querySelector(`[name="${n}"]:checked`)?.value ?? f.querySelector(`[name="${n}"]`)?.value;
    const toggle = (sel, on) => f.querySelectorAll(sel).forEach((e) => { e.style.display = on ? '' : 'none'; });

    const rowsBox = f.querySelector('[data-rows]'), tpl = f.querySelector('[data-row-tpl]');
    f.querySelector('[data-add-row]').addEventListener('click', () => { rowsBox.appendChild(tpl.content.cloneNode(true)); calc(); });
    rowsBox.addEventListener('click', (e) => { if (e.target.closest('[data-del-row]')) { e.target.closest('[data-row]').remove(); calc(); } });

    const calc = () => {
        const basis = val('pay_basis'), basic = Number(val('basic_salary') || 0), days = Number(val('working_days_per_month') || 30), hours = Number(val('working_hours_per_day') || 8);
        const daily = basis === 'daily' ? basic : basic / days, hourly = daily / hours;
        let allow = 0, ded = 0;
        rowsBox.querySelectorAll('[data-row]').forEach((r) => {
            const amt = Number(r.querySelector('input').value || 0), t = r.querySelector('select').selectedOptions[0]?.dataset.type;
            t === 'deduction' ? ded += amt : allow += amt;
        });
        const monthBasic = basis === 'daily' ? daily * days : basic;
        const ins = f.is_insured.checked ? (monthBasic + allow) * Number(val('insurance_percent') || 0) / 100 : 0;
        ded += ins;
        f.querySelector('[data-out=net]').textContent = fmt(monthBasic + allow - ded);
        f.querySelector('[data-out=net-note]').textContent = basis === 'daily' ? `لو حضر ${days} يوم` : '';
        f.querySelector('[data-out=day]').textContent = fmt(daily);
        f.querySelector('[data-out=hour]').textContent = fmt(hourly);
        f.querySelector('[data-out=ot]').textContent = f.overtime_eligible.checked ? fmt(hourly * Number(val('overtime_rate') || 1.25)) : @js(__('مش مفعّل'));
        f.querySelector('[data-out=allow]').textContent = fmt(allow);
        f.querySelector('[data-out=ded]').textContent = fmt(ded);

        const type = val('employment_type');
        toggle('[data-contract]', type !== 'freelance');
        toggle('[data-sponsor-tip]', type === 'sponsored');
        toggle('[data-bank]', val('payment_method') === 'bank');
        toggle('[data-ot]', f.overtime_eligible.checked);
        toggle('[data-comm]', val('commission_type') !== 'none');
        toggle('[data-ins]', f.is_insured.checked);
        f.querySelector('[data-basic-label]').textContent = basis === 'daily' ? @js(__('اليومية')) : @js(__('المرتب الأساسي'));
    };
    f.addEventListener('input', calc);
    f.addEventListener('change', calc);
    calc();
})();
</script>
@endsection
