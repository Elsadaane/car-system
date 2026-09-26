<?php

/**
 * حماية: لو باكدج Spatie مش متسطّب (vendor ناقص)، السيستم ميقعش.
 * في الحالة دي المدير العام بس اللي يقدر يستخدم اللوحة لحد ما تشغّل:
 *   composer require spatie/laravel-permission
 */

namespace Spatie\Permission\Traits;

if (! trait_exists(HasRoles::class, false)) {
    trait HasRoles
    {
    }
}
