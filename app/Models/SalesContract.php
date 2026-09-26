<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_number', 'customer_id', 'branch_id', 'vehicle_id', 'sale_price',
        'tax_percent', 'tax_amount', 'total_amount', 'paid_amount', 'remaining_amount',
        'contract_date', 'delivery_date', 'status_id', 'notes', 'customer_signature',
        'seller_signature', 'user_id'
    ];

    protected $casts = [
        'contract_date' => 'date',
        'delivery_date' => 'date',
        'sale_price' => 'decimal:3',
        'tax_percent' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'total_amount' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'remaining_amount' => 'decimal:3',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function status()
    {
        return $this->belongsTo(ContractStatus::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deliveryContracts()
    {
        return $this->hasMany(DeliveryContract::class, 'sales_contract_id');
    }

    public function installments()
    {
        return $this->hasMany(Installment::class, 'sales_contract_id');
    }

    public function financeRequests()
    {
        return $this->hasMany(FinanceRequest::class, 'sales_contract_id');
    }

    public function cancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class, 'sales_contract_id');
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }


    // ===== Scopes =====

    public function scopeActive($query)
    {
        return $query->whereHas('status', fn ($q) => $q->where('code', 'active'));
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    // ===== Business logic =====

    /**
     * توليد جدول الأقساط.
     *
     * @param  int    $months          عدد الأقساط
     * @param  float  $downPayment     المقدم (بيتخصم قبل التقسيم)
     * @param  float  $lateFeePercent  نسبة غرامة التأخير الشهرية
     * @param  mixed  $firstDueDate    ميعاد أول قسط (افتراضي: بعد شهر)
     */
    public function generateInstallments(
        int $months = 12,
        float $downPayment = 0,
        float $lateFeePercent = 2,
        $firstDueDate = null
    ): void {
        if ($months < 1) {
            throw new \InvalidArgumentException('عدد الأقساط لازم يكون 1 على الأقل');
        }

        $financed = round((float) $this->total_amount - $downPayment, 3);
        if ($financed <= 0) {
            throw new \InvalidArgumentException('المقدم مش ممكن يكون أكبر من أو يساوي إجمالي العقد');
        }

        // التقريب بيتحط كله على آخر قسط عشان المجموع يطلع مظبوط بالمليم
        $base      = floor(($financed / $months) * 1000) / 1000;
        $lastExtra = round($financed - ($base * $months), 3);

        $start = $firstDueDate ? \Carbon\Carbon::parse($firstDueDate) : now()->addMonth();

        $this->installments()->delete(); // امسح أي جدول قديم

        for ($i = 1; $i <= $months; $i++) {
            $this->installments()->create([
                'installment_number'   => $this->contract_number . '-I' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'customer_id'          => $this->customer_id,
                'installment_sequence' => $i,
                'total_installments'   => $months,
                'amount'               => $i === $months ? round($base + $lastExtra, 3) : $base,
                'due_date'             => $start->copy()->addMonths($i - 1),
                'late_fee_percent'     => $lateFeePercent,
                'status'               => 'pending',
                'is_paid'              => false,
            ]);
        }
    }

    /** إعادة حساب المدفوع والمتبقي من سجل المدفوعات */
    public function refreshPaymentTotals(): void
    {
        $paid = (float) $this->payments()->where('status', 'completed')->sum('amount');

        $this->forceFill([
            'paid_amount'      => $paid,
            'remaining_amount' => round((float) $this->total_amount - $paid, 3),
        ])->save();
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return (float) $this->remaining_amount <= 0;
    }

    public function getOverdueInstallmentsCountAttribute(): int
    {
        return $this->installments()->overdue()->count();
    }
}
