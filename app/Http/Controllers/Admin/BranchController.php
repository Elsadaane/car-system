<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\CarBrand;
use App\Models\SalesContract;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public const VEHICLE_STATUSES = [
        'available'   => 'متاحة',
        'reserved'    => 'محجوزة',
        'sold'        => 'مباعة',
        'maintenance' => 'صيانة',
        'damaged'     => 'متضررة',
        'returned'    => 'مرتجعة للمورد',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $monthStart = now()->startOfMonth()->toDateString();
        $search = trim((string) $request->query('q'));

        $branches = Branch::query()
            ->addSelect([
                'vehicles_count'  => $this->vehicleCount(),
                'available_count' => $this->vehicleCount('available'),
                'reserved_count'  => $this->vehicleCount('reserved'),
                'sold_count'      => $this->vehicleCount('sold'),
                'staff_count'     => User::selectRaw('count(*)')
                    ->whereColumn('users.branch_id', 'branches.id')
                    ->whereIn('users.role', ['staff', 'admin']),
                'month_sales'     => SalesContract::selectRaw('coalesce(sum(total_amount), 0)')
                    ->whereColumn('sales_contracts.branch_id', 'branches.id')
                    ->where('contract_date', '>=', $monthStart),
                'manager_name'    => User::select('name')
                    ->whereColumn('users.id', 'branches.manager_id')
                    ->limit(1),
            ])
            ->when(! $user->canSeeAllBranches(), fn ($q) => $q->whereKey($user->branch_id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('branch_code', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            ->when(in_array($request->query('status'), ['active', 'inactive'], true),
                fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('is_main')
            ->orderBy('name_ar')
            ->get();

        $totals = [
            'branches'    => $branches->count(),
            'active'      => $branches->where('status', 'active')->count(),
            'vehicles'    => $branches->sum('vehicles_count'),
            'month_sales' => $branches->sum('month_sales'),
        ];

        $canManage = $user->canManageSettings();

        return view('admin.branches.index', [
            'branches'  => $branches,
            'totals'    => $totals,
            'search'    => $search,
            'managers'  => $canManage ? $this->managers() : collect(),
            'newBranch' => new Branch(['branch_code' => $this->nextCode(), 'status' => 'active']),
        ]);
    }

    public function show(Request $request, Branch $branch): View
    {
        $user = $request->user();
        abort_unless($user->canSeeAllBranches() || (int) $user->branch_id === (int) $branch->id, 403);

        $monthStart = now()->startOfMonth()->toDateString();
        $branchVehicles = fn () => Vehicle::where('branch_id', $branch->id);

        $statusCounts = $branchVehicles()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'vehicles'          => (int) $statusCounts->sum(),
            'available'         => (int) ($statusCounts['available'] ?? 0),
            'reserved'          => (int) ($statusCounts['reserved'] ?? 0),
            'sold'              => (int) ($statusCounts['sold'] ?? 0),
            'stock_value'       => (float) $branchVehicles()->where('status', 'available')
                ->sum(DB::raw('coalesce(discount_price, selling_price, 0)')),
            'stock_cost'        => $user->canSeeVehicleCost()
                ? (float) $branchVehicles()->whereIn('status', ['available', 'reserved'])->sum('total_cost')
                : null,
            'month_sales_count' => SalesContract::where('branch_id', $branch->id)
                ->where('contract_date', '>=', $monthStart)->count(),
            'month_sales_total' => (float) SalesContract::where('branch_id', $branch->id)
                ->where('contract_date', '>=', $monthStart)->sum('total_amount'),
            'new_inquiries'     => class_exists(\App\Models\VehicleInquiry::class)
                ? \App\Models\VehicleInquiry::where('branch_id', $branch->id)->where('status', 'new')->count()
                : null,
        ];

        $search = trim((string) $request->query('q'));
        $status = array_key_exists($request->query('status'), self::VEHICLE_STATUSES) ? $request->query('status') : null;
        $brandId = $request->integer('brand') ?: null;

        $vehicles = Vehicle::query()
            ->where('vehicles.branch_id', $branch->id)
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->select('vehicles.*', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->when($status, fn ($q) => $q->where('vehicles.status', $status))
            ->when($brandId, fn ($q) => $q->where('vehicles.brand_id', $brandId))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('vehicles.vehicle_code', 'like', "%{$search}%")
                ->orWhere('vehicles.plate_number', 'like', "%{$search}%")
                ->orWhere('vehicles.chassis_number', 'like', "%{$search}%")
                ->orWhere('car_brands.name_ar', 'like', "%{$search}%")
                ->orWhere('car_models.name_ar', 'like', "%{$search}%")))
            ->latest('vehicles.created_at')
            ->paginate(12)
            ->withQueryString();

        $brands = CarBrand::whereIn('id', $branchVehicles()->select('brand_id'))
            ->orderBy('name_ar')
            ->pluckLocal('name', 'id');

        $staff = User::internal()
            ->where('branch_id', $branch->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'phone', 'is_active', 'last_login_at']);

        $latestSales = SalesContract::query()
            ->where('sales_contracts.branch_id', $branch->id)
            ->leftJoin('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->select('sales_contracts.*', \App\Support\Lang::sel('customers.name_ar as customer_name'))
            ->latest('contract_date')
            ->limit(5)
            ->get();

        $manager = $branch->manager_id ? User::find($branch->manager_id, ['id', 'name', 'phone']) : null;

        $otherBranches = $user->canManageSettings()
            ? Branch::where('status', 'active')->whereKeyNot($branch->id)->orderBy('name_ar')->pluckLocal('name', 'id')
            : collect();

        return view('admin.branches.show', [
            'branch'        => $branch,
            'stats'         => $stats,
            'vehicles'      => $vehicles,
            'brands'        => $brands,
            'staff'         => $staff,
            'latestSales'   => $latestSales,
            'manager'       => $manager,
            'otherBranches' => $otherBranches,
            'statuses'      => self::VEHICLE_STATUSES,
            'filters'       => ['q' => $search, 'status' => $status, 'brand' => $brandId],
        ]);
    }

    public function create(): View
    {
        return view('admin.branches.form', [
            'branch'   => new Branch(['branch_code' => $this->nextCode(), 'status' => 'active']),
            'managers' => $this->managers(),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = DB::transaction(function () use ($request) {
            $data = $request->validated();

            if ($data['is_main']) {
                Branch::where('is_main', true)->update(['is_main' => false]);
            }

            return Branch::create($data);
        });

        return redirect()->route('admin.branches.show', $branch)->with('status', 'تم إضافة الفرع بنجاح');
    }

    public function edit(Branch $branch): View
    {
        return view('admin.branches.form', [
            'branch'   => $branch,
            'managers' => $this->managers(),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $data = $request->validated();

        if ($branch->is_main && (! $data['is_main'] || $data['status'] === 'inactive')) {
            return back()->withInput()->withErrors([
                'is_main' => 'الفرع الرئيسي لازم يفضل رئيسي ونشط. لو عايز تغيّره، افتح الفرع التاني وخليه هو الرئيسي.',
            ]);
        }

        DB::transaction(function () use ($branch, $data) {
            if ($data['is_main'] && ! $branch->is_main) {
                Branch::where('is_main', true)->update(['is_main' => false]);
            }

            $branch->update($data);
        });

        return redirect()->route('admin.branches.show', $branch)->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        if ($branch->is_main) {
            return back()->with('error', 'مينفعش تمسح الفرع الرئيسي.');
        }

        if (Vehicle::where('branch_id', $branch->id)->exists()
            || SalesContract::where('branch_id', $branch->id)->exists()) {
            return back()->with('error', 'الفرع فيه عربيات أو عقود. انقلهم لفرع تاني الأول، أو خلّي الفرع "غير نشط" بدل المسح.');
        }

        DB::transaction(function () use ($branch) {
            User::where('branch_id', $branch->id)->update(['branch_id' => null]);
            $branch->delete();
        });

        return redirect()->route('admin.branches.index')->with('status', 'تم مسح الفرع');
    }

    public function transferVehicle(Request $request, Branch $branch, Vehicle $vehicle): RedirectResponse
    {
        abort_unless((int) $vehicle->branch_id === (int) $branch->id, 404);

        $data = $request->validate([
            'to_branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')->where('status', 'active')->whereNull('deleted_at'),
                Rule::notIn([$branch->id]),
            ],
        ], [
            'to_branch_id.*' => 'اختار فرع نشط غير الفرع الحالي.',
        ]);

        if ($vehicle->status === 'sold') {
            return back()->with('error', 'العربية دي اتباعت، مينفعش تتنقل.');
        }

        DB::transaction(function () use ($request, $branch, $vehicle, $data) {
            $vehicle->forceFill(['branch_id' => (int) $data['to_branch_id']])->save();

            DB::table('activity_logs')->insert([
                'action'       => 'vehicle_transferred',
                'subject_type' => $vehicle->getMorphClass(),
                'subject_id'   => $vehicle->id,
                'causer_type'  => $request->user()->getMorphClass(),
                'causer_id'    => $request->user()->id,
                'properties'   => json_encode(['from' => $branch->id, 'to' => (int) $data['to_branch_id']]),
                'description'  => 'نقل مركبة بين الفروع',
                'ip_address'   => $request->ip(),
                'user_agent'   => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        $to = Branch::find($data['to_branch_id'])?->name_ar;

        return back()->with('status', "تم نقل العربية {$vehicle->vehicle_code} إلى {$to}");
    }

    private function vehicleCount(?string $status = null)
    {
        return Vehicle::selectRaw('count(*)')
            ->whereColumn('vehicles.branch_id', 'branches.id')
            ->when($status, fn ($q) => $q->where('vehicles.status', $status));
    }

    private function nextCode(): string
    {
        $nextId = (int) DB::table('branches')->max('id') + 1;

        return 'BR-'.str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }

    private function managers()
    {
        return User::internal()->where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }
}
