<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Showroom;
use App\Support\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        Site::captureUtm($request);

        $cards = fn ($q) => $q->get()->map(fn ($v) => Showroom::card($v));

        $total = Showroom::query()->count();

        return view('storefront.home', [
            'banners'      => self::blocks('banner', true),
            'features'     => Site::on('show_features') ? self::blocks('feature') : collect(),
            'testimonials' => Site::on('show_testimonials') ? self::blocks('testimonial') : collect(),
            'faqs'         => Site::on('show_faq') ? self::blocks('faq') : collect(),
            'offers'       => Site::on('show_offers') ? $cards(Showroom::onOffer(Showroom::query())->orderByRaw('vehicles.offer_ends_at IS NULL, vehicles.offer_ends_at')->limit(8)) : collect(),
            'featured'     => Site::on('show_featured') ? $cards(Showroom::query()->where('vehicles.is_featured', true)->latest('vehicles.id')->limit(6)) : collect(),
            'latest'       => Site::on('show_latest') ? $cards(Showroom::query()->latest('vehicles.id')->limit(8)) : collect(),
            'brands'       => self::brands(),
            'bodyTypes'    => Site::on('show_body_types') ? Showroom::bodyTypes() : collect(),
            'totalCars'    => $total,
            'stats'        => [
                'cars'      => $total,
                'sold'      => DB::table('vehicles')->where('status', 'sold')->count(),
                'branches'  => DB::table('branches')->count(),
                'customers' => DB::table('customers')->count(),
            ],
            'posts'        => Site::on('show_blog') ? BlogController::published()->orderByDesc('is_featured')->orderByDesc('published_at')->limit(3)
                ->get(['slug', 'title_ar', 'title_en', 'excerpt_ar', 'excerpt_en', 'cover_image', 'cover_alt', 'published_at', 'reading_minutes']) : collect(),
            'priceRange'   => Showroom::query()->toBase()->selectRaw('MIN('.Showroom::EFFECTIVE_PRICE.') as mn, MAX('.Showroom::EFFECTIVE_PRICE.') as mx')->first(),
        ]);
    }

    /** بلوكات المحتوى النشطة (البانرات بتحترم تاريخ البداية والنهاية) */
    public static function blocks(string $type, bool $dated = false)
    {
        $today = today()->toDateString();

        return DB::table('site_blocks')->where('type', $type)->where('is_active', true)
            ->when($dated, fn ($q) => $q->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
                ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $today)))
            ->orderBy('sort')->orderBy('id')->get();
    }

    public static function brands()
    {
        if (! Site::on('show_brands')) {
            return collect();
        }

        return DB::table('car_brands')
            ->join('vehicles', 'vehicles.brand_id', '=', 'car_brands.id')
            ->where('vehicles.is_published', true)->whereIn('vehicles.status', ['available', 'reserved'])->whereNull('vehicles.deleted_at')
            ->groupBy('car_brands.id', 'car_brands.name_ar', 'car_brands.name_en', 'car_brands.logo')
            ->select('car_brands.id', 'car_brands.name_ar', 'car_brands.name_en', 'car_brands.logo', DB::raw('count(vehicles.id) as vehicles_count'))
            ->orderByDesc('vehicles_count')->get();
    }
}
