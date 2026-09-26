<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\ImportSource;
use App\Models\Vehicle;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * بيانات الكتالوج: الماركات، الطرازات، جهات الاستيراد.
 * كلها صفحة واحدة لكل نوع + مودال للإضافة والتعديل.
 */
class CatalogController extends Controller
{
    public const BODY_TYPES = [
        'sedan' => 'سيدان', 'suv' => 'SUV', 'pickup' => 'بيك أب', 'hatchback' => 'هاتشباك',
        'coupe' => 'كوبيه', 'van' => 'فان', 'truck' => 'شاحنة', 'convertible' => 'مكشوفة',
    ];

    public const FUEL_TYPES = [
        'petrol' => 'بنزين', 'diesel' => 'ديزل', 'hybrid' => 'هايبرد', 'electric' => 'كهرباء',
    ];

    private const MESSAGES = [
        'required' => 'الحقل ده مطلوب.',
        'unique'   => 'الاسم ده موجود قبل كده.',
        'exists'   => 'الاختيار مش صحيح.',
        'image'    => 'لازم تكون صورة.',
        'mimes'    => 'الصورة لازم تكون JPG أو PNG أو WEBP.',
        'max'      => 'القيمة أكبر من المسموح.',
        'min'      => 'القيمة أقل من المسموح.',
        'integer'  => 'لازم يكون رقم صحيح.',
        'in'       => 'الاختيار مش صحيح.',
    ];

    // =========================================================
    //  الماركات
    // =========================================================
    public function brands(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $brands = CarBrand::query()
            ->addSelect([
                'models_count'   => CarModel::selectRaw('count(*)')->whereColumn('car_models.brand_id', 'car_brands.id'),
                'vehicles_count' => Vehicle::selectRaw('count(*)')->whereColumn('vehicles.brand_id', 'car_brands.id'),
                'available_count'=> Vehicle::selectRaw('count(*)')->whereColumn('vehicles.brand_id', 'car_brands.id')->where('vehicles.status', 'available'),
            ])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderBy('name_ar')
            ->paginate(24)
            ->withQueryString();

        return view('admin.catalog.brands', compact('brands', 'search'));
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        $brand = new CarBrand;
        $this->saveBrand($request, $brand);

        return back()->with('status', 'تم إضافة الماركة '.$brand->name_ar);
    }

    public function updateBrand(Request $request, CarBrand $brand): RedirectResponse
    {
        $this->saveBrand($request, $brand);

        return back()->with('status', 'تم حفظ الماركة '.$brand->name_ar);
    }

    public function destroyBrand(CarBrand $brand): RedirectResponse
    {
        if (Vehicle::where('brand_id', $brand->id)->exists() || CarModel::where('brand_id', $brand->id)->exists()) {
            return back()->with('error', 'الماركة عليها طرازات أو عربيات. اقفلها (غير مفعّلة) بدل المسح.');
        }

        Media::delete($brand->logo);
        $brand->delete();

        return back()->with('status', 'تم مسح الماركة');
    }

    private function saveBrand(Request $request, CarBrand $brand): void
    {
        $data = $request->validate([
            'name_ar'        => ['required', 'string', 'max:100', Rule::unique('car_brands', 'name_ar')->ignore($brand->id)],
            'name_en'        => ['nullable', 'string', 'max:100'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'logo'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo'    => ['nullable', 'boolean'],
            'is_active'      => ['nullable', 'boolean'],
        ], self::MESSAGES);

        $attrs = [
            'name_ar'        => $data['name_ar'],
            'name_en'        => $data['name_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'is_active'      => $request->boolean('is_active'),
        ];

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            Media::delete($brand->logo);
            $attrs['logo'] = $request->hasFile('logo') ? Media::store($request->file('logo'), 'brands') : null;
        }

        $brand->forceFill($attrs)->save();
    }

    // =========================================================
    //  الطرازات
    // =========================================================
    public function models(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $brandId = $request->integer('brand') ?: null;

        $models = CarModel::query()
            ->leftJoin('car_brands', 'car_brands.id', '=', 'car_models.brand_id')
            ->select('car_models.*', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), 'car_brands.logo as brand_logo')
            ->addSelect([
                'vehicles_count'  => Vehicle::selectRaw('count(*)')->whereColumn('vehicles.model_id', 'car_models.id'),
                'available_count' => Vehicle::selectRaw('count(*)')->whereColumn('vehicles.model_id', 'car_models.id')->where('vehicles.status', 'available'),
            ])
            ->when($brandId, fn ($q, $v) => $q->where('car_models.brand_id', $v))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('car_models.name_ar', 'like', "%{$search}%")
                ->orWhere('car_models.name_en', 'like', "%{$search}%")
                ->orWhere('car_brands.name_ar', 'like', "%{$search}%")))
            ->orderBy('car_brands.name_ar')
            ->orderBy('car_models.name_ar')
            ->paginate(25)
            ->withQueryString();

        return view('admin.catalog.models', [
            'models'     => $models,
            'brands'     => CarBrand::orderBy('name_ar')->pluckLocal('name', 'id'),
            'bodyTypes'  => self::BODY_TYPES,
            'fuelTypes'  => self::FUEL_TYPES,
            'filters'    => ['q' => $search, 'brand' => $brandId],
        ]);
    }

    public function storeModel(Request $request): RedirectResponse
    {
        $model = new CarModel;
        $this->saveModel($request, $model);

        return back()->with('status', 'تم إضافة الطراز '.$model->name_ar);
    }

    public function updateModel(Request $request, CarModel $carModel): RedirectResponse
    {
        $this->saveModel($request, $carModel);

        return back()->with('status', 'تم حفظ الطراز '.$carModel->name_ar);
    }

    public function destroyModel(CarModel $carModel): RedirectResponse
    {
        if (Vehicle::where('model_id', $carModel->id)->exists()) {
            return back()->with('error', 'الطراز عليه عربيات. اقفله (غير مفعّل) بدل المسح.');
        }

        $brandId = $carModel->brand_id;
        Media::delete($carModel->image);
        $carModel->delete();
        $this->refreshBrandCount($brandId);

        return back()->with('status', 'تم مسح الطراز');
    }

    private function saveModel(Request $request, CarModel $model): void
    {
        $data = $request->validate([
            'brand_id'  => ['required', Rule::exists('car_brands', 'id')],
            'name_ar'   => ['required', 'string', 'max:100',
                Rule::unique('car_models', 'name_ar')->where('brand_id', $request->input('brand_id'))->ignore($model->id)],
            'name_en'   => ['nullable', 'string', 'max:100'],
            'year'      => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'body_type' => ['nullable', Rule::in(array_keys(self::BODY_TYPES))],
            'fuel_type' => ['nullable', Rule::in(array_keys(self::FUEL_TYPES))],
            'image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], self::MESSAGES + ['name_ar.unique' => 'الطراز ده موجود بالفعل تحت نفس الماركة.']);

        $oldBrand = $model->brand_id;

        $attrs = [
            'brand_id'  => (int) $data['brand_id'],
            'name_ar'   => $data['name_ar'],
            'name_en'   => $data['name_en'] ?? null,
            'year'      => $data['year'] ?? null,
            'body_type' => $data['body_type'] ?? null,
            'fuel_type' => $data['fuel_type'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            Media::delete($model->image);
            $attrs['image'] = $request->hasFile('image') ? Media::store($request->file('image'), 'models') : null;
        }

        $model->forceFill($attrs)->save();

        $this->refreshBrandCount((int) $model->brand_id);
        if ($oldBrand && (int) $oldBrand !== (int) $model->brand_id) {
            $this->refreshBrandCount((int) $oldBrand);
        }
    }

    private function refreshBrandCount(int $brandId): void
    {
        CarBrand::whereKey($brandId)->update(['total_models' => CarModel::where('brand_id', $brandId)->count()]);
    }

    // =========================================================
    //  جهات الاستيراد
    // =========================================================
    public function sources(Request $request): View
    {
        $sources = ImportSource::query()
            ->addSelect([
                'vehicles_count' => Vehicle::selectRaw('count(*)')->whereColumn('vehicles.import_source_id', 'import_sources.id'),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name_ar')
            ->get();

        return view('admin.catalog.sources', compact('sources'));
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $source = new ImportSource;
        $this->saveSource($request, $source);

        return back()->with('status', 'تم إضافة جهة الاستيراد');
    }

    public function updateSource(Request $request, ImportSource $importSource): RedirectResponse
    {
        $this->saveSource($request, $importSource);

        return back()->with('status', 'تم حفظ التعديلات');
    }

    public function destroySource(ImportSource $importSource): RedirectResponse
    {
        if (Vehicle::where('import_source_id', $importSource->id)->exists()) {
            return back()->with('error', 'فيه عربيات مسجلة على الجهة دي. اقفلها بدل المسح.');
        }

        $importSource->delete();

        return back()->with('status', 'تم المسح');
    }

    private function saveSource(Request $request, ImportSource $source): void
    {
        $data = $request->validate([
            'name_ar'     => ['required', 'string', 'max:100', Rule::unique('import_sources', 'name_ar')->ignore($source->id)],
            'name_en'     => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ], self::MESSAGES);

        $source->forceFill([
            'name_ar'     => $data['name_ar'],
            'name_en'     => $data['name_en'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active'   => $request->boolean('is_active'),
        ])->save();
    }
}
