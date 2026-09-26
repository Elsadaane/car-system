<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierReturn extends Model
{
    use SoftDeletes;

    public const REASONS = [
        'defect'           => 'عيب فني',
        'not_as_described' => 'مخالفة للمواصفات المتفق عليها',
        'damaged'          => 'تلفيات / حادث',
        'documents'        => 'مشكلة في الأوراق',
        'other'            => 'سبب آخر',
    ];

    public const STATUSES = [
        'pending'   => 'قيد المراجعة',
        'completed' => 'تم الإرجاع',
        'rejected'  => 'مرفوض',
    ];

    protected $fillable = [
        'return_number', 'supplier_id', 'vehicle_id', 'supplier_invoice_id',
        'return_date', 'reason', 'reason_details', 'refund_amount',
        'status', 'completed_at', 'notes', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'return_date'   => 'date',
            'completed_at'  => 'datetime',
            'refund_amount' => 'decimal:3',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
