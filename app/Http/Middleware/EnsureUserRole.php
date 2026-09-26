<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة الصلاحيات.
 *
 * بتستخدم في الـ routes كده:
 *   Route::middleware(['auth', 'role:customer'])->group(...)
 *   Route::middleware(['auth', 'role:staff,admin'])->group(...)
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // حساب موقوف
        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('login')
                ->withErrors(['email' => 'الحساب ده موقوف. برجاء التواصل مع الإدارة.']);
        }

        if (! in_array($user->role, $roles, true)) {
            // مش من حقه يدخل هنا - وديه للوحة بتاعته
            return redirect()->to($user->homeRoute());
        }

        return $next($request);
    }
}
