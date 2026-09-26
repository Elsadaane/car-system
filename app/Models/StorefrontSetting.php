<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * إعدادات الموقع العام - اسم المعرض، التليفون، السوشيال، إلخ.
 * الاستخدام:  StorefrontSetting::get('whatsapp', '');
 */
class StorefrontSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'label_ar'];

    public $timestamps = true;

    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("storefront.$key", function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function put(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget("storefront.$key");
    }

    protected static function booted(): void
    {
        static::saved(fn (self $s) => Cache::forget("storefront.{$s->key}"));
        static::deleted(fn (self $s) => Cache::forget("storefront.{$s->key}"));
    }
}
