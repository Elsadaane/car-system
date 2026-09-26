<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * متابعة التحصيل: كل أقساط العملاء في مكان واحد
 * (المتأخر - المستحق الأسبوع ده - الشهر ده - الجاي - المدفوع).
 */
class InstallmentController extends Controller
{
    public const TABS = [
        'overdue' => 'متأخرة',
        'week'    => 'الأسبوع ده',
        'month'   => 'الشهر ده',
        'upcoming'=> 'الجاية',
        'paid'    => 'اتدفعت',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'overdue';
        $q = trim((string) $request->query('q'));
        $today = today();

        $base = fn () => DB::table('installments')
            ->join('sales_contracts', 'sales_contracts.id', '=', 'installments.sales_contract_id')
            ->whereNull('installments.deleted_at')
            ->whereNull('sales_contracts.deleted_at')
            ->where('installments.status', '!=', 'cancelled')
            ->tap(fn ($q) => \App\Support\Access::scope($q, 'sales_contracts.branch_id'));

        $open = fn () => $base()->where('installments.is_paid', false);

        $counts = [
            'overdue' => $open()->where('installments.due_date', '<', $today->toDateString()),
            'week'    => $open()->whereBetween('installments.due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]),
            'month'   => $open()->whereBetween('installments.due_date', [$today->toDateString(), $today->copy()->endOfMonth()->toDateString()]),
            'upcoming'=> $open()->where('installments.due_date', '>', $today->toDateString()),
            'paid'    => $base()->where('installments.is_paid', true),
        ];

        $stats = [];
        foreach ($counts as $k => $query) {
            $row = (clone $query)->selectRaw('count(*) as c, coalesce(sum(installments.amount - installments.paid_amount), 0) as due, coalesce(sum(installments.paid_amount), 0) as paid')->first();
            $stats[$k] = ['count' => (int) $row->c, 'amount' => (float) ($k === 'paid' ? $row->paid : $row->due)];
        }

        $collectedMonth = (float) DB::table('installment_payments')
            ->join('payments', 'payments.id', '=', 'installment_payments.payment_id')
            ->whereNull('payments.deleted_at')
            ->where('payments.payment_date', '>=', $today->copy()->startOfMonth()->toDateString())
            ->sum('installment_payments.amount');

        $rows = $counts[$tab]
            ->leftJoin('customers', 'customers.id', '=', 'installments.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('customers.name_ar', 'like', "%{$q}%")
                ->orWhere('customers.phone', 'like', "%{$q}%")
                ->orWhere('sales_contracts.contract_number', 'like', "%{$q}%")))
            ->select('installments.*', 'sales_contracts.contract_number', 'sales_contracts.guarantor_name', 'sales_contracts.guarantor_phone',
                \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->when($tab === 'paid', fn ($w) => $w->orderByDesc('installments.actual_payment_date'), fn ($w) => $w->orderBy('installments.due_date'))
            ->paginate(25)
            ->withQueryString();

        return view('admin.installments.index', [
            'rows'   => $rows,
            'stats'  => $stats,
            'tab'    => $tab,
            'q'      => $q,
            'collectedMonth' => $collectedMonth,
        ]);
    }
}
