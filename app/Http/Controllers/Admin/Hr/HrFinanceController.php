<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Payroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * السلف (بتتخصم أقساط من المرتب) + الحوافز والجزاءات (بتنزل في مرتب الشهر).
 */
class HrFinanceController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'adjustments' ? 'adjustments' : 'loans';

        return view('admin.hr.finance.index', [
            'tab' => $tab,
            'loans' => DB::table('hr_loans')->join('hr_employees', 'hr_employees.id', '=', 'hr_loans.employee_id')
                ->leftJoin('financial_accounts as fa', 'fa.id', '=', 'hr_loans.financial_account_id')
                ->select('hr_loans.*', 'hr_employees.full_name_ar', 'hr_employees.job_title', \App\Support\Lang::sel('fa.name_ar as account_name'))
                ->orderByRaw("hr_loans.status = 'active' desc")->latest('loan_date')->get(),
            'adjustments' => DB::table('hr_adjustments')->join('hr_employees', 'hr_employees.id', '=', 'hr_adjustments.employee_id')
                ->leftJoin('hr_payroll_runs as r', 'r.id', '=', 'hr_adjustments.payroll_run_id')
                ->select('hr_adjustments.*', 'hr_employees.full_name_ar', 'r.year as run_year', 'r.month as run_month', 'r.status as run_status')
                ->latest('date')->limit(200)->get(),
            'employees' => DB::table('hr_employees')->whereNull('deleted_at')->where('status', '!=', 'terminated')->orderBy('full_name_ar')
                ->get(['id', 'full_name_ar', 'basic_salary', 'pay_basis', 'working_days_per_month']),
            'accounts' => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->pluckLocal('name', 'id'),
        ]);
    }

    public function storeLoan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id'         => ['required', Rule::exists('hr_employees', 'id')],
            'amount'              => ['required', 'numeric', 'min:0.001'],
            'monthly_installment' => ['required', 'numeric', 'min:0.001', 'lte:amount'],
            'loan_date'           => ['required', 'date'],
            'start_month'         => ['required', 'date_format:Y-m'],
            'financial_account_id'=> ['nullable', Rule::exists('financial_accounts', 'id')],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ], ['required' => 'الحقل ده مطلوب.', 'lte' => 'القسط أكبر من السلفة.']);

        DB::table('hr_loans')->insert([
            'employee_id' => $data['employee_id'], 'amount' => Money::round($data['amount']),
            'monthly_installment' => Money::round($data['monthly_installment']), 'loan_date' => $data['loan_date'],
            'start_month' => $data['start_month'].'-01', 'financial_account_id' => $data['financial_account_id'] ?? null,
            'notes' => $data['notes'] ?? null, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'تم تسجيل السلفة');
    }

    public function cancelLoan(int $loan): RedirectResponse
    {
        DB::table('hr_loans')->where('id', $loan)->where('status', 'active')->update(['status' => 'cancelled', 'updated_at' => now()]);

        return back()->with('status', 'تم إيقاف السلفة (مش هيتخصم منها أقساط تاني)');
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')],
            'type'        => ['required', Rule::in(['bonus', 'deduction'])],
            'reason'      => ['required', 'string', 'max:255'],
            'days'        => ['nullable', 'numeric', 'min:0.25', 'max:31'],
            'amount'      => ['nullable', 'required_without:days', 'numeric', 'min:0.001'],
            'date'        => ['required', 'date'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ], ['required' => 'الحقل ده مطلوب.', 'required_without' => 'اكتب المبلغ أو عدد الأيام.']);

        $amount = $data['amount'] ?? null;
        if (! empty($data['days'])) {
            $e = DB::table('hr_employees')->where('id', $data['employee_id'])->first();
            $amount = (float) $data['days'] * Payroll::dailyRate($e);
        }

        DB::table('hr_adjustments')->insert([
            'employee_id' => $data['employee_id'], 'type' => $data['type'], 'reason' => $data['reason'],
            'days' => $data['days'] ?? null, 'amount' => Money::round($amount), 'date' => $data['date'],
            'notes' => $data['notes'] ?? null, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.hr.finance.index', ['tab' => 'adjustments'])->with('status', $data['type'] === 'bonus' ? 'تم تسجيل الحافز' : 'تم تسجيل الخصم');
    }

    public function destroyAdjustment(int $adjustment): RedirectResponse
    {
        $a = DB::table('hr_adjustments')->leftJoin('hr_payroll_runs as r', 'r.id', '=', 'hr_adjustments.payroll_run_id')
            ->where('hr_adjustments.id', $adjustment)->select('hr_adjustments.id', 'r.status')->first();
        abort_unless($a, 404);
        if ($a->status && $a->status !== 'draft') {
            return back()->with('error', 'الحركة دي نزلت في مرتب شهر معتمد. فك اعتماد الشهر الأول.');
        }
        DB::table('hr_adjustments')->where('id', $adjustment)->delete();

        return back()->with('status', 'تم المسح — أعد حساب مرتبات الشهر لو كانت محسوبة');
    }
}
