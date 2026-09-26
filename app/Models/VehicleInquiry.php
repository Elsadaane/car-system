<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * استفسار زائر على عربية من الموقع.
 * ده lead - لسه مش عميل، بيتحول لعميل لما يشتري.
 */
class VehicleInquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'inquiry_number', 'vehicle_id', 'branch_id',
        'name', 'phone', 'email', 'message', 'type', 'preferred_date',
        'status', 'assigned_to', 'customer_id', 'staff_notes',
        'contacted_at', 'converted_at',
        'source', 'utm_source', 'utm_medium', 'utm_campaign', 'ip_address',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'contacted_at'   => 'datetime',
        'converted_at'   => 'datetime',
    ];

    /** رقم استفسار تلقائي: INQ-20260922-A3F1 */
    protected static function booted(): void
    {
        static::creating(function (self $inquiry) {
            $inquiry->inquiry_number ??= 'INQ-' . now()->format('Ymd') . '-'
                . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        });
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // ===== Scopes =====

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['new', 'contacted', 'qualified']);
    }

    public function scopeForBranch($query, ?int $branchId)
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    // ===== Helpers =====

    public function typeLabel(): string
    {
        return match ($this->type) {
            'test_drive'  => 'تجربة قيادة',
            'reservation' => 'حجز',
            'finance'     => 'طلب تمويل',
            default       => 'استفسار',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'contacted' => 'تم التواصل',
            'qualified' => 'مؤهّل',
            'converted' => 'تحوّل لعميل',
            'closed'    => 'مغلق',
            'spam'      => 'سبام',
            default     => 'جديد',
        };
    }

    public function markContacted(?int $userId = null): void
    {
        $this->forceFill([
            'status'       => 'contacted',
            'contacted_at' => now(),
            'assigned_to'  => $this->assigned_to ?? $userId,
        ])->save();
    }
}
