<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * لوحة العميل - بيشوف فيها عربياته وأقساطه وإشعاراته.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;

        abort_if(! $customer, 403, 'الحساب ده مش مربوط بملف عميل.');

        // العقود ومعاها العربيات - eager loading عشان ما نعملش استعلامات كتير
        $contracts = $customer->salesContracts()
            ->with(['vehicle.brand', 'vehicle.model', 'status'])
            ->latest('contract_date')
            ->get();

        $installments = $customer->installments()
            ->with('salesContract:id,contract_number')
            ->orderBy('due_date')
            ->get();

        return view('customer.dashboard', [
            'customer'  => $customer,
            'contracts' => $contracts,
            'summary'   => $this->summary($customer->id),
            'upcoming'  => $installments->where('is_paid', false)->take(6),
            'overdue'   => $installments->where('status', 'overdue')->where('is_paid', false),
            'unread'    => $customer->unread_notifications_count,
        ]);
    }

    /**
     * ملخص الأرقام - استعلام واحد بدل ما نعد في PHP.
     */
    protected function summary(int $customerId): array
    {
        $row = Installment::forCustomer($customerId)
            ->selectRaw('
                COALESCE(SUM(amount), 0)                                          as total,
                COALESCE(SUM(paid_amount), 0)                                     as paid,
                COALESCE(SUM(CASE WHEN is_paid = 0 THEN amount - paid_amount END), 0) as remaining,
                COALESCE(SUM(late_fees), 0)                                       as late_fees,
                COUNT(*)                                                          as count,
                SUM(CASE WHEN status = "overdue" AND is_paid = 0 THEN 1 ELSE 0 END) as overdue_count
            ')
            ->first();

        return [
            'total'         => (float) $row->total,
            'paid'          => (float) $row->paid,
            'remaining'     => (float) $row->remaining,
            'late_fees'     => (float) $row->late_fees,
            'count'         => (int) $row->count,
            'overdue_count' => (int) $row->overdue_count,
        ];
    }
}
