<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInstallment extends Model
{
    protected $fillable = [
        'supplier_invoice_id', 'supplier_id', 'sequence', 'amount',
        'due_date', 'paid_amount', 'paid_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date'    => 'date',
            'paid_at'     => 'date',
            'amount'      => 'decimal:3',
            'paid_amount' => 'decimal:3',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function remaining(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 3);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isBefore(today());
    }

    /** عدد الأيام: سالب = متأخر، موجب = لسه فاضل */
    public function daysUntilDue(): int
    {
        return (int) today()->diffInDays($this->due_date, false);
    }
}
