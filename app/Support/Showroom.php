<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * العربيات في المتجر: الاستعلامات + تجهيز بيانات الكارت (السعر الفعلي، العرض، القسط...)
 */
class Showroom
{
    public const BODY_ICONS = ['sedan' => '🚗', 'suv' => '🚙', 'pickup' => '🛻', 'hatchback' => '🚘', 'coupe' => '🏎️', 'van' => '🚐', 'truck' => '🚚', 'convertible' => '🏁'];

    public const BODY_LABELS = ['sedan' => 'سيدان', 'suv' => 'SUV / فور ويل', 'pickup' => 'بيك أب', 'hatchback' => 'هاتشباك', 'coupe' => 'كوبيه', 'van' => 'فان', 'truck' => 'نقل', 'convertible' => 'مكشوفة'];

    public const FUEL_LABELS = ['petrol' => 'بنزين', 'gasoline' => 'بنزين', 'diesel' => 'ديزل', 'hybrid' => 'هايبرد', 'electric' => 'كهرباء'];

    public static function bodyLabel(?string $v): string
    {
        return __(self::BODY_LABELS[strtolower((string) $v)] ?? (string) $v);
    }

    public static function bodyIcon(?string $v): string
    {
        return self::BODY_ICONS[strtolower((string) $v)] ?? '🚗';
    }

    public static function fuelLabel(?string $v): string
    {
        return __(self::FUEL_LABELS[strtolower((string) $v)] ?? (string) $v);
    }

    /** لون مناسب للكلام فوق اللون الأساسي (غامق على الفاتح / أبيض على الغامق) */
    public static function inkFor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#0f172a';
        }
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#0f172a' : '#ffffff';
    }

    /** السعر الفعلي: سعر العرض لو شغال ومخلصش */
    public const EFFECTIVE_PRICE = "CASE WHEN vehicles.discount_price > 0 AND vehicles.discount_price < vehicles.selling_price
        AND (vehicles.offer_ends_at IS NULL OR vehicles.offer_ends_at >= CURRENT_DATE) THEN vehicles.discount_price ELSE vehicles.selling_price END";

    public static function query()
    {
        return Vehicle::query()
            ->where('vehicles.is_published', true)
            ->whereIn('vehicles.status', ['available', 'reserved'])
            ->with(['brand', 'model', 'branch']);
    }

    public static function onOffer($q)
    {
        return $q->where('vehicles.discount_price', '>', 0)->whereColumn('vehicles.discount_price', '<', 'vehicles.selling_price')
            ->where(fn ($w) => $w->whereNull('vehicles.offer_ends_at')->orWhere('vehicles.offer_ends_at', '>=', today()->toDateString()));
    }

    /** قسط شهري تقريبي */
    public static function monthly(float $price, ?int $months = null, ?float $downPercent = null): float
    {
        $months ??= (int) Site::get('max_months', 60);
        $down = $price * (($downPercent ?? (float) Site::get('min_down_percent', 20)) / 100);
        $financed = max(0, $price - $down);
        $rate = (float) Site::get('installment_rate', 0) / 100;

        return $months > 0 ? $financed * (1 + $rate * $months / 12) / $months : 0;
    }

    /** أقصى سعر عربية ينفع بقسط شهري معيّن */
    public static function priceForMonthly(float $monthly): float
    {
        $months = (int) Site::get('max_months', 60);
        $rate = (float) Site::get('installment_rate', 0) / 100;
        $down = (float) Site::get('min_down_percent', 20) / 100;

        return $monthly * $months / (1 + $rate * $months / 12) / max(0.01, 1 - $down);
    }

    public static function card(Vehicle $v): object
    {
        $images = array_map(fn ($p) => Media::url($p), Media::list($v->images));
        $selling = (float) $v->selling_price;
        $discount = (float) $v->discount_price;
        $offerEnds = $v->offer_ends_at ? Carbon::parse($v->offer_ends_at)->endOfDay() : null;
        $onOffer = $discount > 0 && $discount < $selling && (! $offerEnds || $offerEnds->isFuture());
        $price = $onOffer ? $discount : $selling;
        $model = $v->model;

        return (object) [
            'id'        => $v->id,
            'url'       => lroute('storefront.vehicles.show', $v),
            'title'     => trim((Lang::pick($v->brand, 'name') ?? '').' '.(Lang::pick($model, 'name') ?? '')),
            'brand'     => Lang::pick($v->brand, 'name'),
            'brand_logo'=> Media::url($v->brand->logo ?? null),
            'model'     => Lang::pick($model, 'name'),
            'year'      => $v->manufacturing_year,
            'mileage'   => (int) $v->mileage,
            'color'     => $v->color ? __(trim($v->color)) : null,
            'body'      => $model->body_type ?? null,
            'fuel'      => $model->fuel_type ?? null,
            'cylinders' => $v->cylinders ?? null,
            'image'     => $images[0] ?? null,
            'images'    => $images,
            'price'     => $price,
            'old_price' => $onOffer ? $selling : null,
            'save'      => $onOffer ? $selling - $discount : 0,
            'discount_pct' => $onOffer && $selling > 0 ? (int) round(($selling - $discount) / $selling * 100) : 0,
            'offer_label'  => $onOffer ? ($v->offer_label ?: null) : null,
            'offer_ends'   => $onOffer && $offerEnds ? $offerEnds->toIso8601String() : null,
            'on_request'=> (bool) $v->price_on_request || $price <= 0,
            'reserved'  => $v->status === 'reserved',
            'is_new'    => $v->created_at && Carbon::parse($v->created_at)->gt(now()->subDays(7)),
            'featured'  => (bool) $v->is_featured,
            'views'     => (int) $v->views_count,
            'monthly'   => $price > 0 ? self::monthly($price) : 0,
            'branch'    => Lang::pick($v->branch, 'name'),
            'code'      => $v->vehicle_code,
        ];
    }

    public static function money(float $v): string
    {
        return number_format($v, 0).' '.__(Money::currency() ?: 'ر.ع');
    }

    /** أنواع الهيكل اللي عليها عربيات */
    public static function bodyTypes()
    {
        return self::query()->toBase()->join('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->whereNotNull('car_models.body_type')->groupBy('car_models.body_type')
            ->select('car_models.body_type', DB::raw('count(*) as c'))->pluck('c', 'body_type');
    }
}
