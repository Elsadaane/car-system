<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * هيدرز حماية لكل صفحات الموقع:
 *  - الموقع ميتفتحش جوه iframe في موقع تاني (Clickjacking)
 *  - المتصفح ميخمّنش نوع الملفات (MIME sniffing)
 *  - الرابط اللي بيتبعت لمواقع تانية من غير تفاصيل
 *  - HTTPS إجباري لما الموقع يكون على HTTPS
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $h = $response->headers;
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $h->remove('X-Powered-By');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // صفحات لوحة الإدارة متتخزنش في كاش المتصفح أو البروكسي (بيانات حساسة)
        if ($request->is('admin', 'admin/*') && ! $h->has('Content-Disposition')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
