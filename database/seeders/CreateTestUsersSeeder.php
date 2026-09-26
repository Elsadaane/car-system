<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * حسابات تجريبية للتطوير والاختبار.
 *
 * ⚠️ للتطوير بس - متشغلوش على نسخة عميل حقيقية.
 *
 * التشغيل:  php artisan db:seed --class=CreateTestUsersSeeder
 */
class CreateTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::main();

        if (! $branch) {
            $this->command->error('مفيش فروع في الداتابيز. شغّل php artisan migrate الأول.');
            return;
        }

        // ---------------- الأدمن ----------------
        $this->verified(User::updateOrCreate(
            ['email' => 'admin@laroville.test'],
            [
                'name'      => 'مدير النظام',
                'password'  => Hash::make('password'),
                'phone'     => '96890000001',
                'role'      => 'admin',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        ));

        // ---------------- الموظف ----------------
        $this->verified(User::updateOrCreate(
            ['email' => 'staff@laroville.test'],
            [
                'name'      => 'موظف مبيعات',
                'password'  => Hash::make('password'),
                'phone'     => '96890000002',
                'role'      => 'staff',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        ));

        // ---------------- العميل ----------------
        $customer = Customer::updateOrCreate(
            ['national_id' => '9876543210987'],
            [
                'customer_code' => 'CUST-0001',
                'name_ar'       => 'أحمد علي',
                'name_en'       => 'Ahmed Ali',
                'phone'         => '96890000003',
                'email'         => 'customer@laroville.test',
                'address'       => 'مسقط - السيب',
                'status'        => 'active',
            ]
        );

        $this->verified(User::updateOrCreate(
            ['email' => 'customer@laroville.test'],
            [
                'name'        => $customer->name_ar,
                'password'    => Hash::make('password'),
                'phone'       => $customer->phone,
                'role'        => 'customer',
                'customer_id' => $customer->id,
                'branch_id'   => null,
                'is_active'   => true,
            ]
        ));

        $this->command->newLine();
        $this->command->info('تم إنشاء الحسابات التجريبية:');
        $this->command->table(
            ['الدور', 'الإيميل', 'الباسورد'],
            [
                ['أدمن',  'admin@laroville.test',    'password'],
                ['موظف',  'staff@laroville.test',    'password'],
                ['عميل',  'customer@laroville.test', 'password'],
            ]
        );
    }

    /** الإيميل متأكَّد - عشان الحسابات التجريبية ماتقفش عند صفحة التأكيد */
    private function verified(User $user): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();
    }
}