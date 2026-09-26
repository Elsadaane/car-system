<?php

namespace App\Models;

use App\Support\Access;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property string|null $national_id
 * @property string $role            نوع الحساب: customer / staff / admin (admin = مدير عام)
 * @property int|null $customer_id
 * @property int|null $branch_id
 * @property bool $all_branches      يشوف كل الفروع ولا فرعه بس
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'national_id',
    'role',
    'customer_id',
    'branch_id',
    'all_branches',
    'is_active',
    'last_login_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /** Spatie: كل الأدوار على حارس الويب */
    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'all_branches' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    // =====================================================
    //  العلاقات
    // =====================================================

    /** ملف العميل - بيتملي لو المستخدم عميل */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** فرع الموظف */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** الفروع اللي هو مديرها */
    public function managedBranches()
    {
        return $this->hasMany(Branch::class, 'manager_id');
    }

    /** إشعارات النظام الموجهة ليه */
    public function receivedNotifications()
    {
        return $this->morphMany(Notification::class, 'recipient');
    }

    // =====================================================
    //  نوع الحساب
    // =====================================================

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** المدير العام (كل الصلاحيات) */
    public function isAdmin(): bool
    {
        return Access::isSuper($this);
    }

    /** موظف أو أدمن - يعني بيدخل لوحة الإدارة */
    public function isInternal(): bool
    {
        return in_array($this->role, ['staff', 'admin'], true);
    }

    // =====================================================
    //  الصلاحيات التفصيلية (Spatie) — المرجع: App\Support\Access
    // =====================================================

    public function canDo(string $permission): bool
    {
        return Access::can($permission, $this);
    }

    public function canSeeVehicleCost(): bool
    {
        return $this->canDo('vehicles.cost');     // سعر الشراء وهامش الربح
    }

    public function canManageVehicles(): bool
    {
        return $this->canDo('vehicles.edit');
    }

    public function canManageContracts(): bool
    {
        return $this->canDo('sales.edit');
    }

    public function canManageSettings(): bool
    {
        return $this->canDo('settings.edit');
    }

    public function canSeeAllBranches(): bool
    {
        return Access::branchIds($this) === null;
    }

    // =====================================================
    //  التوجيه بعد تسجيل الدخول
    // =====================================================

    /** كل دور بيروح للوحته */
    public function homeRoute(): string
    {
        if ($this->isInternal() && Route::has('admin.dashboard')) {
            return route('admin.dashboard');
        }

        if ($this->isCustomer() && Route::has('customer.dashboard')) {
            return route('customer.dashboard');
        }

        return Route::has('storefront.home') ? route('storefront.home') : '/';
    }

    // =====================================================
    //  Scopes
    // =====================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    public function scopeInternal($query)
    {
        return $query->whereIn('role', ['staff', 'admin']);
    }

    public function scopeForBranch($query, ?int $branchId)
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }
}
