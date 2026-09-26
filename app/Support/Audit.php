<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * سجل النشاط — بيتكتب من LogActivity (كل الإجراءات) ومن أحداث الدخول/الخروج.
 */
class Audit
{
    public const EVENTS = [
        'create' => ['➕ إضافة', 'bg-emerald-50 text-emerald-700'],
        'update' => ['✏️ تعديل', 'bg-blue-50 text-blue-700'],
        'delete' => ['🗑️ مسح', 'bg-rose-50 text-rose-700'],
        'action' => ['⚡ إجراء', 'bg-violet-50 text-violet-700'],
        'login' => ['🔓 دخول', 'bg-slate-100 text-slate-700'],
        'logout' => ['🔒 خروج', 'bg-slate-100 text-slate-500'],
        'login_failed' => ['⚠️ دخول غلط', 'bg-amber-50 text-amber-700'],
        'denied' => ['⛔ محاولة بدون صلاحية', 'bg-rose-100 text-rose-800'],
        'backup' => ['💾 نسخة احتياطية', 'bg-teal-50 text-teal-700'],
    ];

    /** حقول عمرها ما تتسجل */
    private const SECRET = ['password', 'password_confirmation', 'current_password', '_token', '_method', '_modal', '_edit_url', '_ts', 'website',
        'two_factor_secret', 'two_factor_recovery_codes', 'signature', 'customer_signature', 'seller_signature', 'token', 'code', 'recovery_code'];

    public static function log(string $event, ?int $userId = null, array $extra = []): void
    {
        try {
            $request = request();
            DB::table('audit_logs')->insert([
                'user_id'     => $userId ?? auth()->id(),
                'event'       => $event,
                'route'       => $extra['route'] ?? $request->route()?->getName(),
                'method'      => $extra['method'] ?? $request->method(),
                'url'         => Str::limit($extra['url'] ?? $request->fullUrl(), 490, ''),
                'subject_id'  => isset($extra['subject_id']) ? Str::limit((string) $extra['subject_id'], 60, '') : null,
                'description' => isset($extra['description']) ? Str::limit($extra['description'], 250) : null,
                'data'        => isset($extra['data']) && $extra['data'] ? json_encode($extra['data'], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'ip'          => $request->ip(),
                'user_agent'  => Str::limit((string) $request->userAgent(), 250, ''),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            report($e); // السجل عمره ما يوقّع السيستم
        }
    }

    /** البيانات اللي اتبعتت — من غير كلمات سر، والملفات بأساميها بس، والنصوص الطويلة متقصّرة */
    public static function payload(Request $request): array
    {
        $clean = function ($v, $depth = 0) use (&$clean) {
            if ($v instanceof UploadedFile) {
                return '📎 '.$v->getClientOriginalName();
            }
            if (is_array($v)) {
                if ($depth > 2) {
                    return '[…]';
                }
                $out = [];
                foreach (array_slice($v, 0, 30, true) as $k => $x) {
                    if (! in_array((string) $k, self::SECRET, true)) {
                        $out[$k] = $clean($x, $depth + 1);
                    }
                }

                return $out;
            }

            return is_string($v) ? Str::limit(strip_tags($v), 200) : $v;
        };

        return $clean($request->except(self::SECRET));
    }

    /** وصف مفهوم للـ route: "عقود البيع — مسح" */
    public static function describe(?string $route, ?string $method = 'GET'): string
    {
        if (! $route) {
            return '—';
        }
        $perm = Access::permissionFor($route, $method ?: 'GET');
        if (! $perm) {
            return $route;
        }
        [$module, $action] = explode('.', $perm) + [1 => ''];
        $label = Access::MODULES[$module][0] ?? $module;
        $verb = Str::afterLast($route, '.');
        $act = match (true) {
            in_array($verb, ['store', 'create'], true) => 'إضافة',
            in_array($verb, ['update', 'edit'], true) => 'تعديل',
            $verb === 'destroy' => 'مسح',
            default => Access::ACTIONS[$action] ?? $verb,
        };

        return __($label).' — '.__($act).($verb !== 'destroy' && ! in_array($verb, ['store', 'update', 'create', 'edit'], true) ? ' ('.$verb.')' : '');
    }
}
