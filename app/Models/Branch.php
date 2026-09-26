<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_code', 'name_ar', 'name_en', 'phone', 'mobile', 'email', 'address', 'city',
        'region', 'location_url', 'manager_id', 'commercial_register', 'tax_number', 'is_main',
        'status', 'opening_date', 'notes'
    ];

    protected $casts = [
        'opening_date' => 'date',
        'is_main' => 'boolean',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class, 'branch_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'branch_id');
    }

    public function salesContracts()
    {
        return $this->hasMany(SalesContract::class, 'branch_id');
    }


    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    /** الفرع الرئيسي - بيتستخدم كافتراضي في النسخ اللي فرع واحد */
    public static function main(): ?self
    {
        return static::where('is_main', true)->first() ?? static::first();
    }

    /** النظام multi-branch ولا فرع واحد؟ الواجهة بتتغير على أساسها */
    public static function isMultiBranch(): bool
    {
        return static::active()->count() > 1;
    }
}
