<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleRequest;
use App\Models\Branch;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\DealerContract;
use App\Models\ImportSource;
use App\Models\Payment;
use App\Models\SalesContract;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierReturn;
use App\Models\TestDrive;
use App\Models\Vehicle;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public const STATUSES = [
        'available'   => 'متاحة',
        'reserved'    => 'محجوزة',
        'sold'        => 'مباعة',
        'maintenance' => 'صيانة',
        'damaged'     => 'متضررة',
        'returned'    => 'مرتجعة للمورد',
    ];

    /** الحالات اللي الموظف يقدر يختارها بإيده - "مباعة" و"مرتجعة" بيحطهم السيستم */
    public const EDITABLE_STATUSES = ['available', 'reserved', 'maintenance', 'damaged'];

    // =========================================================
    //  القايمة
    // =========================================================
    public function index(Request $request): View
    {
        $user = $request->user();

        $f = [
            'q'         => trim((string) $request->query('q')),
            'supplier'  => $request->integer('supplier') ?: null,
            'brand'     => $request->integer('brand') ?: null,
            'model'     => $request->integer('model') ?: null,
            'source'    => $request->integer('source') ?: null,
            'branch'    => $request->integer('branch') ?: null,
            'status'    => array_key_exists((string) $request->query('status'), self::STATUSES) ? $request->query('status') : null,
            'published' => in_array($request->query('published'), ['1', '0'], true) ? $request->query('published') : null,
        ];

        $scoped = fn ($q) => $q->when(! $user->canSeeAllBranches(), fn ($w) => $w->where('vehicles.branch_id', $user->branch_id));

        $vehicles = $this->baseQuery()
            ->tap($scoped)
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('vehicles.vehicle_code', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.plate_number', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.chassis_number', 'like', "%{$f['q']}%")
                ->orWhere('car_brands.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('car_brands.name_en', 'like', "%{$f['q']}%")
                ->orWhere('car_models.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('car_models.name_en', 'like', "%{$f['q']}%")))
            ->when($f['supplier'], fn ($q, $v) => $q->where('vehicles.supplier_id', $v))
            ->when($f['brand'], fn ($q, $v) => $q->where('vehicles.brand_id', $v))
            ->when($f['model'], fn ($q, $v) => $q->where('vehicles.model_id', $v))
            ->when($f['source'], fn ($q, $v) => $q->where('vehicles.import_source_id', $v))
            ->when($f['branch'], fn ($q, $v) => $q->where('vehicles.branch_id', $v))
            ->when($f['status'], fn ($q, $v) => $q->where('vehicles.status', $v))
            ->when($f['published'] !== null, fn ($q) => $q->where('vehicles.is_published', $f['published'] === '1'))
            ->addSelect([
                'sales_contract_number'  => SalesContract::select('contract_number')
                    ->whereColumn('sales_contracts.vehicle_id', 'vehicles.id')->latest('sales_contracts.id')->limit(1),
                'dealer_contract_number' => DealerContract::select('contract_number')
                    ->whereColumn('dealer_contracts.vehicle_id', 'vehicles.id')->latest('dealer_contracts.id')->limit(1),
            ])
            ->latest('vehicles.created_at')
            ->paginate(15)
            ->withQueryString();

        $statusCounts = Vehicle::query()->tap($scoped)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.vehicles.index', [
            'vehicles'     => $vehicles,
            'filters'      => $f,
            'statusCounts' => $statusCounts,
            'statuses'     => self::STATUSES,
        ] + $this->options());
    }

    // =========================================================
    //  تفاصيل السيارة
    // =========================================================
    public function show(Request $request, Vehicle $vehicle): View
    {
        $user = $request->user();
        abort_unless($user->canSeeAllBranches() || (int) $user->branch_id === (int) $vehicle->branch_id, 403);

        $car = $this->baseQuery()
            ->where('vehicles.id', $vehicle->id)
            ->addSelect('suppliers.phone as supplier_phone', 'car_models.body_type', 'car_models.fuel_type')
            ->firstOrFail();

        $payments = function (string $morph, $ids) {
            return Payment::query()
                ->where('payable_type', $morph)
                ->whereIn('payable_id', $ids)
                ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
                ->leftJoin('users', 'users.id', '=', 'payments.user_id')
                ->select('payments.*', \App\Support\Lang::sel('payment_methods.name_ar as method_name'), 'users.name as user_name')
                ->orderBy('payment_date')
                ->get()
                ->groupBy('payable_id');
        };

        $dealerContracts = DealerContract::query()
            ->where('dealer_contracts.vehicle_id', $vehicle->id)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'dealer_contracts.supplier_id')
            ->leftJoin('contract_statuses', 'contract_statuses.id', '=', 'dealer_contracts.status_id')
            ->leftJoin('users', 'users.id', '=', 'dealer_contracts.user_id')
            ->select('dealer_contracts.*', \App\Support\Lang::sel('suppliers.name_ar as supplier_name'), 'suppliers.phone as supplier_phone',
                \App\Support\Lang::sel('contract_statuses.name_ar as status_name'), 'users.name as user_name')
            ->latest('contract_date')
            ->get();

        $salesContracts = SalesContract::query()
            ->where('sales_contracts.vehicle_id', $vehicle->id)
            ->leftJoin('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->leftJoin('contract_statuses', 'contract_statuses.id', '=', 'sales_contracts.status_id')
            ->leftJoin('users', 'users.id', '=', 'sales_contracts.user_id')
            ->select('sales_contracts.*', \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone',
                \App\Support\Lang::sel('contract_statuses.name_ar as status_name'), 'users.name as user_name')
            ->latest('contract_date')
            ->get();

        $testDrives = TestDrive::query()
            ->where('test_drives.vehicle_id', $vehicle->id)
            ->leftJoin('customers', 'customers.id', '=', 'test_drives.customer_id')
            ->select('test_drives.*', \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone')
            ->latest('start_time')
            ->get();

        $invoice = $vehicle->supplier_invoice_id ? SupplierInvoice::find($vehicle->supplier_invoice_id) : null;
        $returns = SupplierReturn::where('vehicle_id', $vehicle->id)->latest('return_date')->get();

        $transfers = DB::table('activity_logs')
            ->where('subject_type', $vehicle->getMorphClass())
            ->where('subject_id', $vehicle->id)
            ->where('action', 'vehicle_transferred')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.causer_id')
            ->select('activity_logs.*', 'users.name as user_name')
            ->latest('activity_logs.created_at')
            ->get();

        $saleContract = $salesContracts->first();
        $cost = (float) $vehicle->total_cost > 0 ? (float) $vehicle->total_cost : (float) ($dealerContracts->first()->agreed_price ?? 0);

        $finance = [
            'supplier_remaining' => (float) $dealerContracts->sum('remaining_amount') + ($invoice?->remaining() ?? 0),
            'customer_remaining' => (float) $salesContracts->sum('remaining_amount'),
            'cost'               => $cost,
            'profit'             => $saleContract && $cost > 0 ? round((float) $saleContract->sale_price - $cost, 3) : null,
            'expected_profit'    => ! $saleContract && $cost > 0 && (float) $vehicle->selling_price > 0
                ? round((float) ($vehicle->discount_price ?: $vehicle->selling_price) - $cost, 3) : null,
        ];

        return view('admin.vehicles.show', [
            'vehicle'         => $vehicle,
            'car'             => $car,
            'images'          => array_map([Media::class, 'url'], Media::list($vehicle->images)),
            'specs'           => is_array($vehicle->specifications) ? $vehicle->specifications : (json_decode((string) $vehicle->specifications, true) ?: []),
            'dealerContracts' => $dealerContracts,
            'vehiclePartners' => \App\Support\Partners::forVehicle($vehicle->id),
            'allPartners'     => \App\Support\Partners::ready() ? DB::table('partners')->whereNull('deleted_at')->where('is_active', true)->orderBy('name')->pluck('name', 'id') : collect(),
            'inspections'     => \Illuminate\Support\Facades\Schema::hasTable('vehicle_inspections')
                ? DB::table('vehicle_inspections')->where('vehicle_id', $vehicle->id)->latest('inspected_at')->latest('id')->get() : collect(),
            'dealerPayments'  => $payments((new DealerContract)->getMorphClass(), $dealerContracts->pluck('id')),
            'salesContracts'  => $salesContracts,
            'salesPayments'   => $payments((new SalesContract)->getMorphClass(), $salesContracts->pluck('id')),
            'testDrives'      => $testDrives,
            'invoice'         => $invoice,
            'returns'         => $returns,
            'transfers'       => $transfers,
            'branchNames'     => DB::table('branches')->pluckLocal('name', 'id'),
            'finance'         => $finance,
            'statuses'        => self::STATUSES,
        ]);
    }

    // =========================================================
    //  إضافة وتعديل
    // =========================================================
    public function create(Request $request): View
    {
        $vehicle = new Vehicle;
        $vehicle->forceFill([
            'vehicle_code'       => $this->nextCode(),
            'status'             => 'available',
            'branch_id'          => $request->user()->branch_id ?: Branch::main()?->id,
            'manufacturing_year' => (int) date('Y'),
            'supplier_id'        => $request->integer('supplier') ?: null,
        ]);

        return view('admin.vehicles.form', ['vehicle' => $vehicle] + $this->options(true));
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        $vehicle = new Vehicle;
        $this->fill($vehicle, $request);
        $vehicle->save();

        return redirect()->route('admin.vehicles.show', $vehicle)->with('status', 'تم إضافة المركبة '.$vehicle->vehicle_code);
    }

    public function edit(Vehicle $vehicle): View
    {
        return view('admin.vehicles.form', ['vehicle' => $vehicle] + $this->options(true));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->fill($vehicle, $request);
        $vehicle->save();

        return redirect()->route('admin.vehicles.show', $vehicle)->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $linked = SalesContract::where('vehicle_id', $vehicle->id)->exists()
            || DealerContract::where('vehicle_id', $vehicle->id)->exists()
            || TestDrive::where('vehicle_id', $vehicle->id)->exists()
            || SupplierReturn::where('vehicle_id', $vehicle->id)->exists();

        if ($linked) {
            return back()->with('error', 'العربية عليها عقود أو تجارب قيادة، مينفعش تتمسح. غيّر حالتها بدل المسح.');
        }

        $vehicle->delete();

        return redirect()->route('admin.vehicles.index')->with('status', 'تم مسح المركبة');
    }

    /** نشر / إخفاء من المتجر بضغطة */
    public function togglePublish(Vehicle $vehicle): RedirectResponse
    {
        if (! $vehicle->is_published) {
            if (in_array($vehicle->status, ['sold', 'returned'], true)) {
                return back()->with('error', 'مينفعش تنشر عربية مباعة أو مرتجعة.');
            }
            if (! $vehicle->price_on_request && (float) $vehicle->selling_price <= 0) {
                return back()->with('error', 'حط سعر البيع الأول عشان تنشر العربية.');
            }
        }

        $vehicle->forceFill(['is_published' => ! $vehicle->is_published])->save();

        return back()->with('status', $vehicle->is_published ? 'العربية ظاهرة في المتجر دلوقتي' : 'العربية اتشالت من المتجر');
    }

    // =========================================================
    //  أدوات داخلية
    // =========================================================
    private function fill(Vehicle $vehicle, VehicleRequest $request): void
    {
        $data = $request->validated();
        $user = $request->user();

        $attrs = Arr::except($data, ['images', 'remove_images', 'cover_image', 'specs', 'purchase_price', 'tax_percent',
            'license_front', 'license_back', 'remove_license', 'before_images', 'after_images', 'remove_condition']);
        $attrs['mileage'] = (int) ($data['mileage'] ?? 0);

        if (! in_array($data['status'], self::EDITABLE_STATUSES, true) && $data['status'] !== $vehicle->status) {
            $attrs['status'] = $vehicle->status ?: 'available';
        }

        // التكلفة للأدمن بس
        if ($user->canSeeVehicleCost()) {
            $price = round((float) ($data['purchase_price'] ?? 0), 3);
            $taxPercent = (float) ($data['tax_percent'] ?? 0);
            $tax = round($price * $taxPercent / 100, 3);
            $attrs += ['purchase_price' => $price, 'tax_percent' => $taxPercent, 'tax_amount' => $tax, 'total_cost' => round($price + $tax, 3)];
        } elseif (! $vehicle->exists) {
            $attrs += ['purchase_price' => 0, 'tax_percent' => 0, 'tax_amount' => 0, 'total_cost' => 0];
        }

        // المواصفات
        $specs = array_filter($data['specs'] ?? [], fn ($v) => $v !== null && $v !== '');
        $attrs['specifications'] = $vehicle->hasCast('specifications') ? $specs : json_encode($specs, JSON_UNESCAPED_UNICODE);

        // الصور: شيل المحذوف، ضيف الجديد، وحط الغلاف الأول
        $images = Media::list($vehicle->images);
        $remove = array_intersect($images, $data['remove_images'] ?? []);
        foreach ($remove as $path) {
            Media::delete($path);
        }
        $images = array_values(array_diff($images, $remove));
        foreach ($request->file('images', []) as $file) {
            $images[] = Media::store($file, 'vehicles');
        }
        if (! empty($data['cover_image']) && in_array($data['cover_image'], $images, true)) {
            $images = array_values(array_unique(array_merge([$data['cover_image']], $images)));
        }
        $attrs['images'] = $vehicle->hasCast('images') ? $images : json_encode($images, JSON_UNESCAPED_UNICODE);

        // الرخصة (أمام / خلف) وصور حالة العربية قبل وبعد — بعد php artisan migrate
        if (\Illuminate\Support\Facades\Schema::hasColumn('vehicles', 'license_front')) {
            foreach (['front', 'back'] as $side) {
                $col = 'license_'.$side;
                if ($request->hasFile($col) || in_array($side, $data['remove_license'] ?? [], true)) {
                    Media::delete($vehicle->{$col});
                    $attrs[$col] = $request->hasFile($col) ? Media::store($request->file($col), 'vehicles/licenses') : null;
                }
            }
            foreach (['before_images', 'after_images'] as $col) {
                $list = Media::list($vehicle->{$col});
                $drop = array_intersect($list, $data['remove_condition'] ?? []);
                foreach ($drop as $path) {
                    Media::delete($path);
                }
                $list = array_values(array_diff($list, $drop));
                foreach ($request->file($col, []) as $file) {
                    if (count($list) < 5) {
                        $list[] = Media::store($file, 'vehicles/condition');
                    }
                }
                $attrs[$col] = json_encode($list, JSON_UNESCAPED_UNICODE);
            }
        }

        if (in_array($attrs['status'] ?? $vehicle->status, ['sold', 'returned'], true)) {
            $attrs['is_published'] = false;
        }

        if (! $vehicle->exists) {
            $attrs['vehicle_code'] = ($attrs['vehicle_code'] ?? null) ?: $this->nextCode();
            $attrs['user_id'] = $user->id;
        } else {
            $attrs['vehicle_code'] = ($attrs['vehicle_code'] ?? null) ?: $vehicle->vehicle_code;
        }

        $vehicle->forceFill($attrs);

        if (! $vehicle->slug) {
            $brand = CarBrand::find($vehicle->brand_id);
            $model = CarModel::find($vehicle->model_id);
            $vehicle->slug = Str::slug(trim(($brand?->name_en ?: $brand?->name_ar).' '.($model?->name_en ?: $model?->name_ar).' '.$vehicle->manufacturing_year))
                .'-'.Str::lower(Str::random(4));
        }
    }

    private function baseQuery()
    {
        return Vehicle::query()
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'vehicles.supplier_id')
            ->leftJoin('import_sources', 'import_sources.id', '=', 'vehicles.import_source_id')
            ->leftJoin('branches', 'branches.id', '=', 'vehicles.branch_id')
            ->select('vehicles.*',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), 'car_brands.name_en as brand_name_en', 'car_brands.logo as brand_logo',
                \App\Support\Lang::sel('car_models.name_ar as model_name'), \App\Support\Lang::sel('suppliers.name_ar as supplier_name'),
                \App\Support\Lang::sel('import_sources.name_ar as source_name'), \App\Support\Lang::sel('branches.name_ar as branch_name'));
    }

    private function options(bool $activeOnly = false): array
    {
        return [
            'brands'    => CarBrand::when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name_ar')->pluckLocal('name', 'id'),
            'models'    => CarModel::when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name_ar')->get(['id', 'brand_id', 'name_ar', 'year']),
            'suppliers' => Supplier::when($activeOnly, fn ($q) => $q->where('status', 'active'))->orderBy('name_ar')->pluckLocal('name', 'id'),
            'sources'   => ImportSource::when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name_ar')->pluckLocal('name', 'id'),
            'branches'  => Branch::where('status', 'active')->orderByDesc('is_main')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'statuses'  => self::STATUSES,
        ];
    }

    private function nextCode(): string
    {
        $next = (int) DB::table('vehicles')->max('id') + 1;

        return 'VH-'.str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
