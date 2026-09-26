<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إعدادات الموقع (جدول storefront_settings) - بتتعدل من "إعدادات المتجر" في لوحة الإدارة.
 */
class Site
{
    private const KEY = 'laroville.site_settings';

    /** [group => [title, [key => [label, type, default, hint]]]] */
    public const FIELDS = [
        'general' => ['🏪 عام', [
            'site_name'         => ['اسم الموقع', 'text', null, 'لو فاضي بيتاخد اسم الشركة من إعدادات النظام'],
            'tagline'           => ['وصف قصير', 'text', 'سيارات مفحوصة بالكاش والتقسيط', null],
            'primary_color'     => ['اللون الأساسي', 'color', '#fbbf24', 'لون الأزرار والعناوين المميزة'],
            'announcement_text' => ['شريط إعلان فوق الموقع', 'text', null, 'مثلاً: 🔥 عروض العيد — خصومات لحد 15%'],
            'announcement_link' => ['رابط الشريط', 'text', null, null],
            'phone'             => ['رقم التليفون', 'text', null, null],
            'whatsapp'          => ['رقم الواتساب', 'text', null, 'بالكود الدولي: 96895800300'],
            'email'             => ['الإيميل', 'text', null, null],
            'address'           => ['العنوان', 'text', null, null],
            'working_hours'     => ['مواعيد العمل', 'text', 'السبت - الخميس: 9 ص - 10 م', null],
            'map_url'           => ['رابط الخريطة (Google Maps)', 'text', null, null],
            'about'             => ['عن المعرض (صفحة من نحن)', 'textarea', null, null],
        ]],
        'hero' => ['🖼️ الواجهة الرئيسية', [
            'hero_title'     => ['العنوان', 'text', 'عربيتك الجديدة', null],
            'hero_highlight' => ['الكلمة الملوّنة', 'text', 'مستنياك', null],
            'hero_subtitle'  => ['الوصف', 'textarea', 'تشكيلة سيارات مفحوصة بالكامل، بأسعار واضحة من غير مفاجآت، ونظام تقسيط مريح تختار مدته بنفسك.', null],
            'hero_image'     => ['صورة الخلفية', 'image', null, 'عربية بخلفية غامقة بتطلع أحلى'],
        ]],
        'sections' => ['🧩 أقسام الرئيسية', [
            'show_offers'       => ['العروض بعداد تنازلي', 'bool', '1', null],
            'show_featured'     => ['السيارات المميزة', 'bool', '1', null],
            'show_body_types'   => ['تصفح حسب النوع (SUV، سيدان...)', 'bool', '1', null],
            'show_brands'       => ['الماركات', 'bool', '1', null],
            'show_latest'       => ['أحدث الإضافات', 'bool', '1', null],
            'show_calculator'   => ['حاسبة التقسيط', 'bool', '1', null],
            'show_features'     => ['ليه إحنا', 'bool', '1', null],
            'show_testimonials' => ['آراء العملاء', 'bool', '1', null],
            'show_sell_cta'     => ['قيّم عربيتك', 'bool', '1', null],
            'show_faq'          => ['الأسئلة الشائعة', 'bool', '1', null],
            'show_blog'         => ['أحدث المقالات', 'bool', '1', null],
        ]],
        'finance' => ['💳 التقسيط', [
            'installment_rate' => ['نسبة الربح السنوية %', 'number', '0', 'ثابتة على المبلغ المقسّط (0 = من غير فوايد)'],
            'min_down_percent' => ['أقل مقدم %', 'number', '20', null],
            'max_months'       => ['أقصى مدة (شهر)', 'number', '60', null],
            'finance_note'     => ['ملاحظة تحت الحاسبة', 'text', 'الأرقام تقريبية — القسط النهائي بيتحدد في العقد.', null],
        ]],
        'social' => ['📱 السوشيال', [
            'instagram' => ['Instagram', 'text', null, null], 'facebook' => ['Facebook', 'text', null, null],
            'tiktok'    => ['TikTok', 'text', null, null],    'snapchat' => ['Snapchat', 'text', null, null],
            'x'         => ['X / Twitter', 'text', null, null], 'youtube' => ['YouTube', 'text', null, null],
        ]],
        'english' => ['🇬🇧 النسخة الإنجليزية', [
            'site_name_en'        => ['Site name', 'text', null, 'لو فاضي بياخد الاسم الإنجليزي للشركة'],
            'tagline_en'          => ['Tagline', 'text', 'Inspected cars — cash or installments', null],
            'announcement_text_en'=> ['Announcement bar', 'text', null, null],
            'hero_title_en'       => ['Hero title', 'text', 'Your next car', null],
            'hero_highlight_en'   => ['Highlighted word', 'text', 'is waiting', null],
            'hero_subtitle_en'    => ['Hero subtitle', 'textarea', 'Fully inspected cars, clear prices with no surprises, and flexible installments on your terms.', null],
            'address_en'          => ['Address', 'text', null, null],
            'working_hours_en'    => ['Working hours', 'text', 'Sat – Thu: 9 AM – 10 PM', null],
            'finance_note_en'     => ['Calculator note', 'text', 'Figures are estimates — the final installment is set in the contract.', null],
            'about_en'            => ['About us', 'textarea', null, null],
            'meta_title_en'       => ['Google title', 'text', null, null],
            'meta_description_en' => ['Google description', 'textarea', null, null],
        ]],
        'seo' => ['📈 SEO والتتبع', [
            'meta_title'       => ['عنوان الموقع في جوجل', 'text', null, null],
            'meta_description' => ['وصف الموقع في جوجل', 'textarea', null, null],
            'og_image'         => ['صورة المشاركة (واتساب/فيسبوك)', 'image', null, '1200×630'],
            'meta_pixel_id'    => ['Meta Pixel ID', 'text', null, 'بيبعت PageView + ViewContent + Lead'],
            'ga4_id'           => ['Google Analytics 4 (G-XXXX)', 'text', null, null],
            'gtm_id'           => ['Google Tag Manager (GTM-XXXX)', 'text', null, null],
            'tiktok_pixel_id'  => ['TikTok Pixel ID', 'text', null, null],
            'snap_pixel_id'    => ['Snap Pixel ID', 'text', null, null],
        ]],
    ];

    public static function all(): array
    {
        return Cache::rememberForever(self::KEY, fn () => DB::table('storefront_settings')->pluck('value', 'key')->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = self::all()[$key] ?? null;
        if ($v === null || $v === '') {
            foreach (self::FIELDS as [, $fields]) {
                if (isset($fields[$key])) {
                    return $fields[$key][2] ?? $default;
                }
            }

            return $default;
        }

        return $v;
    }

    public static function on(string $key): bool
    {
        return (string) self::get($key, '0') === '1';
    }

    public static function set(string $key, ?string $value, string $group = 'general'): void
    {
        DB::table('storefront_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'group' => $group, 'updated_at' => now(), 'created_at' => now()]);
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    public const TRANSLATABLE = ['site_name', 'tagline', 'announcement_text', 'hero_title', 'hero_highlight', 'hero_subtitle', 'address', 'working_hours', 'finance_note', 'about', 'meta_title', 'meta_description'];

    /** قيمة الإعداد بلغة الزائر (النسخة الإنجليزية لو موجودة) */
    public static function t(string $key, mixed $default = null): mixed
    {
        if (app()->getLocale() === 'en' && in_array($key, self::TRANSLATABLE, true)) {
            $en = self::get($key.'_en');
            if (filled($en)) {
                return $en;
            }
        }

        return self::get($key, $default);
    }

    public static function name(): string
    {
        if (app()->getLocale() === 'en') {
            $en = self::get('site_name_en') ?: Company::get('name_en');
            if (filled($en)) {
                return (string) $en;
            }
        }

        return (string) (self::get('site_name') ?: Company::name());
    }

    public static function whatsapp(): ?string
    {
        $n = preg_replace('/\D/', '', (string) (self::get('whatsapp') ?: Company::get('whatsapp') ?: self::get('phone') ?: Company::get('phone')));

        return $n ?: null;
    }

    public static function phone(): ?string
    {
        return self::get('phone') ?: Company::get('phone');
    }

    /** بنحفظ مصدر الزيارة (إعلان فيسبوك/جوجل...) عشان يتسجل مع الطلب */
    public static function captureUtm(Request $request): void
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'gclid'];
        $found = array_filter($request->only($keys));
        if ($found) {
            $request->session()->put('utm', array_merge($request->session()->get('utm', []), $found));
        }
    }
}
