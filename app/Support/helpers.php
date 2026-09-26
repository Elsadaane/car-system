<?php

use App\Support\Lang;

if (! function_exists('lroute')) {
    /** رابط صفحة في الموقع بلغة الزائر الحالية */
    function lroute(string $name, mixed $params = [], ?string $locale = null): string
    {
        return Lang::route($name, $params, $locale);
    }
}
