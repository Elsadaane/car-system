<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CancellationRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cancellation_number', 'sales_contract_id', 'customer_id', 'cancellation_date',
        'reason_ar', 'reason_en', 'additional_notes', 'status', 'refund_amount',
        'penalty_amount', 'net_refund', 'refund_date', 'approval_notes', 'user_id'
    ];

    protected $casts = [
        'cancellation_date' => 'date',
        'refund_date' => 'date',
        'refund_amount' => 'decimal:3',
        'penalty_amount' => 'decimal:3',
        'net_refund' => 'decimal:3',
    ];

    public function salesContract()
    {
        return $this->belongsTo(SalesContract::class, 'sales_contract_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
