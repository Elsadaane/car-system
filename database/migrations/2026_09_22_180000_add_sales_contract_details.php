<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تفاصيل عقد البيع: الخصم، طريقة السداد (كاش/تقسيط)، المقدم، الضامن،
 * الشروط عربي/إنجليزي، نقل الملكية والفحص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_contracts', function (Blueprint $table) {
            $table->decimal('discount_amount', 15, 3)->default(0)->after('sale_price')->comment('الخصم');

            $table->enum('payment_type', ['cash', 'installments'])->default('cash')->after('total_amount');
            $table->decimal('down_payment', 15, 3)->default(0)->after('payment_type')->comment('المقدم / المدفوع وقت العقد');
            $table->unsignedBigInteger('down_payment_id')->nullable()->after('down_payment')->comment('دفعة المقدم في جدول payments');
            $table->unsignedSmallInteger('installments_count')->nullable()->after('down_payment_id');
            $table->unsignedTinyInteger('installment_interval')->default(1)->after('installments_count')->comment('كل كام شهر قسط');
            $table->date('first_due_date')->nullable()->after('installment_interval');

            $table->boolean('has_guarantor')->default(false)->after('first_due_date');
            $table->string('guarantor_name')->nullable()->after('has_guarantor');
            $table->string('guarantor_phone')->nullable()->after('guarantor_name');
            $table->string('guarantor_national_id')->nullable()->after('guarantor_phone');
            $table->string('guarantor_address')->nullable()->after('guarantor_national_id');

            $table->text('terms_ar')->nullable()->after('notes');
            $table->text('terms_en')->nullable()->after('terms_ar');

            $table->date('ownership_transferred_at')->nullable()->comment('تاريخ نقل الملكية');
            $table->date('inspection_due_date')->nullable()->comment('ميعاد الفحص الجاي');
            $table->text('ownership_notes')->nullable();

            $table->index('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('sales_contracts', function (Blueprint $table) {
            $table->dropIndex(['payment_type']);
            $table->dropColumn([
                'discount_amount', 'payment_type', 'down_payment', 'down_payment_id', 'installments_count',
                'installment_interval', 'first_due_date', 'has_guarantor', 'guarantor_name', 'guarantor_phone',
                'guarantor_national_id', 'guarantor_address', 'terms_ar', 'terms_en',
                'ownership_transferred_at', 'inspection_due_date', 'ownership_notes',
            ]);
        });
    }
};
