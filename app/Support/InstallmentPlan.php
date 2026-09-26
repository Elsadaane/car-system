<?php

namespace App\Support;

use App\Models\SalesContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * حسابات عقد البيع والأقساط.
 *
 *   الإجمالي   = السعر − الخصم + الضريبة
 *   المقسّط    = الإجمالي − المقدم  ← بيتقسم على عدد الأقساط
 *   (الكسور بتتحسب بالبيسة/القرش، والفرق بيروح على آخر قسط)
 *
 * recalculate() هي "مصدر الحقيقة": بتوزّع كل الدفعات (غير المقدم) على الأقساط
 * بالترتيب من الأقدم، وبتحدّث المدفوع والمتبقي وحالة العقد.
 * فلو دفعة اتمسحت أو اتعدلت، كل حاجة بتتظبط لوحدها.
 */
class InstallmentPlan
{
    public static function units(mixed $value): int
    {
        return (int) round((float) $value * 10 ** Money::decimals());
    }

    public static function money(int $units): float
    {
        return Money::round($units / 10 ** Money::decimals());
    }

    /** بيقسم المبلغ على عدد الأقساط - الفرق على آخر قسط */
    public static function split(float $amount, int $count): array
    {
        $total = self::units($amount);
        $base = intdiv($total, $count);
        $amounts = array_fill(0, $count, self::money($base));
        $amounts[$count - 1] = self::money($total - $base * ($count - 1));

        return $amounts;
    }

    /** بيمسح الأقساط القديمة ويعمل جدول جديد */
    public static function generate(SalesContract $contract): void
    {
        DB::table('installments')->where('sales_contract_id', $contract->id)->delete();

        if ($contract->payment_type !== 'installments' || (int) $contract->installments_count < 1) {
            return;
        }

        $financed = Money::round((float) $contract->total_amount - (float) $contract->down_payment);
        if ($financed <= 0) {
            return;
        }

        $count = (int) $contract->installments_count;
        $interval = max(1, (int) $contract->installment_interval);
        $first = Carbon::parse($contract->first_due_date);
        $now = now();

        $rows = [];
        foreach (self::split($financed, $count) as $i => $amount) {
            $rows[] = [
                'installment_number' => $contract->contract_number.'-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'sales_contract_id'  => $contract->id,
                'customer_id'        => $contract->customer_id,
                'installment_sequence' => $i + 1,
                'total_installments' => $count,
                'amount'             => $amount,
                'due_date'           => $first->copy()->addMonthsNoOverflow($i * $interval)->toDateString(),
                'status'             => 'pending',
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        DB::table('installments')->insert($rows);
    }

    /** بيوزّع الدفعات على الأقساط ويحدّث أرقام العقد */
    public static function recalculate(SalesContract $contract): void
    {
        $payments = DB::table('payments')
            ->where('payable_type', $contract->getMorphClass())
            ->where('payable_id', $contract->id)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->orderBy('payment_date')->orderBy('id')
            ->get(['id', 'amount', 'payment_date']);

        $paidUnits = $payments->sum(fn ($p) => self::units($p->amount));
        $totalUnits = self::units($contract->total_amount);

        $installments = DB::table('installments')
            ->where('sales_contract_id', $contract->id)
            ->whereNull('deleted_at')
            ->orderBy('installment_sequence')
            ->get();

        $cancelled = (int) $contract->status_id === ContractStatus::id('cancelled');

        if ($installments->isNotEmpty() && ! $cancelled) {
            DB::table('installment_payments')->whereIn('installment_id', $installments->pluck('id'))->delete();

            $state = $installments->mapWithKeys(fn ($i) => [$i->id => ['need' => self::units($i->amount), 'paid' => 0, 'date' => null]])->all();
            $ids = array_keys($state);
            $cursor = 0;
            $links = [];
            $now = now();

            foreach ($payments as $p) {
                if ((int) $p->id === (int) $contract->down_payment_id) {
                    continue;
                }
                $left = self::units($p->amount);
                while ($left > 0 && $cursor < count($ids)) {
                    $id = $ids[$cursor];
                    if ($state[$id]['paid'] >= $state[$id]['need']) {
                        $state[$id]['date'] ??= $p->payment_date;
                        $cursor++;
                        continue;
                    }
                    $take = min($left, $state[$id]['need'] - $state[$id]['paid']);
                    $state[$id]['paid'] += $take;
                    $left -= $take;
                    $links[] = ['installment_id' => $id, 'payment_id' => $p->id, 'amount' => self::money($take), 'created_at' => $now, 'updated_at' => $now];
                    if ($state[$id]['paid'] >= $state[$id]['need']) {
                        $state[$id]['date'] = $p->payment_date;
                        $cursor++;
                    }
                }
            }

            // لو دفعة واحدة غطت نفس القسط مرتين (مستحيل عملياً) نجمعهم عشان الـ unique
            $merged = [];
            foreach ($links as $l) {
                $key = $l['installment_id'].'-'.$l['payment_id'];
                if (isset($merged[$key])) {
                    $merged[$key]['amount'] = Money::round($merged[$key]['amount'] + $l['amount']);
                } else {
                    $merged[$key] = $l;
                }
            }
            if ($merged) {
                DB::table('installment_payments')->insert(array_values($merged));
            }

            $today = today();
            foreach ($installments as $i) {
                $s = $state[$i->id];
                $due = Carbon::parse($i->due_date);
                $isPaid = $s['paid'] >= $s['need'];
                $status = match (true) {
                    $isPaid          => 'paid',
                    $due->lt($today) => 'overdue',
                    $s['paid'] > 0   => 'partially_paid',
                    default          => 'pending',
                };
                $daysOverdue = $isPaid
                    ? max(0, (int) $due->diffInDays(Carbon::parse($s['date']), false))
                    : ($due->lt($today) ? (int) $due->diffInDays($today) : 0);

                DB::table('installments')->where('id', $i->id)->update([
                    'paid_amount'         => self::money($s['paid']),
                    'is_paid'             => $isPaid,
                    'actual_payment_date' => $isPaid ? $s['date'] : null,
                    'status'              => $status,
                    'days_overdue'        => $daysOverdue,
                    'updated_at'          => now(),
                ]);
            }
        }

        $update = [
            'paid_amount'      => self::money($paidUnits),
            'remaining_amount' => self::money(max(0, $totalUnits - $paidUnits)),
        ];
        if (! $cancelled) {
            $update['status_id'] = $paidUnits >= $totalUnits ? ContractStatus::id('completed') : ContractStatus::id('active');
        }

        $contract->forceFill($update)->save();
    }

    /** إجمالي مشتريات العميل (العقود غير الملغية) */
    public static function refreshCustomer(int $customerId): void
    {
        $total = DB::table('sales_contracts')
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('status_id')->orWhere('status_id', '!=', ContractStatus::id('cancelled')))
            ->sum('total_amount');

        DB::table('customers')->where('id', $customerId)->update(['total_purchases' => Money::round($total)]);
    }
}
