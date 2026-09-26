<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\SupplierInvoice;
use App\Models\SupplierReturn;
use App\Models\Vehicle;
use App\Support\SupplierAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * العمليات المالية على المورد: فواتير، دفع أقساط، مرتجعات.
 */
class SupplierAccountController extends Controller
{
    private const MESSAGES = [
        'required'       => 'الحقل ده مطلوب.',
        'numeric'        => 'لازم يكون رقم.',
        'integer'        => 'لازم يكون رقم صحيح.',
        'min'            => 'القيمة أقل من المسموح.',
        'max'            => 'القيمة أكبر من المسموح.',
        'date'           => 'لازم يكون تاريخ.',
        'unique'         => 'الرقم ده مستخدم قبل كده.',
        'exists'         => 'الاختيار مش صحيح.',
        'in'             => 'الاختيار مش صحيح.',
        'after_or_equal' => 'التاريخ لازم يكون بعد تاريخ الفاتورة أو نفسه.',
        'before_or_equal'=> 'مينفعش تاريخ في المستقبل.',
    ];

    // =========================================================
    //  فاتورة شراء جديدة + توليد الأقساط
    // =========================================================
    public function storeInvoice(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number'     => ['required', 'string', 'max:50', 'unique:supplier_invoices,invoice_number'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'invoice_date'       => ['required', 'date', 'before_or_equal:today'],
            'subtotal'           => ['required', 'numeric', 'min:0.001', 'max:999999999999'],
            'tax_percent'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:60'],
            'first_due_date'     => ['required', 'date', 'after_or_equal:invoice_date'],
            'vehicle_ids'        => ['nullable', 'array'],
            'vehicle_ids.*'      => ['integer', Rule::exists('vehicles', 'id')
                ->where('supplier_id', $supplier->id)
                ->whereNull('supplier_invoice_id')
                ->whereNull('deleted_at')],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ], self::MESSAGES);

        $decimals = (int) config('laroville.decimals', 3);
        $subtotal = round((float) $data['subtotal'], $decimals);
        $taxPercent = (float) ($data['tax_percent'] ?? 0);
        $taxAmount = round($subtotal * $taxPercent / 100, $decimals);
        $total = round($subtotal + $taxAmount, $decimals);

        DB::transaction(function () use ($request, $supplier, $data, $subtotal, $taxPercent, $taxAmount, $total, $decimals) {
            $invoice = SupplierInvoice::create([
                'invoice_number'     => $data['invoice_number'],
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'supplier_id'        => $supplier->id,
                'branch_id'          => $supplier->branch_id,
                'invoice_date'       => $data['invoice_date'],
                'subtotal'           => $subtotal,
                'tax_percent'        => $taxPercent,
                'tax_amount'         => $taxAmount,
                'total_amount'       => $total,
                'status'             => 'unpaid',
                'notes'              => $data['notes'] ?? null,
                'user_id'            => $request->user()->id,
            ]);

            if (! empty($data['vehicle_ids'])) {
                Vehicle::whereIn('id', $data['vehicle_ids'])
                    ->where('supplier_id', $supplier->id)
                    ->whereNull('supplier_invoice_id')
                    ->update(['supplier_invoice_id' => $invoice->id]);
            }

            // التقسيم بالفلس عشان مفيش كسور ضايعة - الفرق بيتحط على آخر قسط
            $count = (int) $data['installments_count'];
            $factor = 10 ** $decimals;
            $totalUnits = (int) round($total * $factor);
            $baseUnits = intdiv($totalUnits, $count);
            $firstDue = Carbon::parse($data['first_due_date']);

            for ($i = 1; $i <= $count; $i++) {
                $units = $i === $count ? $totalUnits - $baseUnits * ($count - 1) : $baseUnits;

                SupplierInstallment::create([
                    'supplier_invoice_id' => $invoice->id,
                    'supplier_id'         => $supplier->id,
                    'sequence'            => $i,
                    'amount'              => $units / $factor,
                    'due_date'            => $firstDue->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                    'status'              => 'pending',
                ]);
            }

            (new SupplierAccount($supplier))->refresh();
        });

        return redirect()->to(route('admin.suppliers.show', $supplier).'#installments')
            ->with('status', 'تم تسجيل الفاتورة وتقسيمها على '.$data['installments_count'].' قسط');
    }

    // =========================================================
    //  دفع قسط
    // =========================================================
    public function payInstallment(Request $request, Supplier $supplier, SupplierInstallment $installment): RedirectResponse
    {
        abort_unless((int) $installment->supplier_id === (int) $supplier->id, 404);

        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.001'],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payment_date'      => ['required', 'date', 'before_or_equal:today'],
            'reference'         => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES);

        DB::transaction(function () use ($request, $supplier, $installment, $data) {
            $locked = SupplierInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();
            $remaining = $locked->remaining();
            $amount = round((float) $data['amount'], 3);

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['amount' => 'القسط ده متدفع بالكامل.']);
            }
            if ($amount > $remaining + 0.0005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من المتبقي على القسط ('.number_format($remaining, 3).').']);
            }

            Payment::forceCreate([
                'payment_reference' => 'SPAY-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
                'payable_type'      => $locked->getMorphClass(),
                'payable_id'        => $locked->id,
                'payment_method_id' => $data['payment_method_id'],
                'amount'            => $amount,
                'payment_date'      => $data['payment_date'],
                'bank_reference'    => $data['reference'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'status'            => 'completed',
                'user_id'           => $request->user()->id,
            ]);

            $paid = round((float) $locked->paid_amount + $amount, 3);
            $fullyPaid = $paid + 0.0005 >= (float) $locked->amount;

            $locked->forceFill([
                'paid_amount' => $paid,
                'status'      => $fullyPaid ? 'paid' : 'partial',
                'paid_at'     => $fullyPaid ? $data['payment_date'] : null,
            ])->save();

            $locked->invoice->refreshPaid();
            (new SupplierAccount($supplier))->refresh();
        });

        return redirect()->to(route('admin.suppliers.show', $supplier).'#installments')
            ->with('status', 'تم تسجيل الدفعة');
    }

    // =========================================================
    //  المرتجعات
    // =========================================================
    public function storeReturn(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_id'     => ['required', 'integer', Rule::exists('vehicles', 'id')
                ->where('supplier_id', $supplier->id)
                ->whereNotIn('status', ['sold', 'returned'])
                ->whereNull('deleted_at')],
            'return_date'    => ['required', 'date', 'before_or_equal:today'],
            'reason'         => ['required', Rule::in(array_keys(SupplierReturn::REASONS))],
            'reason_details' => ['nullable', 'string', 'max:2000'],
            'refund_amount'  => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ], self::MESSAGES);

        $pending = SupplierReturn::where('vehicle_id', $data['vehicle_id'])->where('status', 'pending')->exists();
        if ($pending) {
            return back()->withInput()->withErrors(['vehicle_id' => 'العربية دي عليها طلب إرجاع مفتوح بالفعل.']);
        }

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        SupplierReturn::create($data + [
            'return_number'       => self::nextNumber('SRET', 'supplier_returns', 'return_number'),
            'supplier_id'         => $supplier->id,
            'supplier_invoice_id' => $vehicle->supplier_invoice_id,
            'status'              => 'pending',
            'user_id'             => $request->user()->id,
        ]);

        return redirect()->to(route('admin.suppliers.show', $supplier).'#returns')
            ->with('status', 'تم تسجيل طلب الإرجاع. أكّده لما العربية تتسلم للمورد.');
    }

    public function updateReturn(Request $request, Supplier $supplier, SupplierReturn $supplierReturn): RedirectResponse
    {
        abort_unless((int) $supplierReturn->supplier_id === (int) $supplier->id, 404);

        $action = $request->validate(['action' => ['required', Rule::in(['complete', 'reject'])]])['action'];

        if ($supplierReturn->status !== 'pending') {
            return back()->with('error', 'الطلب ده اتقفل قبل كده.');
        }

        DB::transaction(function () use ($supplier, $supplierReturn, $action) {
            if ($action === 'reject') {
                $supplierReturn->update(['status' => 'rejected']);
                return;
            }

            $supplierReturn->update(['status' => 'completed', 'completed_at' => now()]);

            Vehicle::whereKey($supplierReturn->vehicle_id)->update([
                'status'       => 'returned',
                'is_published' => false,
            ]);

            (new SupplierAccount($supplier))->refresh();
        });

        return redirect()->to(route('admin.suppliers.show', $supplier).'#returns')
            ->with('status', $action === 'complete' ? 'تم تأكيد الإرجاع وخصم المبلغ من حساب المورد' : 'تم رفض طلب الإرجاع');
    }

    public static function nextNumber(string $prefix, string $table, string $column): string
    {
        $base = $prefix.'-'.now()->format('Y').'-';
        $last = DB::table($table)->where($column, 'like', $base.'%')->orderByDesc($column)->value($column);
        $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $base.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
