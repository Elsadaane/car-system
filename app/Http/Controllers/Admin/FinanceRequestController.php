<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SalesContract;
use App\Support\ContractStatus;
use App\Support\InstallmentPlan;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * طلبات التمويل: المعرض بيطلب من بنك/شركة تمويل تدفع باقي تمن العربية.
 *   معلّق ← موافق عليه (بالمبلغ والفايدة والمدة) ← اتصرف (البنك حوّل الفلوس = دفعة على العقد)
 *                ↘ مرفوض / ملغي
 */
class FinanceRequestController extends Controller
{
    public const STATUSES = [
        'pending'  => 'مستني الرد',
        'approved' => 'موافق عليه',
        'paid_off' => 'اتصرف',
        'rejected' => 'مرفوض',
        'cancelled'=> 'ملغي',
    ];

    public function index(Request $request): View
    {
        $f = [
            'q'       => trim((string) $request->query('q')),
            'status'  => array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : null,
            'company' => $request->integer('company') ?: null,
        ];

        $base = DB::table('finance_requests')->whereNull('finance_requests.deleted_at');

        $requests = (clone $base)
            ->join('sales_contracts', 'sales_contracts.id', '=', 'finance_requests.sales_contract_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->leftJoin('finance_companies', 'finance_companies.id', '=', 'finance_requests.finance_company_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('users', 'users.id', '=', 'finance_requests.user_id')
            ->when($f['q'] !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('finance_requests.request_number', 'like', "%{$f['q']}%")
                ->orWhere('sales_contracts.contract_number', 'like', "%{$f['q']}%")
                ->orWhere('customers.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('customers.phone', 'like', "%{$f['q']}%")))
            ->when($f['status'], fn ($w, $v) => $w->where('finance_requests.status', $v))
            ->tap(fn ($w) => \App\Support\Access::scopeVia($w, 'finance_requests.sales_contract_id', 'sales_contracts'))
            ->when($f['company'], fn ($w, $v) => $w->where('finance_requests.finance_company_id', $v))
            ->select('finance_requests.*', 'sales_contracts.contract_number', 'sales_contracts.remaining_amount',
                \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone',
                \App\Support\Lang::sel('finance_companies.name_ar as company_name'), \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'),
                'users.name as user_name')
            ->latest('finance_requests.id')
            ->paginate(20)->withQueryString();

        $stats = (clone $base)->groupBy('status')->select('status', DB::raw('count(*) as c'), DB::raw('coalesce(sum(coalesce(approved_amount, requested_amount)), 0) as total'))->get()->keyBy('status');

        return view('admin.finance.requests', [
            'requests'  => $requests,
            'stats'     => $stats,
            'filters'   => $f,
            'companies' => DB::table('finance_companies')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'methods'   => DB::table('payment_methods')->where('is_active', true)->orderBy('sort_order')->pluckLocal('name', 'id'),
        ]);
    }

    public function store(Request $request, SalesContract $salesContract): RedirectResponse
    {
        abort_if((int) $salesContract->status_id === ContractStatus::id('cancelled'), 403);

        $remaining = (float) $salesContract->remaining_amount;
        $data = $request->validate([
            'finance_company_id' => ['required', Rule::exists('finance_companies', 'id')->where('is_active', true)],
            'requested_amount'   => ['required', 'numeric', 'min:0.001', 'max:'.$remaining],
            'installment_period' => ['nullable', 'integer', 'min:1', 'max:120'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ], ['required' => 'الحقل ده مطلوب.', 'max' => 'أكبر من الباقي على العميل ('.Money::format($remaining).').', 'exists' => 'اختار شركة.']);

        $open = DB::table('finance_requests')->where('sales_contract_id', $salesContract->id)
            ->whereIn('status', ['pending', 'approved'])->whereNull('deleted_at')->exists();
        if ($open) {
            return back()->with('error', 'العقد عليه طلب تمويل مفتوح بالفعل.');
        }

        DB::table('finance_requests')->insert([
            'request_number'     => 'FIN-'.now()->format('ymd').'-'.str_pad((int) DB::table('finance_requests')->max('id') + 1, 4, '0', STR_PAD_LEFT),
            'sales_contract_id'  => $salesContract->id,
            'finance_company_id' => $data['finance_company_id'],
            'requested_amount'   => Money::round($data['requested_amount']),
            'installment_period' => $data['installment_period'] ?? null,
            'status'             => 'pending',
            'request_date'       => today()->toDateString(),
            'notes'              => $data['notes'] ?? null,
            'user_id'            => $request->user()->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return back()->with('status', 'تم تسجيل طلب التمويل');
    }

    public function decide(Request $request, int $financeRequest, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'reject', 'cancel'], true), 404);
        $row = DB::table('finance_requests')->where('id', $financeRequest)->whereNull('deleted_at')->first();
        abort_unless($row, 404);

        if ($decision === 'approve') {
            abort_unless($row->status === 'pending', 403);
            $data = $request->validate([
                'approved_amount'    => ['required', 'numeric', 'min:0.001'],
                'interest_rate'      => ['nullable', 'numeric', 'min:0', 'max:100'],
                'installment_period' => ['nullable', 'integer', 'min:1', 'max:120'],
            ], ['required' => 'الحقل ده مطلوب.']);

            $amount = Money::round($data['approved_amount']);
            $months = (int) ($data['installment_period'] ?? 0);
            $rate = (float) ($data['interest_rate'] ?? 0);
            // قسط تقريبي للعميل مع البنك (فايدة ثابتة سنوية)
            $monthly = $months ? Money::round(($amount + $amount * $rate / 100 * $months / 12) / $months) : null;

            DB::table('finance_requests')->where('id', $row->id)->update([
                'status' => 'approved', 'approved_amount' => $amount, 'interest_rate' => $rate ?: null,
                'installment_period' => $months ?: null, 'monthly_payment' => $monthly,
                'approval_date' => today()->toDateString(), 'updated_at' => now(),
            ]);

            return back()->with('status', 'تم تسجيل الموافقة. لما البنك يحوّل الفلوس دوس "استلام المبلغ".');
        }

        abort_unless(in_array($row->status, ['pending', 'approved'], true), 403);
        $reason = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']])['rejection_reason'] ?? null;

        DB::table('finance_requests')->where('id', $row->id)->update([
            'status' => $decision === 'reject' ? 'rejected' : 'cancelled',
            'rejection_reason' => $reason, 'updated_at' => now(),
        ]);

        return back()->with('status', $decision === 'reject' ? 'تم تسجيل الرفض' : 'تم إلغاء الطلب');
    }

    /** البنك حوّل المبلغ ← بيتسجل دفعة على العقد والأقساط بتتقفل */
    public function receive(Request $request, int $financeRequest): RedirectResponse
    {
        $row = DB::table('finance_requests')->where('id', $financeRequest)->where('status', 'approved')->whereNull('deleted_at')->first();
        abort_unless($row, 404);

        $data = $request->validate([
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payment_date'      => ['required', 'date', 'before_or_equal:today'],
            'reference'         => ['nullable', 'string', 'max:100'],
        ], ['required' => 'الحقل ده مطلوب.', 'before_or_equal' => 'مينفعش تاريخ في المستقبل.']);

        $payment = DB::transaction(function () use ($request, $row, $data) {
            $contract = SalesContract::whereKey($row->sales_contract_id)->lockForUpdate()->firstOrFail();
            $amount = min(Money::round($row->approved_amount), Money::round($contract->remaining_amount));

            if ($amount <= 0) {
                throw ValidationException::withMessages(['payment_method_id' => 'العقد مدفوع بالكامل بالفعل.']);
            }

            $payment = Payment::forceCreate([
                'payment_reference' => 'SPAY-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
                'payable_type'      => $contract->getMorphClass(),
                'payable_id'        => $contract->id,
                'payment_method_id' => $data['payment_method_id'],
                'amount'            => $amount,
                'payment_date'      => $data['payment_date'],
                'bank_reference'    => $data['reference'] ?? null,
                'notes'             => 'تمويل — طلب '.$row->request_number,
                'status'            => 'completed',
                'user_id'           => $request->user()->id,
            ]);

            InstallmentPlan::recalculate($contract);
            DB::table('finance_requests')->where('id', $row->id)->update(['status' => 'paid_off', 'updated_at' => now()]);

            return $payment;
        });

        return back()->with('status', 'تم استلام مبلغ التمويل '.Money::format($payment->amount).' وتسجيله على العقد')
            ->with('receipt_url', route('admin.sale-payments.receipt', $payment));
    }
}
