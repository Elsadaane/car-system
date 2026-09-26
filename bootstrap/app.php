<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// لو Spatie مش متسطّب: بدل ما الدخول يقع، المدير العام بس يدخل لحد ما يتسطّب
if (! trait_exists(\Spatie\Permission\Traits\HasRoles::class)) {
    require __DIR__.'/../app/Support/Compat/spatie-fallback.php';
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \App\Providers\AccessServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'perm' => \App\Http\Middleware\EnsurePermission::class,
            'audit' => \App\Http\Middleware\LogActivity::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        // هيدرز الحماية على كل صفحات الموقع واللوحة
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);

        $middleware->redirectGuestsTo(fn (Request $request) =>
            $request->is('customer', 'customer/*')
                ? route('customer.login')
                : route('login')
        );
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // نسخة احتياطية كل يوم 3 الفجر (لو السيرفر عليه cron) — ولو مفيش، بتتعمل مع أول دخول للوحة في اليوم
        foreach (array_filter(array_map('trim', explode(',', (string) config('backup.times', '03:00,15:00')))) as $at) {
            $schedule->command('laroville:backup')->dailyAt($at)->withoutOverlapping(120);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
