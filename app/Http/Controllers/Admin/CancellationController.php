<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * طلبات إلغاء عقود البيع. الطلب بيتعمل من صفحة العقد، والموافقة/الرفض من هنا أو من العقد.
 */
class CancellationController extends Controller
{
    public const STATUSES = ['pending' => 'مستني الموافقة', 'approved' => 'اتلغى', 'rejected' => 'مرفوض'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : null;

        $rows = DB::table('cancellation_requests')
            ->whereNull('cancellation_requests.deleted_at')
            ->join('sales_contracts', 'sales_contracts.id', '=', 'cancellation_requests.sales_contract_id')
            ->leftJoin('customers', 'customers.id', '=', 'cancellation_requests.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('users', 'users.id', '=', 'cancellation_requests.user_id')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('customers.name_ar', 'like', "%{$q}%")->orWhere('customers.phone', 'like', "%{$q}%")
                ->orWhere('sales_contracts.contract_number', 'like', "%{$q}%")))
            ->when($status, fn ($w, $v) => $w->where('cancellation_requests.status', $v))
            ->tap(fn ($w) => \App\Support\Access::scopeVia($w, 'cancellation_requests.sales_contract_id', 'sales_contracts'))
            ->select('cancellation_requests.*', 'sales_contracts.contract_number', \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'), 'users.name as user_name')
            ->orderByRaw("cancellation_requests.status = 'pending' desc")
            ->latest('cancellation_requests.id')
            ->paginate(20)->withQueryString();

        $counts = DB::table('cancellation_requests')->whereNull('deleted_at')->groupBy('status')->select('status', DB::raw('count(*) as c'))->pluck('c', 'status');

        return view('admin.cancellations.index', compact('rows', 'q', 'status', 'counts'));
    }
}
