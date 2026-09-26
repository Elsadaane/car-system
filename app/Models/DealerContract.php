<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_number', 'supplier_id', 'vehicle_id', 'agreed_price', 'commission_amount',
        'tax_percent', 'tax_amount', 'total_amount', 'paid_amount', 'remaining_amount',
        'contract_date', 'expiry_date', 'status_id', 'notes', 'attachment', 'user_id'
    ];

    protected $casts = [
        'contract_date' => 'date',
        'expiry_date' => 'date',
        'agreed_price' => 'decimal:3',
        'commission_amount' => 'decimal:3',
        'tax_percent' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'total_amount' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'remaining_amount' => 'decimal:3',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
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

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
