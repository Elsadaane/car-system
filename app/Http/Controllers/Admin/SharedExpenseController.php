<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * مصروف مشترك (شحن حاوية، تخليص، نقل…) بيتقسم على كذا عربية:
 *   بالتساوي، أو حسب تكلفة كل عربية.
 * كل جزء بيتسجل مصروف عادي على عربيته ← ربح كل عربية والخزنة بيتظبطوا لوحدهم.
 */
class SharedExpenseController extends Controller
{
    public const METHODS = ['equal' => 'بالتساوي على العربيات', 'cost' => 'حسب تكلفة كل عربية'];

    public function index(): View
    {
        return view('admin.shared-expenses.index', [
            'rows' => DB::table('shared_expenses')->whereNull('shared_expenses.deleted_at')
                ->leftJoin('finance_categories', 'finance_categories.id', '=', 'shared_expenses.category_id')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'shared_expenses.supplier_id')
                ->tap(fn ($q) => \App\Support\Access::branchIds() === null ? $q : $q->whereIn('shared_expenses.branch_id', \App\Support\Access::branchIds()))
                ->select('shared_expenses.*', \App\Support\Lang::sel('finance_categories.name_ar as category_name'), \App\Support\Lang::sel('suppliers.name_ar as supplier_name'),
                    DB::raw('(select count(*) from cash_entries ce where ce.shared_expense_id = shared_expenses.id and ce.deleted_at is null) as cars'))
                ->latest('entry_date')->latest('shared_expenses.id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $vehicles = DB::table('vehicles')->whereNull('vehicles.deleted_at')
            ->whereNotIn('vehicles.status', ['returned'])
            ->tap(fn ($q) => \App\Support\Access::scope($q, 'vehicles.branch_id'))
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->select('vehicles.id', 'vehicles.vehicle_code', 'vehicles.chassis_number', 'vehicles.manufacturing_year', 'vehicles.status', 'vehicles.total_cost', 'vehicles.created_at',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->orderByRaw("case when vehicles.status = 'sold' then 1 else 0 end")->latest('vehicles.created_at')->limit(400)->get();

        return view('admin.shared-expenses.form', [
            'vehicles'   => $vehicles,
            'categories' => DB::table('finance_categories')->where('kind', 'expense')->whereNull('parent_id')->where('is_active', true)->orderBy('name_ar')->pluckLocal('name', 'id'),
            'accounts'   => DB::table('financial_accounts')->whereNull('deleted_at')->where('is_active', true)->orderBy('name_ar')->pluckLocal('name', 'id'),
            'suppliers'  => DB::table('suppliers')->whereNull('deleted_at')->orderBy('name_ar')->pluckLocal('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date'           => ['required', 'date', 'before_or_equal:today'],
            'category_id'          => ['required', Rule::exists('finance_categories', 'id')->where('kind', 'expense')],
            'supplier_id'          => ['nullable', Rule::exists('suppliers', 'id')],
            'financial_account_id' => ['required', Rule::exists('financial_accounts', 'id')->whereNull('deleted_at')],
            'amount'               => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'tax_percent'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'method'               => ['required', 'in:equal,cost'],
            'details'              => ['required', 'string', 'max:255'],
            'vehicles'             => ['required', 'array', 'min:2', 'max:200'],
            'vehicles.*'           => ['integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
        ], [
            'vehicles.required' => 'اختار العربيات اللي المصروف هيتقسم عليها.', 'vehicles.min' => 'المصروف المشترك لازم على عربيتين على الأقل — لعربية واحدة سجّله مصروف عادي.',
            'required' => 'الحقل ده مطلوب.', 'before_or_equal' => 'مينفعش تاريخ في المستقبل.',
        ]);

        $cars = DB::table('vehicles')->whereIn('id', array_unique($data['vehicles']))->get(['id', 'branch_id', 'total_cost', 'vehicle_code'])->keyBy('id');
        $amount = Money::round($data['amount']);
        $taxPct = (float) ($data['tax_percent'] ?? 0);
        $tax = Money::round($amount * $taxPct / 100);

        // الأوزان: بالتساوي أو حسب التكلفة (لو كل التكاليف صفر يرجع للتساوي)
        $weights = $cars->map(fn ($c) => $data['method'] === 'cost' ? max(0, (float) $c->total_cost) : 1.0);
        if ($weights->sum() <= 0) {
            $weights = $weights->map(fn () => 1.0);
        }
        $shares = $this->split($amount, $weights);
        $taxShares = $this->split($tax, $weights);

        $branches = $cars->pluck('branch_id')->filter()->unique();
        $code = 'SE-'.now()->format('ymd').'-'.Str::upper(Str::random(4));

        $id = DB::transaction(function () use ($request, $data, $cars, $amount, $taxPct, $tax, $shares, $taxShares, $branches, $code) {
            $id = DB::table('shared_expenses')->insertGetId([
                'code'                 => $code,
                'entry_date'           => $data['entry_date'],
                'category_id'          => $data['category_id'],
                'supplier_id'          => $data['supplier_id'] ?? null,
                'financial_account_id' => $data['financial_account_id'],
                'branch_id'            => $branches->count() === 1 ? $branches->first() : null,
                'amount'               => $amount,
                'tax_percent'          => $taxPct,
                'tax_amount'           => $tax,
                'total_amount'         => Money::round($amount + $tax),
                'method'               => $data['method'],
                'details'              => $data['details'],
                'user_id'              => $request->user()->id,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $n = 0;
            foreach ($cars as $car) {
                $a = $shares[$car->id];
                $t = $taxShares[$car->id];
                DB::table('cash_entries')->insert([
                    'code'                 => $code.'-'.(++$n),
                    'kind'                 => 'expense',
                    'branch_id'            => $car->branch_id,
                    'category_id'          => $data['category_id'],
                    'supplier_id'          => $data['supplier_id'] ?? null,
                    'vehicle_id'           => $car->id,
                    'amount'               => $a,
                    'tax_percent'          => $taxPct,
                    'tax_amount'           => $t,
                    'total_amount'         => Money::round($a + $t),
                    'entry_date'           => $data['entry_date'],
                    'financial_account_id' => $data['financial_account_id'],
                    'details'              => 'مصروف مشترك '.$code.': '.$data['details'],
                    'shared_expense_id'    => $id,
                    'user_id'              => $request->user()->id,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            return $id;
        });

        return redirect()->route('admin.shared-expenses.show', $id)->with('status', 'اتسجل المصروف المشترك واتقسم على '.$cars->count().' عربية.');
    }

    public function show(int $sharedExpense): View
    {
        $e = DB::table('shared_expenses')->where('shared_expenses.id', $sharedExpense)->whereNull('shared_expenses.deleted_at')
            ->leftJoin('finance_categories', 'finance_categories.id', '=', 'shared_expenses.category_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'shared_expenses.supplier_id')
            ->leftJoin('financial_accounts', 'financial_accounts.id', '=', 'shared_expenses.financial_account_id')
            ->leftJoin('users', 'users.id', '=', 'shared_expenses.user_id')
            ->select('shared_expenses.*', \App\Support\Lang::sel('finance_categories.name_ar as category_name'), \App\Support\Lang::sel('suppliers.name_ar as supplier_name'),
                \App\Support\Lang::sel('financial_accounts.name_ar as account_name'), 'users.name as user_name')
            ->first() ?? abort(404);

        return view('admin.shared-expenses.show', [
            'e'     => $e,
            'parts' => DB::table('cash_entries')->where('shared_expense_id', $e->id)->whereNull('cash_entries.deleted_at')
                ->join('vehicles', 'vehicles.id', '=', 'cash_entries.vehicle_id')
                ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
                ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
                ->select('cash_entries.*', 'vehicles.vehicle_code', 'vehicles.total_cost', 'vehicles.status as vehicle_status', 'vehicles.manufacturing_year',
                    \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
                ->orderBy('cash_entries.id')->get(),
        ]);
    }

    public function destroy(int $sharedExpense): RedirectResponse
    {
        DB::transaction(function () use ($sharedExpense) {
            DB::table('cash_entries')->where('shared_expense_id', $sharedExpense)->update(['deleted_at' => now()]);
            DB::table('shared_expenses')->where('id', $sharedExpense)->update(['deleted_at' => now()]);
        });

        return redirect()->route('admin.shared-expenses.index')->with('status', 'اتمسح المصروف المشترك ومصروفات العربيات بتاعته.');
    }

    /** بيقسم مبلغ حسب الأوزان — والكسور بتتحط على آخر عربية عشان المجموع يطلع مظبوط */
    private function split(float $total, $weights): array
    {
        $sum = $weights->sum();
        $out = [];
        $left = $total;
        $ids = $weights->keys()->all();
        foreach ($ids as $i => $id) {
            $share = $i === count($ids) - 1 ? Money::round($left) : Money::round($total * $weights[$id] / $sum);
            $out[$id] = $share;
            $left -= $share;
        }

        return $out;
    }
}
