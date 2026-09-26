<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Installment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'installment_number', 'sales_contract_id', 'customer_id', 'installment_sequence',
        'total_installments', 'amount', 'due_date', 'is_paid', 'paid_amount',
        'actual_payment_date', 'payment_id', 'status', 'days_overdue', 'late_fees',
        'late_fee_percent', 'first_reminder_sent', 'first_reminder_date',
        'second_reminder_sent', 'second_reminder_date', 'final_notice_sent',
        'final_notice_date', 'notes', 'customer_notes'
    ];

    protected $casts = [
        'due_date' => 'date',
        'actual_payment_date' => 'date',
        'first_reminder_date' => 'datetime',
        'second_reminder_date' => 'datetime',
        'final_notice_date' => 'datetime',
        'amount' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'late_fees' => 'decimal:3',
        'late_fee_percent' => 'decimal:3',
        'is_paid' => 'boolean',
        'first_reminder_sent' => 'boolean',
        'second_reminder_sent' => 'boolean',
        'final_notice_sent' => 'boolean',
    ];

    public function salesContract()
    {
        return $this->belongsTo(SalesContract::class, 'sales_contract_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }


    // ===== Scopes =====

    /** أقساط متأخرة (فات ميعادها ولسه مادفعتش) */
    public function scopeOverdue($query)
    {
        return $query->whereDate('due_date', '<', now())->where('is_paid', false);
    }

    /** أقساط قرب ميعادها (خلال عدد أيام) */
    public function scopeUpcoming($query, int $days = 5)
    {
        return $query->whereBetween('due_date', [now(), now()->addDays($days)])
                     ->where('is_paid', false);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // ===== Accessors =====

    /** المستحق الكلي = القسط + غرامة التأخير - المدفوع */
    public function getTotalDueAttribute(): float
    {
        return round((float) $this->amount + (float) $this->late_fees - (float) $this->paid_amount, 3);
    }

    // ===== Business logic =====

    /**
     * حساب غرامة التأخير.
     * النسبة شهرية، وبتتحسب بالتناسب مع عدد أيام التأخير.
     */
    public function calculateLateFees(): float
    {
        if ($this->is_paid || ! $this->late_fee_percent) {
            return 0.0;
        }

        $daysOverdue = (int) ($this->days_overdue ?? 0);
        if ($daysOverdue <= 0) {
            return 0.0;
        }

        $monthsOverdue = $daysOverdue / 30;

        return round((float) $this->amount * ((float) $this->late_fee_percent / 100) * $monthsOverdue, 3);
    }

    /**
     * تحديث حالة التأخير - بيتنادى من الـ job اليومي.
     */
    public function updateOverdueStatus(): void
    {
        if ($this->is_paid) {
            return;
        }

        if ($this->due_date->startOfDay()->isFuture()) {
            return; // لسه مافاتش ميعاده
        }

        $days = (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay());

        $this->days_overdue = $days;
        $this->status       = 'overdue';
        $this->late_fees    = $this->calculateLateFees();
        $this->save();
    }

    /**
     * إرسال التذكيرات في مواعيدها:
     *   - قبل الاستحقاق بـ 5 أيام  → تذكير أول
     *   - بعد الاستحقاق بـ 3 أيام  → تنبيه تأخير
     *   - بعد الاستحقاق بـ 15 يوم  → إنذار نهائي
     */
    public function sendRemindersIfNeeded(): void
    {
        if ($this->is_paid) {
            return;
        }

        $today = now()->startOfDay();
        $due   = $this->due_date->startOfDay();
        $diff  = (int) $today->diffInDays($due, false); // موجب = لسه قدامنا

        if ($diff <= 5 && $diff >= 0 && ! $this->first_reminder_sent) {
            $this->pushNotification('installment_due', 'medium');
            $this->forceFill(['first_reminder_sent' => true, 'first_reminder_date' => now()])->save();
            return;
        }

        if ($diff <= -3 && $diff > -15 && ! $this->second_reminder_sent) {
            $this->pushNotification('installment_overdue', 'high');
            $this->forceFill(['second_reminder_sent' => true, 'second_reminder_date' => now()])->save();
            return;
        }

        if ($diff <= -15 && ! $this->final_notice_sent) {
            $this->pushNotification('installment_final_notice', 'urgent');
            $this->forceFill(['final_notice_sent' => true, 'final_notice_date' => now()])->save();
        }
    }

    /** تسجيل دفع القسط */
    public function markAsPaid(?Payment $payment = null, ?float $amount = null): void
    {
        $paid = $amount ?? $this->total_due;

        $this->forceFill([
            'is_paid'             => true,
            'paid_amount'         => $paid,
            'actual_payment_date' => now(),
            'payment_id'          => $payment?->id,
            'status'              => 'paid',
        ])->save();

        $this->salesContract?->refreshPaymentTotals();
    }

    protected function pushNotification(string $type, string $priority): void
    {
        Notification::create([
            'notification_type' => $type,
            'notifiable_id'     => $this->id,
            'notifiable_type'   => self::class,
            'title_ar'          => $this->titleAr($type),
            'title_en'          => $this->titleEn($type),
            'message_ar'        => $this->messageAr($type),
            'message_en'        => $this->messageEn($type),
            'recipient_id'      => $this->customer_id,
            'recipient_type'    => Customer::class,
            'channels'          => ['email', 'sms', 'in_app'],
            'priority'          => $priority,
        ]);
    }

    protected function titleAr(string $t): string
    {
        return match ($t) {
            'installment_due'          => 'تذكير بقسط مستحق',
            'installment_overdue'      => 'تنبيه - القسط متأخر',
            'installment_final_notice' => 'إنذار نهائي - القسط متأخر',
            default                    => 'إشعار جديد',
        };
    }

    protected function titleEn(string $t): string
    {
        return match ($t) {
            'installment_due'          => 'Payment Due Reminder',
            'installment_overdue'      => 'Alert - Payment Overdue',
            'installment_final_notice' => 'Final Notice - Payment Overdue',
            default                    => 'New Notification',
        };
    }

    protected function messageAr(string $t): string
    {
        $no   = $this->installment_sequence;
        $date = $this->due_date->format('d/m/Y');

        return match ($t) {
            'installment_due'          => "القسط رقم {$no} مستحق يوم {$date}. المبلغ: {$this->amount}",
            'installment_overdue'      => "القسط رقم {$no} متأخر {$this->days_overdue} يوم. برجاء السداد.",
            'installment_final_notice' => "إنذار نهائي: القسط رقم {$no} متأخر {$this->days_overdue} يوم، والغرامة {$this->late_fees}.",
            default                    => 'برجاء مراجعة حسابك.',
        };
    }

    protected function messageEn(string $t): string
    {
        $no   = $this->installment_sequence;
        $date = $this->due_date->format('d/m/Y');

        return match ($t) {
            'installment_due'          => "Installment #{$no} is due on {$date}. Amount: {$this->amount}",
            'installment_overdue'      => "Installment #{$no} is {$this->days_overdue} days overdue. Please pay.",
            'installment_final_notice' => "Final notice: installment #{$no} is {$this->days_overdue} days overdue, late fees {$this->late_fees}.",
            default                    => 'Please review your account.',
        };
    }
}
