<?php

namespace App\Providers;

use App\Support\Access;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Support/helpers.php'); // lroute()
    }

    public function boot(): void
    {
        // pluckLocal('name', 'id'): زي pluck('name_ar', 'id') بس في الإنجليزي بياخد name_en لو مكتوب
        $pluckLocal = function (string $field, ?string $key = null) {
            if (! \App\Support\Lang::isEn()) {
                return $this->pluck($field.'_ar', $key);
            }

            return \App\Support\Lang::pluckRows($this instanceof \Illuminate\Support\Collection ? $this : $this->get(), $field, $key);
        };
        \Illuminate\Database\Query\Builder::macro('pluckLocal', $pluckLocal);
        \Illuminate\Database\Eloquent\Builder::macro('pluckLocal', $pluckLocal);
        \Illuminate\Support\Collection::macro('pluckLocal', $pluckLocal);

        // المدير العام عنده كل الصلاحيات من غير ما نحددها واحدة واحدة
        Gate::before(fn ($user) => Access::isSuper($user) ? true : null);

        // أي نص عربي بيتطبع بـ {{ }} بيتترجم لوحده لو اللغة إنجليزي (labels الحالات والأنواع اللي في الكود)
        Blade::setEchoFormat('e(\\App\\Support\\Lang::ui(%s))');
        // …بس خانات الإدخال (value / textarea / data-*) عمرها ما تتترجم — عشان البيانات تتحفظ زي ما هي
        Blade::precompiler(fn (string $v) => preg_replace_callback(
            '/(\s(?:value|data-[\w-]+)=")\{\{\s*((?:(?!\{\{|\}\}|").)+?)\s*\}\}(")|(<textarea\b[^>]*>)\s*\{\{\s*((?:(?!\{\{|\}\}).)+?)\s*\}\}\s*(<\/textarea>)/s',
            fn ($m) => ! empty($m[1]) ? $m[1].'{!! e('.$m[2].') !!}'.$m[3] : $m[4].'{!! e('.$m[5].') !!}'.$m[6],
            $v
        ));

        // رسايل الـ validation المكتوبة بالعربي في الكنترولرز بتتترجم لو اللغة إنجليزي
        \Illuminate\Support\Facades\Validator::resolver(function ($translator, $data, $rules, $messages, $attributes) {
            $tr = fn ($v) => is_string($v) ? \App\Support\Lang::ui($v) : $v;

            return new \Illuminate\Validation\Validator($translator, $data, $rules, array_map($tr, $messages), array_map($tr, $attributes));
        });

        // @perm('sales.delete') ... @endperm
        Blade::if('perm', fn (string $permission) => Access::can($permission));

        // الدخول والخروج ومحاولات الدخول الغلط بتتسجل في سجل النشاط
        Event::listen(Login::class, function (Login $e) {
            DB::table('users')->where('id', $e->user->getAuthIdentifier())->update(['last_login_at' => now()]);
            Audit::log('login', $e->user->getAuthIdentifier(), ['description' => 'تسجيل دخول']);
        });
        Event::listen(Logout::class, fn (Logout $e) => $e->user && Audit::log('logout', $e->user->getAuthIdentifier(), ['description' => 'تسجيل خروج']));
        Event::listen(Failed::class, fn (Failed $e) => Audit::log('login_failed', $e->user?->getAuthIdentifier(), [
            'description' => 'محاولة دخول غلط بإيميل: '.\Illuminate\Support\Str::limit((string) ($e->credentials['email'] ?? '—'), 80),
        ]));
    }
}
