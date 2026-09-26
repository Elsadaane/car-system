<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Payroll;
use App\Support\SalesStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * عمولات المندوبين لشهر: كل مندوب باع كام عربية، وعمولته كام (نفس حسبة المرتبات بالظبط)،
 * واتصرفت في المرتب ولا لسه.
 */
class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        [$from, $to] = [$start->toDateString(), $end->toDateString()];

        // كل البيعات في الشهر متجمعة بالمندوب
        $sold = SalesStats::sold(['from' => $from, 'to' => $to])
            ->when(\App\Support\Access::branchIds(), fn ($c, $ids) => $c->whereIn('branch_id', $ids));
        $byUser = $sold->groupBy('user_id');

        $employees = DB::table('hr_employees')->whereNull('deleted_at')->whereNotNull('user_id')
            ->get()->keyBy('user_id');
        $run = DB::table('hr_payroll_runs')->where('year', $start->year)->where('month', $start->month)->first();
        $payrolls = $run ? DB::table('hr_payrolls')->where('payroll_run_id', $run->id)->get()->keyBy('employee_id') : collect();
        $users = DB::table('users')->whereIn('id', $byUser->keys()->merge($employees->where('commission_type', '!=', 'none')->keys())->unique())
            ->pluck('name', 'id');

        $rows = collect();
        foreach ($users as $uid => $name) {
            $e = $employees[$uid] ?? null;
            $mine = $byUser[$uid] ?? collect();
            $commission = 0.0;
            $details = $mine;
            if ($e && $e->commission_type !== 'none') {
                [$commission, $details] = Payroll::commissionFor($e, $from, $to);
                if (\App\Support\Access::branchIds()) {
                    $details = $details->whereIn('branch_id', \App\Support\Access::branchIds());
                    $commission = \App\Support\Money::round($details->sum('commission'));
                }
            }
            $p = $e ? ($payrolls[$e->id] ?? null) : null;
            $rows->push((object) [
                'user_id'    => $uid,
                'name'       => $name,
                'employee'   => $e,
                'rule'       => $e ? (Payroll::COMMISSION[$e->commission_type] ?? '—').($e->commission_type !== 'none' ? ' — '.(float) $e->commission_value.($e->commission_type === 'per_car' ? '' : '%') : '') : null,
                'cars'       => $details->count(),
                'net_sales'  => (float) $details->sum('net_sale'),
                'profit'     => (float) $details->sum('profit'),
                'commission' => $commission,
                'details'    => $details->sortBy('contract_date')->values(),
                'payroll'    => $p ? ($p->paid_date ? 'paid' : 'pending') : null,
                'payroll_commission' => $p ? (float) $p->commission : null,
            ]);
        }
        $rows = $rows->sortByDesc(fn ($r) => [$r->commission, $r->cars])->values();

        return view('admin.reports.commissions', [
            'rows'   => $rows,
            'month'  => $month,
            'start'  => $start,
            'totals' => ['cars' => $rows->sum('cars'), 'sales' => $rows->sum('net_sales'), 'commission' => $rows->sum('commission')],
            'run'    => $run,
        ]);
    }
}
