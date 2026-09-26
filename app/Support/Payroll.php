<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * حساب المرتبات.
 *
 *   شهري:  الأساسي (بالنسبة لأيام الشغل لو دخل/خرج نص الشهر) − غياب × أجر اليوم
 *   يومية: عدد أيام الحضور × اليومية
 *   + البدلات الثابتة + الأوفر تايم + العمولة + الحوافز
 *   − الخصومات الثابتة + الجزاءات + قسط السلفة + التأمينات
 *
 *   أجر اليوم  = المرتب ÷ أيام الشغل في الشهر
 *   أجر الساعة = أجر اليوم ÷ ساعات اليوم،  والأوفر تايم = الساعات × أجر الساعة × المعامل
 */
class Payroll
{
    public const EMPLOYMENT = ['sponsored' => 'على كفالة المعرض', 'contract' => 'بعقد (من غير كفالة)', 'freelance' => 'من غير عقد (يومية / حر)'];
    public const PAY_BASIS = ['monthly' => 'مرتب شهري', 'daily' => 'يومية'];
    public const PAY_METHOD = ['cash' => 'كاش في الإيد', 'bank' => 'تحويل بنكي'];
    public const COMMISSION = ['none' => 'مفيش عمولة', 'sale_percent' => '% من قيمة البيعة', 'profit_percent' => '% من ربح البيعة', 'per_car' => 'مبلغ ثابت لكل عربية'];
    public const STATUSES = ['active' => 'على رأس العمل', 'on_leave' => 'في إجازة', 'suspended' => 'موقوف', 'terminated' => 'انتهت خدمته'];
    public const DOCUMENTS = ['civil_id' => 'البطاقة المدنية', 'passport' => 'جواز السفر', 'visa' => 'التأشيرة / الإقامة', 'driving_license' => 'رخصة القيادة', 'contract' => 'عقد العمل', 'health' => 'الشهادة الصحية', 'other' => 'أخرى'];

    /**
     * عمولة الموظف عن مبيعاته (هو مندوب البيعة) في المدة: [المبلغ, البيعات مع عمولة كل واحدة]
     * نفس الحسبة اللي في المرتبات وصفحة عمولات المندوبين.
     */
    public static function commissionFor(object $e, string $from, string $to): array
    {
        $sold = SalesStats::sold(['from' => $from, 'to' => $to, 'user' => $e->user_id]);
        $v = (float) $e->commission_value;
        foreach ($sold as $r) {
            $r->commission = match ($e->commission_type) {
                'sale_percent'   => $r->net_sale * $v / 100,
                'profit_percent' => max(0, $r->profit) * $v / 100,
                'per_car'        => $v,
                default          => 0,
            };
        }

        return [Money::round($sold->sum('commission')), $sold];
    }

    public static function dailyRate(object $e): float
    {
        return $e->pay_basis === 'daily'
            ? (float) $e->basic_salary
            : (float) $e->basic_salary / max(1, (int) $e->working_days_per_month);
    }

    /** مرتب موظف في شهر - بيرجّع صف جاهز لجدول hr_payrolls + التفاصيل */
    public static function calculate(object $e, int $year, int $month, ?int $runId = null): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;
        $daily = self::dailyRate($e);
        $lines = [];

        // ─── الحضور ───
        $att = DB::table('hr_attendances')->where('employee_id', $e->id)->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("sum(status = 'absent') as absent, sum(status in ('present','late')) as present, coalesce(sum(overtime_minutes),0) as ot, coalesce(sum(delay_minutes),0) as late")
            ->first();

        $unpaidLeave = 0.0;
        $leaves = DB::table('hr_leave_requests')->join('hr_leave_types', 'hr_leave_types.id', '=', 'hr_leave_requests.leave_type_id')
            ->where('hr_leave_requests.employee_id', $e->id)->where('hr_leave_requests.status', 'approved')->where('hr_leave_types.is_paid', false)
            ->where('start_date', '<=', $end->toDateString())->where('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);
        foreach ($leaves as $l) {
            $a = Carbon::parse($l->start_date)->max($start);
            $b = Carbon::parse($l->end_date)->min($end);
            $unpaidLeave += (int) $a->diffInDays($b) + 1;
        }

        // ─── الأساسي ───
        if ($e->pay_basis === 'daily') {
            $daysWorked = (float) ($att->present ?? 0);
            $basic = $daysWorked * $daily;
            $absent = 0.0;
            $absence = 0.0;
            $lines[] = ['اليومية × أيام الحضور', "{$daysWorked} × ".Money::format($daily, false), $basic];
        } else {
            // لو دخل أو خرج نص الشهر
            $from = Carbon::parse($e->join_date)->max($start);
            $to = $e->termination_date ? Carbon::parse($e->termination_date)->min($end) : $end;
            $employedDays = max(0, (int) $from->diffInDays($to) + 1);
            $ratio = $employedDays >= $daysInMonth ? 1 : $employedDays / $daysInMonth;
            $basic = (float) $e->basic_salary * $ratio;
            $absent = (float) ($att->absent ?? 0) + $unpaidLeave;
            $absence = $absent * $daily;
            $daysWorked = max(0, round($employedDays * (int) $e->working_days_per_month / $daysInMonth - $absent, 2));
            $lines[] = ['المرتب الأساسي', $ratio < 1 ? "اشتغل {$employedDays} يوم من {$daysInMonth}" : null, $basic];
            if ($absence > 0) {
                $lines[] = ['خصم غياب', "{$absent} يوم × ".Money::format($daily, false), -$absence];
            }
        }
        $ratioAllow = $e->pay_basis === 'monthly' && isset($ratio) ? $ratio : 1;

        // ─── البدلات والخصومات الثابتة ───
        $fixed = DB::table('hr_employee_allowances')->join('hr_allowance_types', 'hr_allowance_types.id', '=', 'hr_employee_allowances.allowance_type_id')
            ->where('employee_id', $e->id)->get(['hr_allowance_types.name_ar', 'hr_allowance_types.type', 'hr_employee_allowances.amount']);
        $allowances = 0.0;
        $fixedDed = 0.0;
        foreach ($fixed as $f) {
            $amt = (float) $f->amount * $ratioAllow;
            if ($f->type === 'allowance') {
                $allowances += $amt;
                $lines[] = [$f->name_ar, null, $amt];
            } else {
                $fixedDed += $amt;
                $lines[] = [$f->name_ar, null, -$amt];
            }
        }

        // ─── الأوفر تايم ───
        $otHours = 0.0;
        $otAmount = 0.0;
        if ($e->overtime_eligible && (int) ($att->ot ?? 0) > 0) {
            $otHours = round((int) $att->ot / 60, 2);
            $hourly = $daily / max(1, (float) $e->working_hours_per_day);
            $otAmount = $otHours * $hourly * (float) $e->overtime_rate;
            $lines[] = ['أوفر تايم', "{$otHours} ساعة × ".Money::format($hourly, false).' × '.(float) $e->overtime_rate, $otAmount];
        }

        // ─── العمولة (من عقود البيع اللي عملها بحسابه) ───
        $commission = 0.0;
        if ($e->commission_type !== 'none' && $e->user_id) {
            [$commission, $sold] = self::commissionFor($e, $start->toDateString(), $end->toDateString());
            if ($sold->count()) {
                $lines[] = ['عمولة', $sold->count().' عربية ('.self::COMMISSION[$e->commission_type].')', $commission];
            }
        }

        // ─── الحوافز والجزاءات المسجلة ───
        $adj = DB::table('hr_adjustments')->where('employee_id', $e->id)->where('date', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('payroll_run_id')->when($runId, fn ($w) => $w->orWhere('payroll_run_id', $runId)))
            ->get();
        $bonuses = 0.0;
        $adjDed = 0.0;
        foreach ($adj as $a) {
            if ($a->type === 'bonus') {
                $bonuses += (float) $a->amount;
                $lines[] = ['حافز: '.$a->reason, null, (float) $a->amount];
            } else {
                $adjDed += (float) $a->amount;
                $lines[] = ['جزاء/خصم: '.$a->reason, $a->days ? $a->days.' يوم' : null, -(float) $a->amount];
            }
        }

        // ─── السلف ───
        $loanDed = 0.0;
        $loanParts = [];
        $loans = DB::table('hr_loans')->where('employee_id', $e->id)->where('status', 'active')->where('start_month', '<=', $end->toDateString())->get();
        foreach ($loans as $l) {
            $part = min((float) $l->monthly_installment, (float) $l->amount - (float) $l->paid_amount);
            if ($part > 0) {
                $loanDed += $part;
                $loanParts[$l->id] = Money::round($part);
                $lines[] = ['قسط سلفة', 'الباقي بعده '.Money::format((float) $l->amount - (float) $l->paid_amount - $part, false), -$part];
            }
        }

        // ─── التأمينات ───
        $insurance = 0.0;
        if ($e->is_insured && (float) $e->insurance_percent > 0) {
            $insurance = ($basic + $allowances) * (float) $e->insurance_percent / 100;
            $lines[] = ['التأمينات', (float) $e->insurance_percent.'%', -$insurance];
        }

        $gross = $basic + $allowances + $otAmount + $commission + $bonuses;
        $ded = $absence + $fixedDed + $adjDed + $loanDed + $insurance;

        return [
            'row' => [
                'employee_id' => $e->id, 'branch_id' => $e->branch_id, 'pay_basis' => $e->pay_basis,
                'days_worked' => $daysWorked, 'absent_days' => $absent,
                'basic' => Money::round($basic), 'allowances' => Money::round($allowances),
                'overtime_hours' => $otHours, 'overtime_amount' => Money::round($otAmount),
                'commission' => Money::round($commission), 'bonuses' => Money::round($bonuses),
                'absence_deduction' => Money::round($absence), 'fixed_deductions' => Money::round($fixedDed),
                'adjustment_deductions' => Money::round($adjDed), 'loan_deduction' => Money::round($loanDed),
                'insurance_deduction' => Money::round($insurance),
                'gross' => Money::round($gross), 'total_deductions' => Money::round($ded),
                'net' => Money::round($gross - $ded),
                'payment_method' => $e->payment_method,
            ],
            'lines'       => array_map(fn ($l) => ['label' => $l[0], 'note' => $l[1], 'amount' => Money::round($l[2])], $lines),
            'loans'       => $loanParts,
            'adjustments' => $adj->pluck('id')->all(),
            'late_minutes'=> (int) ($att->late ?? 0),
        ];
    }

    /** الموظفين اللي ليهم مرتب في الشهر ده */
    public static function eligible(int $year, int $month)
    {
        $start = Carbon::create($year, $month, 1);

        return DB::table('hr_employees')->whereNull('deleted_at')
            ->where('join_date', '<=', $start->copy()->endOfMonth()->toDateString())
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $start->toDateString()))
            ->where(fn ($q) => $q->where('status', '!=', 'terminated')->orWhere('termination_date', '>=', $start->toDateString()))
            ->orderBy('full_name_ar')->get();
    }

    /** بيحسب (أو يعيد حساب) كل مرتبات الشهر - للمسودة بس */
    public static function generate(object $run): int
    {
        DB::table('hr_adjustments')->where('payroll_run_id', $run->id)->update(['payroll_run_id' => null]);
        $old = DB::table('hr_payrolls')->where('payroll_run_id', $run->id)->get()->keyBy('employee_id');
        $keep = [];

        foreach (self::eligible($run->year, $run->month) as $e) {
            $c = self::calculate($e, $run->year, $run->month, $run->id);
            $row = $c['row'];
            // التعديلات اليدوية بتفضل زي ما هي
            $manualAdd = (float) ($old[$e->id]->other_additions ?? 0);
            $manualDed = (float) ($old[$e->id]->other_deductions ?? 0);
            $row['other_additions'] = $manualAdd;
            $row['other_deductions'] = $manualDed;
            $row['gross'] = Money::round($row['gross'] + $manualAdd);
            $row['total_deductions'] = Money::round($row['total_deductions'] + $manualDed);
            $row['net'] = Money::round($row['gross'] - $row['total_deductions']);
            $row['notes'] = $old[$e->id]->notes ?? null;
            $row['breakdown'] = json_encode(['lines' => $c['lines'], 'loans' => $c['loans'], 'late_minutes' => $c['late_minutes']], JSON_UNESCAPED_UNICODE);
            $row['updated_at'] = now();

            DB::table('hr_payrolls')->updateOrInsert(['payroll_run_id' => $run->id, 'employee_id' => $e->id], $row + ['created_at' => now()]);
            if ($c['adjustments']) {
                DB::table('hr_adjustments')->whereIn('id', $c['adjustments'])->update(['payroll_run_id' => $run->id]);
            }
            $keep[] = $e->id;
        }

        DB::table('hr_payrolls')->where('payroll_run_id', $run->id)->whereNotIn('employee_id', $keep ?: [0])->delete();

        return count($keep);
    }

    /** بعد التعديل اليدوي على صف */
    public static function retotal(int $payrollId): void
    {
        $p = DB::table('hr_payrolls')->where('id', $payrollId)->first();
        $gross = (float) $p->basic + (float) $p->allowances + (float) $p->overtime_amount + (float) $p->commission + (float) $p->bonuses + (float) $p->other_additions;
        $ded = (float) $p->absence_deduction + (float) $p->fixed_deductions + (float) $p->adjustment_deductions + (float) $p->loan_deduction + (float) $p->insurance_deduction + (float) $p->other_deductions;
        DB::table('hr_payrolls')->where('id', $payrollId)->update(['gross' => Money::round($gross), 'total_deductions' => Money::round($ded), 'net' => Money::round($gross - $ded), 'updated_at' => now()]);
    }

    /** اعتماد / فك اعتماد الشهر: أقساط السلف بتتخصم فعلياً */
    public static function applyLoans(object $run, int $direction): void
    {
        foreach (DB::table('hr_payrolls')->where('payroll_run_id', $run->id)->get(['breakdown']) as $p) {
            foreach ((json_decode($p->breakdown, true)['loans'] ?? []) as $loanId => $amount) {
                $loan = DB::table('hr_loans')->where('id', $loanId)->first();
                if (! $loan) {
                    continue;
                }
                $paid = Money::round(max(0, (float) $loan->paid_amount + $direction * (float) $amount));
                DB::table('hr_loans')->where('id', $loanId)->update([
                    'paid_amount' => $paid,
                    'status' => $loan->status === 'cancelled' ? 'cancelled' : ($paid + 0.0005 >= (float) $loan->amount ? 'completed' : 'active'),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** رصيد الإجازات لسنة */
    public static function leaveBalances(int $employeeId, int $year)
    {
        $used = DB::table('hr_leave_requests')->where('employee_id', $employeeId)->where('status', 'approved')
            ->whereYear('start_date', $year)->groupBy('leave_type_id')->select('leave_type_id', DB::raw('sum(days) as d'))->pluck('d', 'leave_type_id');

        return DB::table('hr_leave_types')->where('is_active', true)->orderBy('id')->get()->map(function ($t) use ($used) {
            $t->used = (float) ($used[$t->id] ?? 0);
            $t->remaining = $t->days_per_year > 0 ? max(0, $t->days_per_year - $t->used) : null;

            return $t;
        });
    }

    /** حساب نهاية الخدمة */
    public static function endOfService(object $e, string $date, float $gratuityDaysPerYear): array
    {
        $end = Carbon::parse($date);
        $years = round(Carbon::parse($e->join_date)->diffInDays($end) / 365.25, 2);
        $daily = $e->pay_basis === 'daily' ? (float) $e->basic_salary : (float) $e->basic_salary / 30;
        $gratuity = $years * $gratuityDaysPerYear * $daily;

        // رصيد الإجازة السنوية للسنة الحالية (بالنسبة للشهور اللي اشتغلها)
        $yearStart = $end->copy()->startOfYear()->max(Carbon::parse($e->join_date));
        $months = max(0, $yearStart->diffInMonths($end));
        $entitled = round((int) $e->annual_leave_days * $months / 12, 1);
        $annualTypes = DB::table('hr_leave_types')->where('is_paid', true)->where('days_per_year', '>', 0)->orderBy('id')->limit(1)->pluck('id');
        $used = (float) DB::table('hr_leave_requests')->where('employee_id', $e->id)->where('status', 'approved')
            ->whereIn('leave_type_id', $annualTypes)->whereYear('start_date', $end->year)->sum('days');
        $leaveDays = max(0, $entitled - $used);

        $loans = (float) DB::table('hr_loans')->where('employee_id', $e->id)->where('status', 'active')->sum(DB::raw('amount - paid_amount'));

        return [
            'service_years' => $years, 'daily_rate' => Money::round($daily), 'gratuity_days_per_year' => $gratuityDaysPerYear,
            'gratuity_amount' => Money::round($gratuity), 'leave_days' => $leaveDays, 'leave_encashment' => Money::round($leaveDays * $daily),
            'loan_deduction' => Money::round($loans),
        ];
    }
}
