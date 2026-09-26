<?php

namespace App\Support;

use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\SupplierInvoice;
use App\Models\SupplierReturn;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * كشف حساب المورد وتقييمه - كل الحسابات في مكان واحد.
 */
class SupplierAccount
{
    public function __construct(private Supplier $supplier) {}

    public function summary(): array
    {
        $id = $this->supplier->id;
        $today = today()->toDateString();

        $invoices = SupplierInvoice::where('supplier_id', $id)->where('status', '!=', 'cancelled');
        $invoiced = (float) (clone $invoices)->sum('total_amount');
        $paid     = (float) (clone $invoices)->sum('paid_amount');

        $completedReturns = SupplierReturn::where('supplier_id', $id)->where('status', 'completed');
        $credits  = (float) (clone $completedReturns)->sum('refund_amount');
        $returned = (clone $completedReturns)->count();

        $open = SupplierInstallment::where('supplier_id', $id)->where('status', '!=', 'paid');
        $overdue      = (float) (clone $open)->where('due_date', '<', $today)->sum(DB::raw('amount - paid_amount'));
        $overdueCount = (clone $open)->where('due_date', '<', $today)->count();
        $dueSoon      = (float) (clone $open)->whereBetween('due_date', [$today, today()->addDays(7)->toDateString()])
            ->sum(DB::raw('amount - paid_amount'));
        $next = (clone $open)->where('due_date', '>=', $today)->orderBy('due_date')->first();

        $purchased = Vehicle::where('supplier_id', $id)->count();
        $sold      = Vehicle::where('supplier_id', $id)->where('status', 'sold')->count();

        return [
            'invoiced'      => $invoiced,
            'paid'          => $paid,
            'credits'       => $credits,
            'balance'       => round($invoiced - $paid - $credits, 3),
            'overdue'       => $overdue,
            'overdue_count' => $overdueCount,
            'due_soon'      => $dueSoon,
            'next'          => $next,
            'purchased'     => $purchased,
            'sold'          => $sold,
            'returned'      => $returned,
            'rating'        => self::rating($purchased, $returned),
        ];
    }

    /** بيحدّث الأرقام المخزنة على المورد (بتتستخدم في الترتيب والقوائم) */
    public function refresh(): void
    {
        $s = $this->summary();

        $this->supplier->forceFill([
            'current_balance' => $s['balance'],
            'total_vehicles'  => $s['purchased'],
            'sold_vehicles'   => $s['sold'],
            'average_rating'  => $s['rating']['score'],
        ])->save();
    }

    /**
     * التقييم من نسبة المرتجعات:
     *   0% مرتجعات  → 5 نجوم
     *   10%         → 4.2
     *   25%         → 3
     *   50% وأكتر   → 1
     * أقل من 3 عربيات = بيانات مش كفاية للحكم.
     */
    public static function rating(int $purchased, int $returned): array
    {
        $rate = $purchased > 0 ? round($returned / $purchased * 100, 1) : 0;

        if ($purchased < 3) {
            return ['score' => null, 'rate' => $rate, 'label' => 'بيانات غير كافية', 'tone' => 'slate'];
        }

        $score = round(max(1, 5 - ($returned / $purchased) * 8), 1);

        [$label, $tone] = match (true) {
            $score >= 4.5 => ['ممتاز', 'emerald'],
            $score >= 3.5 => ['جيد', 'blue'],
            $score >= 2.5 => ['متوسط', 'amber'],
            default       => ['ضعيف', 'rose'],
        };

        return ['score' => $score, 'rate' => $rate, 'label' => $label, 'tone' => $tone];
    }
}
