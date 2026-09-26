<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\SalesContract;
use App\Models\TestDrive;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Support\CustomerScore;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public const STATUSES = ['active' => 'نشط', 'inactive' => 'غير نشط', 'blocked' => 'محظور'];

    private const MESSAGES = [
        'required' => 'الحقل ده مطلوب.',
        'unique'   => 'القيمة دي مسجلة لعميل تاني.',
        'email'    => 'الإيميل مش صحيح.',
        'max'      => 'أطول من المسموح.',
        'min'      => 'أقل من المسموح.',
        'numeric'  => 'لازم يكون رقم.',
        'in'       => 'الاختيار مش صحيح.',
        'required_if' => 'اكتب سبب الحظر.',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = array_key_exists((string) $request->query('status'), self::STATUSES) ? $request->query('status') : null;
        $filter = in_array($request->query('filter'), ['buyers', 'installments', 'portal', 'leads'], true) ? $request->query('filter') : null;

        $customers = Customer::query()
            ->addSelect([
                'contracts_count' => SalesContract::selectRaw('count(*)')->whereColumn('sales_contracts.customer_id', 'customers.id'),
                'remaining_total' => SalesContract::selectRaw('coalesce(sum(remaining_amount), 0)')->whereColumn('sales_contracts.customer_id', 'customers.id'),
                'overdue_count'   => Installment::selectRaw('count(*)')->whereColumn('installments.customer_id', 'customers.id')
                    ->where('installments.is_paid', false)->where('installments.due_date', '<', today()->toDateString()),
                'drives_count'    => TestDrive::selectRaw('count(*)')->whereColumn('test_drives.customer_id', 'customers.id'),
                'portal_user_id'  => User::select('id')->whereColumn('users.customer_id', 'customers.id')->limit(1),
                'inst_paid'       => Installment::selectRaw('count(*)')->whereColumn('installments.customer_id', 'customers.id')
                    ->where('installments.is_paid', true),
                'inst_on_time'    => Installment::selectRaw('count(*)')->whereColumn('installments.customer_id', 'customers.id')
                    ->where('installments.is_paid', true)->whereColumn('installments.actual_payment_date', '<=', 'installments.due_date'),
                'avg_days_late'   => Installment::selectRaw('coalesce(avg(greatest(datediff(installments.actual_payment_date, installments.due_date), 0)), 0)')
                    ->whereColumn('installments.customer_id', 'customers.id')->where('installments.is_paid', true),
            ])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")->orWhere('national_id', 'like', "%{$search}%")
                ->orWhere('customer_code', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($filter === 'buyers', fn ($q) => $q->whereExists(fn ($s) => $s->from('sales_contracts')->whereColumn('sales_contracts.customer_id', 'customers.id')->whereNull('deleted_at')))
            ->when($filter === 'leads', fn ($q) => $q->whereNotExists(fn ($s) => $s->from('sales_contracts')->whereColumn('sales_contracts.customer_id', 'customers.id')->whereNull('deleted_at')))
            ->when($filter === 'installments', fn ($q) => $q->whereExists(fn ($s) => $s->from('installments')->whereColumn('installments.customer_id', 'customers.id')->where('is_paid', false)->whereNull('deleted_at')))
            ->when($filter === 'portal', fn ($q) => $q->whereExists(fn ($s) => $s->from('users')->whereColumn('users.customer_id', 'customers.id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'all'       => Customer::count(),
            'buyers'    => SalesContract::distinct('customer_id')->count('customer_id'),
            'remaining' => (float) SalesContract::sum('remaining_amount'),
            'overdue'   => Installment::where('is_paid', false)->where('due_date', '<', today()->toDateString())->distinct('customer_id')->count('customer_id'),
        ];

        return view('admin.customers.index', [
            'customers' => $customers,
            'totals'    => $totals,
            'statuses'  => self::STATUSES,
            'filters'   => ['q' => $search, 'status' => $status, 'filter' => $filter],
        ]);
    }

    public function show(Customer $customer): View
    {
        $contracts = SalesContract::query()
            ->where('sales_contracts.customer_id', $customer->id)
            ->leftJoin('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('contract_statuses', 'contract_statuses.id', '=', 'sales_contracts.status_id')
            ->select('sales_contracts.*', 'vehicles.vehicle_code', 'vehicles.manufacturing_year', 'vehicles.images',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'), \App\Support\Lang::sel('contract_statuses.name_ar as status_name'))
            ->latest('contract_date')
            ->get();

        $installments = Installment::where('customer_id', $customer->id)->orderBy('due_date')->get();

        $drives = TestDrive::query()
            ->where('test_drives.customer_id', $customer->id)
            ->leftJoin('vehicles', 'vehicles.id', '=', 'test_drives.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->select('test_drives.*', 'vehicles.vehicle_code', \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->latest('start_time')
            ->get();

        $today = today();
        $open = $installments->where('is_paid', false);
        $paid = $installments->where('is_paid', true);
        $late = fn ($i) => $i->actual_payment_date && Carbon::parse($i->actual_payment_date)->gt(Carbon::parse($i->due_date));
        $overdueNow = $open->filter(fn ($i) => $i->due_date && Carbon::parse($i->due_date)->lt($today));

        $payments = Payment::query()
            ->where('payments.payable_type', (new SalesContract)->getMorphClass())
            ->whereIn('payments.payable_id', $contracts->pluck('id'))
            ->leftJoin('sales_contracts', 'sales_contracts.id', '=', 'payments.payable_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->select('payments.*', 'sales_contracts.contract_number', \App\Support\Lang::sel('payment_methods.name_ar as method_name'), 'users.name as user_name')
            ->latest('payments.payment_date')
            ->get();

        $notes = DB::table('activity_logs')
            ->where('subject_type', $customer->getMorphClass())
            ->where('subject_id', $customer->id)
            ->where('action', 'customer_note')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.causer_id')
            ->select('activity_logs.id', 'activity_logs.description', 'activity_logs.properties', 'activity_logs.created_at', 'users.name as user_name')
            ->latest('activity_logs.created_at')
            ->get();

        // طلبات الموقع: المربوطة بالعميل أو بنفس رقم الموبايل
        $tail = substr(preg_replace('/\D/', '', (string) $customer->phone), -8);
        $inquiries = \App\Support\Access::can('inquiries.view') ? DB::table('vehicle_inquiries as i')->whereNull('i.deleted_at')
            ->where(fn ($q) => $q->where('i.customer_id', $customer->id)->when(strlen($tail) >= 7, fn ($w) => $w->orWhere('i.phone', 'like', '%'.$tail)))
            ->leftJoin('vehicles as v', 'v.id', '=', 'i.vehicle_id')->leftJoin('car_brands as b', 'b.id', '=', 'v.brand_id')->leftJoin('car_models as m', 'm.id', '=', 'v.model_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.assigned_to')
            ->select('i.*', \App\Support\Lang::sel('b.name_ar as brand_name'), \App\Support\Lang::sel('m.name_ar as model_name'), 'u.name as assignee')
            ->orderByDesc('i.id')->limit(30)->get() : collect();

        return view('admin.customers.show', [
            'customer'     => $customer,
            'inquiries'    => $inquiries,
            'contracts'    => $contracts,
            'installments' => $installments,
            'drives'       => $drives,
            'payments'     => $payments,
            'notes'        => $notes,
            'attachments'  => Attachment::where('attachable_type', $customer->getMorphClass())->where('attachable_id', $customer->id)
                ->with('user:id,name')->latest()->get(),
            'portalUser'   => User::where('customer_id', $customer->id)->first(),
            'score'        => CustomerScore::calculate(
                $paid->count(),
                $paid->reject($late)->count(),
                $overdueNow->count(),
                (float) ($paid->avg(fn ($i) => $late($i) ? Carbon::parse($i->due_date)->diffInDays(Carbon::parse($i->actual_payment_date)) : 0) ?? 0),
                $contracts->count(),
            ),
            'summary'      => [
                'purchases'  => (float) $contracts->sum('total_amount'),
                'paid'       => (float) $contracts->sum('paid_amount'),
                'remaining'  => (float) $contracts->sum('remaining_amount'),
                'overdue'    => (float) $overdueNow->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount),
                'next'       => $open->first(fn ($i) => $i->due_date && Carbon::parse($i->due_date)->gte($today)),
                'open_count' => $open->count(),
                'paid_count' => $paid->count(),
                'late_count' => $paid->filter($late)->count(),
                'overdue_count' => $overdueNow->count(),
                'drives'     => $drives->count(),
                'interested' => $drives->where('is_interested', true)->count(),
            ],
            'statuses'     => self::STATUSES,
        ]);
    }

    /**
     * كشف حساب العميل: مدين (عقود، غرامات، مبالغ مردودة) ودائن (دفعات، إلغاء عقود)
     * والرصيد بعد كل حركة. الرصيد الموجب = على العميل، السالب = للعميل.
     */
    public function statement(Request $request, Customer $customer): View
    {
        $from = $request->date('from')?->toDateString();
        $to = ($request->date('to') ?? today())->toDateString();

        $contracts = DB::table('sales_contracts as s')->whereNull('s.deleted_at')->where('s.customer_id', $customer->id)
            ->leftJoin('vehicles as v', 'v.id', '=', 's.vehicle_id')->leftJoin('car_brands as b', 'b.id', '=', 'v.brand_id')->leftJoin('car_models as m', 'm.id', '=', 'v.model_id')
            ->select('s.id', 's.contract_number', 's.contract_date', 's.total_amount', 's.payment_type', \App\Support\Lang::sel('b.name_ar as brand'), \App\Support\Lang::sel('m.name_ar as model'), 'v.manufacturing_year as year')
            ->get();

        $entries = collect();
        foreach ($contracts as $c) {
            $car = trim($c->brand.' '.$c->model.' '.$c->year);
            $entries->push(['date' => $c->contract_date, 'order' => 1, 'ref' => $c->contract_number, 'text' => __('عقد بيع :type — :car', ['type' => __($c->payment_type === 'installments' ? 'تقسيط' : 'كاش'), 'car' => $car]), 'debit' => (float) $c->total_amount, 'credit' => 0.0]);
        }

        if ($contracts->isNotEmpty()) {
            DB::table('payments as p')->whereNull('p.deleted_at')->where('p.status', 'completed')
                ->where('p.payable_type', (new SalesContract)->getMorphClass())->whereIn('p.payable_id', $contracts->pluck('id'))
                ->leftJoin('payment_methods as pm', 'pm.id', '=', 'p.payment_method_id')
                ->leftJoin('sales_contracts as s', 's.id', '=', 'p.payable_id')
                ->select('p.payment_date', 'p.payment_reference', 'p.amount', 'p.notes', \App\Support\Lang::sel('pm.name_ar as method'), 's.contract_number')
                ->get()->each(fn ($p) => $entries->push(['date' => $p->payment_date, 'order' => 2, 'ref' => $p->payment_reference,
                    'text' => $p->method === \App\Support\Importer::OPENING_METHOD
                        ? __('عقد :no', ['no' => $p->contract_number]).' — '.self::openingNote((string) $p->notes)
                        : __('دفعة على عقد :no', ['no' => $p->contract_number]).($p->method ? ' — '.__($p->method) : '').($p->notes ? ' ('.__($p->notes).')' : ''), 'debit' => 0.0, 'credit' => (float) $p->amount]));

            DB::table('cancellation_requests as r')->whereNull('r.deleted_at')->where('r.status', 'approved')->whereIn('r.sales_contract_id', $contracts->pluck('id'))
                ->join('sales_contracts as s', 's.id', '=', 'r.sales_contract_id')
                ->select('r.*', 's.contract_number', 's.total_amount')->get()
                ->each(function ($r) use ($entries) {
                    $entries->push(['date' => $r->cancellation_date, 'order' => 3, 'ref' => $r->cancellation_number, 'text' => __('إلغاء عقد :no', ['no' => $r->contract_number]), 'debit' => 0.0, 'credit' => (float) $r->total_amount]);
                    if ((float) $r->penalty_amount > 0) {
                        $entries->push(['date' => $r->cancellation_date, 'order' => 4, 'ref' => $r->cancellation_number, 'text' => __('غرامة إلغاء'), 'debit' => (float) $r->penalty_amount, 'credit' => 0.0]);
                    }
                    if ((float) $r->net_refund > 0) {
                        $entries->push(['date' => $r->refund_date ?: $r->cancellation_date, 'order' => 5, 'ref' => $r->cancellation_number, 'text' => __('مبلغ اترد للعميل'), 'debit' => (float) $r->net_refund, 'credit' => 0.0]);
                    }
                });
        }

        $entries = $entries->map(fn ($e) => ['date' => substr((string) $e['date'], 0, 10)] + $e)
            ->filter(fn ($e) => $e['date'] <= $to)->sortBy(fn ($e) => $e['date'].'-'.$e['order'])->values();

        $opening = $from ? Money::round($entries->filter(fn ($e) => $e['date'] < $from)->sum(fn ($e) => $e['debit'] - $e['credit'])) : 0.0;
        $balance = $opening;
        $rows = $entries->filter(fn ($e) => ! $from || $e['date'] >= $from)->map(function ($e) use (&$balance) {
            $balance = Money::round($balance + $e['debit'] - $e['credit']);

            return $e + ['balance' => $balance];
        })->values();

        $overdue = DB::table('installments')->whereNull('deleted_at')->where('customer_id', $customer->id)->where('is_paid', false)->where('due_date', '<', today()->toDateString())
            ->selectRaw('count(*) as n, coalesce(sum(amount - paid_amount), 0) as total')->first();

        return view('admin.customers.statement', [
            'customer' => $customer, 'rows' => $rows, 'opening' => $opening, 'closing' => $balance, 'from' => $from, 'to' => $to,
            'totals' => ['debit' => $rows->sum('debit'), 'credit' => $rows->sum('credit')],
            'overdue' => $overdue,
            'next' => DB::table('installments')->whereNull('deleted_at')->where('customer_id', $customer->id)->where('is_paid', false)
                ->where('due_date', '>=', today()->toDateString())->orderBy('due_date')->first(),
        ]);
    }

    /** "قسط 3 (اندفع قبل السيستم)" بلغة الصفحة */
    private static function openingNote(string $note): string
    {
        return preg_match('/^قسط (\d+) \(اندفع قبل السيستم\)$/u', $note, $m) ? __('قسط :n (اندفع قبل السيستم)', ['n' => $m[1]]) : __($note);
    }

    /** ملاحظة متابعة: "كلمته ووعد يدفع الخميس" */
    public function addNote(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'note'      => ['required', 'string', 'max:1000'],
            'follow_up' => ['nullable', 'date', 'after_or_equal:today'],
        ], ['note.required' => 'اكتب الملاحظة.', 'follow_up.after_or_equal' => 'ميعاد المتابعة لازم يكون النهارده أو بعد كده.']);

        DB::table('activity_logs')->insert([
            'action'       => 'customer_note',
            'subject_type' => $customer->getMorphClass(),
            'subject_id'   => $customer->id,
            'causer_type'  => $request->user()->getMorphClass(),
            'causer_id'    => $request->user()->id,
            'description'  => $data['note'],
            'properties'   => json_encode(['follow_up' => $data['follow_up'] ?? null]),
            'ip_address'   => $request->ip(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return redirect()->to(route('admin.customers.show', $customer).'#notes')->with('status', 'تم حفظ الملاحظة');
    }

    public function create(): View
    {
        $customer = new Customer;
        $customer->forceFill(['customer_code' => $this->nextCode(), 'status' => 'active']);

        return view('admin.customers.form', ['customer' => $customer, 'statuses' => self::STATUSES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = new Customer;
        $customer->forceFill($this->validated($request, $customer))->save();

        return redirect()->route('admin.customers.show', $customer)->with('status', 'تم إضافة العميل '.$customer->name_ar);
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.form', ['customer' => $customer, 'statuses' => self::STATUSES]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        DB::transaction(function () use ($request, $customer) {
            $customer->forceFill($this->validated($request, $customer))->save();

            // حالة العميل وحساب البوابة يفضلوا متطابقين
            User::where('customer_id', $customer->id)->update([
                'is_active' => $customer->status !== 'blocked',
                'name'      => $customer->name_ar,
            ]);
        });

        return redirect()->route('admin.customers.show', $customer)->with('status', 'تم حفظ التعديلات');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if (SalesContract::where('customer_id', $customer->id)->exists() || Installment::where('customer_id', $customer->id)->exists()) {
            return back()->with('error', 'العميل ليه عقود بيع. غيّر حالته بدل المسح عشان السجل المالي يفضل محفوظ.');
        }

        DB::transaction(function () use ($customer) {
            User::where('customer_id', $customer->id)->delete();
            TestDrive::where('customer_id', $customer->id)->delete();
            $customer->delete();
        });

        return redirect()->route('admin.customers.index')->with('status', 'تم مسح العميل');
    }

    // =========================================================
    //  حساب بوابة العميل
    // =========================================================
    public function createAccount(Request $request, Customer $customer): RedirectResponse
    {
        if (User::where('customer_id', $customer->id)->exists()) {
            return back()->with('error', 'العميل ده عنده حساب بالفعل.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], self::MESSAGES + ['email.unique' => 'الإيميل ده مستخدم في حساب تاني.']);

        $password = $this->makePassword();

        $user = new User;
        $user->forceFill([
            'name'              => $customer->name_ar,
            'email'             => $data['email'],
            'phone'             => $customer->phone,
            'password'          => Hash::make($password),
            'role'              => 'customer',
            'customer_id'       => $customer->id,
            'is_active'         => $customer->status !== 'blocked',
            'email_verified_at' => now(), // الموظف اتأكد من العميل وجهاً لوجه
        ])->save();

        if (! $customer->email) {
            $customer->forceFill(['email' => $data['email']])->save();
        }

        return back()->with('portal_password', $password)->with('status', 'تم إنشاء حساب البوابة');
    }

    public function resetPassword(Customer $customer): RedirectResponse
    {
        $user = User::where('customer_id', $customer->id)->firstOrFail();
        $password = $this->makePassword();
        $user->forceFill(['password' => Hash::make($password)])->save();

        return back()->with('portal_password', $password)->with('status', 'تم تغيير كلمة المرور');
    }

    // =========================================================
    private function validated(Request $request, Customer $customer): array
    {
        $id = $customer->id;

        $data = $request->validate([
            'customer_code' => ['required', 'string', 'max:30', Rule::unique('customers', 'customer_code')->ignore($id)],
            'name_ar'       => ['required', 'string', 'max:255'],
            'name_en'       => ['nullable', 'string', 'max:255'],
            'national_id'   => ['nullable', 'string', 'max:50', Rule::unique('customers', 'national_id')->ignore($id)],
            'phone'         => ['required', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:255'],
            'address'       => ['nullable', 'string', 'max:1000'],
            'job_title'     => ['nullable', 'string', 'max:255'],
            'nationality'   => ['nullable', 'string', 'max:100'],
            'credit_limit'  => ['nullable', 'numeric', 'min:0'],
            'status'        => ['required', Rule::in(array_keys(self::STATUSES))],
            'block_reason'  => ['nullable', 'required_if:status,blocked', 'string', 'max:1000'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ], self::MESSAGES);

        if ($data['status'] !== 'blocked') {
            $data['block_reason'] = null;
        }

        return $data;
    }

    private function nextCode(): string
    {
        return 'CUST-'.str_pad((int) DB::table('customers')->max('id') + 1, 4, '0', STR_PAD_LEFT);
    }

    private function makePassword(): string
    {
        // سهلة تتقال في التليفون: بدون حروف متشابهة زي O و I
        $letters = 'ABCDEFGHJKMNPQRSTUVWXYZ';

        return $letters[random_int(0, 22)].$letters[random_int(0, 22)].random_int(100000, 999999);
    }
}
