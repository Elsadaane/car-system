<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Support\Company;
use App\Support\Ledger;
use App\Support\Money;
use App\Support\Payroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مرتبات الشهر:  حساب (مسودة) ← مراجعة وتعديل يدوي ← اعتماد (السلف بتتخصم) ← صرف (كاش / بنك)
 */
class PayrollController extends Controller
{
    public const MONTHS = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    public const RUN_STATUS = ['draft' => 'مسودة', 'approved' => 'معتمد', 'paid' => 'اتصرف'];

    public function index(): View
    {
        $runs = DB::table('hr_payroll_runs')
            ->select('hr_payroll_runs.*',
                DB::raw('(select count(*) from hr_payrolls p where p.payroll_run_id = hr_payroll_runs.id) as employees'),
                DB::raw('(select coalesce(sum(net),0) from hr_payrolls p where p.payroll_run_id = hr_payroll_runs.id) as total_net'),
                DB::raw('(select coalesce(sum(gross),0) from hr_payrolls p where p.payroll_run_id = hr_payroll_runs.id) as total_gross'),
                DB::raw('(select coalesce(sum(overtime_amount + commission),0) from hr_payrolls p where p.payroll_run_id = hr_payroll_runs.id) as extras'))
            ->orderByDesc('year')->orderByDesc('month')->get();

        $next = $runs->first() ? Carbon::create($runs->first()->year, $runs->first()->month, 1)->addMonth() : now()->startOfMonth();

        return view('admin.hr.payroll.index', ['runs' => $runs, 'next' => $next]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']], ['required' => 'اختار الشهر.']);
        [$y, $m] = array_map('intval', explode('-', $data['month']));

        $run = DB::table('hr_payroll_runs')->where('year', $y)->where('month', $m)->first();
        if (! $run) {
            $id = DB::table('hr_payroll_runs')->insertGetId(['year' => $y, 'month' => $m, 'status' => 'draft', 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $run = DB::table('hr_payroll_runs')->where('id', $id)->first();
            DB::transaction(fn () => Payroll::generate($run));
        }

        return redirect()->route('admin.hr.payroll.show', $run->id)->with('status', 'تم حساب مرتبات '.self::MONTHS[$m].' '.$y);
    }

    public function show(int $run): View
    {
        $r = $this->run($run);
        $rows = DB::table('hr_payrolls')->where('payroll_run_id', $run)
            ->join('hr_employees as e', 'e.id', '=', 'hr_payrolls.employee_id')
            ->leftJoin('branches', 'branches.id', '=', 'hr_payrolls.branch_id')
            ->leftJoin('financial_accounts as fa', 'fa.id', '=', 'hr_payrolls.financial_account_id')
            ->select('hr_payrolls.*', 'e.full_name_ar', 'e.job_title', 'e.employee_code', 'e.employment_type', 'e.iban', 'e.bank_name', 'e.basic_salary as base_salary',
                \App\Support\Lang::sel('branches.name_ar as branch_name'), \App\Support\Lang::sel('fa.name_ar as account_name'))
            ->orderBy('e.full_name_ar')->get();

        $balances = Ledger::balances();

        return view('admin.hr.payroll.show', [
            'run' => $r, 'rows' => $rows,
            'byMethod' => ['cash' => $rows->where('payment_method', 'cash')->sum('net'), 'bank' => $rows->where('payment_method', 'bank')->sum('net')],
            'warnings' => array_filter([
                ($n = $rows->where('pay_basis', 'daily')->where('days_worked', 0)->count()) ? "{$n} موظف يومية مالهمش حضور متسجل الشهر ده — راجع شيت الحضور." : null,
                ($n = $rows->where('net', '<', 0)->count()) ? "{$n} موظف صافي مرتبهم بالسالب (الخصومات أكبر من المستحق)." : null,
                ($n = $rows->where('payment_method', 'bank')->filter(fn ($x) => ! $x->iban)->count()) ? "{$n} موظف بيقبض تحويل ومفيش IBAN متسجل." : null,
            ]),
            'accounts' => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->get()
                ->mapWithKeys(fn ($a) => [$a->id => $a->name_ar.' ('.Money::format($balances[$a->id]['balance'] ?? 0, false).')']),
            'accountTypes' => DB::table('financial_accounts')->whereNull('deleted_at')->pluck('type', 'id'),
        ]);
    }

    public function generate(int $run): RedirectResponse
    {
        $r = $this->run($run);
        abort_unless($r->status === 'draft', 403);
        $n = DB::transaction(fn () => Payroll::generate($r));

        return back()->with('status', "تم إعادة حساب مرتبات {$n} موظف من الحضور والسلف والحوافز");
    }

    public function updateRow(Request $request, int $payroll): RedirectResponse
    {
        $p = DB::table('hr_payrolls')->join('hr_payroll_runs as r', 'r.id', '=', 'hr_payrolls.payroll_run_id')->where('hr_payrolls.id', $payroll)->select('hr_payrolls.*', 'r.status')->first();
        abort_unless($p && $p->status === 'draft', 403);

        $data = $request->validate([
            'other_additions'  => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'commission'       => ['nullable', 'numeric', 'min:0'],
            'overtime_amount'  => ['nullable', 'numeric', 'min:0'],
            'payment_method'   => ['required', Rule::in(['cash', 'bank'])],
            'notes'            => ['nullable', 'string', 'max:255'],
        ]);
        DB::table('hr_payrolls')->where('id', $payroll)->update([
            'other_additions' => Money::round($data['other_additions'] ?? 0), 'other_deductions' => Money::round($data['other_deductions'] ?? 0),
            'commission' => Money::round($data['commission'] ?? $p->commission), 'overtime_amount' => Money::round($data['overtime_amount'] ?? $p->overtime_amount),
            'payment_method' => $data['payment_method'], 'notes' => $data['notes'] ?? null,
        ]);
        Payroll::retotal($payroll);

        return back()->with('status', 'تم تعديل المرتب');
    }

    public function approve(int $run): RedirectResponse
    {
        $r = $this->run($run);
        abort_unless($r->status === 'draft', 403);
        DB::transaction(function () use ($r) {
            Payroll::applyLoans($r, 1);
            DB::table('hr_payroll_runs')->where('id', $r->id)->update(['status' => 'approved', 'approved_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'تم اعتماد مرتبات الشهر — أقساط السلف اتخصمت');
    }

    public function unapprove(int $run): RedirectResponse
    {
        $r = $this->run($run);
        abort_unless($r->status === 'approved', 403);
        DB::transaction(function () use ($r) {
            Payroll::applyLoans($r, -1);
            DB::table('hr_payroll_runs')->where('id', $r->id)->update(['status' => 'draft', 'approved_at' => null, 'updated_at' => now()]);
        });

        return back()->with('status', 'رجع مسودة — تقدر تعدّل وتعيد الحساب');
    }

    public function pay(Request $request, int $run): RedirectResponse
    {
        $r = $this->run($run);
        abort_unless($r->status === 'approved', 403);
        $data = $request->validate([
            'cash_account_id' => ['nullable', Rule::exists('financial_accounts', 'id')],
            'bank_account_id' => ['nullable', Rule::exists('financial_accounts', 'id')],
            'paid_date'       => ['required', 'date', 'before_or_equal:today'],
        ], ['before_or_equal' => 'مينفعش تاريخ في المستقبل.']);

        $rows = DB::table('hr_payrolls')->where('payroll_run_id', $run)->get(['id', 'payment_method']);
        foreach (['cash', 'bank'] as $m) {
            if ($rows->where('payment_method', $m)->isNotEmpty() && empty($data[$m.'_account_id'])) {
                return back()->withErrors([$m.'_account_id' => $m === 'cash' ? 'اختار الخزنة اللي هيتصرف منها الكاش.' : 'اختار الحساب البنكي اللي هيتحوّل منه.']);
            }
        }

        DB::transaction(function () use ($r, $data) {
            foreach (['cash', 'bank'] as $m) {
                DB::table('hr_payrolls')->where('payroll_run_id', $r->id)->where('payment_method', $m)
                    ->update(['financial_account_id' => $data[$m.'_account_id'] ?? null, 'paid_date' => $data['paid_date'], 'updated_at' => now()]);
            }
            DB::table('hr_payroll_runs')->where('id', $r->id)->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'تم صرف المرتبات واتخصمت من الخزائن');
    }

    public function unpay(int $run): RedirectResponse
    {
        $r = $this->run($run);
        abort_unless($r->status === 'paid', 403);
        DB::table('hr_payrolls')->where('payroll_run_id', $run)->update(['financial_account_id' => null, 'paid_date' => null]);
        DB::table('hr_payroll_runs')->where('id', $run)->update(['status' => 'approved', 'paid_at' => null, 'updated_at' => now()]);

        return back()->with('status', 'تم إلغاء الصرف والفلوس رجعت للخزائن');
    }

    public function destroy(int $run): RedirectResponse
    {
        $r = $this->run($run);
        abort_unless($r->status === 'draft', 403);
        DB::table('hr_adjustments')->where('payroll_run_id', $run)->update(['payroll_run_id' => null]);
        DB::table('hr_payroll_runs')->where('id', $run)->delete();

        return redirect()->route('admin.hr.payroll.index')->with('status', 'تم مسح المسودة');
    }

    public function payslip(int $payroll): View
    {
        $p = DB::table('hr_payrolls')->where('hr_payrolls.id', $payroll)
            ->join('hr_payroll_runs as r', 'r.id', '=', 'hr_payrolls.payroll_run_id')
            ->join('hr_employees as e', 'e.id', '=', 'hr_payrolls.employee_id')
            ->leftJoin('branches', 'branches.id', '=', 'hr_payrolls.branch_id')
            ->leftJoin('hr_departments as d', 'd.id', '=', 'e.hr_department_id')
            ->select('hr_payrolls.*', 'r.year', 'r.month', 'r.status as run_status', 'e.full_name_ar', 'e.full_name_en', 'e.employee_code', 'e.job_title',
                'e.national_id', 'e.iban', 'e.bank_name', 'e.join_date', \App\Support\Lang::sel('branches.name_ar as branch_name'), \App\Support\Lang::sel('d.name_ar as department_name'))
            ->first();
        abort_unless($p, 404);

        return view('admin.hr.payroll.payslip', ['p' => $p, 'lines' => json_decode($p->breakdown, true)['lines'] ?? [], 'company' => Company::info()]);
    }

    public function sheet(int $run): View
    {
        $r = $this->run($run);

        return view('admin.hr.payroll.sheet', [
            'run' => $r,
            'rows' => DB::table('hr_payrolls')->where('payroll_run_id', $run)->join('hr_employees as e', 'e.id', '=', 'hr_payrolls.employee_id')
                ->select('hr_payrolls.*', 'e.full_name_ar', 'e.employee_code', 'e.job_title', 'e.iban')
                ->orderBy('hr_payrolls.payment_method')->orderBy('e.full_name_ar')->get(),
            'company' => Company::info(),
        ]);
    }

    /** ملف التحويل البنكي (Excel/CSV) */
    public function bankFile(int $run): StreamedResponse
    {
        $r = $this->run($run);
        $rows = DB::table('hr_payrolls')->where('payroll_run_id', $run)->where('payment_method', 'bank')->where('net', '>', 0)
            ->join('hr_employees as e', 'e.id', '=', 'hr_payrolls.employee_id')
            ->select('e.employee_code', 'e.full_name_ar', 'e.full_name_en', 'e.bank_name', 'e.iban', 'hr_payrolls.net')->orderBy('e.full_name_ar')->get();

        return response()->streamDownload(function () use ($rows, $r) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Code', 'Name (AR)', 'Name (EN)', 'Bank', 'IBAN', 'Amount', 'Reference']);
            foreach ($rows as $x) {
                fputcsv($out, [$x->employee_code, $x->full_name_ar, $x->full_name_en, $x->bank_name, $x->iban, number_format((float) $x->net, Money::decimals(), '.', ''), 'SALARY '.$r->year.'-'.str_pad($r->month, 2, '0', STR_PAD_LEFT)]);
            }
            fclose($out);
        }, 'salaries-bank-'.$r->year.'-'.str_pad($r->month, 2, '0', STR_PAD_LEFT).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function run(int $id): object
    {
        $r = DB::table('hr_payroll_runs')->where('id', $id)->first();
        abort_unless($r, 404);
        $r->label = self::MONTHS[$r->month].' '.$r->year;

        return $r;
    }
}
