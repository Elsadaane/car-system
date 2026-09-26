<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\SalesContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الشيكات المؤجلة على الأقساط:
 *   مستلم ← اتودع البنك ← اتصرف (بتتعمل دفعة على العقد أوتوماتيك وتتوزع على الأقساط)
 *                       ↘ ارتجع (لو كان اتصرف: الدفعة بتتلغي والقسط يرجع مستحق)
 *   ممكن كمان يترجع للعميل أو يتلغى قبل الإيداع.
 */
class Cheques
{
    public const STATUSES = [
        'received'  => ['مستلم', 'bg-slate-100 text-slate-700'],
        'deposited' => ['اتودع البنك', 'bg-blue-100 text-blue-700'],
        'cleared'   => ['اتصرف ✓', 'bg-emerald-100 text-emerald-700'],
        'bounced'   => ['مرتجع ✕', 'bg-rose-100 text-rose-700'],
        'returned'  => ['اترجع للعميل', 'bg-amber-100 text-amber-800'],
        'cancelled' => ['ملغي', 'bg-slate-100 text-slate-400'],
    ];

    /** الإجراءات المسموحة من كل حالة */
    public const ACTIONS = [
        'received'  => ['deposit', 'clear', 'bounce', 'return', 'cancel'],
        'deposited' => ['clear', 'bounce'],
        'cleared'   => ['bounce'],
        'bounced'   => ['deposit', 'return'],
        'returned'  => [],
        'cancelled' => [],
    ];

    public const ACTION_LABELS = [
        'deposit' => 'إيداع في البنك', 'clear' => 'تم صرف الشيك', 'bounce' => 'ارتجع', 'return' => 'رجّعته للعميل', 'cancel' => 'إلغاء',
    ];

    public static function table(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::hasTable('cheques');
    }

    /** طريقة الدفع اللي بتتسجل بيها الدفعة لما الشيك يتصرف (اللي اسمها فيه "شيك"، وإلا أول طريقة) */
    public static function paymentMethodId(): ?int
    {
        return DB::table('payment_methods')->where('is_active', true)
            ->orderByRaw("case when name_ar like '%شيك%' or name_en like '%cheque%' or name_en like '%check%' then 0 else 1 end")
            ->orderBy('sort_order')->value('id');
    }

    /** تنفيذ إجراء على شيك — بيرجع رسالة النجاح */
    public static function apply(object $cheque, string $action, array $data, int $userId): string
    {
        if (! in_array($action, self::ACTIONS[$cheque->status] ?? [], true)) {
            throw ValidationException::withMessages(['action' => 'الإجراء ده مش مسموح وحالة الشيك "'.(self::STATUSES[$cheque->status][0] ?? $cheque->status).'".']);
        }
        $date = $data['date'] ?? today()->toDateString();

        return DB::transaction(function () use ($cheque, $action, $data, $date, $userId) {
            $contract = SalesContract::whereKey($cheque->sales_contract_id)->lockForUpdate()->firstOrFail();
            $update = ['updated_at' => now()];

            switch ($action) {
                case 'deposit':
                    $update += ['status' => 'deposited', 'deposited_at' => $date];
                    $msg = 'الشيك اتسجل إنه اتودع البنك.';
                    break;

                case 'clear':
                    $remaining = Money::round($contract->remaining_amount);
                    if ((float) $cheque->amount > $remaining + 0.0005) {
                        throw ValidationException::withMessages(['action' => 'مبلغ الشيك أكبر من المتبقي على العميل ('.Money::format($remaining).').']);
                    }
                    $methodId = $data['payment_method_id'] ?? self::paymentMethodId();
                    if (! $methodId) {
                        throw ValidationException::withMessages(['action' => 'ضيف طريقة دفع "شيك" من صفحة طرق الدفع الأول.']);
                    }
                    $payment = Payment::forceCreate([
                        'payment_reference' => 'CHQ-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5)),
                        'payable_type'      => $contract->getMorphClass(),
                        'payable_id'        => $contract->id,
                        'payment_method_id' => $methodId,
                        'amount'            => Money::round($cheque->amount),
                        'payment_date'      => $date,
                        'check_number'      => $cheque->cheque_number,
                        'bank_reference'    => $cheque->bank_name,
                        'notes'             => 'شيك رقم '.$cheque->cheque_number.($cheque->bank_name ? ' — '.$cheque->bank_name : ''),
                        'status'            => 'completed',
                        'user_id'           => $userId,
                    ]);
                    $update += ['status' => 'cleared', 'cleared_at' => $date, 'payment_id' => $payment->id];
                    $msg = 'الشيك اتصرف واتسجل دفعة '.Money::format($cheque->amount).' على العقد.';
                    break;

                case 'bounce':
                    if ($cheque->payment_id) {
                        // كان اتصرف واتسجل: الدفعة بتتلغي والقسط يرجع مستحق
                        Payment::whereKey($cheque->payment_id)->first()?->delete();
                    }
                    $update += ['status' => 'bounced', 'bounced_at' => $date, 'payment_id' => null, 'bounce_reason' => $data['reason'] ?? null];
                    $msg = 'الشيك اتسجل مرتجع.'.($cheque->payment_id ? ' الدفعة اتلغت والقسط رجع مستحق.' : '');
                    break;

                case 'return':
                    $update += ['status' => 'returned'];
                    $msg = 'الشيك اتسجل إنه اترجع للعميل.';
                    break;

                default: // cancel
                    $update += ['status' => 'cancelled'];
                    $msg = 'الشيك اتلغى.';
            }

            DB::table('cheques')->where('id', $cheque->id)->update($update);
            InstallmentPlan::recalculate($contract->fresh());

            DB::table('activity_logs')->insert([
                'action'       => 'cheque_'.$action,
                'subject_type' => $contract->getMorphClass(),
                'subject_id'   => $contract->id,
                'causer_type'  => (new \App\Models\User)->getMorphClass(),
                'causer_id'    => $userId,
                'properties'   => json_encode(['cheque' => $cheque->cheque_number, 'amount' => (float) $cheque->amount, 'reason' => $data['reason'] ?? null], JSON_UNESCAPED_UNICODE),
                'description'  => 'شيك '.$cheque->cheque_number.': '.self::ACTION_LABELS[$action],
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return $msg;
        });
    }
}
