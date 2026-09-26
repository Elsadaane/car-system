<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number', 'sales_contract_id', 'finance_company_id', 'requested_amount',
        'approved_amount', 'interest_rate', 'installment_period', 'monthly_payment', 'status',
        'request_date', 'approval_date', 'rejection_reason', 'notes', 'user_id'
    ];

    protected $casts = [
        'request_date' => 'date',
        'approval_date' => 'date',
        'requested_amount' => 'decimal:3',
        'approved_amount' => 'decimal:3',
        'interest_rate' => 'decimal:3',
        'monthly_payment' => 'decimal:3',
    ];

    public function salesContract()
    {
        return $this->belongsTo(SalesContract::class, 'sales_contract_id');
    }

    public function financeCompany()
    {
        return $this->belongsTo(FinanceCompany::class, 'finance_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
