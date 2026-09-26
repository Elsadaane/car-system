<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تفاصيل العقود: الشروط والتوقيعات الإلكترونية
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_contracts', function (Blueprint $table) {
            $table->text('terms_ar')->nullable()->after('notes')->comment('شروط العقد');
            $table->string('owner_signature')->nullable()->after('terms_ar')->comment('توقيع المالك');
            $table->string('showroom_signature')->nullable()->after('owner_signature')->comment('توقيع المعرض');
        });

        Schema::table('test_drives', function (Blueprint $table) {
            $table->string('license_number')->nullable()->after('vehicle_id')->comment('رقم رخصة العميل');
            $table->unsignedInteger('start_mileage')->nullable()->after('end_time');
            $table->unsignedInteger('end_mileage')->nullable()->after('start_mileage');
            $table->text('terms_ar')->nullable();
            $table->text('terms_en')->nullable();
            $table->string('customer_signature')->nullable();
            $table->string('showroom_signature')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index('status');
        });

        if (DB::table('contract_statuses')->count() === 0) {
            $now = now();
            DB::table('contract_statuses')->insert([
                ['code' => 'draft',     'name_ar' => 'مسودة',  'name_en' => 'Draft',     'created_at' => $now, 'updated_at' => $now],
                ['code' => 'active',    'name_ar' => 'مفعّل',  'name_en' => 'Active',    'created_at' => $now, 'updated_at' => $now],
                ['code' => 'completed', 'name_ar' => 'مكتمل',  'name_en' => 'Completed', 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'cancelled', 'name_ar' => 'ملغي',   'name_en' => 'Cancelled', 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'expired',   'name_ar' => 'منتهي',  'name_en' => 'Expired',   'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('test_drives', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['status']);
            $table->dropColumn(['license_number', 'start_mileage', 'end_mileage', 'terms_ar', 'terms_en',
                'customer_signature', 'showroom_signature', 'user_id']);
        });

        Schema::table('dealer_contracts', function (Blueprint $table) {
            $table->dropColumn(['terms_ar', 'owner_signature', 'showroom_signature']);
        });
    }
};
