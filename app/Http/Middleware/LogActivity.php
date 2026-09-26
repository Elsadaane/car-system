<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use App\Support\Backup;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * بيسجل كل إضافة/تعديل/مسح في لوحة الإدارة (بعد ما تنجح)،
 * وبعد ما الصفحة توصل للمستخدم: نسخة احتياطية يومية لو لسه ما اتعملتش (للسيرفرات اللي مفيهاش cron زي Laragon).
 */
class LogActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && $request->user() && $response->getStatusCode() < 400
            && ! $request->routeIs('admin.dashboard.*', 'admin.notifications.*', 'admin.locale.*')
            && ! ($request->session()->has('errors') && $response->getStatusCode() === 302 && $request->session()->get('errors')->any())) {
            $route = $request->route();
            $verb = Str::afterLast((string) $route?->getName(), '.');
            $subject = collect($route?->parameters() ?? [])->map(fn ($p) => $p instanceof Model ? $p->getKey() : $p)->filter(fn ($p) => is_scalar($p))->implode('/');

            Audit::log(match ($verb) {
                'store', 'upload' => 'create',
                'update' => 'update',
                'destroy' => 'delete',
                default => 'action',
            }, null, [
                'subject_id'  => $subject ?: null,
                'description' => Audit::describe($route?->getName(), $request->method()),
                'data'        => Audit::payload($request),
            ]);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        // تنضيف السجل القديم مرة في اليوم
        if (Cache::add('laroville.audit_prune.'.today()->toDateString(), 1, now()->endOfDay())) {
            try {
                DB::table('audit_logs')->where('created_at', '<', now()->subDays(365))->delete();
            } catch (\Throwable) {
            }
        }

        if ($request->user() && Backup::dueNow()) {
            Backup::runSafely('auto');
        }
    }
}
