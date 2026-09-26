<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AssetFinance;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * الأصول الثابتة (أثاث، أجهزة، عربيات الشركة...) مع الإهلاك الشهري بالقسط الثابت:
 *   الإهلاك الشهري = (التكلفة − قيمة الخردة) ÷ العمر بالشهور
 *   القيمة الدفترية = التكلفة − الإهلاك المتراكم
 */
class AssetController extends Controller
{
    public const STATUSES = ['in_use' => 'شغال', 'maintenance' => 'صيانة', 'disposed' => 'اتكهّن', 'sold' => 'اتباع'];

    public static function depreciation(object $a, ?\Carbon\CarbonInterface $at = null): array
    {
        $at ??= $a->disposal_date ? Carbon::parse($a->disposal_date) : today();
        $life = max(1, (int) $a->useful_life_months);
        $base = max(0, (float) $a->purchase_cost - (float) $a->salvage_value);
        $months = min($life, max(0, (int) Carbon::parse($a->purchase_date)->startOfMonth()->diffInMonths($at->copy()->startOfMonth())));
        $monthly = $base / $life;
        $acc = Money::round($monthly * $months);

        return [
            'monthly'     => Money::round($monthly),
            'months'      => $months,
            'accumulated' => $acc,
            'book_value'  => Money::round((float) $a->purchase_cost - $acc),
            'pct'         => $life ? (int) round($months / $life * 100) : 100,
        ];
    }

    public function index(Request $request): View
    {
        $f = [
            'q'        => trim((string) $request->query('q')),
            'status'   => array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : null,
            'branch'   => $request->integer('branch') ?: null,
            'category' => $request->integer('category') ?: null,
        ];

        $assets = DB::table('assets')->whereNull('assets.deleted_at')
            ->leftJoin('finance_categories as c', 'c.id', '=', 'assets.category_id')
            ->leftJoin('finance_categories as sc', 'sc.id', '=', 'assets.sub_category_id')
            ->leftJoin('branches', 'branches.id', '=', 'assets.branch_id')
            ->when($f['q'] !== '', fn ($w) => $w->where(fn ($s) => $s->where('assets.name_ar', 'like', "%{$f['q']}%")
                ->orWhere('assets.asset_code', 'like', "%{$f['q']}%")->orWhere('assets.serial_number', 'like', "%{$f['q']}%")->orWhere('assets.custodian', 'like', "%{$f['q']}%")))
            ->when($f['status'], fn ($w, $v) => $w->where('assets.status', $v))
            ->when($f['branch'], fn ($w, $v) => $w->where('assets.branch_id', $v))
            ->when($f['category'], fn ($w, $v) => $w->where(fn ($s) => $s->where('assets.category_id', $v)->orWhere('assets.sub_category_id', $v)))
            ->select('assets.*', \App\Support\Lang::sel('c.name_ar as category_name'), \App\Support\Lang::sel('sc.name_ar as sub_category_name'), \App\Support\Lang::sel('branches.name_ar as branch_name'))
            ->latest('assets.purchase_date')->get()
            ->map(function ($a) {
                $a->dep = self::depreciation($a);
                $a->fin = $a->payment_type === 'installments' ? AssetFinance::summary($a->id) : null;

                return $a;
            });

        $active = $assets->whereIn('status', ['in_use', 'maintenance']);
        $categories = DB::table('finance_categories')->where('kind', 'asset')->where('is_active', true)->orderBy('name_ar')->get(['id', 'parent_id', 'name_ar']);

        return view('admin.assets.index', [
            'assets'  => $assets,
            'filters' => $f,
            'stats'   => [
                'count'      => $active->count(),
                'cost'       => $active->sum('purchase_cost'),
                'book'       => $active->sum(fn ($a) => $a->dep['book_value']),
                'monthly'    => $active->filter(fn ($a) => $a->dep['pct'] < 100)->sum(fn ($a) => $a->dep['monthly']),
            ],
            'mainCats' => $categories->whereNull('parent_id')->pluckLocal('name', 'id'),
            'subCats'  => $categories->whereNotNull('parent_id')->values(),
            'branches' => DB::table('branches')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'accounts' => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->pluckLocal('name', 'id'),
            'suppliers'=> DB::table('suppliers')->whereNull('deleted_at')->orderBy('name_ar')->pluckLocal('name', 'id'),
            'alerts'   => AssetFinance::alerts(),
            'debt'     => (float) DB::table('asset_installments')->join('assets', 'assets.id', '=', 'asset_installments.asset_id')
                ->whereNull('assets.deleted_at')->whereNull('asset_installments.paid_date')->sum('asset_installments.amount'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['asset_code'] = 'AST-'.str_pad((int) DB::table('assets')->max('id') + 1, 4, '0', STR_PAD_LEFT);
        $data['user_id'] = $request->user()->id;
        if ($request->hasFile('image')) {
            $data['image'] = Media::store($request->file('image'), 'assets');
        }
        $id = DB::table('assets')->insertGetId($data + ['status' => 'in_use', 'created_at' => now(), 'updated_at' => now()]);
        AssetFinance::regenerate($id);

        return back()->with('status', 'تم إضافة الأصل '.$data['asset_code'].($data['payment_type'] === 'installments' ? ' — واتعمل جدول '.$data['installments_count'].' قسط' : ''));
    }

    public function update(Request $request, int $asset): RedirectResponse
    {
        $row = DB::table('assets')->where('id', $asset)->first();
        abort_unless($row, 404);
        $data = $this->validated($request);
        $data['status'] = in_array($request->input('status'), ['in_use', 'maintenance'], true) && in_array($row->status, ['in_use', 'maintenance'], true)
            ? $request->input('status') : $row->status;
        if ($request->hasFile('image')) {
            Media::delete($row->image);
            $data['image'] = Media::store($request->file('image'), 'assets');
        }
        // لو فيه أقساط اتدفعت: بيانات التمويل والتكلفة متتغيرش (عشان الحسابات متبوظش)
        $finKeys = ['purchase_cost', 'payment_type', 'financier_type', 'lender_name', 'down_payment', 'interest_rate', 'installments_count', 'first_due_date'];
        $hasPaid = DB::table('asset_installments')->where('asset_id', $asset)->whereNotNull('paid_date')->exists();
        $changed = collect($finKeys)->contains(fn ($k) => (string) ($data[$k] ?? '') !== (string) ($row->{$k} ?? '') && ! (is_numeric($data[$k] ?? null) && is_numeric($row->{$k} ?? null) && (float) $data[$k] === (float) $row->{$k}));
        $warning = null;
        if ($hasPaid && $changed) {
            foreach ($finKeys as $k) {
                unset($data[$k]);
            }
            $warning = 'اتحفظت التعديلات، بس بيانات التمويل والتكلفة ما اتغيرتش لأن فيه أقساط اتدفعت. لو محتاج تغيّرها، ألغي دفع الأقساط الأول.';
        }
        DB::table('assets')->where('id', $asset)->update($data + ['updated_at' => now()]);
        if (! $hasPaid && $changed) {
            AssetFinance::regenerate($asset);
        }

        return back()->with('status', 'تم حفظ التعديلات')->with('warning', $warning);
    }

    /** تكهين أو بيع الأصل */
    public function dispose(Request $request, int $asset): RedirectResponse
    {
        $data = $request->validate([
            'status'          => ['required', Rule::in(['disposed', 'sold'])],
            'disposal_date'   => ['required', 'date', 'before_or_equal:today'],
            'disposal_amount' => ['nullable', 'numeric', 'min:0'],
        ], ['required' => 'الحقل ده مطلوب.']);

        DB::table('assets')->where('id', $asset)->update([
            'status' => $data['status'], 'disposal_date' => $data['disposal_date'],
            'disposal_amount' => $data['status'] === 'sold' ? Money::round($data['disposal_amount'] ?? 0) : 0,
            'updated_at' => now(),
        ]);

        return back()->with('status', $data['status'] === 'sold' ? 'تم تسجيل بيع الأصل والمبلغ دخل الخزنة' : 'تم تكهين الأصل');
    }

    public function destroy(int $asset): RedirectResponse
    {
        DB::table('assets')->where('id', $asset)->update(['deleted_at' => now()]);

        return back()->with('status', 'تم المسح');
    }

    /** كل أقساط الأصول: متأخرة / مستحقة / مدفوعة */
    public function installments(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['overdue', 'upcoming', 'paid', 'all'], true) ? $request->query('tab') : 'upcoming';
        $assetId = $request->integer('asset') ?: null;
        $today = today()->toDateString();

        $rows = DB::table('asset_installments as i')->join('assets as a', 'a.id', '=', 'i.asset_id')->whereNull('a.deleted_at')
            ->leftJoin('suppliers as s', 's.id', '=', 'a.supplier_id')
            ->leftJoin('financial_accounts as fa', 'fa.id', '=', 'i.financial_account_id')
            ->when($assetId, fn ($q, $v) => $q->where('i.asset_id', $v))
            ->when($tab === 'overdue', fn ($q) => $q->whereNull('i.paid_date')->where('i.due_date', '<', $today))
            ->when($tab === 'upcoming', fn ($q) => $q->whereNull('i.paid_date'))
            ->when($tab === 'paid', fn ($q) => $q->whereNotNull('i.paid_date'))
            ->select('i.*', \App\Support\Lang::sel('a.name_ar as asset_name'), 'a.asset_code', 'a.financier_type', 'a.lender_name', 'a.installments_count', \App\Support\Lang::sel('s.name_ar as supplier_name'), \App\Support\Lang::sel('fa.name_ar as account_name'))
            ->orderBy($tab === 'paid' ? 'i.paid_date' : 'i.due_date', $tab === 'paid' ? 'desc' : 'asc')
            ->paginate(50)->withQueryString();

        $alerts = AssetFinance::alerts();

        return view('admin.assets.installments', [
            'rows'     => $rows,
            'tab'      => $tab,
            'asset'    => $assetId ? DB::table('assets')->find($assetId) : null,
            'summary'  => $assetId ? AssetFinance::summary($assetId) : null,
            'alerts'   => $alerts,
            'accounts' => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->pluckLocal('name', 'id'),
            'balances' => \App\Support\Ledger::balances(),
        ]);
    }

    public function payInstallment(Request $request, int $installment): RedirectResponse
    {
        $data = $request->validate([
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')],
            'paid_date'            => ['required', 'date', 'before_or_equal:today'],
            'reference'            => ['nullable', 'string', 'max:100'],
        ], ['required' => 'الحقل ده مطلوب.', 'before_or_equal' => 'مينفعش تاريخ في المستقبل.']);

        $updated = DB::table('asset_installments')->where('id', $installment)->whereNull('paid_date')
            ->update($data + ['paid_by' => $request->user()->id, 'updated_at' => now()]);

        return back()->with($updated ? 'status' : 'error', $updated ? 'تم دفع القسط واتخصم من الخزنة ✅' : 'القسط ده مدفوع قبل كده.');
    }

    public function unpayInstallment(int $installment): RedirectResponse
    {
        DB::table('asset_installments')->where('id', $installment)
            ->update(['paid_date' => null, 'financial_account_id' => null, 'reference' => null, 'paid_by' => null, 'updated_at' => now()]);

        return back()->with('status', 'اتلغى دفع القسط والمبلغ رجع للخزنة');
    }

    /** سداد كل الباقي مرة واحدة (سداد مبكر) — ولو البنك خصم فوايد، اكتب المبلغ الفعلي */
    public function payoff(Request $request, int $asset): RedirectResponse
    {
        $data = $request->validate([
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')],
            'paid_date'            => ['required', 'date', 'before_or_equal:today'],
            'payoff_amount'        => ['nullable', 'numeric', 'min:0'],
        ], ['required' => 'الحقل ده مطلوب.']);

        $open = DB::table('asset_installments')->where('asset_id', $asset)->whereNull('paid_date')->orderBy('seq')->get();
        abort_if($open->isEmpty(), 422, 'مفيش أقساط باقية');

        DB::transaction(function () use ($open, $data, $request) {
            $remaining = (float) $open->sum('amount');
            $actual = isset($data['payoff_amount']) ? Money::round($data['payoff_amount']) : $remaining;
            // الخصم (لو السداد أقل) بيتشال من الفوايد الأول: آخر قسط بياخد الفرق
            $discount = Money::round(max(0, $remaining - $actual));
            $last = $open->last();
            foreach ($open as $i) {
                $upd = ['paid_date' => $data['paid_date'], 'financial_account_id' => $data['financial_account_id'], 'reference' => 'سداد مبكر', 'paid_by' => $request->user()->id, 'updated_at' => now()];
                if ($discount > 0) {
                    $cut = min($discount, (float) $i->interest);
                    if ($i->id === $last->id) {
                        $cut = min($discount, (float) $i->amount);
                    }
                    $upd += ['amount' => Money::round($i->amount - $cut), 'interest' => Money::round(max(0, $i->interest - $cut))];
                    $discount = Money::round($discount - $cut);
                }
                DB::table('asset_installments')->where('id', $i->id)->update($upd);
            }
        });

        return back()->with('status', 'تم سداد كل الأقساط الباقية 🎉');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_ar'         => ['required', 'string', 'max:150'],
            'name_en'         => ['nullable', 'string', 'max:150'],
            'category_id'     => ['required', Rule::exists('finance_categories', 'id')->where('kind', 'asset')->whereNull('parent_id')],
            'sub_category_id' => ['nullable', Rule::exists('finance_categories', 'id')->where('kind', 'asset')->where('parent_id', $request->input('category_id'))],
            'branch_id'       => ['nullable', Rule::exists('branches', 'id')],
            'supplier_id'     => ['nullable', Rule::exists('suppliers', 'id')],
            'financial_account_id' => ['nullable', Rule::exists('financial_accounts', 'id')],
            'serial_number'   => ['nullable', 'string', 'max:100'],
            'location'        => ['nullable', 'string', 'max:150'],
            'custodian'       => ['nullable', 'string', 'max:150'],
            'purchase_date'   => ['required', 'date', 'before_or_equal:today'],
            'purchase_cost'   => ['required', 'numeric', 'min:0.001'],
            'salvage_value'   => ['nullable', 'numeric', 'min:0', 'lte:purchase_cost'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:600'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'image'           => ['nullable', 'image', 'max:4096'],
            'payment_type'    => ['required', Rule::in(['cash', 'installments'])],
            'financier_type'  => ['nullable', 'required_if:payment_type,installments', Rule::in(array_keys(AssetFinance::FINANCIERS))],
            'lender_name'     => ['nullable', 'required_if:financier_type,bank', 'string', 'max:150'],
            'down_payment'    => ['nullable', 'numeric', 'min:0', 'lte:purchase_cost'],
            'interest_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installments_count' => ['nullable', 'required_if:payment_type,installments', 'integer', 'min:1', 'max:360'],
            'first_due_date'  => ['nullable', 'required_if:payment_type,installments', 'date'],
        ], ['required' => 'الحقل ده مطلوب.', 'required_if' => 'الحقل ده مطلوب في التقسيط.', 'down_payment.lte' => 'المقدم أكبر من التكلفة.', 'lte' => 'قيمة الخردة أكبر من التكلفة.', 'exists' => 'الاختيار مش صحيح.', 'before_or_equal' => 'مينفعش تاريخ في المستقبل.']);

        unset($data['image']);

        $inst = $data['payment_type'] === 'installments';

        return array_merge($data, [
            'purchase_cost'      => Money::round($data['purchase_cost']),
            'salvage_value'      => Money::round($data['salvage_value'] ?? 0),
            'financier_type'     => $inst ? $data['financier_type'] : null,
            'lender_name'        => $inst ? ($data['lender_name'] ?? null) : null,
            'down_payment'       => $inst ? Money::round($data['down_payment'] ?? 0) : 0,
            'interest_rate'      => $inst ? (float) ($data['interest_rate'] ?? 0) : 0,
            'installments_count' => $inst ? (int) $data['installments_count'] : null,
            'first_due_date'     => $inst ? $data['first_due_date'] : null,
        ]);
    }
}
