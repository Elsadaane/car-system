<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Support\Media;
use App\Support\Payroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : null;
        $employee = $request->integer('employee') ?: null;

        $rows = DB::table('hr_leave_requests')
            ->join('hr_employees', 'hr_employees.id', '=', 'hr_leave_requests.employee_id')
            ->join('hr_leave_types', 'hr_leave_types.id', '=', 'hr_leave_requests.leave_type_id')
            ->leftJoin('users', 'users.id', '=', 'hr_leave_requests.decided_by')
            ->when($status, fn ($q, $v) => $q->where('hr_leave_requests.status', $v))
            ->when($employee, fn ($q, $v) => $q->where('hr_leave_requests.employee_id', $v))
            ->select('hr_leave_requests.*', 'hr_employees.full_name_ar', 'hr_employees.job_title', \App\Support\Lang::sel('hr_leave_types.name_ar as type_name'), 'hr_leave_types.is_paid', 'users.name as decider')
            ->orderByRaw("hr_leave_requests.status = 'pending' desc")->latest('hr_leave_requests.start_date')
            ->paginate(25)->withQueryString();

        $today = today()->toDateString();

        return view('admin.hr.leaves.index', [
            'rows' => $rows, 'status' => $status, 'employee' => $employee,
            'counts' => DB::table('hr_leave_requests')->groupBy('status')->select('status', DB::raw('count(*) as c'))->pluck('c', 'status'),
            'today' => DB::table('hr_leave_requests')->join('hr_employees', 'hr_employees.id', '=', 'hr_leave_requests.employee_id')
                ->where('hr_leave_requests.status', 'approved')->where('start_date', '<=', $today)->where('end_date', '>=', $today)
                ->select('hr_employees.full_name_ar', 'hr_leave_requests.end_date')->get(),
            'employees' => DB::table('hr_employees')->whereNull('deleted_at')->where('status', '!=', 'terminated')->orderBy('full_name_ar')->pluckLocal('full_name', 'id'),
            'types' => DB::table('hr_leave_types')->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id'   => ['required', Rule::exists('hr_employees', 'id')],
            'leave_type_id' => ['required', Rule::exists('hr_leave_types', 'id')],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'reason'        => ['nullable', 'string', 'max:1000'],
            'attachment'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], ['required' => 'الحقل ده مطلوب.', 'after_or_equal' => 'تاريخ الرجوع قبل تاريخ البداية.']);

        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;

        $overlap = DB::table('hr_leave_requests')->where('employee_id', $data['employee_id'])->where('status', '!=', 'rejected')
            ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'الموظف عنده إجازة متسجلة في نفس الفترة.']);
        }

        $type = Payroll::leaveBalances((int) $data['employee_id'], Carbon::parse($data['start_date'])->year)->firstWhere('id', (int) $data['leave_type_id']);
        $warning = $type && $type->remaining !== null && $days > $type->remaining ? 'تنبيه: الإجازة ('.$days.' يوم) أكبر من رصيده ('.$type->remaining.' يوم).' : null;

        DB::table('hr_leave_requests')->insert([
            'employee_id' => $data['employee_id'], 'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'days' => $days, 'reason' => $data['reason'] ?? null,
            'attachment' => $request->hasFile('attachment') ? Media::store($request->file('attachment'), 'hr-leaves') : null,
            'status' => $request->boolean('approve_now') && \App\Support\Access::can('hr.approve') ? 'approved' : 'pending',
            'decided_by' => $request->boolean('approve_now') ? $request->user()->id : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'تم تسجيل الإجازة'.($warning ? ' — '.$warning : ''));
    }

    public function decide(Request $request, int $leave, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 404);
        DB::table('hr_leave_requests')->where('id', $leave)->update(['status' => $decision, 'decided_by' => $request->user()->id, 'updated_at' => now()]);

        if ($decision === 'rejected') {
            $l = DB::table('hr_leave_requests')->where('id', $leave)->first();
            DB::table('hr_attendances')->where('employee_id', $l->employee_id)->where('status', 'leave')->whereBetween('date', [$l->start_date, $l->end_date])->delete();
        }

        if ($decision === 'approved') {
            // بنعلّم أيام الإجازة في الحضور
            $l = DB::table('hr_leave_requests')->where('id', $leave)->first();
            for ($d = Carbon::parse($l->start_date); $d->lte(Carbon::parse($l->end_date)); $d->addDay()) {
                DB::table('hr_attendances')->updateOrInsert(['employee_id' => $l->employee_id, 'date' => $d->toDateString()],
                    ['status' => 'leave', 'check_in' => null, 'check_out' => null, 'delay_minutes' => 0, 'overtime_minutes' => 0, 'updated_at' => now(), 'created_at' => now()]);
            }
        }

        return back()->with('status', $decision === 'approved' ? 'تمت الموافقة' : 'تم الرفض');
    }

    public function destroy(int $leave): RedirectResponse
    {
        $l = DB::table('hr_leave_requests')->where('id', $leave)->first();
        abort_unless($l, 404);
        DB::table('hr_attendances')->where('employee_id', $l->employee_id)->where('status', 'leave')->whereBetween('date', [$l->start_date, $l->end_date])->delete();
        DB::table('hr_leave_requests')->where('id', $leave)->delete();

        return back()->with('status', 'تم المسح');
    }
}
