<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_reference', 'payable_type', 'payable_id', 'payment_method_id', 'amount',
        'payment_date', 'check_number', 'transaction_id', 'bank_reference', 'notes', 'status',
        'financial_account_id', 'user_id'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:3',
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payable()
    {
        return $this->morphTo();
    }

    public function installments()
    {
        return $this->hasMany(Installment::class, 'payment_id');
    }
}
