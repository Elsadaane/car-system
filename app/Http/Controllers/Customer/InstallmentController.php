<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallmentController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        abort_if(! $customer, 403);

        $installments = $customer->installments()
            ->with('salesContract.vehicle.brand')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return view('customer.installments.index', compact('installments'));
    }

    public function show(Request $request, Installment $installment): View
    {
        $this->authorizeOwnership($request, $installment);

        $installment->load('salesContract.vehicle.brand', 'payment');

        return view('customer.installments.show', compact('installment'));
    }

    /**
     * العميل لازم يشوف أقساطه هو بس - مش أقساط حد تاني.
     */
    protected function authorizeOwnership(Request $request, Installment $installment): void
    {
        abort_if(
            $installment->customer_id !== $request->user()->customer_id,
            403,
            'مش مسموح لك تشوف القسط ده.'
        );
    }
}
