<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\SupplierInvoice;
use App\Models\SupplierReturn;
use App\Models\Vehicle;
use App\Support\SupplierAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $today = today()->toDateString();
        $search = trim((string) $request->query('q'));
        $status = array_key_exists($request->query('status'), SupplierRequest::STATUSES) ? $request->query('status') : null;
        $onlyOverdue = $request->boolean('overdue');

        $openInstallments = fn () => SupplierInstallment::whereColumn('supplier_installments.supplier_id', 'suppliers.id')
            ->where('supplier_installments.status', '!=', 'paid');

        $suppliers = Supplier::query()
            ->addSelect([
                'vehicles_count' => Vehicle::selectRaw('count(*)')->whereColumn('vehicles.supplier_id', 'suppliers.id'),
                'returns_count'  => SupplierReturn::selectRaw('count(*)')
                    ->whereColumn('supplier_returns.supplier_id', 'suppliers.id')
                    ->where('supplier_returns.status', 'completed'),
                'overdue_amount' => $openInstallments()->selectRaw('coalesce(sum(amount - paid_amount), 0)')
                    ->where('due_date', '<', $today),
                'next_due_date'  => $openInstallments()->select('due_date')
                    ->where('due_date', '>=', $today)->orderBy('due_date')->limit(1),
            ])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name_ar', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('supplier_code', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('national_id', 'like', "%{$search}%")))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($onlyOverdue, fn ($q) => $q->whereExists(fn ($s) => $s->from('supplier_installments')
                ->whereColumn('supplier_installments.supplier_id', 'suppliers.id')
                ->where('status', '!=', 'paid')
                ->where('due_date', '<', $today)))
            ->orderBy('name_ar')
            ->paginate(15)
            ->withQueryString();

        $open = SupplierInstallment::where('status', '!=', 'paid');

        $totals = [
            'suppliers' => Supplier::count(),
            'balance'   => (float) Supplier::where('current_balance', '>', 0)->sum('current_balance'),
            'overdue'   => (float) (clone $open)->where('due_date', '<', $today)->sum(DB::raw('amount - paid_amount')),
            'due_week'  => (float) (clone $open)->whereBetween('due_date', [$today, today()->addDays(7)->toDateString()])
                ->sum(DB::raw('amount - paid_amount')),
        ];

        return view('admin.suppliers.index', [
            'suppliers' => $suppliers,
            'totals'    => $totals,
            'statuses'  => SupplierRequest::STATUSES,
            'filters'   => ['q' => $search, 'status' => $status, 'overdue' => $onlyOverdue],
        ]);
    }

    public function show(Supplier $supplier): View
    {
        $summary = (new SupplierAccount($supplier))->summary();

        $installments = SupplierInstallment::with('invoice:id,invoice_number')
            ->where('supplier_id', $supplier->id)
            ->orderByRaw("status = 'paid'")
            ->orderBy('due_date')
            ->get();

        $invoices = SupplierInvoice::where('supplier_id', $supplier->id)
            ->withCount(['vehicles', 'installments'])
            ->latest('invoice_date')
            ->get();

        $payments = Payment::query()
            ->where('payable_type', (new SupplierInstallment)->getMorphClass())
            ->whereIn('payable_id', $installments->pluck('id'))
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->select('payments.*', \App\Support\Lang::sel('payment_methods.name_ar as method_name'))
            ->latest('payment_date')
            ->get();

        $returns = SupplierReturn::query()
            ->where('supplier_returns.supplier_id', $supplier->id)
            ->leftJoin('vehicles', 'vehicles.id', '=', 'supplier_returns.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->select('supplier_returns.*', 'vehicles.vehicle_code', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->latest('return_date')
            ->get();

        $vehicles = Vehicle::query()
            ->where('vehicles.supplier_id', $supplier->id)
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('supplier_invoices', 'supplier_invoices.id', '=', 'vehicles.supplier_invoice_id')
            ->select('vehicles.*', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'),
                'supplier_invoices.invoice_number')
            ->latest('vehicles.created_at')
            ->get();

        $pendingReturnVehicleIds = $returns->where('status', 'pending')->pluck('vehicle_id');

        return view('admin.suppliers.show', [
            'supplier'           => $supplier,
            'summary'            => $summary,
            'installments'       => $installments,
            'invoices'           => $invoices,
            'payments'           => $payments,
            'installmentNumbers' => $installments->mapWithKeys(fn ($i) => [$i->id => $i->invoice?->invoice_number.' · قسط '.$i->sequence]),
            'returns'            => $returns,
            'vehicles'           => $vehicles,
            'uninvoicedVehicles' => $vehicles->whereNull('supplier_invoice_id')->where('status', '!=', 'returned')->values(),
            'returnableVehicles' => $vehicles->whereNotIn('status', ['sold', 'returned'])
                ->whereNotIn('id', $pendingReturnVehicleIds)->values(),
            'paymentMethods'     => PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluckLocal('name', 'id'),
            'nextInvoiceNumber'  => SupplierAccountController::nextNumber('SINV', 'supplier_invoices', 'invoice_number'),
            'statuses'           => SupplierRequest::STATUSES,
        ]);
    }

    public function create(): View
    {
        $nextId = (int) DB::table('suppliers')->max('id') + 1;

        return view('admin.suppliers.form', [
            'supplier' => new Supplier([
                'supplier_code'   => 'SUP-'.str_pad($nextId, 3, '0', STR_PAD_LEFT),
                'status'          => 'active',
                'settlement_days' => 30,
            ]),
            'branches' => $this->branches(),
            'statuses' => SupplierRequest::STATUSES,
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = new Supplier;
        $supplier->forceFill($request->validated() + ['user_id' => $request->user()->id])->save();

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'تم إضافة المورد بنجاح');
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.form', [
            'supplier' => $supplier,
            'branches' => $this->branches(),
            'statuses' => SupplierRequest::STATUSES,
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->forceFill($request->validated())->save();

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $hasHistory = Vehicle::where('supplier_id', $supplier->id)->exists()
            || SupplierInvoice::where('supplier_id', $supplier->id)->exists()
            || DB::table('dealer_contracts')->where('supplier_id', $supplier->id)->exists();

        if ($hasHistory) {
            return back()->with('error', 'المورد ليه عربيات أو فواتير. غيّر حالته لـ "غير نشط" بدل المسح عشان السجل يفضل محفوظ.');
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', 'تم مسح المورد');
    }

    private function branches()
    {
        return Branch::isMultiBranch()
            ? Branch::where('status', 'active')->orderBy('name_ar')->pluckLocal('name', 'id')
            : collect();
    }
}
