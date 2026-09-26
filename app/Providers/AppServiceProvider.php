<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLogin();
    }

    protected function configureLogin(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input('email'))->first();

            if (! $user || ! Hash::check($request->input('password'), $user->password)) {
                return null;
            }

            $fail = fn (string $message) => throw ValidationException::withMessages(['email' => $message]);

            if (! $user->is_active) {
                $fail('الحساب ده موقوف. برجاء التواصل مع الإدارة.');
            }

            $fromCustomerPage = $request->input('portal') === 'customer';

            if ($fromCustomerPage && ! $user->isCustomer()) {
                $fail('الصفحة دي للعملاء. برجاء الدخول من صفحة الموظفين.');
            }

            if (! $fromCustomerPage && $user->isCustomer()) {
                $fail('الصفحة دي للموظفين. برجاء الدخول من صفحة العملاء.');
            }

            if ($user->isCustomer()) {
                if (! $user->customer) {
                    $fail('الحساب غير مربوط بملف عميل. برجاء التواصل مع المعرض.');
                }

                if ($user->customer->status === 'blocked') {
                    $fail('الحساب ده موقوف. برجاء التواصل مع المعرض.');
                }
            }

            return $user;
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}