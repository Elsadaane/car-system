<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name_ar', 'name_en'
    ];

    public function dealerContracts()
    {
        return $this->hasMany(DealerContract::class, 'status_id');
    }

    public function salesContracts()
    {
        return $this->hasMany(SalesContract::class, 'status_id');
    }
}
