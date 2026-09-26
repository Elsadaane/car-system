<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * لغات الموقع: العربي على الروابط العادية، والإنجليزي تحت /en
 *   lroute('storefront.vehicles.index')  ← رابط الصفحة بلغة الزائر الحالية
 *   Lang::pick($row, 'name')             ← name_en لو إنجليزي (ولو فاضي يرجع للعربي)
 */
class Lang
{
    public const LOCALES = [
        'ar' => ['name' => 'العربية', 'short' => 'ع', 'dir' => 'rtl', 'og' => 'ar_AR'],
        'en' => ['name' => 'English', 'short' => 'EN', 'dir' => 'ltr', 'og' => 'en_US'],
    ];

    public static function current(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ar';
    }

    public static function isEn(): bool
    {
        return self::current() === 'en';
    }

    public static function dir(): string
    {
        return self::LOCALES[self::current()]['dir'];
    }

    /** اسم الـ route بلغة معيّنة */
    public static function name(string $name, ?string $locale = null): string
    {
        $locale ??= self::current();
        $base = str_starts_with($name, 'storefront.en.') ? 'storefront.'.substr($name, 14) : $name;
        if ($locale === 'en' && str_starts_with($base, 'storefront.')) {
            $en = 'storefront.en.'.substr($base, 11);

            return Route::has($en) ? $en : $base;
        }

        return $base;
    }

    public static function route(string $name, mixed $params = [], ?string $locale = null): string
    {
        return route(self::name($name, $locale), $params);
    }

    /** الصفحة الحالية = الـ route ده؟ (بأي لغة) */
    public static function is(string $name): bool
    {
        $current = (string) request()->route()?->getName();
        $current = str_starts_with($current, 'storefront.en.') ? 'storefront.'.substr($current, 14) : $current;

        return $current === $name || (str_ends_with($name, '.index') && str_starts_with($current, substr($name, 0, -5)));
    }

    /** نفس الصفحة باللغة التانية (للزرار وللـ hreflang) */
    public static function switchUrl(string $locale): ?string
    {
        $route = request()->route();
        $name = $route?->getName();
        if (! $name || ! str_starts_with($name, 'storefront.') || request()->isMethod('POST')) {
            return null;
        }
        $target = self::name($name, $locale);
        if (! Route::has($target) || ($locale === 'ar' && str_starts_with($target, 'storefront.en.'))) {
            return null;
        }

        try {
            $url = route($target, $route->parameters());
        } catch (\Throwable) {
            return null;
        }
        $query = request()->getQueryString();

        return $url.($query ? '?'.$query : '');
    }

    /**
     * بيترجم أي نص عربي بيتطبع بـ {{ }} لو اللغة إنجليزي (الـ labels اللي جاية من الكود زي الحالات والأنواع).
     * البيانات نفسها (أسماء العملاء…) مالهاش ترجمة فبترجع زي ما هي.
     */
    public static function ui(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || app()->getLocale() === 'ar' || ! preg_match('/[\x{0600}-\x{06FF}]/u', $value)) {
            return $value;
        }
        return self::translateUi($value, 3);
    }

    /** ترجمة كاملة، ولو الجملة متركّبة مع اسم/رقم ("تم إضافة العميل خالد") نترجم أطول جزء معروف منها */
    private static function translateUi(string $value, int $depth): string
    {
        if (\Illuminate\Support\Facades\Lang::hasForLocale($value, 'en')) {
            $t = __($value);

            return is_string($t) ? $t : $value;
        }
        if ($depth <= 0 || ! preg_match('/[\x{0600}-\x{06FF}]/u', $value)) {
            return $value;
        }
        $parts = preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        $n = count($parts);
        if ($n < 2) {
            return $value;
        }
        // أوله رقم/اسم لاتيني ← نسيبه ونترجم الباقي
        if (preg_match('/^[\d.,:#\/\-A-Za-z]+$/u', $parts[0])) {
            return $parts[0].($parts[1] ?? '').self::translateUi(implode('', array_slice($parts, 2)), $depth - 1);
        }
        for ($k = $n - 1; $k >= 1; $k--) {
            $head = implode('', array_slice($parts, 0, $k));
            foreach ([$head, rtrim($head)] as $cand) {
                if ($cand !== '' && \Illuminate\Support\Facades\Lang::hasForLocale($cand, 'en')) {
                    return __($cand).self::translateUi(implode('', array_slice($parts, $k)), $depth - 1);
                }
            }
        }

        return $value;
    }

    /** اللغة اللي الموظف مختارها للوحة التحكم */
    public static function adminLocale(): string
    {
        $u = auth()->user();
        $l = session('admin_locale') ?? $u?->locale ?? config('app.admin_locale', 'ar');

        return in_array($l, ['ar', 'en'], true) ? $l : 'ar';
    }

    /** الحقل باللغة الحالية: name_en / name_ar (أو name لو مفيش _ar) */
    public static function pick(mixed $row, string $field): ?string
    {
        if (! $row) {
            return null;
        }
        $get = fn ($k) => is_array($row) ? ($row[$k] ?? null) : ($row->{$k} ?? null);
        if (self::isEn() && filled($get($field.'_en'))) {
            return $get($field.'_en');
        }

        return $get($field.'_ar') ?? $get($field);
    }

    /**
     * عمود اسم في select حسب لغة اللوحة: 'car_brands.name_ar as brand_name'
     * في الإنجليزي بيرجع COALESCE(name_en, name_ar) — ولو الجدول مفيهوش عمود إنجليزي بيفضل العربي زي ما هو.
     */
    public static function sel(string $expr): mixed
    {
        if (! self::isEn() || ! preg_match('/^(\w+)\.(\w+)_ar as (\w+)$/', $expr, $m) || ! self::hasEn($m[1], $m[2])) {
            return $expr;
        }

        return \Illuminate\Support\Facades\DB::raw("COALESCE(NULLIF({$m[1]}.{$m[2]}_en, ''), {$m[1]}.{$m[2]}_ar) as {$m[3]}");
    }

    /** نفس sel بس كتعبير SQL خام (جوه selectRaw): COALESCE(NULLIF(c.name_en,''), c.name_ar) أو c.name_ar */
    public static function sqlName(string $table, string $field = 'name'): string
    {
        return self::isEn() && self::hasEn($table, $field) ? "COALESCE(NULLIF({$table}.{$field}_en, ''), {$table}.{$field}_ar)" : "{$table}.{$field}_ar";
    }

    /** الجداول اللي فيها خانة _en (الاختصارات b / m / c… اللي مستخدمة في الاستعلامات متوصّلة بجداولها) */
    private static function hasEn(string $table, string $field): bool
    {
        static $alias = ['b' => 'car_brands', 'm' => 'car_models', 'c' => 'customers', 'sc' => 'finance_categories', 'fa' => 'financial_accounts',
            't' => 'financial_accounts', 'f' => 'financial_accounts', 'br' => 'branches', 'pm' => 'payment_methods', 's' => 'suppliers',
            'd' => 'hr_departments', 'e' => 'hr_employees', 'a' => 'assets'];
        static $cache = [];
        $real = $alias[$table] ?? $table;

        return $cache[$real.'.'.$field] ??= (function () use ($real, $field) {
            try {
                return \Illuminate\Support\Facades\Schema::hasColumn($real, $field.'_en');
            } catch (\Throwable) {
                return false;
            }
        })();
    }

    /** زي pick بس لمجموعة صفوف: [id => الاسم باللغة الحالية] (للقوايم المنسدلة) */
    public static function pluckRows(iterable $rows, string $field, ?string $key = null): \Illuminate\Support\Collection
    {
        $out = collect();
        foreach ($rows as $r) {
            $v = self::pick($r, $field);
            $key === null ? $out->push($v) : $out->put(is_array($r) ? $r[$key] : $r->{$key}, $v);
        }

        return $out;
    }
}
