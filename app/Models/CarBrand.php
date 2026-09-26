<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarBrand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar', 'name_en', 'logo', 'description_ar', 'description_en', 'is_active',
        'total_models'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function carModels()
    {
        return $this->hasMany(CarModel::class, 'brand_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'brand_id');
    }
}
