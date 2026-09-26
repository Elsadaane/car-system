<?php

namespace App\Support;

use App\Http\Controllers\Admin\InquiryController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * مركز التنبيهات (الجرس): بيتحسب من البيانات نفسها — مفيش حاجة بتتبعت أو بتتخزن.
 * كل موظف بيشوف تنبيهات صلاحياته وفرعه بس.
 * "شفته" بيتحفظ كبصمة للتنبيه — لو الرقم اتغيّر (قسط جديد اتأخر مثلاً) يرجع يظهر تاني.
 */
class Alerts
{
    public const LEVELS = [
        'danger'  => ['bg-rose-50', 'text-rose-700', 'bg-rose-500'],
        'warning' => ['bg-amber-50', 'text-amber-700', 'bg-amber-500'],
        'info'    => ['bg-sky-50', 'text-sky-700', 'bg-sky-500'],
    ];

    /** @return array<int, array{key:string, level:string, icon:string, title:string, detail:string, url:?string, fp:string, seen:bool}> */
    public static function for($user): array
    {
        $key = 'alerts.'.$user->id.'.'.app()->getLocale().'.'.md5(json_encode(Access::branchIds($user)));
        $list = Cache::remember($key, now()->addMinutes(3), fn () => self::build($user));
        $seen = self::seenMap($user);

        return array_map(fn ($a) => $a + ['seen' => ($seen[$a['key']] ?? null) === $a['fp']], $list);
    }

    public static function unseenCount($user): int
    {
        try {
            return collect(self::for($user))->where('seen', false)->count();
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    public static function markSeen($user): void
    {
        $map = collect(self::for($user))->mapWithKeys(fn ($a) => [$a['key'] => $a['fp']])->all();
        DB::table('users')->where('id', $user->id)->update(['alerts_seen' => json_encode($map)]);
    }

    public static function forget($user): void
    {
        foreach (['ar', 'en'] as $l) {
            Cache::forget('alerts.'.$user->id.'.'.$l.'.'.md5(json_encode(Access::branchIds($user))));
        }
    }

    private static function seenMap($user): array
    {
        $raw = DB::table('users')->where('id', $user->id)->value('alerts_seen');

        return $raw ? (json_decode($raw, true) ?: []) : [];
    }

    // =========================================================

    private static function build($user): array
    {
        // حساب الفرع بيعتمد على اليوزر الحالي — بنثبّته للحساب
        $can = fn (string $p) => Access::can($p, $user);
        $url = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : null;
        $today = today()->toDateString();
        $out = [];
        $add = function (string $key, string $level, string $icon, string $title, string $detail, ?string $url, $fpData = null) use (&$out) {
            $out[] = compact('key', 'level', 'icon', 'title', 'detail', 'url') + ['fp' => md5($key.'|'.json_encode($fpData ?? $title))];
        };
        $safe = function (callable $fn) {
            try {
                $fn();
            } catch (Throwable $e) {
                report($e);
            }
        };
        $branches = Access::branchIds($user);
        $scope = fn ($q, string $col) => $branches === null ? $q : $q->whereIn($col, $branches);
        $viaContract = fn ($q, string $col) => $branches === null ? $q : $q->whereIn($col, fn ($s) => $s->select('id')->from('sales_contracts')->whereIn('branch_id', $branches));

        // ─── الأقساط ───
        if ($can('payments.view')) {
            $safe(function () use ($add, $url, $today, $viaContract) {
                $base = fn () => $viaContract(DB::table('installments')->whereNull('deleted_at')->where('is_paid', false)->where('status', '!=', 'cancelled'), 'sales_contract_id');
                $over = $base()->where('due_date', '<', $today)->selectRaw('count(*) n, count(distinct customer_id) c, coalesce(sum(amount - paid_amount),0) s')->first();
                if ($over->n) {
                    $add('inst_overdue', 'danger', '🔴', __(':n قسط متأخر على :c عميل', ['n' => $over->n, 'c' => $over->c]), __('إجمالي :amount — كلّمهم النهارده', ['amount' => Money::format($over->s)]), $url('admin.installments.index', ['tab' => 'overdue']), [$over->n, (float) $over->s]);
                }
                $due = $base()->where('due_date', $today)->selectRaw('count(*) n, coalesce(sum(amount - paid_amount),0) s')->first();
                if ($due->n) {
                    $add('inst_today', 'warning', '⏰', __(':n قسط مستحق النهارده', ['n' => $due->n]), __('بإجمالي :amount', ['amount' => Money::format($due->s)]), $url('admin.installments.index', ['tab' => 'week']), [$today, $due->n]);
                }
            });
        }

        // ─── الشيكات المؤجلة ───
        if ($can('payments.view') && Cheques::table()) {
            $safe(function () use ($add, $url, $today, $viaContract) {
                $base = fn () => $viaContract(DB::table('cheques')->whereNull('deleted_at'), 'sales_contract_id');
                $dep = $base()->where('status', 'received')->where('due_date', '<=', today()->addDays(3)->toDateString())->selectRaw('count(*) n, coalesce(sum(amount),0) s')->first();
                if ($dep->n) {
                    $add('cheq_deposit', 'warning', '🧾', __(':n شيك لازم يتودع البنك (خلال 3 أيام أو فات ميعاده)', ['n' => $dep->n]), __('بإجمالي :amount', ['amount' => Money::format($dep->s)]), $url('admin.cheques.index', ['tab' => 'due']), [$today, $dep->n]);
                }
                $bounced = $base()->where('status', 'bounced')->selectRaw('count(*) n, coalesce(sum(amount),0) s')->first();
                if ($bounced->n) {
                    $add('cheq_bounced', 'danger', '⛔', __(':n شيك مرتجع محتاج متابعة', ['n' => $bounced->n]), __('بإجمالي :amount — كلّم العملاء', ['amount' => Money::format($bounced->s)]), $url('admin.cheques.index', ['tab' => 'bounced']), [$bounced->n, (float) $bounced->s]);
                }
            });
        }

        // ─── طلبات الموقع ───
        if ($can('inquiries.view')) {
            $safe(function () use ($add, $url, $scope) {
                $new = fn () => $scope(DB::table('vehicle_inquiries')->whereNull('deleted_at')->where('status', 'new'), 'branch_id');
                $late = $new()->where('created_at', '<', now()->subHours(2))->count();
                $fresh = $new()->where('created_at', '>=', now()->subHours(2))->count();
                if ($late) {
                    $add('lead_late', 'danger', '📵', __(':n طلب من الموقع محدش رد عليه من أكتر من ساعتين', ['n' => $late]), __('كل ساعة تأخير بتقلّل فرصة البيع — ردّ دلوقتي'), $url('admin.inquiries.index', ['view' => 'list', 'status' => 'new']), $late);
                }
                if ($fresh) {
                    $last = $new()->orderByDesc('id')->first(['name', 'type']);
                    $add('lead_new', 'info', '🆕', $fresh === 1 ? __('طلب جديد من :name', ['name' => $last->name]) : __(':n طلبات جديدة من الموقع', ['n' => $fresh]), __(InquiryController::TYPES[$last->type] ?? '').__(' — لسه واصل'), $url('admin.inquiries.index'), [$fresh, $last->name]);
                }
            });
        }

        // ─── متابعات العملاء النهارده ───
        if ($can('customers.view')) {
            $safe(function () use ($add, $url, $today) {
                $notes = DB::table('activity_logs as a')->where('a.action', 'customer_note')->where('a.properties', 'like', '%"follow_up":"'.$today.'"%')
                    ->join('customers as c', 'c.id', '=', 'a.subject_id')->whereNull('c.deleted_at')
                    ->orderBy('a.id')->limit(5)->get(['a.id', 'a.description', 'c.id as cid', 'c.name_ar']);
                foreach ($notes as $n) {
                    $add('followup_'.$n->id, 'warning', '📅', __('متابعة النهارده مع :name', ['name' => $n->name_ar]), \Illuminate\Support\Str::limit($n->description, 80),
                        ($u = $url('admin.customers.show', $n->cid)) ? $u.'#notes' : null, $today);
                }
            });
        }

        // ─── المخزون ───
        if ($can('vehicles.view')) {
            $safe(function () use ($add, $url, $today, $scope) {
                $stock = fn () => $scope(DB::table('vehicles')->whereNull('deleted_at')->whereIn('status', ['available', 'reserved', 'maintenance']), 'branch_id');
                $aged = $stock()->where('created_at', '<', now()->subDays(90))->count();
                if ($aged) {
                    $add('stock_aged', 'info', '🐢', __(':n عربية قاعدة في المعرض أكتر من 90 يوم', ['n' => $aged]), __('فكّر في عرض أو تخفيض — كل يوم زيادة بيكلّفك'), $url('admin.vehicles.index', ['status' => 'available']), $aged);
                }
                $lic = $stock()->whereNotNull('license_expiry_date')->where('license_expiry_date', '<=', today()->addDays(30)->toDateString())->count();
                if ($lic) {
                    $add('stock_license', 'warning', '📄', __(':n عربية رخصتها بتخلص خلال 30 يوم (أو خلصت)', ['n' => $lic]), __('جدّدها قبل ما تتباع أو تتعرض'), $url('admin.vehicles.index'), [$lic, $today]);
                }
            });
        }

        // ─── تجارب القيادة النهارده ───
        if ($can('test_drives.view')) {
            $safe(function () use ($add, $url, $today) {
                $n = DB::table('test_drives')->where('status', 'scheduled')->whereDate('start_time', $today)->count();
                if ($n) {
                    $add('drives_today', 'info', '🚙', __(':n تجربة قيادة النهارده', ['n' => $n]), __('جهّز العربيات قبل الميعاد'), $url('admin.test-drives.index'), [$today, $n]);
                }
            });
        }

        // ─── موافقات مستنياك ───
        if ($can('cancellations.approve')) {
            $safe(function () use ($add, $url) {
                $n = DB::table('cancellation_requests')->whereNull('deleted_at')->where('status', 'pending')->count();
                if ($n) {
                    $add('cancel_pending', 'warning', '↩️', __(':n طلب إلغاء عقد مستني قرارك', ['n' => $n]), __('العميل مستني رد'), $url('admin.cancellations.index'), $n);
                }
            });
        }
        if ($can('finance.approve')) {
            $safe(function () use ($add, $url, $viaContract) {
                $n = $viaContract(DB::table('finance_requests')->whereNull('deleted_at')->where('status', 'pending'), 'sales_contract_id')->count();
                if ($n) {
                    $add('finance_pending', 'info', '🏦', __(':n طلب تمويل مستني رد البنك', ['n' => $n]), __('تابع مع شركات التمويل'), $url('admin.finance-requests.index', ['status' => 'pending']), $n);
                }
            });
        }
        if ($can('hr.approve')) {
            $safe(function () use ($add, $url) {
                $n = DB::table('hr_leave_requests')->where('status', 'pending')->count();
                if ($n) {
                    $add('leave_pending', 'info', '🌴', __(':n طلب إجازة مستني موافقتك', ['n' => $n]), '', $url('admin.hr.leaves.index', ['status' => 'pending']), $n);
                }
            });
        }

        // ─── أقساط الأصول (عربيات الشركة، معدات…) ───
        if ($can('assets.view')) {
            $safe(function () use ($add, $url) {
                $a = AssetFinance::alerts();
                if ($a['overdue']) {
                    $add('asset_overdue', 'danger', '🏢', __(':n قسط أصل متأخر', ['n' => $a['overdue']]), __('بإجمالي :amount', ['amount' => Money::format($a['overdue_amount'])]), $url('admin.asset-installments.index'), [$a['overdue'], $a['overdue_amount']]);
                } elseif ($a['soon']) {
                    $add('asset_soon', 'warning', '🏢', __(':n قسط أصل مستحق خلال أسبوع', ['n' => $a['soon']]), __('بإجمالي :amount', ['amount' => Money::format($a['soon_amount'])]), $url('admin.asset-installments.index'), [$a['soon'], today()->toDateString()]);
                }
            });
        }

        // ─── الأمان والنسخ الاحتياطي ───
        if ($can('backups.view')) {
            $safe(function () use ($add, $url) {
                $last = collect(Backup::list())->max('time');
                if (! $last || $last < now()->subDays(2)->timestamp) {
                    $add('backup_old', 'danger', '💾', $last ? __('آخر نسخة احتياطية من :time', ['time' => Carbon::createFromTimestamp($last)->diffForHumans()]) : __('لسه مفيش ولا نسخة احتياطية'), __('اعمل نسخة دلوقتي — دي بياناتك كلها'), $url('admin.backups.index'), $last);
                }
                foreach (Offsite::problems() as $t => $why) {
                    $add('offsite_'.$t, 'danger', '☁️', __('النسخة ما اترفعتش على :target', ['target' => __(Offsite::TARGETS[$t])]), __($why), $url('admin.backups.index'), [$why, today()->toDateString()]);
                }
            });
        }
        if ($can('audit.view')) {
            $safe(function () use ($add, $url) {
                $n = DB::table('audit_logs')->where('event', 'login_failed')->where('created_at', '>=', now()->subDay())->count();
                if ($n >= 5) {
                    $add('login_failed', 'danger', '🛡️', __(':n محاولة دخول غلط آخر 24 ساعة', ['n' => $n]), __('ممكن حد بيحاول يخمّن كلمة سر — راجع السجل'), $url('admin.audit.index', ['event' => 'login_failed']), $n);
                }
            });
        }

        $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($out, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

        return $out;
    }
}
