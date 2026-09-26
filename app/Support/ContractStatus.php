<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * أرقام حالات العقود (draft / active / completed / cancelled / expired)
 * من جدول contract_statuses - بتتقرا مرة واحدة في الريكوست.
 */
class ContractStatus
{
    private static array $cache = [];

    public static function id(string $code): ?int
    {
        self::$cache[$code] ??= (int) DB::table('contract_statuses')->where('code', $code)->value('id');

        return self::$cache[$code] ?: null;
    }
}
