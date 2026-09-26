<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * إعدادات الـ HR: الأقسام، الورديات، أنواع البدلات والخصومات، أنواع الإجازات، العطلات الرسمية.
 */
class HrSettingsController extends Controller
{
    public const TABS = [
        'departments' => ['title' => 'الأقسام', 'table' => 'hr_departments', 'fields' => [
            'name_ar' => ['الاسم', 'text', 'required'], 'name_en' => ['بالإنجليزي', 'text', 'nullable']]],
        'shifts' => ['title' => 'الورديات', 'table' => 'hr_shifts', 'fields' => [
            'name_ar' => ['الاسم', 'text', 'required'], 'start_time' => ['من', 'time', 'required'], 'end_time' => ['إلى', 'time', 'required'],
            'grace_minutes' => ['سماح التأخير (دقيقة)', 'number', 'required'], 'is_active' => ['مفعّلة', 'bool', '']]],
        'allowances' => ['title' => 'البدلات والخصومات الثابتة', 'table' => 'hr_allowance_types', 'fields' => [
            'name_ar' => ['الاسم', 'text', 'required'], 'type' => ['النوع', 'select:allowance=بدل (بيزود المرتب),deduction=خصم ثابت', 'required'], 'is_active' => ['مفعّل', 'bool', '']]],
        'leaves' => ['title' => 'أنواع الإجازات', 'table' => 'hr_leave_types', 'fields' => [
            'name_ar' => ['الاسم', 'text', 'required'], 'days_per_year' => ['الرصيد السنوي (0 = بدون حد)', 'number', 'required'],
            'is_paid' => ['مدفوعة الأجر', 'bool', ''], 'is_active' => ['مفعّلة', 'bool', '']]],
        'holidays' => ['title' => 'العطلات الرسمية', 'table' => 'hr_holidays', 'fields' => [
            'name_ar' => ['المناسبة', 'text', 'required'], 'start_date' => ['من', 'date', 'required'], 'end_date' => ['إلى', 'date', 'required']]],
    ];

    public function index(string $tab = 'departments'): View
    {
        abort_unless(isset(self::TABS[$tab]), 404);
        $cfg = self::TABS[$tab];

        return view('admin.hr.settings', [
            'tab' => $tab, 'cfg' => $cfg,
            'rows' => DB::table($cfg['table'])->orderBy($tab === 'holidays' ? 'start_date' : 'id', $tab === 'holidays' ? 'desc' : 'asc')->get(),
        ]);
    }

    public function store(Request $request, string $tab): RedirectResponse
    {
        abort_unless(isset(self::TABS[$tab]), 404);
        DB::table(self::TABS[$tab]['table'])->insert($this->data($request, $tab) + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تم الإضافة');
    }

    public function update(Request $request, string $tab, int $id): RedirectResponse
    {
        abort_unless(isset(self::TABS[$tab]), 404);
        DB::table(self::TABS[$tab]['table'])->where('id', $id)->update($this->data($request, $tab) + ['updated_at' => now()]);

        return back()->with('status', 'تم الحفظ');
    }

    public function destroy(string $tab, int $id): RedirectResponse
    {
        abort_unless(isset(self::TABS[$tab]), 404);
        try {
            DB::table(self::TABS[$tab]['table'])->where('id', $id)->delete();
        } catch (QueryException) {
            return back()->with('error', 'مستخدم في بيانات موظفين. اقفله بدل المسح.');
        }

        return back()->with('status', 'تم المسح');
    }

    private function data(Request $request, string $tab): array
    {
        $rules = [];
        $bools = [];
        foreach (self::TABS[$tab]['fields'] as $name => [$label, $type, $req]) {
            if ($type === 'bool') {
                $bools[] = $name;
                continue;
            }
            $r = [$req ?: 'nullable'];
            $r[] = match (true) {
                $type === 'number' => 'numeric',
                $type === 'date' => 'date',
                $type === 'time' => 'date_format:H:i',
                str_starts_with($type, 'select:') => 'in:'.implode(',', array_map(fn ($o) => explode('=', $o)[0], explode(',', substr($type, 7)))),
                default => 'string',
            };
            $rules[$name] = $r;
        }
        if ($tab === 'holidays') {
            $rules['end_date'][] = 'after_or_equal:start_date';
        }
        $data = $request->validate($rules, ['required' => 'الحقل ده مطلوب.', 'date_format' => 'الوقت لازم يكون بالشكل 09:00.']);
        foreach ($bools as $b) {
            $data[$b] = $request->boolean($b);
        }

        return $data;
    }
}
