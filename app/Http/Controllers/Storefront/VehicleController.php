<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Support\Lang;
use App\Support\Showroom;
use App\Support\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public const SORTS = [
        ''           => 'الأحدث',
        'price_asc'  => 'الأرخص أولاً',
        'price_desc' => 'الأغلى أولاً',
        'year_desc'  => 'الأحدث صناعة',
        'mileage'    => 'الأقل كيلومترات',
        'popular'    => 'الأكثر مشاهدة',
    ];

    private const FILTERS = ['q', 'brand', 'model', 'body', 'fuel', 'color', 'branch', 'year_from', 'year_to', 'price_from', 'price_to', 'monthly_max', 'offers'];

    public function index(Request $request): View
    {
        Site::captureUtm($request);

        $f = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $request->only(self::FILTERS));
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : '';
        $price = Showroom::EFFECTIVE_PRICE;

        $q = Showroom::query()->select('vehicles.*');

        if (! empty($f['q'])) {
            $term = '%'.$f['q'].'%';
            $q->where(fn ($w) => $w->where('vehicles.color', 'like', $term)->orWhere('vehicles.vehicle_code', 'like', $term)
                ->orWhere('vehicles.manufacturing_year', 'like', $term)
                ->orWhereHas('brand', fn ($b) => $b->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term))
                ->orWhereHas('model', fn ($m) => $m->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term)));
        }
        foreach (['brand' => 'brand_id', 'model' => 'model_id', 'branch' => 'branch_id'] as $key => $col) {
            if (! empty($f[$key])) {
                $q->where('vehicles.'.$col, (int) $f[$key]);
            }
        }
        if (! empty($f['color'])) {
            $q->where('vehicles.color', $f['color']);
        }
        if (! empty($f['body']) || ! empty($f['fuel'])) {
            $q->join('car_models', 'car_models.id', '=', 'vehicles.model_id');
            ! empty($f['body']) && $q->where('car_models.body_type', $f['body']);
            ! empty($f['fuel']) && $q->where('car_models.fuel_type', $f['fuel']);
        }
        if (! empty($f['year_from'])) {
            $q->where('vehicles.manufacturing_year', '>=', (int) $f['year_from']);
        }
        if (! empty($f['year_to'])) {
            $q->where('vehicles.manufacturing_year', '<=', (int) $f['year_to']);
        }
        if (! empty($f['price_from'])) {
            $q->whereRaw("($price) >= ?", [(float) $f['price_from']]);
        }
        if (! empty($f['price_to'])) {
            $q->whereRaw("($price) <= ?", [(float) $f['price_to']]);
        }
        if (! empty($f['monthly_max'])) {
            $q->whereRaw("($price) <= ?", [Showroom::priceForMonthly((float) $f['monthly_max'])])->whereRaw("($price) > 0");
        }
        if (! empty($f['offers'])) {
            Showroom::onOffer($q);
        }

        match ($sort) {
            'price_asc'  => $q->orderByRaw("($price) = 0, ($price) asc"),
            'price_desc' => $q->orderByRaw("($price) desc"),
            'year_desc'  => $q->orderByDesc('vehicles.manufacturing_year'),
            'mileage'    => $q->orderBy('vehicles.mileage'),
            'popular'    => $q->orderByDesc('vehicles.views_count'),
            default      => $q->orderByDesc('vehicles.is_featured')->orderByDesc('vehicles.id'),
        };

        $vehicles = $q->paginate(12)->withQueryString();
        $vehicles->setCollection($vehicles->getCollection()->map(fn ($v) => Showroom::card($v)));

        $base = Showroom::query()->toBase();

        return view('storefront.vehicles.index', [
            'vehicles' => $vehicles,
            'filters'  => $f,
            'sort'     => $sort,
            'brands'   => DB::table('car_brands')->whereIn('id', (clone $base)->select('vehicles.brand_id'))->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']),
            'models'   => ! empty($f['brand'])
                ? DB::table('car_models')->where('brand_id', (int) $f['brand'])->whereIn('id', (clone $base)->select('vehicles.model_id'))->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])
                : collect(),
            'colors'   => (clone $base)->whereNotNull('vehicles.color')->distinct()->orderBy('vehicles.color')->pluck('vehicles.color'),
            'branches' => DB::table('branches')->whereIn('id', (clone $base)->select('vehicles.branch_id'))->orderBy('id')->get(),
            'bodies'   => Showroom::bodyTypes(),
            'fuels'    => (clone $base)->join('car_models', 'car_models.id', '=', 'vehicles.model_id')->whereNotNull('car_models.fuel_type')->distinct()->pluck('car_models.fuel_type'),
            'title'    => ! empty($f['offers']) ? __('العروض والخصومات') : __('السيارات المتاحة'),
        ]);
    }

    public function offers(Request $request)
    {
        return redirect()->to(lroute('storefront.vehicles.index', array_merge($request->query(), ['offers' => 1])));
    }

    public function show(Request $request, Vehicle $vehicle): View
    {
        abort_unless($vehicle->is_published && in_array($vehicle->status, ['available', 'reserved'], true), 404);
        Site::captureUtm($request);

        // مشاهدة واحدة لكل زائر في الجلسة
        $seen = $request->session()->get('seen_cars', []);
        if (! in_array($vehicle->id, $seen, true)) {
            DB::table('vehicles')->where('id', $vehicle->id)->increment('views_count');
            $request->session()->put('seen_cars', array_slice(array_merge($seen, [$vehicle->id]), -100));
        }

        $vehicle->loadMissing(['brand', 'model', 'branch']);
        $card = Showroom::card($vehicle);

        $similar = Showroom::query()->where('vehicles.id', '!=', $vehicle->id)
            ->where(fn ($w) => $w->where('vehicles.model_id', $vehicle->model_id)->orWhere('vehicles.brand_id', $vehicle->brand_id)
                ->orWhereRaw('('.Showroom::EFFECTIVE_PRICE.') BETWEEN ? AND ?', [$card->price * 0.8, $card->price * 1.2]))
            ->orderByRaw('vehicles.model_id = ? desc, vehicles.brand_id = ? desc', [$vehicle->model_id, $vehicle->brand_id])
            ->limit(4)->get()->map(fn ($v) => Showroom::card($v));

        $specs = array_filter([
            __('الماركة')     => Lang::pick($vehicle->brand, 'name'),
            __('الموديل')     => Lang::pick($vehicle->model, 'name'),
            __('سنة الصنع')   => $vehicle->manufacturing_year,
            __('اللون')       => $vehicle->color ? __(trim($vehicle->color)) : null,
            __('الكيلومترات') => $vehicle->mileage !== null ? number_format((int) $vehicle->mileage).' '.__('كم') : null,
            __('نوع الهيكل')  => $vehicle->model?->body_type ? Showroom::bodyLabel($vehicle->model->body_type) : null,
            __('الوقود')      => $vehicle->model?->fuel_type ? Showroom::fuelLabel($vehicle->model->fuel_type) : null,
            __('السلندرات')   => $vehicle->cylinders ?? null,
            __('كود العربية') => $vehicle->vehicle_code,
        ], fn ($v) => $v !== null && $v !== '');
        $extra = $vehicle->specifications;
        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }
        foreach ((array) $extra as $k => $v) {
            if ($v !== null && $v !== '' && ! is_array($v)) {
                $specs[__($k)] = is_bool($v) ? ($v ? __('نعم') : __('لا')) : $v;
            }
        }

        return view('storefront.vehicles.show', [
            'vehicle' => $vehicle,
            'car'     => $card,
            'specs'   => $specs,
            'similar' => $similar,
        ]);
    }

    /** مقارنة لحد 3 عربيات: ?ids=1,2,3 */
    public function compare(Request $request): View
    {
        Site::captureUtm($request);
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))), 0, 3);

        $cars = $ids ? Showroom::query()->whereIn('vehicles.id', $ids)->get()
            ->map(fn ($v) => [Showroom::card($v), $v])->values() : collect();

        return view('storefront.compare', ['cars' => $cars]);
    }

    /** المفضلة محفوظة في المتصفح: ?ids=1,2,3 */
    public function favorites(Request $request): View
    {
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))), 0, 40);

        return view('storefront.favorites', [
            'cars' => $ids ? Showroom::query()->whereIn('vehicles.id', $ids)->get()->map(fn ($v) => Showroom::card($v)) : collect(),
            'hasIds' => (bool) $ids,
        ]);
    }
}
