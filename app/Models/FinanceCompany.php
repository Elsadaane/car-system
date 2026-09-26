<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar', 'name_en', 'phone', 'email', 'website', 'address', 'terms_ar', 'terms_en',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function financeRequests()
    {
        return $this->hasMany(FinanceRequest::class, 'finance_company_id');
    }
}
