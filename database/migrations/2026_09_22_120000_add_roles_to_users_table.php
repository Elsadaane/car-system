<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'staff', 'admin'])
                  ->default('customer')
                  ->after('password');

            $table->string('phone')->nullable()->after('email');

            $table->string('national_id')->nullable()->unique()->after('phone')
                  ->comment('الرقم القومي / المدني');

            $table->boolean('is_active')->default(true)->after('national_id');

            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            // unique: ملف العميل الواحد ليه حساب دخول واحد بس
            $table->foreignId('customer_id')->nullable()->unique()->after('role')
                  ->constrained('customers')->nullOnDelete();

            $table->foreignId('branch_id')->nullable()->after('customer_id')
                  ->constrained('branches')->nullOnDelete();

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['branch_id']);
            $table->dropUnique(['customer_id']);
            $table->dropUnique(['national_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'role', 'customer_id', 'branch_id', 'phone',
                'national_id', 'is_active', 'last_login_at',
            ]);
        });
    }
};
