<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DealerContract;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * عقد تفويض بيع مركبة:
 * صاحب العربية (مورد/فرد) بيسيب عربيته في المعرض يبيعها بالنيابة عنه.
 *
 *   المستحق للمالك = السعر المتفق عليه + الضريبة − عمولة المعرض
 *   المعرض بيدفعله على دفعات لحد ما المتبقي يبقى صفر ← العقد يبقى "مكتمل"
 */
class DealerContractController extends Controller
{
    public const DEFAULT_TERMS = "1. يفوّض المالك المعرض ببيع المركبة الموضحة أعلاه بالسعر المتفق عليه.\n"
        ."2. يقر المالك بأن المركبة خالية من أي رهن أو حجز أو مخالفات، ويتحمل مسؤولية أي مطالبات سابقة لتاريخ العقد.\n"
        ."3. يستحق المعرض العمولة المتفق عليها عند إتمام البيع.\n"
        ."4. يلتزم المعرض بسداد المستحق للمالك وفق الدفعات المسجلة في هذا العقد.\n"
        ."5. لا يحق للمالك سحب المركبة خلال مدة العقد إلا بعد الاتفاق مع إدارة المعرض.";

    public const DEFAULT_TERMS_EN = "1. The owner authorizes the showroom to sell the vehicle described above at the agreed price.\n"
        ."2. The owner confirms the vehicle is free of any mortgage, seizure or violations, and bears any claims prior to the contract date.\n"
        ."3. The showroom is entitled to the agreed commission upon completion of the sale.\n"
        ."4. The showroom shall pay the amount due to the owner according to the payments recorded in this contract.\n"
        ."5. The owner may not withdraw the vehicle during the contract period except by agreement with the showroom management.";

    private const MESSAGES = [
        'required' => 'الحقل ده مطلوب.',
        'unique'   => 'الرقم ده مستخدم قبل كده.',
        'exists'   => 'الاختيار مش صحيح.',
        'numeric'  => 'لازم يكون رقم.',
        'min'      => 'أقل من المسموح.',
        'max'      => 'أكبر من المسموح.',
        'date'     => 'لازم يكون تاريخ.',
        'after_or_equal'  => 'لازم يكون بعد تاريخ العقد.',
        'before_or_equal' => 'مينفعش تاريخ في المستقبل.',
        'vehicle_id.exists' => 'العربية دي مش تابعة للمورد المختار أو عليها عقد تفويض مفتوح.',
        'min_price.lte'     => 'الحد الأدنى لازم يكون أقل من أو يساوي السعر المتفق عليه.',
        'commission_rate.required_if' => 'اكتب نسبة العمولة.',
    ];

    // =========================================================
    //  القايمة
    // =========================================================
    public function index(Request $request): View
    {
        $f = [
            'q'        => trim((string) $request->query('q')),
            'supplier' => $request->integer('supplier') ?: null,
            'status'   => $request->query('status') ?: null,
            'from'     => $request->date('from')?->toDateString(),
            'to'       => $request->date('to')?->toDateString(),
        ];

        $contracts = $this->baseQuery()
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('dealer_contracts.contract_number', 'like', "%{$f['q']}%")
                ->orWhere('suppliers.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('suppliers.phone', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.vehicle_code', 'like', "%{$f['q']}%")
                ->orWhere('vehicles.plate_number', 'like', "%{$f['q']}%")))
            ->when($f['supplier'], fn ($q, $v) => $q->where('dealer_contracts.supplier_id', $v))
            ->tap(fn ($q) => \App\Support\Access::scopeVia($q, 'dealer_contracts.vehicle_id', 'vehicles'))
            ->when($f['status'], fn ($q, $v) => $q->where('contract_statuses.code', $v))
            ->when($f['from'], fn ($q, $v) => $q->where('dealer_contracts.contract_date', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->where('dealer_contracts.contract_date', '<=', $v))
            ->latest('dealer_contracts.contract_date')
            ->latest('dealer_contracts.id')
            ->paginate(15)
            ->withQueryString();

        $activeId = $this->statusId('active');

        $totals = [
            'active'     => DealerContract::where('status_id', $activeId)->count(),
            'remaining'  => (float) DealerContract::where('status_id', $activeId)->sum('remaining_amount'),
            'paid_month' => (float) Payment::where('payable_type', (new DealerContract)->getMorphClass())
                ->where('payment_date', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'commission' => (float) DealerContract::where('status_id', '!=', $this->statusId('cancelled'))->sum('commission_amount'),
        ];

        return view('admin.dealer-contracts.index', [
            'contracts' => $contracts,
            'totals'    => $totals,
            'filters'   => $f,
            'suppliers' => Supplier::orderBy('name_ar')->pluckLocal('name', 'id'),
            'statuses'  => DB::table('contract_statuses')->whereIn('code', ['active', 'completed', 'cancelled'])->pluckLocal('name', 'code'),
        ]);
    }

    // =========================================================
    //  التفاصيل
    // =========================================================
    public function show(DealerContract $dealerContract): View
    {
        $contract = $this->baseQuery()->where('dealer_contracts.id', $dealerContract->id)
            ->addSelect('vehicles.chassis_number', 'vehicles.engine_number', 'vehicles.color', 'vehicles.mileage',
                'suppliers.national_id as supplier_national_id', 'suppliers.iban_number', 'suppliers.bank_name')
            ->firstOrFail();

        $history = DB::table('activity_logs')
            ->where('subject_type', $dealerContract->getMorphClass())
            ->where('subject_id', $dealerContract->id)
            ->leftJoin('users', 'users.id', '=', 'activity_logs.causer_id')
            ->select('activity_logs.action', 'activity_logs.properties', 'activity_logs.created_at', 'activity_logs.ip_address', 'users.name as user_name')
            ->latest('activity_logs.created_at')
            ->get();

        return view('admin.dealer-contracts.show', [
            'contract'       => $contract,
            'model'          => $dealerContract,
            'history'        => $history,
            'vehicleSold'    => \App\Models\SalesContract::where('vehicle_id', $dealerContract->vehicle_id)->exists(),
            'signLink'       => $dealerContract->owner_signature ? null : self::signLink($dealerContract),
            'showroomLink'   => $dealerContract->showroom_signature ? null : self::signLink($dealerContract, 'showroom'),
            'priceRequests'  => $this->priceRequests($dealerContract),
            'payments'       => $this->payments($dealerContract),
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->pluckLocal('name', 'id'),
        ]);
    }

    public function print(DealerContract $dealerContract): View
    {
        // الطباعة بالعربي والإنجليزي جنب بعض (زي العقود الرسمية) — ?lang=ar أو ?lang=en لو عايز لغة واحدة
        $lang = in_array(request('lang'), ['ar', 'en'], true) ? request('lang') : 'both';
        app()->setLocale($lang === 'en' ? 'en' : 'ar');

        $contract = $this->baseQuery()->where('dealer_contracts.id', $dealerContract->id)
            ->addSelect('vehicles.chassis_number', 'vehicles.engine_number', 'vehicles.color', 'vehicles.mileage',
                'suppliers.national_id as supplier_national_id', 'suppliers.address as supplier_address')
            ->firstOrFail();

        return view('admin.dealer-contracts.print', [
            'contract' => $contract,
            'payments' => $this->payments($dealerContract),
            'company'  => \App\Support\Company::info(),
            'lang'     => $lang,
            'termsAr'  => $dealerContract->terms_ar ?: \App\Support\Company::get('dealer_contract_terms_ar'),
            'termsEn'  => ($dealerContract->terms_en ?? null) ?: \App\Support\Company::get('dealer_contract_terms_en'),
        ]);
    }

    // =========================================================
    //  إضافة وتعديل
    // =========================================================
    public function create(Request $request): View
    {
        $contract = new DealerContract;
        $contract->forceFill([
            'contract_number' => $this->nextNumber(),
            'contract_date'   => today()->toDateString(),
            'supplier_id'     => $request->integer('supplier') ?: null,
            'vehicle_id'      => $request->integer('vehicle') ?: null,
            'tax_percent'     => 0,
            'commission_amount' => 0,
            'terms_ar'        => \App\Support\Company::get('dealer_contract_terms_ar') ?: self::DEFAULT_TERMS,
            'terms_en'        => \App\Support\Company::get('dealer_contract_terms_en') ?: self::DEFAULT_TERMS_EN,
        ]);

        return view('admin.dealer-contracts.form', ['contract' => $contract] + $this->formOptions(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $contract = new DealerContract;
        $data = $this->validated($request, $contract);

        DB::transaction(function () use ($request, $contract, $data) {
            $contract->forceFill($data + [
                'paid_amount' => 0,
                'remaining_amount' => $data['total_amount'],
                'status_id'   => $this->statusId('active'),
                'user_id'     => $request->user()->id,
                'owner_signature'    => Media::storeSignature($request->input('owner_signature_data')),
                'showroom_signature' => Media::storeSignature($request->input('showroom_signature_data')),
            ])->save();
        });

        return redirect()->route('admin.dealer-contracts.show', $contract)->with('status', 'تم إنشاء العقد '.$contract->contract_number);
    }

    public function edit(DealerContract $dealerContract): View
    {
        return view('admin.dealer-contracts.form', ['contract' => $dealerContract] + $this->formOptions($dealerContract));
    }

    public function update(Request $request, DealerContract $dealerContract): RedirectResponse
    {
        $data = $this->validated($request, $dealerContract);
        $remaining = Money::round($data['total_amount'] - (float) $dealerContract->paid_amount);

        if ($remaining < 0) {
            return back()->withInput()->withErrors(['agreed_price' => 'المستحق الجديد أقل من المدفوع فعلاً ('.Money::format($dealerContract->paid_amount).').']);
        }

        foreach (['owner', 'showroom'] as $party) {
            if ($path = Media::storeSignature($request->input($party.'_signature_data'))) {
                Media::delete($dealerContract->{$party.'_signature'});
                $data[$party.'_signature'] = $path;
            }
        }

        $dealerContract->forceFill($data + ['remaining_amount' => $remaining])->save();
        $this->syncStatus($dealerContract);

        return redirect()->route('admin.dealer-contracts.show', $dealerContract)->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(DealerContract $dealerContract): RedirectResponse
    {
        if ((float) $dealerContract->paid_amount > 0) {
            return back()->with('error', 'العقد عليه مدفوعات، مينفعش يتمسح. ألغيه بدل المسح.');
        }

        $dealerContract->delete();

        return redirect()->route('admin.dealer-contracts.index')->with('status', 'تم مسح العقد');
    }

    public function cancel(DealerContract $dealerContract): RedirectResponse
    {
        if ((float) $dealerContract->paid_amount > 0) {
            return back()->with('error', 'العقد عليه مدفوعات. سوّي الحساب مع المالك الأول.');
        }

        $dealerContract->forceFill(['status_id' => $this->statusId('cancelled')])->save();

        return back()->with('status', 'تم إلغاء العقد');
    }

    // =========================================================
    //  الدفعات والتوقيع
    // =========================================================
    public function storePayment(Request $request, DealerContract $dealerContract): RedirectResponse
    {
        if ((int) $dealerContract->status_id === $this->statusId('cancelled')) {
            return back()->with('error', 'العقد ده ملغي.');
        }

        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:0.001'],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payment_date'      => ['required', 'date', 'before_or_equal:today'],
            'reference'         => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ], self::MESSAGES);

        $payment = DB::transaction(function () use ($request, $dealerContract, $data) {
            $locked = DealerContract::whereKey($dealerContract->id)->lockForUpdate()->firstOrFail();
            $amount = Money::round($data['amount']);
            $remaining = Money::round($locked->remaining_amount);

            if ($amount > $remaining + 0.0005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من المتبقي للمالك ('.Money::format($remaining).').']);
            }

            $payment = Payment::forceCreate([
                'payment_reference' => 'DPAY-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
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

            $locked->forceFill([
                'paid_amount'      => Money::round((float) $locked->paid_amount + $amount),
                'remaining_amount' => Money::round($remaining - $amount),
            ])->save();

            $this->syncStatus($locked);

            return $payment;
        });

        return redirect()->route('admin.dealer-contracts.show', $dealerContract)
            ->with('status', 'تم تسجيل الدفعة')
            ->with('receipt_url', route('admin.dealer-payments.receipt', $payment));
    }

    public function sign(Request $request, DealerContract $dealerContract, string $party): RedirectResponse
    {
        abort_unless(in_array($party, ['owner', 'showroom'], true), 404);

        $path = Media::storeSignature($request->input('signature_data'));
        if (! $path) {
            return back()->with('error', 'ارسم التوقيع الأول.');
        }

        Media::delete($dealerContract->{$party.'_signature'});
        $dealerContract->forceFill([$party.'_signature' => $path] + self::signedAt($party))->save();

        return back()->with('status', $party === 'owner' ? 'تم حفظ توقيع المالك' : 'تم حفظ توقيع المعرض');
    }

    // =========================================================
    //  تغيير السعر المتفق عليه (مع سجل)
    // =========================================================
    /**
     * تغيير السعر المتفق عليه:
     *  - mode=request (الافتراضي): بيتعمل طلب، ويتبعت للمالك رابط يوافق أو يرفض منه
     *  - mode=direct: يتطبق على طول (لو المالك وافق قدامك أو بالتليفون) — وبيتسجل باسمك
     */
    public function changePrice(Request $request, DealerContract $dealerContract): RedirectResponse
    {
        if ($error = $this->priceBlocker($dealerContract)) {
            return back()->with('error', $error);
        }

        $data = $request->validate([
            'new_price' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'reason'    => ['nullable', 'string', 'max:500'],
            'mode'      => ['nullable', 'in:request,direct'],
        ], self::MESSAGES);

        $old = (float) $dealerContract->agreed_price;
        $new = Money::round($data['new_price']);
        if (abs($new - $old) < 0.0005) {
            return back()->with('error', 'ده نفس السعر الحالي.');
        }
        $this->priceTotals($dealerContract, $new); // بيرمي خطأ لو السعر يخلي المستحق أقل من المدفوع

        if (($data['mode'] ?? 'request') === 'direct' || ! \Illuminate\Support\Facades\Schema::hasTable('dealer_price_requests')) {
            DB::transaction(fn () => $this->applyPrice($dealerContract, $new, $data['reason'] ?? null, $request, 'direct'));

            return back()->with('status', 'تم تغيير السعر من '.Money::format($old).' إلى '.Money::format($new));
        }

        $id = DB::transaction(function () use ($request, $dealerContract, $old, $new, $data) {
            // طلب واحد مفتوح بس لكل عقد: القديم بيتلغي
            DB::table('dealer_price_requests')->where('dealer_contract_id', $dealerContract->id)->where('status', 'pending')
                ->update(['status' => 'cancelled', 'decided_at' => now(), 'updated_at' => now()]);

            $id = DB::table('dealer_price_requests')->insertGetId([
                'dealer_contract_id' => $dealerContract->id,
                'old_price'          => $old,
                'new_price'          => $new,
                'reason'             => $data['reason'] ?? null,
                'status'             => 'pending',
                'requested_by'       => $request->user()->id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $this->log($dealerContract, 'dealer_price_requested', ['old' => $old, 'new' => $new, 'reason' => $data['reason'] ?? null], 'طلب تغيير السعر — مستني موافقة المالك', $request);

            return $id;
        });

        return back()->with('status', 'اتعمل طلب تغيير السعر. ابعت الرابط للمالك يوافق عليه.')->with('price_link', self::priceLink($id));
    }

    public function cancelPriceRequest(Request $request, DealerContract $dealerContract, int $priceRequest): RedirectResponse
    {
        $n = DB::table('dealer_price_requests')->where('id', $priceRequest)->where('dealer_contract_id', $dealerContract->id)
            ->where('status', 'pending')->update(['status' => 'cancelled', 'decided_at' => now(), 'updated_at' => now()]);

        return back()->with($n ? 'status' : 'error', $n ? 'اتلغى طلب تغيير السعر.' : 'الطلب ده اتقفل قبل كده.');
    }

    /** صفحة المالك: يشوف السعر القديم والجديد ويوافق أو يرفض */
    public function publicPriceForm(int $priceRequest): View
    {
        $req = DB::table('dealer_price_requests')->where('id', $priceRequest)->first() ?? abort(404);
        $model = DealerContract::findOrFail($req->dealer_contract_id);

        return view('public.dealer-price-approval', [
            'req'      => $req,
            'contract' => $this->baseQuery()->where('dealer_contracts.id', $model->id)->addSelect('vehicles.chassis_number', 'vehicles.color')->firstOrFail(),
            'totals'   => rescue(fn () => $this->priceTotals($model, (float) $req->new_price), null, false),
            'company'  => \App\Support\Company::info(),
            'blocked'  => $req->status === 'pending' ? $this->priceBlocker($model) : null,
        ]);
    }

    public function publicPriceDecide(Request $request, int $priceRequest): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'owner_note' => ['nullable', 'string', 'max:500']]);

        $result = DB::transaction(function () use ($request, $priceRequest, $data) {
            $req = DB::table('dealer_price_requests')->where('id', $priceRequest)->lockForUpdate()->first() ?? abort(404);
            if ($req->status !== 'pending') {
                return 'الطلب ده اتقفل قبل كده.';
            }
            $contract = DealerContract::whereKey($req->dealer_contract_id)->lockForUpdate()->firstOrFail();
            $approve = $data['decision'] === 'approve';

            if ($approve) {
                if ($error = $this->priceBlocker($contract)) {
                    return $error;
                }
                $this->applyPrice($contract, (float) $req->new_price, $req->reason, $request, 'owner');
            } else {
                $this->log($contract, 'dealer_price_rejected', ['old' => (float) $req->old_price, 'new' => (float) $req->new_price, 'note' => $data['owner_note'] ?? null], 'المالك رفض تغيير السعر', $request);
            }

            DB::table('dealer_price_requests')->where('id', $req->id)->update([
                'status'     => $approve ? 'approved' : 'rejected',
                'decided_at' => now(),
                'decided_ip' => $request->ip(),
                'owner_note' => $data['owner_note'] ?? null,
                'updated_at' => now(),
            ]);

            return $approve ? true : false;
        });

        if (is_string($result)) {
            return back()->withErrors(['decision' => $result]);
        }

        return back()->with('status', $result ? 'شكراً! تمت الموافقة على السعر الجديد.' : 'تم تسجيل رفضك للسعر الجديد.');
    }

    public static function priceLink(int $id): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute('public.dealer-price.show', now()->addDays(7), ['priceRequest' => $id]);
    }

    /** سبب يمنع تغيير السعر (أو null) */
    private function priceBlocker(DealerContract $contract): ?string
    {
        if ((int) $contract->status_id === $this->statusId('cancelled')) {
            return 'العقد ده ملغي.';
        }
        if (\App\Models\SalesContract::where('vehicle_id', $contract->vehicle_id)->exists()) {
            return 'لا يمكن تغيير السعر: العربية عليها عقد بيع بالفعل.';
        }

        return null;
    }

    /** الإجماليات بعد السعر الجديد — بيرمي خطأ لو المستحق يبقى أقل من اللي اتدفع للمالك */
    private function priceTotals(DealerContract $contract, float $new): array
    {
        $tax = Money::round($new * (float) $contract->tax_percent / 100);
        $commission = ($contract->commission_type ?? 'fixed') === 'percent'
            ? Money::round($new * (float) $contract->commission_rate / 100) : (float) $contract->commission_amount;
        $total = Money::round($new + $tax - $commission);
        $remaining = Money::round($total - (float) $contract->paid_amount);

        if ($total <= 0 || $remaining < 0) {
            throw ValidationException::withMessages(['new_price' => 'السعر ده بيخلي المستحق أقل من اللي اتدفع فعلاً للمالك.']);
        }

        return compact('tax', 'total', 'remaining', 'commission');
    }

    private function applyPrice(DealerContract $contract, float $new, ?string $reason, Request $request, string $via): void
    {
        $old = (float) $contract->agreed_price;
        ['tax' => $tax, 'total' => $total, 'remaining' => $remaining, 'commission' => $commission] = $this->priceTotals($contract, $new);

        $contract->forceFill([
            'agreed_price'      => $new,
            'tax_amount'        => $tax,
            'commission_amount' => $commission,
            'total_amount'      => $total,
            'remaining_amount'  => $remaining,
        ] + (! empty($contract->min_price) && (float) $contract->min_price > $new ? ['min_price' => $new] : []))->save();

        $this->log($contract, 'dealer_price_changed', ['old' => $old, 'new' => $new, 'reason' => $reason, 'via' => $via],
            $via === 'owner' ? 'المالك وافق على تغيير السعر من الرابط' : 'تغيير السعر المتفق عليه في عقد التفويض', $request);

        $this->syncStatus($contract);
    }

    private function log(DealerContract $contract, string $action, array $props, string $description, Request $request): void
    {
        $user = $request->user();
        DB::table('activity_logs')->insert([
            'action'       => $action,
            'subject_type' => $contract->getMorphClass(),
            'subject_id'   => $contract->id,
            'causer_type'  => $user?->getMorphClass(),
            'causer_id'    => $user?->id,
            'properties'   => json_encode($props, JSON_UNESCAPED_UNICODE),
            'description'  => $description,
            'ip_address'   => $request->ip(),
            'user_agent'   => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function priceRequests(DealerContract $contract)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('dealer_price_requests')) {
            return collect();
        }

        return DB::table('dealer_price_requests')->where('dealer_contract_id', $contract->id)
            ->leftJoin('users', 'users.id', '=', 'dealer_price_requests.requested_by')
            ->select('dealer_price_requests.*', 'users.name as user_name')
            ->latest('dealer_price_requests.id')->get()
            ->each(fn ($r) => $r->link = $r->status === 'pending' ? self::priceLink($r->id) : null);
    }

    public static function hasCommissionType(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('dealer_contracts', 'commission_type');
    }

    private static function signedAt(string $party): array
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('dealer_contracts', $party.'_signed_at') ? [$party.'_signed_at' => now()] : [];
    }

    /** رابط التوقيع: المالك (الطرف الثاني) أو المعرض/المفوّض (الطرف الأول) — كل واحد يوقّع من موبايله */
    public function publicSignForm(DealerContract $dealerContract, string $party = 'owner'): View
    {
        return view('public.dealer-contract-sign', [
            'contract' => $this->baseQuery()->where('dealer_contracts.id', $dealerContract->id)
                ->addSelect('vehicles.chassis_number', 'vehicles.color')->firstOrFail(),
            'company'  => \App\Support\Company::info(),
            'party'    => $party,
            'signed'   => (bool) $dealerContract->{$party.'_signature'},
        ]);
    }

    public function publicSign(Request $request, DealerContract $dealerContract, string $party = 'owner'): RedirectResponse
    {
        if ($dealerContract->{$party.'_signature'}) {
            return back()->with('status', 'العقد اتوقّع قبل كده.');
        }

        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'لازم توافق على الشروط الأول.']);

        $path = Media::storeSignature($request->input('signature_data'));
        if (! $path) {
            return back()->withErrors(['signature_data' => 'ارسم توقيعك في المربع الأول.']);
        }

        DB::transaction(function () use ($request, $dealerContract, $path, $party) {
            $dealerContract->forceFill([$party.'_signature' => $path] + self::signedAt($party))->save();
            $this->log($dealerContract, 'dealer_signed_remotely', ['party' => $party],
                $party === 'owner' ? 'توقيع المالك عن طريق الرابط' : 'توقيع المعرض (المفوّض) عن طريق الرابط', $request);
        });

        return back()->with('status', 'شكراً! تم حفظ توقيعك.');
    }

    public static function signLink(DealerContract $contract, string $party = 'owner'): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'public.dealer-contracts.sign', now()->addDays(7), ['dealerContract' => $contract->id] + ($party === 'owner' ? [] : ['party' => $party])
        );
    }

    // =========================================================
    //  أدوات داخلية
    // =========================================================
    private function validated(Request $request, DealerContract $contract): array
    {
        $data = $request->validate([
            'contract_number'   => ['required', 'string', 'max:50', Rule::unique('dealer_contracts', 'contract_number')->ignore($contract->id)],
            'supplier_id'       => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'vehicle_id'        => ['required', Rule::exists('vehicles', 'id')
                ->where('supplier_id', $request->input('supplier_id'))
                ->whereNull('deleted_at')],
            'agreed_price'      => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'commission_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'commission_type'   => ['nullable', 'in:fixed,percent'],
            'commission_rate'   => ['nullable', 'required_if:commission_type,percent', 'numeric', 'min:0', 'max:100'],
            'min_price'         => ['nullable', 'numeric', 'min:0', 'lte:agreed_price'],
            'tax_percent'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contract_date'     => ['required', 'date'],
            'expiry_date'       => ['nullable', 'date', 'after_or_equal:contract_date'],
            'terms_ar'          => ['nullable', 'string', 'max:10000'],
            'terms_en'          => ['nullable', 'string', 'max:10000'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ], self::MESSAGES);

        $open = DealerContract::where('vehicle_id', $data['vehicle_id'])
            ->where('status_id', $this->statusId('active'))
            ->when($contract->exists, fn ($q) => $q->whereKeyNot($contract->id))
            ->exists();
        if ($open) {
            throw ValidationException::withMessages(['vehicle_id' => 'العربية دي عليها عقد تفويض مفتوح بالفعل.']);
        }

        $agreed = Money::round($data['agreed_price']);
        $hasExtra = self::hasCommissionType();
        $percent = $hasExtra && ($data['commission_type'] ?? 'fixed') === 'percent';
        $commission = $percent ? Money::round($agreed * (float) $data['commission_rate'] / 100) : Money::round($data['commission_amount'] ?? 0);
        if ($hasExtra) {
            $data['commission_type'] = $percent ? 'percent' : 'fixed';
            $data['commission_rate'] = $percent ? (float) $data['commission_rate'] : null;
            $data['min_price'] = isset($data['min_price']) && $data['min_price'] !== '' && (float) $data['min_price'] > 0 ? Money::round($data['min_price']) : null;
        } else {
            unset($data['commission_type'], $data['commission_rate'], $data['min_price']);
        }
        $taxPercent = (float) ($data['tax_percent'] ?? 0);
        $tax = Money::round($agreed * $taxPercent / 100);
        $total = Money::round($agreed + $tax - $commission);

        if ($total <= 0) {
            throw ValidationException::withMessages(['commission_amount' => 'العمولة أكبر من المستحق للمالك.']);
        }

        $data['terms_ar'] = \App\Support\RichText::clean($data['terms_ar'] ?? null, 'terms');
        if (\Illuminate\Support\Facades\Schema::hasColumn('dealer_contracts', 'terms_en')) {
            $data['terms_en'] = \App\Support\RichText::clean($data['terms_en'] ?? null, 'terms');
        } else {
            unset($data['terms_en']);
        }

        return array_merge($data, [
            'agreed_price'      => $agreed,
            'commission_amount' => $commission,
            'tax_percent'       => $taxPercent,
            'tax_amount'        => $tax,
            'total_amount'      => $total,
        ]);
    }

    private function syncStatus(DealerContract $contract): void
    {
        $cancelled = $this->statusId('cancelled');
        if ((int) $contract->status_id === $cancelled) {
            return;
        }

        $status = (float) $contract->remaining_amount <= 0.0005 ? $this->statusId('completed') : $this->statusId('active');
        if ((int) $contract->status_id !== $status) {
            $contract->forceFill(['status_id' => $status])->save();
        }
    }

    private function baseQuery()
    {
        return DealerContract::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'dealer_contracts.supplier_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'dealer_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('contract_statuses', 'contract_statuses.id', '=', 'dealer_contracts.status_id')
            ->leftJoin('users', 'users.id', '=', 'dealer_contracts.user_id')
            ->select('dealer_contracts.*',
                \App\Support\Lang::sel('suppliers.name_ar as supplier_name'), 'suppliers.phone as supplier_phone',
                'vehicles.vehicle_code', 'vehicles.plate_number', 'vehicles.manufacturing_year', 'vehicles.status as vehicle_status', 'vehicles.images as vehicle_images',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'),
                'contract_statuses.code as status_code', \App\Support\Lang::sel('contract_statuses.name_ar as status_name'),
                'users.name as user_name');
    }

    private function payments(DealerContract $contract)
    {
        return Payment::query()
            ->where('payable_type', $contract->getMorphClass())
            ->where('payable_id', $contract->id)
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->select('payments.*', \App\Support\Lang::sel('payment_methods.name_ar as method_name'), 'users.name as user_name')
            ->orderBy('payment_date')
            ->get();
    }

    private function formOptions(?DealerContract $current): array
    {
        $busy = DealerContract::where('status_id', $this->statusId('active'))
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->pluck('vehicle_id');

        $vehicles = Vehicle::query()
            ->whereNotNull('vehicles.supplier_id')
            ->where(fn ($q) => $q->whereNotIn('vehicles.status', ['sold', 'returned'])
                ->when($current, fn ($w) => $w->orWhere('vehicles.id', $current->vehicle_id)))
            ->whereNotIn('vehicles.id', $busy)
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->select('vehicles.id', 'vehicles.supplier_id', 'vehicles.vehicle_code', 'vehicles.manufacturing_year',
                'vehicles.plate_number', 'vehicles.selling_price', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->orderBy('car_brands.name_ar')
            ->get();

        return [
            'suppliers' => Supplier::where('status', 'active')
                ->when($current, fn ($q) => $q->orWhere('id', $current->supplier_id))
                ->orderBy('name_ar')->get(['id', 'name_ar', 'phone']),
            'vehicles'  => $vehicles,
        ];
    }

    private function statusId(string $code): ?int
    {
        static $cache = [];

        $cache[$code] ??= (int) DB::table('contract_statuses')->where('code', $code)->value('id');

        return $cache[$code] ?: null;
    }

    private function nextNumber(): string
    {
        $prefix = 'SC-'.now()->format('Ymd').'-';
        $last = DB::table('dealer_contracts')->where('contract_number', 'like', $prefix.'%')->orderByDesc('contract_number')->value('contract_number');

        return $prefix.str_pad($last ? ((int) Str::afterLast($last, '-')) + 1 : 1, 5, '0', STR_PAD_LEFT);
    }
}
