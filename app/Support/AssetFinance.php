<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * جدول أقساط الأصل:
 *   المبلغ المموّل = التكلفة − المقدم
 *   الفايدة = المموّل × النسبة السنوية × (عدد الشهور ÷ 12)   (نسبة ثابتة زي البنوك والمعارض)
 *   القسط = (المموّل + الفايدة) ÷ عدد الأقساط — وآخر قسط بياخد فرق التقريب
 */
class AssetFinance
{
    public const FINANCIERS = ['supplier' => 'المورد مباشرة', 'bank' => 'بنك / شركة تمويل'];

    /** @return array<int, array{seq:int, due_date:string, amount:float, principal:float, interest:float}> */
    public static function schedule(float $cost, float $down, float $ratePct, int $count, string $firstDue): array
    {
        $count = max(1, $count);
        $financed = Money::round(max(0, $cost - $down));
        $interest = Money::round($financed * $ratePct / 100 * $count / 12);
        $each = Money::round(($financed + $interest) / $count);
        $eachPrincipal = Money::round($financed / $count);
        $first = Carbon::parse($firstDue);

        $rows = [];
        $sumAmount = $sumPrincipal = 0;
        for ($i = 1; $i <= $count; $i++) {
            $last = $i === $count;
            $amount = $last ? Money::round($financed + $interest - $sumAmount) : $each;
            $principal = $last ? Money::round($financed - $sumPrincipal) : $eachPrincipal;
            $rows[] = [
                'seq'       => $i,
                // addMonthsNoOverflow: قسط يوم 31 مايقعش في الشهر اللي بعده
                'due_date'  => $first->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                'amount'    => $amount,
                'principal' => $principal,
                'interest'  => Money::round($amount - $principal),
            ];
            $sumAmount += $amount;
            $sumPrincipal += $principal;
        }

        return $rows;
    }

    /** بيعمل الجدول من جديد (بس لو مفيش ولا قسط اتدفع) */
    public static function regenerate(int $assetId): bool
    {
        $a = DB::table('assets')->find($assetId);
        if (! $a) {
            return false;
        }
        if (DB::table('asset_installments')->where('asset_id', $assetId)->whereNotNull('paid_date')->exists()) {
            return false;
        }

        DB::table('asset_installments')->where('asset_id', $assetId)->delete();
        if ($a->payment_type !== 'installments' || ! $a->installments_count || ! $a->first_due_date) {
            return true;
        }

        $now = now();
        foreach (self::schedule((float) $a->purchase_cost, (float) $a->down_payment, (float) $a->interest_rate, (int) $a->installments_count, $a->first_due_date) as $r) {
            DB::table('asset_installments')->insert($r + ['asset_id' => $assetId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return true;
    }

    /** ملخص تمويل أصل */
    public static function summary(int $assetId): array
    {
        $rows = DB::table('asset_installments')->where('asset_id', $assetId)->get();
        $paid = $rows->whereNotNull('paid_date');
        $today = today()->toDateString();

        return [
            'count'     => $rows->count(),
            'paid'      => $paid->count(),
            'total'     => (float) $rows->sum('amount'),
            'paid_sum'  => (float) $paid->sum('amount'),
            'remaining' => Money::round((float) $rows->sum('amount') - (float) $paid->sum('amount')),
            'interest'  => (float) $rows->sum('interest'),
            'overdue'   => $rows->whereNull('paid_date')->where('due_date', '<', $today)->count(),
            'next'      => $rows->whereNull('paid_date')->sortBy('due_date')->first(),
        ];
    }

    /** للسايدبار والتنبيهات: الأقساط المتأخرة والمستحقة خلال 7 أيام */
    public static function alerts(): array
    {
        $today = today()->toDateString();
        $q = DB::table('asset_installments')->join('assets', 'assets.id', '=', 'asset_installments.asset_id')
            ->whereNull('assets.deleted_at')->whereNull('asset_installments.paid_date');

        return [
            'overdue'        => (clone $q)->where('due_date', '<', $today)->count(),
            'overdue_amount' => (float) (clone $q)->where('due_date', '<', $today)->sum('amount'),
            'soon'           => (clone $q)->whereBetween('due_date', [$today, today()->addDays(7)->toDateString()])->count(),
            'soon_amount'    => (float) (clone $q)->whereBetween('due_date', [$today, today()->addDays(7)->toDateString()])->sum('amount'),
        ];
    }
}
