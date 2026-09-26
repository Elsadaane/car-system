<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'delivery_number', 'sales_contract_id', 'customer_id', 'vehicle_id',
        'scheduled_delivery', 'actual_delivery', 'delivery_location', 'delivery_notes',
        'delivered_by', 'received_by', 'signature_path', 'status', 'cancellation_reason',
        'user_id'
    ];

    protected $casts = [
        'scheduled_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
    ];

    public function salesContract()
    {
        return $this->belongsTo(SalesContract::class, 'sales_contract_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
