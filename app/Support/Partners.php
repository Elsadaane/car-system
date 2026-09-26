<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الشركاء في العربيات:
 *   الشريك بيدخل برأس مال في عربية (أو أكتر) وله نسبة من ربحها.
 *   لما العربية تتباع: مستحقه = رأس ماله + (ربح العربية × نسبته)
 *   رصيده = المستحق من العربيات المباعة − اللي اتدفعله
 */
class Partners
{
    public static function ready(): bool
    {
        static $ok;

        return $ok ??= Schema::hasTable('partners');
    }

    /** نصيب كل شريك في عربية معيّنة */
    public static function forVehicle(int $vehicleId): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return DB::table('vehicle_partners')->where('vehicle_id', $vehicleId)
            ->join('partners', 'partners.id', '=', 'vehicle_partners.partner_id')
            ->select('vehicle_partners.*', 'partners.name', 'partners.phone')->orderBy('partners.name')->get();
    }

    /** كشف حساب شريك: العربيات + الحركات + الأرقام */
    public static function statement(int $partnerId): array
    {
        $shares = DB::table('vehicle_partners')->where('vehicle_partners.partner_id', $partnerId)
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_partners.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->whereNull('vehicles.deleted_at')
            ->select('vehicle_partners.*', 'vehicles.vehicle_code', 'vehicles.manufacturing_year', 'vehicles.status as vehicle_status', 'vehicles.total_cost',
                \App\Support\Lang::sel('car_brands.name_ar as brand_name'), \App\Support\Lang::sel('car_models.name_ar as model_name'))
            ->get();

        $sold = $shares->isEmpty() ? collect()
            : SalesStats::sold(['from' => '1900-01-01', 'to' => '2999-12-31', 'vehicles' => $shares->pluck('vehicle_id')->all()])->keyBy('vehicle_id');

        foreach ($shares as $s) {
            $sale = $sold[$s->vehicle_id] ?? null;
            $s->sale = $sale;
            $s->profit_share = $sale ? Money::round($sale->profit * (float) $s->profit_percent / 100) : 0.0;
            $s->due = $sale ? Money::round((float) $s->capital + $s->profit_share) : 0.0;
        }

        $tx = DB::table('partner_transactions')->where('partner_id', $partnerId)->whereNull('partner_transactions.deleted_at')
            ->leftJoin('financial_accounts', 'financial_accounts.id', '=', 'partner_transactions.financial_account_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'partner_transactions.vehicle_id')
            ->select('partner_transactions.*', \App\Support\Lang::sel('financial_accounts.name_ar as account_name'), 'vehicles.vehicle_code')
            ->orderByDesc('entry_date')->orderByDesc('partner_transactions.id')->get();

        $invested = (float) $shares->sum('capital');
        $active = (float) $shares->whereNull('sale')->sum('capital');
        $profit = (float) $shares->sum('profit_share');
        $due = (float) $shares->sum('due');
        $received = (float) $tx->where('kind', 'capital_in')->sum('amount');
        $paid = (float) $tx->where('kind', 'payout')->sum('amount');

        return [
            'shares' => $shares,
            'tx'     => $tx,
            'totals' => [
                'invested' => $invested,          // رأس المال في كل العربيات
                'active'   => $active,            // لسه في عربيات متباعتش
                'profit'   => $profit,            // نصيبه من الربح (المباع)
                'due'      => $due,               // مستحقه من المباع (رأس مال + ربح)
                'received' => $received,          // استلمنا منه فلوس
                'paid'     => $paid,              // دفعناله
                'balance'  => Money::round($due - $paid), // الباقي له
                'unfunded' => Money::round($invested - $received), // رأس مال اتسجل ولسه ماستلمناهوش (لو موجب)
            ],
        ];
    }
}
