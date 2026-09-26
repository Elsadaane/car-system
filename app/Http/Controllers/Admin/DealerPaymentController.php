<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DealerContract;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * كل الدفعات اللي المعرض دفعها لأصحاب العربيات (عقود التفويض).
 */
class DealerPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $f = [
            'q'      => trim((string) $request->query('q')),
            'method' => $request->integer('method') ?: null,
            'from'   => $request->date('from')?->toDateString(),
            'to'     => $request->date('to')?->toDateString(),
        ];

        $query = $this->baseQuery()
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('dealer_contracts.contract_number', 'like', "%{$f['q']}%")
                ->orWhere('suppliers.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('suppliers.phone', 'like', "%{$f['q']}%")
                ->orWhere('payments.payment_reference', 'like', "%{$f['q']}%")))
            ->when($f['method'], fn ($q, $v) => $q->where('payments.payment_method_id', $v))
            ->when($f['from'], fn ($q, $v) => $q->where('payments.payment_date', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->where('payments.payment_date', '<=', $v));

        $byMethod = (clone $query)->reorder()
            ->select(\App\Support\Lang::sel('payment_methods.name_ar as method_name'), DB::raw('sum(payments.amount) as total'), DB::raw('count(*) as cnt'))
            ->groupBy('payment_methods.name_ar', 'payment_methods.name_en')
            ->get();

        return view('admin.dealer-payments.index', [
            'payments' => $query->latest('payments.payment_date')->latest('payments.id')->paginate(20)->withQueryString(),
            'total'    => (float) $byMethod->sum('total'),
            'byMethod' => $byMethod,
            'methods'  => PaymentMethod::orderBy('sort_order')->pluckLocal('name', 'id'),
            'filters'  => $f,
        ]);
    }

    public function receipt(Payment $payment): View
    {
        abort_unless($payment->payable_type === (new DealerContract)->getMorphClass(), 404);

        return view('admin.dealer-payments.receipt', [
            'payment' => $this->baseQuery()->where('payments.id', $payment->id)
                ->addSelect('dealer_contracts.total_amount', 'dealer_contracts.paid_amount', 'dealer_contracts.remaining_amount')
                ->firstOrFail(),
            'company' => \App\Support\Company::info(),
        ]);
    }

    private function baseQuery()
    {
        return Payment::query()
            ->where('payments.payable_type', (new DealerContract)->getMorphClass())
            ->join('dealer_contracts', 'dealer_contracts.id', '=', 'payments.payable_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'dealer_contracts.supplier_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'dealer_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->select('payments.*', 'dealer_contracts.contract_number', 'dealer_contracts.id as contract_id',
                \App\Support\Lang::sel('suppliers.name_ar as supplier_name'), 'suppliers.phone as supplier_phone',
                'vehicles.vehicle_code', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'),
                \App\Support\Lang::sel('payment_methods.name_ar as method_name'), 'users.name as user_name');
    }
}
