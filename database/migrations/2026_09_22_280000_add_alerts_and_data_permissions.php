<?php

use App\Support\Access;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - users.alerts_seen: التنبيهات اللي الموظف شافها (عشان الرقم الأحمر على الجرس)
 * - صلاحيات "استيراد وتصدير Excel" الجديدة + إضافتها لمدير الفرع والمحاسب
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'alerts_seen')) {
            Schema::table('users', fn (Blueprint $t) => $t->json('alerts_seen')->nullable());
        }

        if (class_exists(\Spatie\Permission\Models\Permission::class) && Schema::hasTable('permissions')) {
            Access::sync();
            $give = ['مدير فرع' => ['data.view', 'data.create'], 'محاسب' => ['data.view']];
            foreach ($give as $role => $perms) {
                $r = \Spatie\Permission\Models\Role::where('name', $role)->where('guard_name', 'web')->first();
                $r?->givePermissionTo($perms);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'alerts_seen')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('alerts_seen'));
        }
    }
};
