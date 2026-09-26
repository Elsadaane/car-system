<?php

namespace App\Support;

use App\Models\DealerContract;
use App\Models\SalesContract;
use Illuminate\Support\Facades\DB;

/**
 * دفتر الخزائن: كل حركة فلوس في السيستم في مكان واحد.
 *
 *   داخل:  تحصيل من العملاء، إيرادات، بيع أصل، تحويل وارد
 *   خارج:  دفعات للملاك والموردين، مصروفات، شراء أصل، تحويل صادر
 *
 * الدفعة بتتحسب على الخزنة المربوطة بيها، ولو مش مربوطة
 * بتتحسب على خزنة طريقة الدفع (من صفحة طرق الدفع).
 */
class Ledger
{
    public const KINDS = [
        'sale_payment'     => 'تحصيل من عميل',
        'dealer_payment'   => 'دفعة لمالك (تفويض)',
        'supplier_payment' => 'دفعة لمورد',
        'expense'          => 'مصروف',
        'revenue'          => 'إيراد',
        'asset_purchase'   => 'شراء أصل',
        'asset_sale'       => 'بيع أصل',
        'asset_installment'=> 'قسط أصل',
        'transfer_in'      => 'تحويل وارد',
        'transfer_out'     => 'تحويل صادر',
        'salary'           => 'مرتبات',
        'employee_loan'    => 'سلفة موظف',
        'end_of_service'   => 'نهاية خدمة',
        'partner_in'       => 'استلام من شريك',
        'partner_out'      => 'دفعة لشريك',
    ];

    /** كل الحركات كـ query واحد: account_id, entry_date, amount_in, amount_out, kind, ref, description, branch_id, source_id */
    public static function entries()
    {
        $pdo = DB::getPdo();
        $sale = $pdo->quote((new SalesContract)->getMorphClass());
        $dealer = $pdo->quote((new DealerContract)->getMorphClass());

        $payments = DB::table('payments')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'payments.payment_method_id')
            ->whereNull('payments.deleted_at')->where('payments.status', 'completed')
            ->selectRaw("coalesce(payments.financial_account_id, pm.financial_account_id) as account_id, payments.payment_date as entry_date,
                case when payments.payable_type = {$sale} then payments.amount else 0 end as amount_in,
                case when payments.payable_type <> {$sale} then payments.amount else 0 end as amount_out,
                case when payments.payable_type = {$sale} then 'sale_payment' when payments.payable_type = {$dealer} then 'dealer_payment' else 'supplier_payment' end as kind,
                payments.payment_reference as ref, payments.notes as description,
                case when payments.payable_type = {$sale} then (select sc.branch_id from sales_contracts sc where sc.id = payments.payable_id) end as branch_id,
                payments.id as source_id");

        $cash = DB::table('cash_entries')->whereNull('deleted_at')
            ->selectRaw("financial_account_id as account_id, entry_date,
                case when kind = 'revenue' then total_amount else 0 end as amount_in,
                case when kind = 'expense' then total_amount else 0 end as amount_out,
                kind, code as ref, details as description, branch_id, id as source_id");

        // الكاش: التكلفة كلها يوم الشراء — التقسيط: المقدم بس (والأقساط بتتسجل لما تتدفع)
        $assetBuy = DB::table('assets')->whereNull('deleted_at')->whereNotNull('financial_account_id')
            ->whereRaw("(case when payment_type = 'installments' then down_payment else purchase_cost end) > 0")
            ->selectRaw("financial_account_id as account_id, purchase_date as entry_date, 0 as amount_in,
                case when payment_type = 'installments' then down_payment else purchase_cost end as amount_out,
                'asset_purchase' as kind, asset_code as ref, name_ar as description, branch_id, id as source_id");

        $assetInst = DB::table('asset_installments as ai')->join('assets as a', 'a.id', '=', 'ai.asset_id')
            ->whereNull('a.deleted_at')->whereNotNull('ai.paid_date')->whereNotNull('ai.financial_account_id')
            ->selectRaw("ai.financial_account_id as account_id, ai.paid_date as entry_date, 0 as amount_in, ai.amount as amount_out,
                'asset_installment' as kind, a.asset_code as ref, a.name_ar as description, a.branch_id, ai.id as source_id");

        $assetSell = DB::table('assets')->whereNull('deleted_at')->whereNotNull('financial_account_id')
            ->where('disposal_amount', '>', 0)->whereNotNull('disposal_date')
            ->selectRaw("financial_account_id as account_id, disposal_date as entry_date, disposal_amount as amount_in, 0 as amount_out,
                'asset_sale' as kind, asset_code as ref, name_ar as description, branch_id, id as source_id");

        $tIn = DB::table('account_transfers')->whereNull('deleted_at')
            ->selectRaw("to_account_id as account_id, transfer_date as entry_date, amount as amount_in, 0 as amount_out,
                'transfer_in' as kind, code as ref, notes as description, null as branch_id, id as source_id");

        $tOut = DB::table('account_transfers')->whereNull('deleted_at')
            ->selectRaw("from_account_id as account_id, transfer_date as entry_date, 0 as amount_in, amount as amount_out,
                'transfer_out' as kind, code as ref, notes as description, null as branch_id, id as source_id");

        $salaries = DB::table('hr_payrolls')->join('hr_employees as e', 'e.id', '=', 'hr_payrolls.employee_id')
            ->join('hr_payroll_runs as r', 'r.id', '=', 'hr_payrolls.payroll_run_id')
            ->whereNotNull('hr_payrolls.paid_date')->where('hr_payrolls.net', '>', 0)
            ->selectRaw("hr_payrolls.financial_account_id as account_id, hr_payrolls.paid_date as entry_date, 0 as amount_in, hr_payrolls.net as amount_out,
                'salary' as kind, concat('PAY-', r.year, '-', lpad(r.month, 2, '0')) as ref, e.full_name_ar as description, hr_payrolls.branch_id, hr_payrolls.id as source_id");

        $loans = DB::table('hr_loans')->join('hr_employees as e', 'e.id', '=', 'hr_loans.employee_id')
            ->whereNotNull('hr_loans.financial_account_id')->where('hr_loans.status', '!=', 'cancelled')
            ->selectRaw("hr_loans.financial_account_id as account_id, hr_loans.loan_date as entry_date, 0 as amount_in, hr_loans.amount as amount_out,
                'employee_loan' as kind, concat('LOAN-', hr_loans.id) as ref, e.full_name_ar as description, e.branch_id, hr_loans.id as source_id");

        $eos = DB::table('hr_end_of_services')->join('hr_employees as e', 'e.id', '=', 'hr_end_of_services.employee_id')
            ->whereNotNull('hr_end_of_services.paid_date')->where('hr_end_of_services.net_payout', '>', 0)
            ->selectRaw("hr_end_of_services.financial_account_id as account_id, hr_end_of_services.paid_date as entry_date, 0 as amount_in, hr_end_of_services.net_payout as amount_out,
                'end_of_service' as kind, concat('EOS-', hr_end_of_services.id) as ref, e.full_name_ar as description, e.branch_id, hr_end_of_services.id as source_id");

        $all = $payments->unionAll($cash)->unionAll($assetBuy)->unionAll($assetInst)->unionAll($assetSell)->unionAll($tIn)->unionAll($tOut)
            ->unionAll($salaries)->unionAll($loans)->unionAll($eos);

        // الشركاء: استلام رأس مال (داخل) ودفعات ليهم (خارج)
        if (Partners::ready()) {
            $all = $all->unionAll(DB::table('partner_transactions as pt')->join('partners as pa', 'pa.id', '=', 'pt.partner_id')
                ->whereNull('pt.deleted_at')->whereNotNull('pt.financial_account_id')
                ->selectRaw("pt.financial_account_id as account_id, pt.entry_date, case when pt.kind = 'capital_in' then pt.amount else 0 end as amount_in,
                    case when pt.kind = 'payout' then pt.amount else 0 end as amount_out, case when pt.kind = 'capital_in' then 'partner_in' else 'partner_out' end as kind,
                    concat('PRT-', pt.id) as ref, pa.name as description, null as branch_id, pt.id as source_id"));
        }

        return DB::query()->fromSub($all, 'ledger');
    }

    /** رصيد كل خزنة [account_id => ['in','out','balance']] - لحد تاريخ معيّن (اختياري) */
    public static function balances(?string $until = null): array
    {
        $sums = self::entries()
            ->when($until, fn ($q, $v) => $q->where('entry_date', '<=', $v))
            ->groupBy('account_id')
            ->selectRaw('account_id, sum(amount_in) as total_in, sum(amount_out) as total_out')
            ->get()->keyBy('account_id');

        $out = [];
        foreach (DB::table('financial_accounts')->whereNull('deleted_at')->get() as $a) {
            $s = $sums[$a->id] ?? null;
            $in = (float) ($s->total_in ?? 0);
            $o = (float) ($s->total_out ?? 0);
            $out[$a->id] = ['in' => $in, 'out' => $o, 'balance' => Money::round((float) $a->opening_balance + $in - $o)];
        }
        $un = $sums[''] ?? ($sums[null] ?? null);
        $out['unlinked'] = ['in' => (float) ($un->total_in ?? 0), 'out' => (float) ($un->total_out ?? 0)];

        return $out;
    }
}
