<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Support\Company;
use App\Support\Media;
use App\Support\Money;
use App\Support\Payroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ملفات الموظفين: البيانات، نوع التوظيف، هيكل المرتب، المستندات، نهاية الخدمة.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $f = [
            'q'          => trim((string) $request->query('q')),
            'branch'     => $request->integer('branch') ?: null,
            'department' => $request->integer('department') ?: null,
            'type'       => array_key_exists($request->query('type'), Payroll::EMPLOYMENT) ? $request->query('type') : null,
            'status'     => array_key_exists($request->query('status'), Payroll::STATUSES) ? $request->query('status') : 'active',
        ];
        if ($request->query('status') === 'all') {
            $f['status'] = null;
        }

        $employees = $this->base()
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('hr_employees.full_name_ar', 'like', "%{$f['q']}%")
                ->orWhere('hr_employees.employee_code', 'like', "%{$f['q']}%")->orWhere('hr_employees.phone', 'like', "%{$f['q']}%")
                ->orWhere('hr_employees.national_id', 'like', "%{$f['q']}%")->orWhere('hr_employees.job_title', 'like', "%{$f['q']}%")))
            ->when($f['branch'], fn ($q, $v) => $q->where('hr_employees.branch_id', $v))
            ->when($f['department'], fn ($q, $v) => $q->where('hr_employees.hr_department_id', $v))
            ->when($f['type'], fn ($q, $v) => $q->where('hr_employees.employment_type', $v))
            ->when($f['status'], fn ($q, $v) => $q->where('hr_employees.status', $v))
            ->addSelect(DB::raw("(select min(expiry_date) from hr_documents d where d.employee_id = hr_employees.id and d.expiry_date is not null) as next_expiry"))
            ->orderBy('hr_employees.full_name_ar')->get();

        $active = DB::table('hr_employees')->whereNull('deleted_at')->where('status', '!=', 'terminated');
        $soon = today()->addDays(30)->toDateString();

        return view('admin.hr.employees.index', [
            'employees' => $employees,
            'filters'   => $f,
            'stats'     => [
                'count'     => (clone $active)->count(),
                'payroll'   => (float) (clone $active)->where('pay_basis', 'monthly')->sum('basic_salary')
                    + (float) DB::table('hr_employee_allowances')->join('hr_employees', 'hr_employees.id', '=', 'hr_employee_allowances.employee_id')
                        ->join('hr_allowance_types', 'hr_allowance_types.id', '=', 'hr_employee_allowances.allowance_type_id')
                        ->whereNull('hr_employees.deleted_at')->where('hr_employees.status', '!=', 'terminated')->where('hr_allowance_types.type', 'allowance')
                        ->sum('hr_employee_allowances.amount'),
                'by_type'   => (clone $active)->groupBy('employment_type')->select('employment_type', DB::raw('count(*) as c'))->pluck('c', 'employment_type'),
                'bank'      => (clone $active)->where('payment_method', 'bank')->count(),
            ],
            'expiring'  => DB::table('hr_documents')->join('hr_employees', 'hr_employees.id', '=', 'hr_documents.employee_id')
                ->whereNull('hr_employees.deleted_at')->where('hr_employees.status', '!=', 'terminated')
                ->whereNotNull('expiry_date')->where('expiry_date', '<=', $soon)
                ->select('hr_documents.*', 'hr_employees.full_name_ar')->orderBy('expiry_date')->limit(8)->get(),
            'contractsEnding' => (clone $active)->whereNotNull('contract_end_date')->where('contract_end_date', '<=', $soon)->orderBy('contract_end_date')->get(['id', 'full_name_ar', 'contract_end_date']),
            'onLeaveToday' => DB::table('hr_leave_requests')->where('status', 'approved')->where('start_date', '<=', today()->toDateString())->where('end_date', '>=', today()->toDateString())->count(),
        ] + $this->options());
    }

    public function create(): View
    {
        $e = (object) [
            'id' => null, 'employee_code' => $this->nextCode(), 'join_date' => today()->toDateString(), 'employment_type' => 'contract',
            'pay_basis' => 'monthly', 'working_days_per_month' => 30, 'working_hours_per_day' => 8, 'payment_method' => 'cash',
            'overtime_rate' => 1.25, 'commission_type' => 'none', 'commission_value' => 0, 'annual_leave_days' => 30, 'status' => 'active',
        ];

        return view('admin.hr.employees.form', ['e' => $e, 'allowances' => collect()] + $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $id = DB::table('hr_employees')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        $this->syncAllowances($request, $id);

        return redirect()->route('admin.hr.employees.show', $id)->with('status', 'تم إضافة الموظف');
    }

    public function show(int $employee): View
    {
        $e = $this->base()->where('hr_employees.id', $employee)->first();
        abort_unless($e, 404);

        $monthStart = now()->startOfMonth()->toDateString();

        return view('admin.hr.employees.show', [
            'e'          => $e,
            'allowances' => $this->allowances($employee),
            'documents'  => DB::table('hr_documents')->where('employee_id', $employee)->orderBy('expiry_date')->get(),
            'attendance' => DB::table('hr_attendances')->where('employee_id', $employee)->where('date', '>=', $monthStart)
                ->groupBy('status')->select('status', DB::raw('count(*) as c'), DB::raw('sum(overtime_minutes) as ot'), DB::raw('sum(delay_minutes) as late'))->get()->keyBy('status'),
            'balances'   => Payroll::leaveBalances($employee, now()->year),
            'leaves'     => DB::table('hr_leave_requests')->join('hr_leave_types', 'hr_leave_types.id', '=', 'hr_leave_requests.leave_type_id')
                ->where('employee_id', $employee)->select('hr_leave_requests.*', \App\Support\Lang::sel('hr_leave_types.name_ar as type_name'))->latest('start_date')->limit(10)->get(),
            'loans'      => DB::table('hr_loans')->where('employee_id', $employee)->latest('loan_date')->get(),
            'adjustments'=> DB::table('hr_adjustments')->where('employee_id', $employee)->latest('date')->limit(15)->get(),
            'payslips'   => DB::table('hr_payrolls')->join('hr_payroll_runs as r', 'r.id', '=', 'hr_payrolls.payroll_run_id')
                ->where('employee_id', $employee)->select('hr_payrolls.*', 'r.year', 'r.month', 'r.status as run_status')
                ->orderByDesc('r.year')->orderByDesc('r.month')->limit(12)->get(),
            'eos'        => DB::table('hr_end_of_services')->where('employee_id', $employee)->latest('id')->first(),
            'eosPreview' => $e->status !== 'terminated' ? Payroll::endOfService($e, today()->toDateString(), 30) : null,
            'accounts'   => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->pluckLocal('name', 'id'),
            'leaveTypes' => DB::table('hr_leave_types')->where('is_active', true)->pluckLocal('name', 'id'),
        ]);
    }

    public function edit(int $employee): View
    {
        $e = DB::table('hr_employees')->where('id', $employee)->first();
        abort_unless($e, 404);

        return view('admin.hr.employees.form', ['e' => $e, 'allowances' => $this->allowances($employee)] + $this->options($e->user_id));
    }

    public function update(Request $request, int $employee): RedirectResponse
    {
        $row = DB::table('hr_employees')->where('id', $employee)->first();
        abort_unless($row, 404);
        $data = $this->validated($request, $employee);
        if ($request->hasFile('profile_image')) {
            Media::delete($row->profile_image);
        } else {
            unset($data['profile_image']);
        }
        DB::table('hr_employees')->where('id', $employee)->update($data + ['updated_at' => now()]);
        $this->syncAllowances($request, $employee);

        return redirect()->route('admin.hr.employees.show', $employee)->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(int $employee): RedirectResponse
    {
        if (DB::table('hr_payrolls')->where('employee_id', $employee)->exists()) {
            return back()->with('error', 'الموظف ليه مرتبات متسجلة. اعمله "نهاية خدمة" بدل المسح.');
        }
        DB::table('hr_employees')->where('id', $employee)->update(['deleted_at' => now()]);

        return redirect()->route('admin.hr.employees.index')->with('status', 'تم المسح');
    }

    // ─── المستندات ───
    public function storeDocument(Request $request, int $employee): RedirectResponse
    {
        $data = $request->validate([
            'document_type'   => ['required', Rule::in(array_keys(Payroll::DOCUMENTS))],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date'      => ['nullable', 'date'],
            'expiry_date'     => ['nullable', 'date'],
            'attachment'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], ['required' => 'اختار نوع المستند.', 'mimes' => 'المرفق لازم صورة أو PDF.']);

        $data['attachment'] = $request->hasFile('attachment') ? Media::store($request->file('attachment'), 'hr-documents') : null;
        DB::table('hr_documents')->insert($data + ['employee_id' => $employee, 'created_at' => now(), 'updated_at' => now()]);

        return redirect(route('admin.hr.employees.show', $employee).'#documents')->with('status', 'تم إضافة المستند');
    }

    public function destroyDocument(int $document): RedirectResponse
    {
        $d = DB::table('hr_documents')->where('id', $document)->first();
        abort_unless($d, 404);
        Media::delete($d->attachment);
        DB::table('hr_documents')->where('id', $document)->delete();

        return back()->with('status', 'تم مسح المستند');
    }

    // ─── نهاية الخدمة ───
    public function endService(Request $request, int $employee): RedirectResponse
    {
        $e = DB::table('hr_employees')->where('id', $employee)->first();
        abort_unless($e && $e->status !== 'terminated', 404);

        $data = $request->validate([
            'termination_date'       => ['required', 'date'],
            'reason'                 => ['required', 'string', 'max:255'],
            'gratuity_days_per_year' => ['required', 'numeric', 'min:0', 'max:60'],
            'other_additions'        => ['nullable', 'numeric', 'min:0'],
            'other_deductions'       => ['nullable', 'numeric', 'min:0'],
            'deduct_loans'           => ['nullable', 'boolean'],
            'financial_account_id'   => ['nullable', Rule::exists('financial_accounts', 'id')],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ], ['required' => 'الحقل ده مطلوب.']);

        $calc = Payroll::endOfService($e, $data['termination_date'], (float) $data['gratuity_days_per_year']);
        $loanDed = $request->boolean('deduct_loans') ? $calc['loan_deduction'] : 0;
        $net = $calc['gratuity_amount'] + $calc['leave_encashment'] + (float) ($data['other_additions'] ?? 0) - $loanDed - (float) ($data['other_deductions'] ?? 0);

        DB::transaction(function () use ($request, $e, $data, $calc, $loanDed, $net) {
            $id = DB::table('hr_end_of_services')->insertGetId(array_merge($calc, [
                'employee_id' => $e->id, 'termination_date' => $data['termination_date'], 'reason' => $data['reason'],
                'loan_deduction' => $loanDed, 'other_additions' => Money::round($data['other_additions'] ?? 0),
                'other_deductions' => Money::round($data['other_deductions'] ?? 0), 'net_payout' => Money::round(max(0, $net)),
                'financial_account_id' => $data['financial_account_id'] ?? null,
                'paid_date' => ! empty($data['financial_account_id']) ? $data['termination_date'] : null,
                'notes' => $data['notes'] ?? null, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]));
            if ($loanDed > 0) {
                DB::table('hr_loans')->where('employee_id', $e->id)->where('status', 'active')->update(['paid_amount' => DB::raw('amount'), 'status' => 'completed', 'updated_at' => now()]);
            }
            DB::table('hr_employees')->where('id', $e->id)->update(['status' => 'terminated', 'termination_date' => $data['termination_date'], 'updated_at' => now()]);

            return $id;
        });

        return back()->with('status', 'تم إنهاء خدمة الموظف — صافي المستحق '.Money::format(max(0, $net)));
    }

    public function eosPrint(int $employee): View
    {
        $e = $this->base()->where('hr_employees.id', $employee)->first();
        $eos = DB::table('hr_end_of_services')->where('employee_id', $employee)->latest('id')->first();
        abort_unless($e && $eos, 404);

        return view('admin.hr.employees.eos-print', ['e' => $e, 'eos' => $eos, 'company' => Company::info()]);
    }

    // ─── أدوات ───
    private function base()
    {
        return DB::table('hr_employees')->whereNull('hr_employees.deleted_at')
            ->leftJoin('branches', 'branches.id', '=', 'hr_employees.branch_id')
            ->leftJoin('hr_departments', 'hr_departments.id', '=', 'hr_employees.hr_department_id')
            ->leftJoin('hr_shifts', 'hr_shifts.id', '=', 'hr_employees.hr_shift_id')
            ->leftJoin('users', 'users.id', '=', 'hr_employees.user_id')
            ->select('hr_employees.*', \App\Support\Lang::sel('branches.name_ar as branch_name'), \App\Support\Lang::sel('hr_departments.name_ar as department_name'),
                \App\Support\Lang::sel('hr_shifts.name_ar as shift_name'), 'hr_shifts.start_time as shift_start', 'hr_shifts.end_time as shift_end', 'users.name as user_name', 'users.email as user_email');
    }

    private function allowances(int $employee)
    {
        return DB::table('hr_employee_allowances')->join('hr_allowance_types', 'hr_allowance_types.id', '=', 'hr_employee_allowances.allowance_type_id')
            ->where('employee_id', $employee)->select('hr_employee_allowances.*', 'hr_allowance_types.name_ar', 'hr_allowance_types.type')->get();
    }

    private function options(?int $currentUser = null): array
    {
        $taken = DB::table('hr_employees')->whereNull('deleted_at')->whereNotNull('user_id')->when($currentUser, fn ($q, $v) => $q->where('user_id', '!=', $v))->pluck('user_id');

        return [
            'branches'    => DB::table('branches')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'departments' => DB::table('hr_departments')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'shifts'      => DB::table('hr_shifts')->where('is_active', true)->get()->mapWithKeys(fn ($s) => [$s->id => $s->name_ar.' ('.substr($s->start_time, 0, 5).' - '.substr($s->end_time, 0, 5).')']),
            'users'       => DB::table('users')->whereNotIn('id', $taken)->when(\Illuminate\Support\Facades\Schema::hasColumn('users', 'role'), fn ($q) => $q->where('role', '!=', 'customer'))
                ->orderBy('name')->pluck('name', 'id'),
            'allowanceTypes' => DB::table('hr_allowance_types')->where('is_active', true)->get(['id', 'name_ar', 'type']),
        ];
    }

    private function validated(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'employee_code'   => ['required', 'string', 'max:30', Rule::unique('hr_employees', 'employee_code')->ignore($id)],
            'full_name_ar'    => ['required', 'string', 'max:150'],
            'full_name_en'    => ['nullable', 'string', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:150'],
            'national_id'     => ['nullable', 'string', 'max:50'],
            'nationality'     => ['nullable', 'string', 'max:80'],
            'gender'          => ['nullable', Rule::in(['male', 'female'])],
            'birth_date'      => ['nullable', 'date'],
            'marital_status'  => ['nullable', 'string', 'max:30'],
            'emergency_contact' => ['nullable', 'string', 'max:150'],
            'job_title'       => ['required', 'string', 'max:100'],
            'join_date'       => ['required', 'date'],
            'branch_id'       => ['nullable', Rule::exists('branches', 'id')],
            'hr_department_id'=> ['nullable', Rule::exists('hr_departments', 'id')],
            'hr_shift_id'     => ['nullable', Rule::exists('hr_shifts', 'id')],
            'user_id'         => ['nullable', Rule::exists('users', 'id'), Rule::unique('hr_employees', 'user_id')->ignore($id)],
            'employment_type' => ['required', Rule::in(array_keys(Payroll::EMPLOYMENT))],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date'   => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'pay_basis'       => ['required', Rule::in(array_keys(Payroll::PAY_BASIS))],
            'basic_salary'    => ['required', 'numeric', 'min:0'],
            'working_days_per_month' => ['required', 'integer', 'min:1', 'max:31'],
            'working_hours_per_day'  => ['required', 'numeric', 'min:1', 'max:24'],
            'payment_method'  => ['required', Rule::in(array_keys(Payroll::PAY_METHOD))],
            'bank_name'       => ['nullable', 'required_if:payment_method,bank', 'string', 'max:100'],
            'iban'            => ['nullable', 'required_if:payment_method,bank', 'string', 'max:60'],
            'overtime_rate'   => ['nullable', 'numeric', 'min:1', 'max:5'],
            'commission_type' => ['required', Rule::in(array_keys(Payroll::COMMISSION))],
            'commission_value'=> ['nullable', 'numeric', 'min:0'],
            'insurance_number'  => ['nullable', 'string', 'max:50'],
            'insurance_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'annual_leave_days' => ['required', 'integer', 'min:0', 'max:90'],
            'status'          => ['required', Rule::in(['active', 'on_leave', 'suspended'])],
            'notes'           => ['nullable', 'string', 'max:3000'],
            'profile_image'   => ['nullable', 'image', 'max:3072'],
        ], [
            'required' => 'الحقل ده مطلوب.', 'required_if' => 'مطلوب للتحويل البنكي.', 'unique' => 'مستخدم لموظف تاني.',
            'after_or_equal' => 'نهاية العقد قبل بدايته.', 'email' => 'الإيميل مش صحيح.',
        ]);

        if ($data['commission_type'] !== 'none' && empty($data['user_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['user_id' => 'العمولة بتتحسب من العقود اللي الموظف عملها بحسابه — اربطه بحساب على السيستم.']);
        }

        $data['profile_image'] = $request->hasFile('profile_image') ? Media::store($request->file('profile_image'), 'hr-photos') : null;

        return array_merge($data, [
            'basic_salary'      => Money::round($data['basic_salary']),
            'overtime_eligible' => $request->boolean('overtime_eligible'),
            'overtime_rate'     => (float) ($data['overtime_rate'] ?? 1.25),
            'commission_value'  => $data['commission_type'] === 'none' ? 0 : (float) ($data['commission_value'] ?? 0),
            'is_insured'        => $request->boolean('is_insured'),
            'insurance_percent' => $request->boolean('is_insured') ? (float) ($data['insurance_percent'] ?? 0) : 0,
            'bank_name'         => $data['payment_method'] === 'bank' ? ($data['bank_name'] ?? null) : null,
            'iban'              => $data['payment_method'] === 'bank' ? ($data['iban'] ?? null) : null,
        ]);
    }

    private function syncAllowances(Request $request, int $employee): void
    {
        DB::table('hr_employee_allowances')->where('employee_id', $employee)->delete();
        $types = (array) $request->input('allowance_type', []);
        $amounts = (array) $request->input('allowance_amount', []);
        $valid = DB::table('hr_allowance_types')->pluck('id')->all();
        foreach ($types as $i => $type) {
            $amount = (float) ($amounts[$i] ?? 0);
            if ($type && in_array((int) $type, $valid, true) && $amount > 0) {
                DB::table('hr_employee_allowances')->insert(['employee_id' => $employee, 'allowance_type_id' => (int) $type, 'amount' => Money::round($amount), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function nextCode(): string
    {
        return 'EMP-'.str_pad((int) DB::table('hr_employees')->max('id') + 1, 3, '0', STR_PAD_LEFT);
    }
}
