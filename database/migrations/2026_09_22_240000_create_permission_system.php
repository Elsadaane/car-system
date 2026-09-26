<?php

use App\Support\Access;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نظام الصلاحيات (Spatie laravel-permission):
 *  - جداول Spatie (لو مش موجودة)
 *  - users.all_branches: يشوف كل الفروع ولا فرعه بس
 *  - الصلاحيات من الكود + 7 أدوار جاهزة
 *  - الحسابات الحالية: admin ← مدير عام، staff ← مندوب مبيعات (تقدر تغيّرهم بعدين)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(\Spatie\Permission\Models\Permission::class)) {
            throw new \RuntimeException("\n\n  باكدج الصلاحيات مش متسطّبة. شغّل الأمر ده الأول وبعدين اعمل migrate تاني:\n\n      composer require spatie/laravel-permission\n");
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }
        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }
        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        }
        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
            });
        }

        if (! Schema::hasColumn('users', 'all_branches')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('all_branches')->default(false)->after('branch_id')->comment('يشوف كل الفروع ولا فرعه بس');
            });
        }

        // الصلاحيات والأدوار
        Access::sync();
        foreach (Access::defaultRoles() as $name => $perms) {
            $role = \Spatie\Permission\Models\Role::findOrCreate($name, 'web');
            if ($role->permissions()->count() === 0) {
                $role->syncPermissions($perms);
            }
        }

        // الحسابات الحالية
        $userClass = \App\Models\User::class;
        $userClass::query()->where('role', 'admin')->each(fn ($u) => $u->roles()->exists() ?: $u->assignRole(Access::SUPER_ROLE));
        $userClass::query()->where('role', 'staff')->each(fn ($u) => $u->roles()->exists() ?: $u->assignRole('مندوب مبيعات'));
        DB::table('users')->where('role', 'admin')->update(['all_branches' => true]);
        // موظف مالوش فرع متسجل: يفضل شايف كل حاجة لحد ما تحددله فرع (عشان ميلاقيش السيستم فاضي)
        DB::table('users')->where('role', 'staff')->whereNull('branch_id')->update(['all_branches' => true]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'all_branches')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('all_branches'));
        }
    }
};
