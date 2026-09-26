<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedTinyInteger('cylinders')->nullable()->after('engine_number')->comment('عدد السلندرات');
            $table->date('license_expiry_date')->nullable()->after('mileage')->comment('تاريخ انتهاء الرخصة');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['cylinders', 'license_expiry_date']);
        });
    }
};
