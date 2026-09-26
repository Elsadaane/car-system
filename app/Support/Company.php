<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * بيانات الشركة من صفحة "إعدادات النظام" - بتتقرا مرة وتتخزن في الكاش،
 * وبتستخدم في كل الطباعة والإيصالات والعقود والهيدر.
 *
 *   Company::info()            ← كل البيانات (object)
 *   Company::get('phone')      ← حقل واحد
 *   Company::name()            ← اسم الشركة (أو اسم التطبيق لو لسه ماتسجلش)
 */
class Company
{
    private const KEY = 'laroville.company_info.v2';

    private static ?object $memo = null;

    public static function info(): object
    {
        if (self::$memo) {
            return self::$memo;
        }

        // بنخزن array مش object: Laravel 13 بيمنع فك الـ objects من الكاش (serializable_classes)
        $data = Cache::rememberForever(self::KEY, fn () => (array) (DB::table('company_info')->first() ?? []));
        if (! is_array($data)) {
            Cache::forget(self::KEY);
            $data = (array) (DB::table('company_info')->first() ?? []);
        }

        return self::$memo = (object) $data;
    }

    public static function get(string $field, mixed $default = null): mixed
    {
        $value = self::info()->{$field} ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function name(?bool $en = null): string
    {
        $en ??= app()->getLocale() === 'en'; // لوحة التحكم بالإنجليزي ← الاسم الإنجليزي لو موجود

        return (string) ($en ? (self::get('name_en') ?: self::get('name_ar', config('app.name'))) : self::get('name_ar', config('app.name')));
    }

    public static function logo(): ?string
    {
        return Media::url(self::get('logo'));
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
        self::$memo = null;
    }
}
