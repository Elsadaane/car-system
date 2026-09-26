<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SalesContract;
use App\Models\Vehicle;
use App\Support\ContractStatus;
use App\Support\CustomerScore;
use App\Support\InstallmentPlan;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * عقد بيع مركبة لعميل - كاش أو تقسيط.
 *
 *   الإجمالي = السعر − الخصم + الضريبة
 *   المقدم بيتسجل كدفعة أول ما العقد يتعمل
 *   الباقي (لو تقسيط) بيتقسم أقساط بتتولّد لوحدها
 *   كل دفعة بعد كده بتتوزع على الأقساط من الأقدم (InstallmentPlan)
 */
class SalesContractController extends Controller
{
    public const DEFAULT_TERMS_AR = "1. يقر المشتري بأنه عاين المركبة المعاينة التامة النافية للجهالة وقبلها بحالتها الراهنة.\n"
        ."2. يلتزم المشتري بسداد الأقساط في مواعيدها المحددة بجدول الأقساط المرفق.\n"
        ."3. في حال التأخر عن سداد قسطين متتاليين يحق للمعرض المطالبة بكامل المبلغ المتبقي.\n"
        ."4. لا تنتقل ملكية المركبة للمشتري إلا بعد سداد كامل الثمن ما لم يُتفق على غير ذلك.\n"
        ."5. يتحمل المشتري رسوم نقل الملكية والتأمين والمخالفات من تاريخ الاستلام.";

    public const DEFAULT_TERMS_EN = "1. The buyer acknowledges having inspected the vehicle and accepts it in its current condition.\n"
        ."2. The buyer shall pay the installments on their due dates as per the attached schedule.\n"
        ."3. If two consecutive installments are overdue, the showroom may demand the full remaining balance.\n"
        ."4. Ownership passes to the buyer only after full payment unless otherwise agreed.\n"
        ."5. The buyer bears transfer, insurance and traffic-fine costs from the delivery date.";

    public const PAYMENT_TYPES = ['cash' => 'كاش', 'installments' => 'تقسيط'];

    public const INTERVALS = [1 => 'كل شهر', 2 => 'كل شهرين', 3 => 'كل 3 شهور', 6 => 'كل 6 شهور'];

    private const MESSAGES = [
        'required'    => 'الحقل ده مطلوب.',
        'required_if' => 'الحقل ده مطلوب.',
        'unique'      => 'الرقم ده مستخدم قبل كده.',
        'exists'      => 'الاختيار مش صحيح.',
        'numeric'     => 'لازم يكون رقم.',
        'integer'     => 'لازم يكون رقم صحيح.',
        'min'         => 'أقل من المسموح.',
        'max'         => 'أكبر من المسموح.',
        'date'        => 'لازم يكون تاريخ.',
        'after_or_equal'  => 'لازم يكون بعد تاريخ العقد أو نفس اليوم.',
        'before_or_equal' => 'مينفعش تاريخ في المستقبل.',
        'vehicle_id.exists' => 'العربية دي مش متاحة للبيع.',
    ];

    // =========================================================
    //  القايمة
    // =========================================================
    public function index(Request $request): View
    {
        $f = [
            'q'      => trim((string) $request->query('q')),
            'status' => $request->query('status') ?: null,
            'type'   => in_array($request->query('type'), ['cash', 'installments'], true) ? $request->query('type') : null,
            'flag'   => in_array($request->query('flag'), ['overdue', 'unsigned', 'not_transferred', 'not_delivered'], true) ? $request->query('flag') : null,
            'from'   => $request->date('from')?->toDateString(),
            'to'     => $request->date('to')?->toDateString(),
        ];

        $today = today()->toDateString();

        $contracts = $this->baseQuery()
            ->addSelect([
                'overdue_count' => DB::table('installments')->selectRaw('count(*)')
                    ->whereColumn('installments.sales_contract_id', 'sales_contracts.id')
                    ->whereNull('installments.deleted_at')
                    ->where('installments.is_paid', false)->where('installments.due_date', '<', $today),
                'next_due' => DB::table('installments')->select('due_date')
                    ->whereColumn('installments.sales_contract_id', 'sales_contracts.id')
                    ->whereNull('installments.deleted_at')
                    ->where('installments.is_paid', false)->orderBy('due_date')->limit(1),
                'pending_cancel' => DB::table('cancellation_requests')->selectRaw('count(*)')
                    ->whereColumn('cancellation_requests.sales_contract_id', 'sales_contracts.id')
                    ->where('cancellation_requests.status', 'pending')->whereNull('cancellation_requests.deleted_at'),
            ])
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('sales_contracts.contract_number', 'like', "%{$f['q']}%")
                ->orWhere('customers.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('customers.phone', 'like', "%{$f['q']}%")
                ->orWhere('customers.national_id', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.vehicle_code', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.plate_number', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.chassis_number', 'like', "%{$f['q']}%")))
            ->when($f['status'], fn ($q, $v) => $q->where('contract_statuses.code', $v))
            ->tap(fn ($q) => \App\Support\Access::scope($q, 'sales_contracts.branch_id'))
            ->when($f['type'], fn ($q, $v) => $q->where('sales_contracts.payment_type', $v))
            ->when($f['from'], fn ($q, $v) => $q->where('sales_contracts.contract_date', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->where('sales_contracts.contract_date', '<=', $v))
            ->when($f['flag'] === 'overdue', fn ($q) => $q->whereExists(fn ($s) => $s->from('installments')
                ->whereColumn('installments.sales_contract_id', 'sales_contracts.id')->whereNull('installments.deleted_at')
                ->where('is_paid', false)->where('due_date', '<', $today)))
            ->when($f['flag'] === 'unsigned', fn ($q) => $q->where(fn ($w) => $w->whereNull('sales_contracts.customer_signature')->orWhereNull('sales_contracts.seller_signature')))
            ->when($f['flag'] === 'not_transferred', fn ($q) => $q->whereNull('sales_contracts.ownership_transferred_at'))
            ->when($f['flag'] === 'not_delivered', fn ($q) => $q->whereNull('sales_contracts.delivery_date'))
            ->when($f['flag'], fn ($q) => $q->where('sales_contracts.status_id', '!=', ContractStatus::id('cancelled')))
            ->latest('sales_contracts.contract_date')
            ->latest('sales_contracts.id')
            ->paginate(15)
            ->withQueryString();

        $notCancelled = fn () => SalesContract::where('status_id', '!=', ContractStatus::id('cancelled'));

        $totals = [
            'month_sales' => (float) $notCancelled()->where('contract_date', '>=', now()->startOfMonth()->toDateString())->sum('total_amount'),
            'month_count' => $notCancelled()->where('contract_date', '>=', now()->startOfMonth()->toDateString())->count(),
            'remaining'   => (float) $notCancelled()->sum('remaining_amount'),
            'collected_month' => (float) Payment::where('payable_type', (new SalesContract)->getMorphClass())
                ->where('status', 'completed')->whereNull('deleted_at')
                ->where('payment_date', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'overdue' => (float) DB::table('installments')
                ->join('sales_contracts', 'sales_contracts.id', '=', 'installments.sales_contract_id')
                ->whereNull('installments.deleted_at')->whereNull('sales_contracts.deleted_at')
                ->where('installments.is_paid', false)->where('installments.due_date', '<', $today)
                ->sum(DB::raw('installments.amount - installments.paid_amount')),
        ];

        return view('admin.sales-contracts.index', [
            'contracts' => $contracts,
            'totals'    => $totals,
            'filters'   => $f,
            'statuses'  => DB::table('contract_statuses')->whereIn('code', ['active', 'completed', 'cancelled'])->pluckLocal('name', 'code'),
        ]);
    }

    // =========================================================
    //  التفاصيل والطباعة
    // =========================================================
    public function show(SalesContract $salesContract): View
    {
        $contract = $this->fullQuery($salesContract);

        $history = DB::table('activity_logs')
            ->where('subject_type', $salesContract->getMorphClass())
            ->where('subject_id', $salesContract->id)
            ->leftJoin('users', 'users.id', '=', 'activity_logs.causer_id')
            ->select('activity_logs.action', 'activity_logs.properties', 'activity_logs.description', 'activity_logs.created_at', 'users.name as user_name')
            ->latest('activity_logs.created_at')
            ->get();

        return view('admin.sales-contracts.show', [
            'contract'       => $contract,
            'model'          => $salesContract,
            'installments'   => $this->installments($salesContract),
            'payments'       => $this->payments($salesContract),
            'history'        => $history,
            'delivery'       => DB::table('delivery_contracts')->where('sales_contract_id', $salesContract->id)->whereNull('deleted_at')->latest('id')->first(),
            'cancellation'   => DB::table('cancellation_requests')->where('sales_contract_id', $salesContract->id)->whereNull('deleted_at')
                ->leftJoin('users', 'users.id', '=', 'cancellation_requests.user_id')
                ->select('cancellation_requests.*', 'users.name as user_name')->latest('cancellation_requests.id')->first(),
            'signLink'       => $salesContract->customer_signature ? null : self::signLink($salesContract),
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluckLocal('name', 'id'),
            'editableMoney'  => $this->moneyEditable($salesContract),
            'financeRequests' => DB::table('finance_requests')->where('sales_contract_id', $salesContract->id)->whereNull('finance_requests.deleted_at')
                ->leftJoin('finance_companies', 'finance_companies.id', '=', 'finance_requests.finance_company_id')
                ->select('finance_requests.*', \App\Support\Lang::sel('finance_companies.name_ar as company_name'))->latest('finance_requests.id')->get(),
            'financeCompanies' => DB::table('finance_companies')->where('is_active', true)->orderBy('name_ar')->pluckLocal('name', 'id'),
            'cheques'        => \App\Support\Cheques::table()
                ? DB::table('cheques')->where('sales_contract_id', $salesContract->id)->whereNull('deleted_at')->orderBy('due_date')->get()
                : collect(),
        ]);
    }

    public function print(SalesContract $salesContract): View
    {
        app()->setLocale(request('lang') === 'en' ? 'en' : 'ar'); // لغة العقد المطبوع (مستقلة عن لغة اللوحة)

        return view('admin.sales-contracts.print', [
            'contract'     => $this->fullQuery($salesContract),
            'installments' => $this->installments($salesContract),
            'company'      => \App\Support\Company::info(),
            'lang'         => request('lang') === 'en' ? 'en' : 'ar',
        ]);
    }

    // =========================================================
    //  إضافة وتعديل
    // =========================================================
    public function create(Request $request): View
    {
        $contract = new SalesContract;
        $vehicle = $request->integer('vehicle') ? Vehicle::find($request->integer('vehicle')) : null;
        $price = $vehicle ? (float) ($vehicle->selling_price ?? 0) : null;
        $discount = $vehicle && $vehicle->discount_price && (float) $vehicle->discount_price < (float) $vehicle->selling_price
            ? Money::round((float) $vehicle->selling_price - (float) $vehicle->discount_price) : 0;


        $contract->forceFill([
            'contract_number' => $this->nextNumber(),
            'contract_date'   => today()->toDateString(),
            'customer_id'     => $request->integer('customer') ?: null,
            'vehicle_id'      => $vehicle?->id,
            'sale_price'      => $price ?: null,
            'discount_amount' => $discount,
            'tax_percent'     => 0,
            'payment_type'    => 'cash',
            'down_payment'    => 0,
            'installments_count'   => 12,
            'installment_interval' => 1,
            'first_due_date'  => today()->addMonthNoOverflow()->toDateString(),
            'terms_ar'        => \App\Support\Company::get('sales_contract_terms_ar', self::DEFAULT_TERMS_AR),
            'terms_en'        => \App\Support\Company::get('sales_contract_terms_en', self::DEFAULT_TERMS_EN),
        ]);

        return view('admin.sales-contracts.form', ['contract' => $contract, 'editableMoney' => true] + $this->formOptions(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $contract = new SalesContract;
        $data = $this->validated($request, $contract, true);
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        DB::transaction(function () use ($request, $contract, $data, $vehicle) {
            $down = $data['_down'];
            unset($data['_down']);

            $contract->forceFill($data + [
                'branch_id'        => $vehicle->branch_id ?: 1,
                'paid_amount'      => 0,
                'remaining_amount' => $data['total_amount'],
                'status_id'        => ContractStatus::id('active'),
                'user_id'          => $request->user()->id,
                'customer_signature' => Media::storeSignature($request->input('customer_signature_data')),
                'seller_signature'   => Media::storeSignature($request->input('seller_signature_data')),
            ])->save();

            $this->recordDownPayment($request, $contract, $down);
            InstallmentPlan::generate($contract);
            InstallmentPlan::recalculate($contract);

            $vehicle->forceFill(['status' => 'sold', 'is_published' => false])->save();
            InstallmentPlan::refreshCustomer((int) $contract->customer_id);
            $this->log($request, $contract, 'sale_created', ['total' => (float) $contract->total_amount], 'إنشاء عقد البيع');
        });

        return redirect()->route('admin.sales-contracts.show', $contract)
            ->with('status', 'تم إنشاء العقد '.$contract->contract_number.' والعربية اتعلّمت "مباعة"');
    }

    public function edit(SalesContract $salesContract): View
    {
        abort_if($this->isCancelled($salesContract), 403, 'العقد ملغي');

        return view('admin.sales-contracts.form', [
            'contract'      => $salesContract,
            'editableMoney' => $this->moneyEditable($salesContract),
            'downPayment'   => $salesContract->down_payment_id ? DB::table('payments')->where('id', $salesContract->down_payment_id)->first() : null,
        ] + $this->formOptions($salesContract));
    }

    public function update(Request $request, SalesContract $salesContract): RedirectResponse
    {
        if ($this->isCancelled($salesContract)) {
            return back()->with('error', 'العقد ملغي، مينفعش يتعدل.');
        }

        $moneyEditable = $this->moneyEditable($salesContract);
        $data = $this->validated($request, $salesContract, $moneyEditable);

        foreach (['customer', 'seller'] as $party) {
            if ($path = Media::storeSignature($request->input($party.'_signature_data'))) {
                Media::delete($salesContract->{$party.'_signature'});
                $data[$party.'_signature'] = $path;
            }
        }

        DB::transaction(function () use ($request, $salesContract, $data, $moneyEditable) {
            $oldVehicle = (int) $salesContract->vehicle_id;
            $oldCustomer = (int) $salesContract->customer_id;
            $down = $data['_down'] ?? null;
            unset($data['_down']);

            $salesContract->forceFill($data)->save();

            if ($moneyEditable) {
                // مفيش دفعات غير المقدم ← نعيد المقدم والأقساط من الأول
                if ($salesContract->down_payment_id) {
                    DB::table('payments')->where('id', $salesContract->down_payment_id)->delete();
                    $salesContract->forceFill(['down_payment_id' => null])->save();
                }
                $this->recordDownPayment($request, $salesContract, $down);
                InstallmentPlan::generate($salesContract);
            }
            InstallmentPlan::recalculate($salesContract);

            if ($oldVehicle !== (int) $salesContract->vehicle_id) {
                Vehicle::whereKey($oldVehicle)->update(['status' => 'available']);
                Vehicle::whereKey($salesContract->vehicle_id)->update(['status' => 'sold', 'is_published' => false]);
            }
            DB::table('installments')->where('sales_contract_id', $salesContract->id)->update(['customer_id' => $salesContract->customer_id]);
            InstallmentPlan::refreshCustomer((int) $salesContract->customer_id);
            if ($oldCustomer !== (int) $salesContract->customer_id) {
                InstallmentPlan::refreshCustomer($oldCustomer);
            }
            $this->log($request, $salesContract, 'sale_updated', [], 'تعديل العقد');
        });

        return redirect()->route('admin.sales-contracts.show', $salesContract)->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(SalesContract $salesContract): RedirectResponse
    {
        $hasPayments = Payment::where('payable_type', $salesContract->getMorphClass())
            ->where('payable_id', $salesContract->id)
            ->whereNull('deleted_at')
            ->where('id', '!=', (int) $salesContract->down_payment_id)
            ->exists();

        if ($hasPayments) {
            return back()->with('error', 'العقد عليه دفعات بعد المقدم. اعمل "إلغاء العقد" بدل المسح عشان الحسابات تفضل مظبوطة.');
        }

        DB::transaction(function () use ($salesContract) {
            if ($salesContract->down_payment_id) {
                DB::table('payments')->where('id', $salesContract->down_payment_id)->delete();
            }
            DB::table('installments')->where('sales_contract_id', $salesContract->id)->delete();
            Vehicle::whereKey($salesContract->vehicle_id)->update(['status' => 'available']);
            Media::delete($salesContract->customer_signature);
            Media::delete($salesContract->seller_signature);
            $salesContract->delete();
            InstallmentPlan::refreshCustomer((int) $salesContract->customer_id);
        });

        return redirect()->route('admin.sales-contracts.index')->with('status', 'تم مسح العقد والعربية رجعت "متاحة"');
    }

    // =========================================================
    //  عميل سريع من جوه فورم العقد (بيرجع JSON)
    // =========================================================
    public function quickCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar'     => ['required', 'string', 'max:150'],
            'phone'       => ['required', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('customers', 'national_id')],
            'nationality' => ['nullable', 'string', 'max:80'],
        ], self::MESSAGES + ['national_id.unique' => 'الرقم المدني ده متسجل لعميل تاني.']);

        $customer = new Customer;
        $customer->forceFill($data + [
            'customer_code' => 'CUST-'.str_pad((int) DB::table('customers')->max('id') + 1, 4, '0', STR_PAD_LEFT),
            'status'        => 'active',
        ])->save();

        return response()->json(['id' => $customer->id, 'label' => $customer->name_ar.' — '.$customer->phone]);
    }

    // =========================================================
    //  الدفعات
    // =========================================================
    public function storePayment(Request $request, SalesContract $salesContract): RedirectResponse
    {
        if ($this->isCancelled($salesContract)) {
            return back()->with('error', 'العقد ده ملغي.');
        }

        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.001'],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payment_date'      => ['required', 'date', 'before_or_equal:today'],
            'reference'         => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES);

        $payment = DB::transaction(function () use ($request, $salesContract, $data) {
            $locked = SalesContract::whereKey($salesContract->id)->lockForUpdate()->firstOrFail();
            $amount = Money::round($data['amount']);
            $remaining = Money::round($locked->remaining_amount);

            if ($amount > $remaining + 0.0005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من المتبقي على العميل ('.Money::format($remaining).').']);
            }

            $payment = Payment::forceCreate([
                'payment_reference' => $this->paymentReference(),
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

            InstallmentPlan::recalculate($locked);

            return $payment;
        });

        return redirect()->route('admin.sales-contracts.show', $salesContract)
            ->with('status', 'تم تسجيل الدفعة '.Money::format($payment->amount))
            ->with('receipt_url', route('admin.sale-payments.receipt', $payment));
    }

    // =========================================================
    //  التوقيع
    // =========================================================
    public function sign(Request $request, SalesContract $salesContract, string $party): RedirectResponse
    {
        abort_unless(in_array($party, ['customer', 'seller'], true), 404);

        $path = Media::storeSignature($request->input('signature_data'));
        if (! $path) {
            return back()->with('error', 'ارسم التوقيع الأول.');
        }

        Media::delete($salesContract->{$party.'_signature'});
        $salesContract->forceFill([$party.'_signature' => $path])->save();

        return redirect(route('admin.sales-contracts.show', $salesContract).'#signatures')
            ->with('status', $party === 'customer' ? 'تم حفظ توقيع المشتري' : 'تم حفظ توقيع البائع');
    }

    public function publicSignForm(SalesContract $salesContract): View
    {
        return view('public.sales-contract-sign', [
            'contract'     => $this->fullQuery($salesContract),
            'installments' => $this->installments($salesContract),
            'company'      => \App\Support\Company::info(),
            'signed'       => (bool) $salesContract->customer_signature,
        ]);
    }

    public function publicSign(Request $request, SalesContract $salesContract): RedirectResponse
    {
        if ($salesContract->customer_signature) {
            return back()->with('status', 'العقد اتوقّع قبل كده.');
        }
        if ($this->isCancelled($salesContract)) {
            return back()->withErrors(['signature_data' => 'العقد ده اتلغى.']);
        }

        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'لازم توافق على الشروط الأول.']);

        $path = Media::storeSignature($request->input('signature_data'));
        if (! $path) {
            return back()->withErrors(['signature_data' => 'ارسم توقيعك في المربع الأول.']);
        }

        DB::transaction(function () use ($request, $salesContract, $path) {
            $salesContract->forceFill(['customer_signature' => $path])->save();
            $this->log($request, $salesContract, 'sale_signed_remotely', ['party' => 'customer'], 'توقيع المشتري عن طريق الرابط', false);
        });

        return back()->with('status', 'شكراً! تم حفظ توقيعك.');
    }

    public static function signLink(SalesContract $contract): string
    {
        return URL::temporarySignedRoute('public.sales-contracts.sign', now()->addDays(7), ['salesContract' => $contract->id]);
    }

    // =========================================================
    //  نقل الملكية
    // =========================================================
    public function transfer(Request $request, SalesContract $salesContract): RedirectResponse
    {
        if ($this->isCancelled($salesContract)) {
            return back()->with('error', 'العقد ده ملغي.');
        }

        $data = $request->validate([
            'ownership_transferred_at' => ['required', 'date', 'before_or_equal:today'],
            'inspection_due_date'      => ['nullable', 'date', 'after_or_equal:ownership_transferred_at'],
            'new_plate_number'         => ['nullable', 'string', 'max:30', Rule::unique('vehicles', 'plate_number')->ignore($salesContract->vehicle_id)],
            'ownership_notes'          => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES + ['new_plate_number.unique' => 'رقم اللوحة ده متسجل لعربية تانية.']);

        DB::transaction(function () use ($request, $salesContract, $data) {
            $salesContract->forceFill([
                'ownership_transferred_at' => $data['ownership_transferred_at'],
                'inspection_due_date'      => $data['inspection_due_date'] ?? null,
                'ownership_notes'          => $data['ownership_notes'] ?? null,
            ])->save();

            if (! empty($data['new_plate_number'])) {
                Vehicle::whereKey($salesContract->vehicle_id)->update(['plate_number' => $data['new_plate_number']]);
            }

            $this->log($request, $salesContract, 'sale_ownership_transferred', [
                'date' => $data['ownership_transferred_at'],
                'inspection' => $data['inspection_due_date'] ?? null,
                'plate' => $data['new_plate_number'] ?? null,
                'remaining' => (float) $salesContract->remaining_amount,
            ], 'نقل ملكية المركبة');
        });

        return back()->with('status', 'تم تسجيل نقل الملكية');
    }

    // =========================================================
    //  تسليم العربية
    // =========================================================
    public function deliver(Request $request, SalesContract $salesContract): RedirectResponse
    {
        if ($this->isCancelled($salesContract)) {
            return back()->with('error', 'العقد ده ملغي.');
        }

        $data = $request->validate([
            'actual_delivery'   => ['required', 'date'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'received_by'       => ['required', 'string', 'max:150'],
            'delivery_mileage'  => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'delivery_notes'    => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES);

        DB::transaction(function () use ($request, $salesContract, $data) {
            $notes = trim(($data['delivery_mileage'] ?? null) !== null ? 'العداد عند التسليم: '.number_format($data['delivery_mileage']).' كم. ' : '')
                .($data['delivery_notes'] ?? '');

            DB::table('delivery_contracts')->insert([
                'delivery_number'    => 'DLV-'.now()->format('ymd').'-'.str_pad((int) DB::table('delivery_contracts')->max('id') + 1, 4, '0', STR_PAD_LEFT),
                'sales_contract_id'  => $salesContract->id,
                'customer_id'        => $salesContract->customer_id,
                'vehicle_id'         => $salesContract->vehicle_id,
                'scheduled_delivery' => $data['actual_delivery'],
                'actual_delivery'    => $data['actual_delivery'],
                'delivery_location'  => $data['delivery_location'] ?? null,
                'delivery_notes'     => $notes ?: null,
                'delivered_by'       => $request->user()->name,
                'received_by'        => $data['received_by'],
                'status'             => 'delivered',
                'user_id'            => $request->user()->id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $salesContract->forceFill(['delivery_date' => Carbon::parse($data['actual_delivery'])->toDateString()])->save();

            if (($data['delivery_mileage'] ?? null) !== null) {
                Vehicle::whereKey($salesContract->vehicle_id)->update(['mileage' => $data['delivery_mileage']]);
            }

            $this->log($request, $salesContract, 'sale_delivered', ['received_by' => $data['received_by']], 'تسليم المركبة للعميل');
        });

        return back()->with('status', 'تم تسجيل تسليم العربية');
    }

    // =========================================================
    //  الإلغاء: الموظف بيطلب ← الأدمن بيوافق (الأدمن ممكن يلغي على طول)
    // =========================================================
    public function requestCancel(Request $request, SalesContract $salesContract): RedirectResponse
    {
        if ($this->isCancelled($salesContract)) {
            return back()->with('error', 'العقد ملغي بالفعل.');
        }

        $pending = DB::table('cancellation_requests')->where('sales_contract_id', $salesContract->id)
            ->where('status', 'pending')->whereNull('deleted_at')->exists();
        if ($pending) {
            return back()->with('error', 'فيه طلب إلغاء مستني موافقة بالفعل.');
        }

        $paid = (float) $salesContract->paid_amount;
        $data = $request->validate([
            'reason_ar'      => ['required', 'string', 'max:255'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0', 'max:'.$paid],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES + ['penalty_amount.max' => 'الغرامة أكبر من المدفوع ('.Money::format($paid).').']);

        $penalty = Money::round($data['penalty_amount'] ?? 0);
        $isAdmin = \App\Support\Access::can('cancellations.approve');

        DB::transaction(function () use ($request, $salesContract, $data, $paid, $penalty, $isAdmin) {
            $id = DB::table('cancellation_requests')->insertGetId([
                'cancellation_number' => 'CXL-'.now()->format('ymd').'-'.str_pad((int) DB::table('cancellation_requests')->max('id') + 1, 4, '0', STR_PAD_LEFT),
                'sales_contract_id'   => $salesContract->id,
                'customer_id'         => $salesContract->customer_id,
                'cancellation_date'   => today()->toDateString(),
                'reason_ar'           => $data['reason_ar'],
                'additional_notes'    => $data['additional_notes'] ?? null,
                'status'              => 'pending',
                'refund_amount'       => $paid,
                'penalty_amount'      => $penalty,
                'net_refund'          => Money::round($paid - $penalty),
                'user_id'             => $request->user()->id,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $this->log($request, $salesContract, 'sale_cancel_requested', ['reason' => $data['reason_ar'], 'penalty' => $penalty], 'طلب إلغاء العقد');

            if ($isAdmin) {
                $this->applyCancellation($request, $salesContract, $id);
            }
        });

        return back()->with('status', $isAdmin ? 'تم إلغاء العقد والعربية رجعت "متاحة"' : 'تم إرسال طلب الإلغاء للإدارة');
    }

    public function decideCancel(Request $request, SalesContract $salesContract, int $cancellation, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);

        $row = DB::table('cancellation_requests')->where('id', $cancellation)
            ->where('sales_contract_id', $salesContract->id)->where('status', 'pending')->first();
        abort_unless($row, 404);

        $notes = $request->validate(['approval_notes' => ['nullable', 'string', 'max:1000']])['approval_notes'] ?? null;

        DB::transaction(function () use ($request, $salesContract, $row, $decision, $notes) {
            DB::table('cancellation_requests')->where('id', $row->id)->update(['approval_notes' => $notes, 'updated_at' => now()]);

            if ($decision === 'approve') {
                $this->applyCancellation($request, $salesContract, $row->id);
            } else {
                DB::table('cancellation_requests')->where('id', $row->id)->update(['status' => 'rejected']);
                $this->log($request, $salesContract, 'sale_cancel_rejected', ['notes' => $notes], 'رفض طلب الإلغاء');
            }
        });

        return back()->with('status', $decision === 'approve' ? 'تم إلغاء العقد' : 'تم رفض طلب الإلغاء');
    }

    private function applyCancellation(Request $request, SalesContract $contract, int $cancellationId): void
    {
        DB::table('cancellation_requests')->where('id', $cancellationId)->update([
            'status' => 'approved', 'refund_date' => today()->toDateString(), 'updated_at' => now(),
        ]);

        // الأقساط اللي مااتدفعش منها حاجة بتتشال، واللي اتدفع جزء منها بتتقفل على المدفوع
        // عشان تقييم العميل مايتحسبش عليه أقساط عقد ملغي
        DB::table('installments')->where('sales_contract_id', $contract->id)->where('paid_amount', '<=', 0)->delete();
        DB::table('installments')->where('sales_contract_id', $contract->id)->where('is_paid', false)->update([
            'amount' => DB::raw('paid_amount'), 'is_paid' => true, 'status' => 'cancelled',
            'actual_payment_date' => DB::raw('due_date'), 'updated_at' => now(),
        ]);

        $contract->forceFill(['status_id' => ContractStatus::id('cancelled'), 'remaining_amount' => 0])->save();

        $stillSold = SalesContract::where('vehicle_id', $contract->vehicle_id)->whereKeyNot($contract->id)
            ->where('status_id', '!=', ContractStatus::id('cancelled'))->exists();
        if (! $stillSold) {
            Vehicle::whereKey($contract->vehicle_id)->update(['status' => 'available']);
        }

        InstallmentPlan::refreshCustomer((int) $contract->customer_id);
        $this->log($request, $contract, 'sale_cancelled', [], 'إلغاء العقد');
    }

    // =========================================================
    //  أدوات داخلية
    // =========================================================
    private function validated(Request $request, SalesContract $contract, bool $moneyEditable): array
    {
        $rules = [
            'contract_number' => ['required', 'string', 'max:50', Rule::unique('sales_contracts', 'contract_number')->ignore($contract->id)],
            'contract_date'   => ['required', 'date'],
            'has_guarantor'   => ['nullable', 'boolean'],
            'guarantor_name'  => ['nullable', 'required_if:has_guarantor,1', 'string', 'max:150'],
            'guarantor_phone' => ['nullable', 'required_if:has_guarantor,1', 'string', 'max:30'],
            'guarantor_national_id' => ['nullable', 'string', 'max:50'],
            'guarantor_address'     => ['nullable', 'string', 'max:255'],
            'terms_ar'        => ['nullable', 'string', 'max:20000'],
            'terms_en'        => ['nullable', 'string', 'max:20000'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
        if (self::hasRep()) {
            $rules['sales_rep_id'] = ['nullable', Rule::exists('users', 'id')->where('is_active', true)];
        }

        if ($moneyEditable) {
            $rules += [
                'customer_id'     => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
                'vehicle_id'      => ['required', Rule::exists('vehicles', 'id')->whereNull('deleted_at')
                    ->where(fn ($q) => $q->whereIn('status', ['available', 'reserved'])
                        ->when($contract->exists, fn ($w) => $w->orWhere('id', $contract->vehicle_id)))],
                'sale_price'      => ['required', 'numeric', 'min:0.001', 'max:999999999'],
                'discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:sale_price'],
                'tax_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
                'payment_type'    => ['required', Rule::in(array_keys(self::PAYMENT_TYPES))],
                'down_payment'    => ['nullable', 'numeric', 'min:0', 'max:999999999'],
                'down_payment_method_id' => ['nullable', Rule::exists('payment_methods', 'id')->where('is_active', true)],
                'down_payment_date'      => ['nullable', 'date', 'before_or_equal:today'],
                'down_payment_reference' => ['nullable', 'string', 'max:100'],
                'installments_count'   => ['nullable', 'required_if:payment_type,installments', 'integer', 'min:1', 'max:120'],
                'installment_interval' => ['nullable', 'required_if:payment_type,installments', Rule::in(array_keys(self::INTERVALS))],
                'first_due_date'       => ['nullable', 'required_if:payment_type,installments', 'date', 'after_or_equal:contract_date'],
            ];
        }

        $data = $request->validate($rules, self::MESSAGES + [
            'discount_amount.lte' => 'الخصم أكبر من السعر.',
            'down_payment_date.before_or_equal' => 'تاريخ المقدم مينفعش يكون في المستقبل.',
        ]);

        if (isset($data['customer_id']) && (int) $data['customer_id'] !== (int) $contract->customer_id
            && Customer::whereKey($data['customer_id'])->value('status') === 'blocked') {
            throw ValidationException::withMessages(['customer_id' => 'العميل ده محظور. شيل الحظر من ملفه الأول.']);
        }

        $data['has_guarantor'] = $request->boolean('has_guarantor');
        if (! $data['has_guarantor']) {
            $data = array_merge($data, ['guarantor_name' => null, 'guarantor_phone' => null, 'guarantor_national_id' => null, 'guarantor_address' => null]);
        }

        if (! $moneyEditable) {
            return $data;
        }

        $taken = SalesContract::where('vehicle_id', $data['vehicle_id'])
            ->where('status_id', '!=', ContractStatus::id('cancelled'))
            ->when($contract->exists, fn ($q) => $q->whereKeyNot($contract->id))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['vehicle_id' => 'العربية دي عليها عقد بيع بالفعل.']);
        }

        // عربية تفويض: مينفعش تتباع بأقل من الحد الأدنى اللي المالك وافق عليه
        if (\Illuminate\Support\Facades\Schema::hasColumn('dealer_contracts', 'min_price')) {
            $min = (float) DB::table('dealer_contracts')->where('vehicle_id', $data['vehicle_id'])->whereNull('deleted_at')
                ->where('status_id', ContractStatus::id('active'))->value('min_price');
            $net = (float) $data['sale_price'] - (float) ($data['discount_amount'] ?? 0);
            if ($min > 0 && $net + 0.0005 < $min) {
                throw ValidationException::withMessages(['sale_price' => 'العربية دي تفويض، وأقل سعر وافق عليه المالك '.Money::format($min).'. اطلب من المالك يغيّر السعر من عقد التفويض الأول.']);
            }
        }

        $price = Money::round($data['sale_price']);
        $discount = Money::round($data['discount_amount'] ?? 0);
        $taxPercent = (float) ($data['tax_percent'] ?? 0);
        $tax = Money::round(($price - $discount) * $taxPercent / 100);
        $total = Money::round($price - $discount + $tax);
        $down = Money::round($data['down_payment'] ?? 0);

        if ($total <= 0) {
            throw ValidationException::withMessages(['discount_amount' => 'الإجمالي لازم يكون أكبر من صفر.']);
        }
        if ($down > 0 && empty($data['down_payment_method_id'])) {
            throw ValidationException::withMessages(['down_payment_method_id' => 'اختار طريقة دفع المقدم.']);
        }
        if ($down > $total) {
            throw ValidationException::withMessages(['down_payment' => 'المقدم أكبر من إجمالي العقد ('.Money::format($total).').']);
        }

        $installments = $data['payment_type'] === 'installments';
        if ($installments && $total - $down <= 0) {
            throw ValidationException::withMessages(['down_payment' => 'المقدم مغطي العقد كله - اختار "كاش".']);
        }

        return [
            'contract_number' => $data['contract_number'],
            'contract_date'   => $data['contract_date'],
            'customer_id'     => $data['customer_id'],
            'vehicle_id'      => $data['vehicle_id'],
            'sale_price'      => $price,
            'discount_amount' => $discount,
            'tax_percent'     => $taxPercent,
            'tax_amount'      => $tax,
            'total_amount'    => $total,
            'payment_type'    => $data['payment_type'],
            'down_payment'    => $down,
            'installments_count'   => $installments ? (int) $data['installments_count'] : null,
            'installment_interval' => $installments ? (int) $data['installment_interval'] : 1,
            'first_due_date'  => $installments ? $data['first_due_date'] : null,
            'has_guarantor'   => $data['has_guarantor'],
            'guarantor_name'  => $data['guarantor_name'] ?? null,
            'guarantor_phone' => $data['guarantor_phone'] ?? null,
            'guarantor_national_id' => $data['guarantor_national_id'] ?? null,
            'guarantor_address'     => $data['guarantor_address'] ?? null,
            'terms_ar'        => \App\Support\RichText::clean($data['terms_ar'] ?? null, 'terms'),
            'terms_en'        => \App\Support\RichText::clean($data['terms_en'] ?? null, 'terms'),
            'notes'           => $data['notes'] ?? null,
            '_down' => $down > 0 ? [
                'amount'    => $down,
                'method'    => $data['down_payment_method_id'],
                'date'      => $data['down_payment_date'] ?? $data['contract_date'],
                'reference' => $data['down_payment_reference'] ?? null,
            ] : null,
        ];
    }

    private function recordDownPayment(Request $request, SalesContract $contract, ?array $down): void
    {
        if (! $down) {
            return;
        }

        $payment = Payment::forceCreate([
            'payment_reference' => $this->paymentReference(),
            'payable_type'      => $contract->getMorphClass(),
            'payable_id'        => $contract->id,
            'payment_method_id' => $down['method'],
            'amount'            => $down['amount'],
            'payment_date'      => Carbon::parse($down['date'])->min(today())->toDateString(),
            'bank_reference'    => $down['reference'],
            'notes'             => $contract->payment_type === 'installments' ? 'مقدم العقد' : 'دفعة عند التعاقد',
            'status'            => 'completed',
            'user_id'           => $request->user()->id,
        ]);

        $contract->forceFill(['down_payment_id' => $payment->id])->save();
    }

    /** الفلوس (السعر/العميل/العربية/الأقساط) بتتعدل بس لو مفيش دفعات غير المقدم */
    public static function hasRep(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('sales_contracts', 'sales_rep_id');
    }

    private function moneyEditable(SalesContract $contract): bool
    {
        if (! $contract->exists) {
            return true;
        }

        return ! Payment::where('payable_type', $contract->getMorphClass())
            ->where('payable_id', $contract->id)
            ->whereNull('deleted_at')
            ->where('id', '!=', (int) $contract->down_payment_id)
            ->exists();
    }

    private function isCancelled(SalesContract $contract): bool
    {
        return (int) $contract->status_id === ContractStatus::id('cancelled');
    }

    private function log(Request $request, SalesContract $contract, string $action, array $props, string $description, bool $withCauser = true): void
    {
        DB::table('activity_logs')->insert([
            'action'       => $action,
            'subject_type' => $contract->getMorphClass(),
            'subject_id'   => $contract->id,
            'causer_type'  => $withCauser && $request->user() ? $request->user()->getMorphClass() : null,
            'causer_id'    => $withCauser ? $request->user()?->id : null,
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
        return SalesContract::query()
            ->leftJoin('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('contract_statuses', 'contract_statuses.id', '=', 'sales_contracts.status_id')
            ->leftJoin('users', 'users.id', '=', 'sales_contracts.user_id')
            ->select('sales_contracts.*',
                \App\Support\Lang::sel('customers.name_ar as customer_name'), 'customers.phone as customer_phone', 'customers.customer_code',
                'vehicles.vehicle_code', 'vehicles.plate_number', 'vehicles.manufacturing_year', 'vehicles.images as vehicle_images',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'),
                'contract_statuses.code as status_code', \App\Support\Lang::sel('contract_statuses.name_ar as status_name'),
                'users.name as user_name')
            ->when(self::hasRep(), fn ($q) => $q->leftJoin('users as rep', 'rep.id', '=', 'sales_contracts.sales_rep_id')->addSelect('rep.name as rep_name'));
    }

    private function fullQuery(SalesContract $contract)
    {
        return $this->baseQuery()->where('sales_contracts.id', $contract->id)
            ->leftJoin('branches', 'branches.id', '=', 'sales_contracts.branch_id')
            ->addSelect('vehicles.chassis_number', 'vehicles.engine_number', 'vehicles.color', 'vehicles.mileage', 'vehicles.status as vehicle_status',
                'customers.national_id as customer_national_id', 'customers.nationality as customer_nationality',
                'customers.address as customer_address', 'customers.email as customer_email', 'customers.name_en as customer_name_en',
                'car_brands.name_en as brand_name_en', 'car_models.name_en as model_name_en', \App\Support\Lang::sel('branches.name_ar as branch_name'))
            ->firstOrFail();
    }

    private function installments(SalesContract $contract)
    {
        return DB::table('installments')->where('sales_contract_id', $contract->id)
            ->whereNull('deleted_at')->orderBy('installment_sequence')->get();
    }

    private function payments(SalesContract $contract)
    {
        return Payment::query()
            ->where('payable_type', $contract->getMorphClass())
            ->where('payable_id', $contract->id)
            ->whereNull('payments.deleted_at')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->select('payments.*', \App\Support\Lang::sel('payment_methods.name_ar as method_name'), 'users.name as user_name',
                DB::raw('(select group_concat(i.installment_sequence order by i.installment_sequence) from installment_payments ip join installments i on i.id = ip.installment_id where ip.payment_id = payments.id) as covers'))
            ->orderBy('payment_date')->orderBy('payments.id')
            ->get();
    }

    private function formOptions(?SalesContract $current): array
    {
        $vehicles = Vehicle::query()
            ->where(fn ($q) => $q->whereIn('vehicles.status', ['available', 'reserved'])
                ->when($current, fn ($w) => $w->orWhere('vehicles.id', $current->vehicle_id)))
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('branches', 'branches.id', '=', 'vehicles.branch_id')
            ->select('vehicles.id', 'vehicles.vehicle_code', 'vehicles.manufacturing_year', 'vehicles.color', 'vehicles.status',
                'vehicles.plate_number', 'vehicles.selling_price', 'vehicles.discount_price', 'vehicles.images',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'), \App\Support\Lang::sel('branches.name_ar as branch_name'))
            ->orderBy('car_brands.name_ar')
            ->get();

        // تقييم العملاء اللي عندهم أقساط - بيظهر وقت اختيار العميل
        $today = today()->toDateString();
        $stats = DB::table('installments')->whereNull('deleted_at')
            ->groupBy('customer_id')
            ->select('customer_id',
                DB::raw('sum(case when is_paid = 1 then 1 else 0 end) as paid'),
                DB::raw('sum(case when is_paid = 1 and actual_payment_date <= due_date then 1 else 0 end) as on_time'),
                DB::raw("sum(case when is_paid = 0 and due_date < '{$today}' then 1 else 0 end) as overdue"),
                DB::raw('coalesce(avg(case when is_paid = 1 then greatest(datediff(actual_payment_date, due_date), 0) end), 0) as avg_late'))
            ->get()->keyBy('customer_id');
        $contractsCount = DB::table('sales_contracts')->whereNull('deleted_at')->groupBy('customer_id')
            ->select('customer_id', DB::raw('count(*) as c'))->pluck('c', 'customer_id');

        $customers = Customer::where(fn ($q) => $q->where('status', '!=', 'blocked')
                ->when($current, fn ($w) => $w->orWhere('id', $current->customer_id)))
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'phone', 'national_id'])
            ->map(function ($c) use ($stats, $contractsCount) {
                $s = $stats[$c->id] ?? null;
                $c->score = CustomerScore::calculate((int) ($s->paid ?? 0), (int) ($s->on_time ?? 0), (int) ($s->overdue ?? 0), (float) ($s->avg_late ?? 0), (int) ($contractsCount[$c->id] ?? 0));

                return $c;
            });

        return [
            'customers'      => $customers,
            'vehicles'       => $vehicles,
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluckLocal('name', 'id'),
            'reps'           => self::hasRep()
                ? DB::table('users')->where('is_active', true)->whereIn('role', ['staff', 'admin'])
                    ->tap(fn ($q) => \App\Support\Access::branchIds() === null ? $q : $q->where(fn ($w) => $w->whereIn('branch_id', \App\Support\Access::branchIds())->orWhere('id', auth()->id())))
                    ->orderBy('name')->pluck('name', 'id')
                : collect(),
        ];
    }

    private function paymentReference(): string
    {
        return 'SPAY-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
    }

    private function nextNumber(): string
    {
        $prefix = 'SA-'.now()->format('Ymd').'-';
        $last = DB::table('sales_contracts')->where('contract_number', 'like', $prefix.'%')->orderByDesc('contract_number')->value('contract_number');

        return $prefix.str_pad($last ? ((int) Str::afterLast($last, '-')) + 1 : 1, 4, '0', STR_PAD_LEFT);
    }
}
