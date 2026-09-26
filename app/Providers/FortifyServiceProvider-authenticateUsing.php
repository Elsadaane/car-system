<?php
// الصق الجزء ده جوه boot() في app/Providers/FortifyServiceProvider.php
// بدل Fortify::authenticateUsing القديم

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
