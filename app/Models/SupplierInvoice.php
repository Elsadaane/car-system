<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'unpaid'    => 'غير مدفوعة',
        'partial'   => 'مدفوعة جزئياً',
        'paid'      => 'مدفوعة',
        'cancelled' => 'ملغاة',
    ];

    protected $fillable = [
        'invoice_number', 'supplier_reference', 'supplier_id', 'branch_id',
        'invoice_date', 'subtotal', 'tax_percent', 'tax_amount', 'total_amount',
        'paid_amount', 'status', 'attachment', 'notes', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal'     => 'decimal:3',
            'tax_percent'  => 'decimal:2',
            'tax_amount'   => 'decimal:3',
            'total_amount' => 'decimal:3',
            'paid_amount'  => 'decimal:3',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function installments()
    {
        return $this->hasMany(SupplierInstallment::class)->orderBy('sequence');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'supplier_invoice_id');
    }

    public function remaining(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 3);
    }

    /** بيعيد حساب المدفوع والحالة من الأقساط */
    public function refreshPaid(): void
    {
        $paid = (float) $this->installments()->sum('paid_amount');

        $this->forceFill([
            'paid_amount' => $paid,
            'status'      => match (true) {
                $this->status === 'cancelled'                    => 'cancelled',
                $paid <= 0                                        => 'unpaid',
                $paid + 0.0005 >= (float) $this->total_amount     => 'paid',
                default                                           => 'partial',
            },
        ])->save();
    }
}
