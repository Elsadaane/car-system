<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * الحضور اليومي: شيت واحد لكل الموظفين في اليوم.
 * التأخير والأوفر تايم بيتحسبوا لوحدهم من ميعاد الوردية.
 */
class AttendanceController extends Controller
{
    public const STATUSES = ['present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غايب', 'leave' => 'إجازة', 'holiday' => 'عطلة', 'off' => 'راحة'];

    public function index(Request $request): View
    {
        $date = $request->date('date')?->toDateString() ?? today()->toDateString();
        $branch = $request->integer('branch') ?: null;

        $employees = DB::table('hr_employees')->whereNull('hr_employees.deleted_at')->where('hr_employees.status', '!=', 'terminated')
            ->where('join_date', '<=', $date)
            ->when($branch, fn ($q, $v) => $q->where('hr_employees.branch_id', $v))
            ->leftJoin('hr_shifts', 'hr_shifts.id', '=', 'hr_employees.hr_shift_id')
            ->leftJoin('hr_attendances as a', fn ($j) => $j->on('a.employee_id', '=', 'hr_employees.id')->where('a.date', '=', $date))
            ->select('hr_employees.id', 'hr_employees.full_name_ar', 'hr_employees.job_title', 'hr_employees.profile_image', 'hr_employees.overtime_eligible',
                'hr_shifts.start_time', 'hr_shifts.end_time', 'hr_shifts.grace_minutes',
                'a.id as att_id', 'a.status', 'a.check_in', 'a.check_out', 'a.delay_minutes', 'a.overtime_minutes', 'a.notes')
            ->orderBy('hr_employees.full_name_ar')->get();

        $onLeave = DB::table('hr_leave_requests')->where('status', 'approved')->where('start_date', '<=', $date)->where('end_date', '>=', $date)->pluck('employee_id')->all();
        $holiday = DB::table('hr_holidays')->where('start_date', '<=', $date)->where('end_date', '>=', $date)->value('name_ar');

        return view('admin.hr.attendance.index', [
            'date' => $date, 'branch' => $branch, 'employees' => $employees, 'onLeave' => $onLeave, 'holiday' => $holiday,
            'branches' => DB::table('branches')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'saved' => $employees->whereNotNull('att_id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $date = $request->date('date')?->toDateString();
        abort_unless($date, 422);
        $rows = (array) $request->input('att', []);

        $shifts = DB::table('hr_employees')->whereIn('hr_employees.id', array_keys($rows))
            ->leftJoin('hr_shifts', 'hr_shifts.id', '=', 'hr_employees.hr_shift_id')
            ->get(['hr_employees.id', 'hr_employees.overtime_eligible', 'hr_shifts.start_time', 'hr_shifts.end_time', 'hr_shifts.grace_minutes'])->keyBy('id');

        foreach ($rows as $id => $r) {
            if (! isset($shifts[$id]) || empty($r['status']) || ! array_key_exists($r['status'], self::STATUSES)) {
                continue;
            }
            $s = $shifts[$id];
            $in = $this->time($r['check_in'] ?? null);
            $out = $this->time($r['check_out'] ?? null);
            $status = $r['status'];
            $delay = 0;
            $ot = 0;

            if (in_array($status, ['present', 'late'], true) && $s->start_time) {
                if ($in) {
                    $late = $this->minutes($in) - $this->minutes($s->start_time);
                    $delay = $late > (int) $s->grace_minutes ? $late : 0;
                    $status = $delay > 0 ? 'late' : 'present';
                }
                if ($out && $s->end_time) {
                    $ot = max(0, $this->minutes($out) - $this->minutes($s->end_time));
                }
            }
            if (isset($r['overtime']) && $r['overtime'] !== '' && $r['overtime'] !== null) {
                $ot = max(0, (int) round((float) $r['overtime'] * 60)); // تعديل يدوي بالساعات
            }

            DB::table('hr_attendances')->updateOrInsert(['employee_id' => $id, 'date' => $date], [
                'status' => $status, 'check_in' => in_array($status, ['present', 'late'], true) ? $in : null,
                'check_out' => in_array($status, ['present', 'late'], true) ? $out : null,
                'delay_minutes' => $delay, 'overtime_minutes' => $s->overtime_eligible ? $ot : 0,
                'notes' => $r['notes'] ?? null, 'user_id' => $request->user()->id, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        return back()->with('status', 'تم حفظ حضور يوم '.$date);
    }

    /** شيت الشهر: صف لكل موظف وعمود لكل يوم */
    public function monthly(Request $request): View
    {
        $month = $request->query('month') && preg_match('/^\d{4}-\d{2}$/', $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = Carbon::parse($month.'-01');
        $end = $start->copy()->endOfMonth();

        $records = DB::table('hr_attendances')->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->groupBy('employee_id');
        $employees = DB::table('hr_employees')->whereNull('deleted_at')
            ->where('join_date', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $start->toDateString()))
            ->orderBy('full_name_ar')->get(['id', 'full_name_ar', 'job_title'])
            ->map(function ($e) use ($records) {
                $r = $records[$e->id] ?? collect();
                $e->days = $r->keyBy(fn ($x) => (int) substr($x->date, 8, 2));
                $e->present = $r->whereIn('status', ['present', 'late'])->count();
                $e->absent = $r->where('status', 'absent')->count();
                $e->late = (int) $r->sum('delay_minutes');
                $e->ot = round($r->sum('overtime_minutes') / 60, 1);

                return $e;
            });

        return view('admin.hr.attendance.monthly', ['month' => $month, 'start' => $start, 'days' => $start->daysInMonth, 'employees' => $employees]);
    }

    private function time(?string $t): ?string
    {
        return $t && preg_match('/^\d{1,2}:\d{2}/', $t) ? substr($t, 0, 5) : null;
    }

    private function minutes(string $t): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($t, 0, 5)));

        return $h * 60 + $m;
    }
}
