<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SalesContract;
use App\Support\ContractStatus;
use App\Support\InstallmentPlan;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * كل الفلوس اللي دخلت من العملاء على عقود البيع (مقدمات + أقساط + دفعات كاش).
 * التعديل والمسح للأدمن بس، وبعدهم الأقساط بتتوزع تاني لوحدها.
 */
class SalePaymentController extends Controller
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
                ->where('sales_contracts.contract_number', 'like', "%{$f['q']}%")
                ->orWhere('customers.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('customers.phone', 'like', "%{$f['q']}%")
                ->orWhere('payments.payment_reference', 'like', "%{$f['q']}%")
                ->orWhere('payments.bank_reference', 'like', "%{$f['q']}%")))
            ->when($f['method'], fn ($q, $v) => $q->where('payments.payment_method_id', $v))
            ->tap(fn ($q) => \App\Support\Access::scopeVia($q, 'payments.payable_id', 'sales_contracts'))
            ->when($f['from'], fn ($q, $v) => $q->where('payments.payment_date', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->where('payments.payment_date', '<=', $v));

        $byMethod = (clone $query)->reorder()
            ->select(\App\Support\Lang::sel('payment_methods.name_ar as method_name'), DB::raw('sum(payments.amount) as total'), DB::raw('count(*) as cnt'))
            ->groupBy('payment_methods.name_ar', 'payment_methods.name_en')
            ->orderByDesc('total')
            ->get();

        return view('admin.sale-payments.index', [
            'payments' => $query->latest('payments.payment_date')->latest('payments.id')->paginate(20)->withQueryString(),
            'total'    => (float) $byMethod->sum('total'),
            'byMethod' => $byMethod,
            'today'    => (float) (clone $query)->reorder()->where('payments.payment_date', today()->toDateString())->sum('payments.amount'),
            'methods'  => PaymentMethod::orderBy('sort_order')->pluckLocal('name', 'id'),
            'filters'  => $f,
        ]);
    }

    public function receipt(Payment $payment): View
    {
        abort_unless($payment->payable_type === (new SalesContract)->getMorphClass(), 404);

        return view('admin.sale-payments.receipt', [
            'payment' => $this->baseQuery()->where('payments.id', $payment->id)
                ->addSelect('sales_contracts.total_amount', 'sales_contracts.paid_amount', 'sales_contracts.remaining_amount',
                    'sales_contracts.down_payment_id', 'customers.national_id as customer_national_id')
                ->firstOrFail(),
            'covers'  => DB::table('installment_payments')
                ->join('installments', 'installments.id', '=', 'installment_payments.installment_id')
                ->where('installment_payments.payment_id', $payment->id)
                ->orderBy('installments.installment_sequence')
                ->get(['installments.installment_sequence', 'installments.total_installments', 'installments.due_date',
                    'installments.amount as installment_amount', 'installments.is_paid', 'installment_payments.amount']),
            'company' => \App\Support\Company::info(),
        ]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $contract = $this->contractOf($payment);

        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.001'],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')],
            'payment_date'      => ['required', 'date', 'before_or_equal:today'],
            'reference'         => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ], [
            'required' => 'الحقل ده مطلوب.', 'min' => 'أقل من المسموح.', 'numeric' => 'لازم يكون رقم.',
            'before_or_equal' => 'مينفعش تاريخ في المستقبل.',
        ]);

        DB::transaction(function () use ($request, $payment, $contract, $data) {
            $locked = SalesContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $amount = Money::round($data['amount']);
            $max = Money::round((float) $locked->remaining_amount + (float) $payment->amount);

            if ($amount > $max + 0.0005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من المتبقي على العميل ('.Money::format($max).').']);
            }

            $old = ['amount' => (float) $payment->amount, 'date' => (string) $payment->payment_date];

            $payment->forceFill([
                'amount'            => $amount,
                'payment_method_id' => $data['payment_method_id'],
                'payment_date'      => $data['payment_date'],
                'bank_reference'    => $data['reference'] ?? null,
                'notes'             => $data['notes'] ?? null,
            ])->save();

            if ((int) $payment->id === (int) $locked->down_payment_id) {
                $locked->forceFill(['down_payment' => $amount])->save();
                if ($locked->payment_type === 'installments' && abs($old['amount'] - $amount) > 0.0005) {
                    // المقدم اتغير ← الأقساط بتتقسم من جديد على الباقي
                    InstallmentPlan::generate($locked);
                }
            }

            InstallmentPlan::recalculate($locked);
            $this->log($request, $locked, 'sale_payment_updated', $old + ['new_amount' => $amount, 'reference' => $payment->payment_reference], 'تعديل دفعة');
        });

        return back()->with('status', 'تم تعديل الدفعة '.$payment->payment_reference);
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        $contract = $this->contractOf($payment);

        if ((int) $payment->id === (int) $contract->down_payment_id) {
            return back()->with('error', 'دي دفعة المقدم. عدّلها من هنا أو عدّل العقد نفسه.');
        }

        DB::transaction(function () use ($request, $payment, $contract) {
            DB::table('installment_payments')->where('payment_id', $payment->id)->delete();
            $payment->forceFill(['status' => 'reversed'])->save();
            $payment->delete();

            $locked = SalesContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            InstallmentPlan::recalculate($locked);
            $this->log($request, $locked, 'sale_payment_deleted', ['amount' => (float) $payment->amount, 'reference' => $payment->payment_reference], 'مسح دفعة');
        });

        return back()->with('status', 'تم مسح الدفعة والأقساط اتظبطت');
    }

    private function contractOf(Payment $payment): SalesContract
    {
        abort_unless($payment->payable_type === (new SalesContract)->getMorphClass(), 404);
        $contract = SalesContract::findOrFail($payment->payable_id);
        abort_if((int) $contract->status_id === ContractStatus::id('cancelled'), 403, 'العقد ملغي');

        return $contract;
    }

    private function log(Request $request, SalesContract $contract, string $action, array $props, string $description): void
    {
        DB::table('activity_logs')->insert([
            'action'       => $action,
            'subject_type' => $contract->getMorphClass(),
            'subject_id'   => $contract->id,
            'causer_type'  => $request->user()->getMorphClass(),
            'causer_id'    => $request->user()->id,
            'properties'   => json_encode($props, JSON_UNESCAPED_UNICODE),
            'description'  => $description,
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function baseQuery()
    {
        return Payment::query()
            ->where('payments.payable_type', (new SalesContract)->getMorphClass())
            ->whereNull('payments.deleted_at')
            ->join('sales_contracts', 'sales_contracts.id', '=', 'payments.payable_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->select('payments.*', 'sales_contracts.contract_number', 'sales_contracts.id as contract_id', 'sales_contracts.down_payment_id',
                \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone', 'customers.id as customer_id',
                'vehicles.vehicle_code', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'),
                \App\Support\Lang::sel('payment_methods.name_ar as method_name'), 'users.name as user_name',
                DB::raw('(select group_concat(i.installment_sequence order by i.installment_sequence) from installment_payments ip join installments i on i.id = ip.installment_id where ip.payment_id = payments.id) as covers'));
    }
}
