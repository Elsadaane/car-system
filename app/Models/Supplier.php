<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_code', 'name_ar', 'name_en', 'national_id', 'national_id_expiry',
        'tax_number', 'commercial_register', 'phone', 'email', 'address', 'settlement_days',
        'settlement_terms', 'last_settlement_date', 'bank_name', 'iban_number',
        'account_holder_name', 'status', 'status_reason', 'credit_limit', 'current_balance',
        'total_vehicles', 'sold_vehicles', 'average_rating', 'notes', 'contract_attachment',
        'logo', 'user_id', 'branch_id'
    ];

    protected $casts = [
        'last_settlement_date' => 'date',
        'credit_limit' => 'decimal:3',
        'current_balance' => 'decimal:3',
        'average_rating' => 'decimal:3',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'supplier_id');
    }

    public function dealerContracts()
    {
        return $this->hasMany(DealerContract::class, 'supplier_id');
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }


    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** موردين فات ميعاد تسويتهم */
    public function scopeSettlementOverdue($query)
    {
        return $query->whereNotNull('last_settlement_date')
                     ->whereRaw('DATE_ADD(last_settlement_date, INTERVAL settlement_days DAY) < CURDATE()');
    }

    public function getDaysSinceSettlementAttribute(): ?int
    {
        return $this->last_settlement_date
            ? (int) $this->last_settlement_date->diffInDays(now())
            : null;
    }
}
