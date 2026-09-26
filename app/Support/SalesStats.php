<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * أرقام المبيعات المشتركة بين التقارير وعمولة الموظفين.
 *
 * ربح العربية = سعر البيع بدون ضريبة − تكلفتها − مصروفاتها (صيانة/تجهيز)
 *   تكلفتها = سعر الشراء، ولو عربية تفويض = المستحق لصاحبها
 */
class SalesStats
{
    /** عقود البيع غير الملغية */
    public static function contracts()
    {
        $cancelled = ContractStatus::id('cancelled');

        return DB::table('sales_contracts')->whereNull('sales_contracts.deleted_at')
            ->when($cancelled, fn ($q) => $q->where(fn ($w) => $w->whereNull('sales_contracts.status_id')->orWhere('sales_contracts.status_id', '!=', $cancelled)));
    }

    /** صاحب البيعة: مندوب المبيعات، ولو مش متحدد اللي سجّل العقد */
    public static function repColumn(): string
    {
        static $col;

        return $col ??= \Illuminate\Support\Facades\Schema::hasColumn('sales_contracts', 'sales_rep_id')
            ? 'coalesce(sales_contracts.sales_rep_id, sales_contracts.user_id)' : 'sales_contracts.user_id';
    }

    /** العربيات اللي اتباعت في المدة + تكلفتها وربحها */
    public static function sold(array $p): Collection
    {
        $cancelled = (int) ContractStatus::id('cancelled');

        return self::contracts()
            ->whereBetween('sales_contracts.contract_date', [$p['from'], $p['to']])
            ->when($p['branch'] ?? null, fn ($q, $v) => $q->where('sales_contracts.branch_id', $v))
            ->when($p['user'] ?? null, fn ($q, $v) => $q->whereRaw(self::repColumn().' = ?', [$v]))
            ->when($p['vehicles'] ?? null, fn ($q, $v) => $q->whereIn('sales_contracts.vehicle_id', $v))
            ->join('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('branches', 'branches.id', '=', 'sales_contracts.branch_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_contracts.customer_id')
            ->leftJoin('users', 'users.id', '=', DB::raw(self::repColumn()))
            ->select('sales_contracts.id', 'sales_contracts.contract_number', 'sales_contracts.contract_date', 'sales_contracts.total_amount',
                'sales_contracts.tax_amount', 'sales_contracts.discount_amount', 'sales_contracts.paid_amount', 'sales_contracts.remaining_amount',
                'sales_contracts.payment_type', 'sales_contracts.branch_id', DB::raw(self::repColumn().' as user_id'),
                'vehicles.id as vehicle_id', 'vehicles.vehicle_code', 'vehicles.manufacturing_year', 'vehicles.total_cost', 'vehicles.created_at as vehicle_created',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'), \App\Support\Lang::sel('branches.name_ar as branch_name'),
                \App\Support\Lang::sel('customers.name_ar as customer_name'), 'users.name as user_name',
                DB::raw("(select dc.total_amount from dealer_contracts dc where dc.vehicle_id = vehicles.id and dc.deleted_at is null and (dc.status_id is null or dc.status_id <> {$cancelled}) order by dc.id desc limit 1) as dealer_cost"),
                DB::raw("(select coalesce(sum(ce.total_amount), 0) from cash_entries ce where ce.vehicle_id = vehicles.id and ce.kind = 'expense' and ce.deleted_at is null) as extra_cost"))
            ->when(Partners::ready(), fn ($q) => $q->addSelect(DB::raw('(select coalesce(sum(vp.profit_percent), 0) from vehicle_partners vp where vp.vehicle_id = vehicles.id) as partner_pct')))
            ->get()
            ->map(function ($r) {
                $r->is_dealer = $r->dealer_cost !== null;
                $r->cost = $r->is_dealer ? (float) $r->dealer_cost : (float) $r->total_cost;
                $r->extra_cost = (float) $r->extra_cost;
                $r->net_sale = (float) $r->total_amount - (float) $r->tax_amount;
                $r->profit = Money::round($r->net_sale - $r->cost - $r->extra_cost);
                $r->margin = $r->net_sale > 0 ? round($r->profit / $r->net_sale * 100, 1) : 0;
                // نصيب الشركاء من ربح العربية، واللي فاضل للمعرض
                $r->partner_share = Money::round($r->profit * (float) ($r->partner_pct ?? 0) / 100);
                $r->showroom_profit = Money::round($r->profit - $r->partner_share);
                $r->days_to_sell = max(0, (int) Carbon::parse($r->vehicle_created)->startOfDay()->diffInDays(Carbon::parse($r->contract_date)));
                $r->payment_label = $r->payment_type === 'installments' ? 'تقسيط' : 'كاش';

                return $r;
            });
    }

}
