<?php

namespace App\Support;

use App\Models\Branch;

/**
 * مفاتيح تشغيل الموديولات.
 *
 * الاستخدام في الكود:
 *     if (Module::enabled('storefront')) { ... }
 *
 * في صفحات Blade:
 *     @module('storefront')
 *         <a href="...">المتجر</a>
 *     @endmodule
 */
class Module
{
    /** كل الموديولات المتاحة في النظام */
    public const ALL = [
        'storefront'         => 'المتجر الإلكتروني',
        'installments'       => 'الأقساط',
        'customer_portal'    => 'بوابة العميل',
        'finance'            => 'طلبات التمويل',
        'test_drives'        => 'تجارب القيادة',
        'delivery_contracts' => 'عقود التسليم',
        'multi_branch'       => 'تعدد الفروع',
    ];

    public static function enabled(string $name): bool
    {
        $value = config("laroville.modules.$name", false);

        // 'auto' يعني النظام يقرر بنفسه
        if ($value === 'auto') {
            return match ($name) {
                'multi_branch' => Branch::isMultiBranch(),
                default        => false,
            };
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function disabled(string $name): bool
    {
        return ! static::enabled($name);
    }

    /** أسماء الموديولات الشغالة - للعرض في لوحة الإدارة */
    public static function active(): array
    {
        return array_keys(array_filter(
            static::ALL,
            fn ($key) => static::enabled($key),
            ARRAY_FILTER_USE_KEY
        ));
    }

    /** جدول كامل للعرض: اسم عربي + شغال ولا لأ */
    public static function status(): array
    {
        $out = [];
        foreach (static::ALL as $key => $label) {
            $out[$key] = ['label' => $label, 'enabled' => static::enabled($key)];
        }

        return $out;
    }

    /** بيرمي 404 لو الموديول مقفول - بيتستخدم في الـ controllers */
    public static function ensure(string $name): void
    {
        abort_unless(static::enabled($name), 404, 'الخاصية دي مش متاحة في نسختك.');
    }
}
