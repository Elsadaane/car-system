<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * عقود التسليم: بتتعمل من صفحة عقد البيع ← "تسليم العربية".
 */
class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $date = $request->date('date')?->toDateString();

        $rows = $this->query()
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('customers.name_ar', 'like', "%{$q}%")->orWhere('customers.phone', 'like', "%{$q}%")
                ->orWhere('sales_contracts.contract_number', 'like', "%{$q}%")->orWhere('delivery_contracts.delivery_number', 'like', "%{$q}%")))
            ->when($date, fn ($w, $v) => $w->whereDate('delivery_contracts.actual_delivery', $v))
            ->tap(fn ($w) => \App\Support\Access::scopeVia($w, 'delivery_contracts.sales_contract_id', 'sales_contracts'))
            ->latest('delivery_contracts.actual_delivery')
            ->paginate(20)->withQueryString();

        $month = DB::table('delivery_contracts')->whereNull('deleted_at')->where('status', 'delivered')
            ->where('actual_delivery', '>=', now()->startOfMonth())->count();

        $waiting = DB::table('sales_contracts')->whereNull('deleted_at')->whereNull('delivery_date')
            ->where('status_id', '!=', \App\Support\ContractStatus::id('cancelled'))->count();

        return view('admin.deliveries.index', compact('rows', 'q', 'date', 'month', 'waiting'));
    }

    public function print(int $delivery): View
    {
        $row = $this->query()->where('delivery_contracts.id', $delivery)
            ->addSelect('vehicles.chassis_number', 'vehicles.engine_number', 'vehicles.color', 'vehicles.plate_number', 'vehicles.manufacturing_year',
                'customers.national_id as customer_national_id', 'sales_contracts.customer_signature', 'sales_contracts.seller_signature')
            ->first();
        abort_unless($row, 404);

        return view('admin.deliveries.print', [
            'd'       => $row,
            'company' => \App\Support\Company::info(),
            'terms'   => \App\Support\Company::get('delivery_contract_terms_ar'),
        ]);
    }

    public function destroy(int $delivery): RedirectResponse
    {
        $row = DB::table('delivery_contracts')->where('id', $delivery)->first();
        abort_unless($row, 404);

        DB::transaction(function () use ($row) {
            DB::table('delivery_contracts')->where('id', $row->id)->update(['deleted_at' => now()]);
            $other = DB::table('delivery_contracts')->where('sales_contract_id', $row->sales_contract_id)->whereNull('deleted_at')->latest('actual_delivery')->value('actual_delivery');
            DB::table('sales_contracts')->where('id', $row->sales_contract_id)->update(['delivery_date' => $other ? substr($other, 0, 10) : null]);
        });

        return back()->with('status', 'تم مسح عقد التسليم');
    }

    private function query()
    {
        return DB::table('delivery_contracts')
            ->whereNull('delivery_contracts.deleted_at')
            ->join('sales_contracts', 'sales_contracts.id', '=', 'delivery_contracts.sales_contract_id')
            ->leftJoin('customers', 'customers.id', '=', 'delivery_contracts.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'delivery_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('users', 'users.id', '=', 'delivery_contracts.user_id')
            ->select('delivery_contracts.*', 'sales_contracts.contract_number',
                \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone',
                'vehicles.vehicle_code', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'), 'users.name as user_name');
    }
}
