<?php

namespace App\Support;

/**
 * تنسيق الفلوس في كل الشاشات بنفس الشكل.
 * العملة وعدد الكسور من config/laroville.php
 */
class Money
{
    public static function decimals(): int
    {
        return (int) config('laroville.decimals', 3);
    }

    public static function currency(): string
    {
        return (string) config('laroville.currency', '');
    }

    public static function format(mixed $value, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $value, self::decimals());

        return $withCurrency ? $formatted.' '.self::currency() : $formatted;
    }

    public static function round(mixed $value): float
    {
        return round((float) $value, self::decimals());
    }
}
