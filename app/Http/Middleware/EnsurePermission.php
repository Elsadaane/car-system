<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة الصلاحيات التفصيلية (Spatie) لكل صفحات لوحة الإدارة:
 *  1) الصفحة محتاجة صلاحية؟ (من اسم الـ route — شوف App\Support\Access)
 *  2) اليوزر مقفول على فرعه؟ بيتفلتر أوتوماتيك + ميقدرش يفتح سجل من فرع تاني
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();
        $name = $route?->getName();

        if (! $user || ! $name) {
            return $next($request);
        }

        if (! Access::allows($user, $name, $request->method())) {
            \App\Support\Audit::log('denied', $user->id, ['description' => \App\Support\Audit::describe($name, $request->method())]);
            $msg = 'مش مسموحلك تفتح الصفحة دي أو تعمل الإجراء ده. كلّم المدير العام لو محتاج الصلاحية.';
            if ($request->expectsJson()) {
                abort(403, $msg);
            }

            return $name === 'admin.dashboard' || ! $request->isMethod('GET')
                ? back(fallback: route('admin.dashboard'))->with('error', $msg)
                : redirect()->route('admin.dashboard')->with('error', $msg);
        }

        // مقفول على فرع: الفلاتر بتتثبت على فرعه، وأي سجل من فرع تاني ممنوع
        $ids = Access::branchIds($user);
        if ($ids !== null) {
            if ($request->isMethod('GET')) {
                $request->query->set('branch', $ids[0]);
            }
            foreach ($route->parameters() as $param) {
                if ($param instanceof Model && array_key_exists('branch_id', $param->getAttributes())
                    && $param->branch_id && ! in_array((int) $param->branch_id, $ids, true)) {
                    abort(403, 'السجل ده تابع لفرع تاني.');
                }
            }
        }

        return $next($request);
    }
}
