<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عقود التفويض:
 *  - شروط بالإنجليزي لكل عقد (للطباعة بالعربي والإنجليزي جنب بعض)
 *  - وقت توقيع كل طرف
 *  - طلبات تغيير السعر: المعرض يطلب ← المالك يوافق أو يرفض من رابط
 * المركبات: صور الرخصة (أمام/خلف) + صور حالة العربية قبل وبعد
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('dealer_contracts', 'terms_en')) {
                $table->longText('terms_en')->nullable()->after('terms_ar')->comment('الشروط بالإنجليزي');
            }
            if (! Schema::hasColumn('dealer_contracts', 'owner_signed_at')) {
                $table->timestamp('owner_signed_at')->nullable();
                $table->timestamp('showroom_signed_at')->nullable();
            }
        });

        if (! Schema::hasTable('dealer_price_requests')) {
            Schema::create('dealer_price_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dealer_contract_id')->constrained('dealer_contracts')->cascadeOnDelete();
                $table->decimal('old_price', 15, 3);
                $table->decimal('new_price', 15, 3);
                $table->string('reason', 500)->nullable();
                $table->string('status', 20)->default('pending')->comment('pending / approved / rejected / cancelled');
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->string('decided_ip', 45)->nullable();
                $table->string('owner_note', 500)->nullable();
                $table->timestamps();

                $table->index(['dealer_contract_id', 'status']);
            });
        }

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'license_front')) {
                $table->string('license_front')->nullable()->comment('صورة الرخصة من الأمام');
                $table->string('license_back')->nullable()->comment('صورة الرخصة من الخلف');
                $table->text('before_images')->nullable()->comment('صور العربية وهي داخلة المعرض');
                $table->text('after_images')->nullable()->comment('صور العربية بحالتها الحالية');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_price_requests');
        Schema::table('dealer_contracts', fn (Blueprint $t) => $t->dropColumn(['terms_en', 'owner_signed_at', 'showroom_signed_at']));
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn(['license_front', 'license_back', 'before_images', 'after_images']));
    }
};
