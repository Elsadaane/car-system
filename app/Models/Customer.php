<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_code', 'name_ar', 'name_en', 'national_id', 'phone', 'email', 'address',
        'job_title', 'nationality', 'credit_limit', 'total_purchases', 'status',
        'block_reason', 'rating', 'notes'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:3',
        'total_purchases' => 'decimal:3',
        'rating' => 'decimal:3',
    ];

    public function testDrives()
    {
        return $this->hasMany(TestDrive::class, 'customer_id');
    }

    public function salesContracts()
    {
        return $this->hasMany(SalesContract::class, 'customer_id');
    }

    public function deliveryContracts()
    {
        return $this->hasMany(DeliveryContract::class, 'customer_id');
    }

    public function installments()
    {
        return $this->hasMany(Installment::class, 'customer_id');
    }

    public function cancellationRequests()
    {
        return $this->hasMany(CancellationRequest::class, 'customer_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'customer_id');
    }

    public function receivedNotifications()
    {
        return $this->morphMany(Notification::class, 'recipient');
    }


    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** إجمالي المتأخر على العميل (أقساط + غرامات) */
    public function getTotalOverdueAttribute(): float
    {
        return (float) $this->installments()->overdue()
            ->selectRaw('COALESCE(SUM(amount + late_fees - paid_amount), 0) as t')
            ->value('t');
    }

    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->receivedNotifications()->unread()->count();
    }

    public function user()
    {
        return $this->hasOne(User::class, 'customer_id');
    }
}
