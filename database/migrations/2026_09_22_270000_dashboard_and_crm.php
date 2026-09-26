<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - users.dashboard_layout: ترتيب وأحجام ويدجتس الرئيسية لكل موظف
 * - ad_spends: صرف الإعلانات لكل حملة في الشهر (عشان نحسب CPL و ROAS الحقيقي)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'dashboard_layout')) {
            Schema::table('users', fn (Blueprint $t) => $t->json('dashboard_layout')->nullable());
        }

        Schema::create('ad_spends', function (Blueprint $table) {
            $table->id();
            $table->string('source', 60)->comment('facebook / google / tiktok / snapchat ...');
            $table->string('campaign', 190)->nullable()->comment('نفس utm_campaign في روابط الإعلان');
            $table->char('month', 7)->comment('YYYY-MM');
            $table->decimal('amount', 15, 3);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['month', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_spends');
        if (Schema::hasColumn('users', 'dashboard_layout')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('dashboard_layout'));
        }
    }
};
