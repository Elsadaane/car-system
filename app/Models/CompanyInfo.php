<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyInfo extends Model
{
    use HasFactory;

    protected $table = 'company_info';

    protected $fillable = [
        'name_ar', 'name_en', 'commercial_register_number', 'tax_number', 'address_ar',
        'address_en', 'phone', 'email', 'website', 'logo', 'seal', 'terms_and_conditions_ar',
        'terms_and_conditions_en', 'privacy_policy_ar', 'privacy_policy_en',
        'dealer_contract_terms_ar', 'dealer_contract_terms_en', 'sales_contract_terms_ar',
        'sales_contract_terms_en', 'delivery_contract_terms_ar', 'delivery_contract_terms_en'
    ];
}
