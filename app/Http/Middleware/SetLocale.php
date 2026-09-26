<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/** لغة الصفحة: locale:en للروابط تحت /en، locale:ar للعادية، locale:user = اللغة اللي الموظف اختارها (لوحة التحكم) */
class SetLocale
{
    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        $locale = $locale === 'user' ? \App\Support\Lang::adminLocale() : (in_array($locale, ['ar', 'en'], true) ? $locale : 'ar');
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        if ($request->route()?->getName() !== null && str_starts_with($request->route()->getName(), 'admin.')) {
            self::fillPairs($request);
        }

        return $next($request);
    }

    /**
     * إدخال البيانات بأي لغة: لو الخانة العربي فاضية والإنجليزي مكتوب (name_ar / name_en، address_ar / address_en…)
     * بتتملى من الإنجليزي — والعكس لو المكتوب في الخانة الأساسية إنجليزي والخانة الإنجليزي فاضية.
     */
    private static function fillPairs(Request $request): void
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }
        $in = $request->all();
        $merge = [];
        foreach ($in as $key => $val) {
            if (! is_string($key) || ! str_ends_with($key, '_ar') || ! array_key_exists($base = substr($key, 0, -3).'_en', $in)) {
                continue;
            }
            $ar = is_string($val) ? trim($val) : $val;
            $en = is_string($in[$base]) ? trim($in[$base]) : $in[$base];
            if (($ar === null || $ar === '') && is_string($en) && $en !== '') {
                $merge[$key] = $en;
            } elseif (($en === null || $en === '') && is_string($ar) && $ar !== '' && ! preg_match('/\p{Arabic}/u', strip_tags($ar))) {
                $merge[$base] = $ar;
            }
        }
        if ($merge) {
            $request->merge($merge);
        }
    }
}
