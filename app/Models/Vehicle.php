<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_code', 'supplier_id', 'branch_id', 'brand_id', 'model_id', 'import_source_id',
        'plate_number', 'color', 'manufacturing_year', 'chasis_number', 'engine_number',
        'mileage', 'purchase_price', 'tax_percent', 'tax_amount', 'total_cost',
        'description_ar', 'description_en', 'images', 'status', 'notes', 'condition_report',
        'user_id',
        'selling_price', 'discount_price', 'price_on_request',
        'is_published', 'is_featured', 'views_count', 'slug',
        'specifications', 'meta_title', 'meta_description',
    ];

    protected $casts = [
        'selling_price' => 'decimal:3',
        'discount_price' => 'decimal:3',
        'price_on_request' => 'boolean',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'specifications' => 'array',
        'images' => 'array',
        'purchase_price' => 'decimal:3',
        'tax_percent' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'total_cost' => 'decimal:3',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function brand()
    {
        return $this->belongsTo(CarBrand::class, 'brand_id');
    }

    public function model()
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    public function importSource()
    {
        return $this->belongsTo(ImportSource::class, 'import_source_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function testDrives()
    {
        return $this->hasMany(TestDrive::class, 'vehicle_id');
    }

    public function dealerContracts()
    {
        return $this->hasMany(DealerContract::class, 'vehicle_id');
    }

    public function salesContracts()
    {
        return $this->hasMany(SalesContract::class, 'vehicle_id');
    }

    public function deliveryContracts()
    {
        return $this->hasMany(DeliveryContract::class, 'vehicle_id');
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }


    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeSold($query)
    {
        return $query->where('status', 'sold');
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /** الربح = سعر البيع - التكلفة (لو اتباعت) */
    public function getProfitAttribute(): ?float
    {
        $contract = $this->salesContracts()->latest()->first();

        return $contract
            ? round((float) $contract->sale_price - (float) $this->total_cost, 3)
            : null;
    }

    public function getFullNameAttribute(): string
    {
        return trim(sprintf(
            '%s %s %s',
            $this->brand?->name_ar ?? '',
            $this->model?->name_ar ?? '',
            $this->manufacturing_year ?? ''
        ));
    }

    // =====================================================
    //  المتجر الإلكتروني
    // =====================================================

    /** رابط تلقائي وقت الإنشاء: nissan-altima-2023-a3f1 */
    protected static function booted(): void
    {
        static::creating(function (self $v) {
            $v->slug ??= \Illuminate\Support\Str::slug(
                trim(sprintf('%s %s %s %s',
                    optional(\App\Models\CarBrand::find($v->brand_id))->name_en
                        ?: optional(\App\Models\CarBrand::find($v->brand_id))->name_ar,
                    optional(\App\Models\CarModel::find($v->model_id))->name_en
                        ?: optional(\App\Models\CarModel::find($v->model_id))->name_ar,
                    $v->manufacturing_year,
                    substr(bin2hex(random_bytes(2)), 0, 4)
                )),
                '-'
            );
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function inquiries()
    {
        return $this->hasMany(VehicleInquiry::class, 'vehicle_id');
    }

    /** العربيات اللي بتظهر في الكتالوج العام */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->whereIn('status', ['available', 'reserved']);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /** فلاتر الكتالوج - كلها اختيارية */
    public function scopeFilter($query, array $f)
    {
        return $query
            ->when($f['brand'] ?? null,  fn ($q, $v) => $q->where('brand_id', $v))
            ->when($f['model'] ?? null,  fn ($q, $v) => $q->where('model_id', $v))
            ->when($f['color'] ?? null,  fn ($q, $v) => $q->where('color', $v))
            ->when($f['year_from'] ?? null, fn ($q, $v) => $q->where('manufacturing_year', '>=', $v))
            ->when($f['year_to'] ?? null,   fn ($q, $v) => $q->where('manufacturing_year', '<=', $v))
            ->when($f['price_from'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('discount_price', '>=', $v)
                  ->orWhere(fn ($x) => $x->whereNull('discount_price')->where('selling_price', '>=', $v));
            }))
            ->when($f['price_to'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('discount_price', '<=', $v)
                  ->orWhere(fn ($x) => $x->whereNull('discount_price')->where('selling_price', '<=', $v));
            }))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('description_ar', 'like', "%$v%")
                  ->orWhere('color', 'like', "%$v%")
                  ->orWhere('vehicle_code', 'like', "%$v%")
                  ->orWhereHas('brand', fn ($b) => $b->where('name_ar', 'like', "%$v%")
                                                     ->orWhere('name_en', 'like', "%$v%"))
                  ->orWhereHas('model', fn ($m) => $m->where('name_ar', 'like', "%$v%")
                                                     ->orWhere('name_en', 'like', "%$v%"));
            }));
    }

    public function scopeSortBy($query, ?string $sort)
    {
        return match ($sort) {
            'price_asc'  => $query->orderByRaw('COALESCE(discount_price, selling_price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(discount_price, selling_price) DESC'),
            'year_desc'  => $query->orderByDesc('manufacturing_year'),
            'mileage'    => $query->orderBy('mileage'),
            default      => $query->latest('id'),
        };
    }

    // ----- أسعار -----

    /** السعر اللي بيتعرض فعلاً (الخصم لو موجود) */
    public function getCurrentPriceAttribute(): ?float
    {
        if ($this->price_on_request) {
            return null;
        }

        return (float) ($this->discount_price ?: $this->selling_price) ?: null;
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_price && $this->selling_price
            && (float) $this->discount_price < (float) $this->selling_price;
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (! $this->has_discount) {
            return null;
        }

        return (int) round(
            (1 - ((float) $this->discount_price / (float) $this->selling_price)) * 100
        );
    }

    public function formattedPrice(): string
    {
        if ($this->price_on_request || ! $this->current_price) {
            return 'اتصل للسعر';
        }

        return number_format($this->current_price, 0) . ' ر.ع';
    }

    // ----- صور -----

    public function getImageListAttribute(): array
    {
        $imgs = $this->images ?? [];

        return array_values(array_filter(is_array($imgs) ? $imgs : []));
    }

    public function getMainImageAttribute(): ?string
    {
        return $this->image_list[0] ?? null;
    }

    public function incrementViews(): void
    {
        $this->newQuery()->whereKey($this->getKey())->increment('views_count');
    }
}
