<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حسابات الموردين
 * =================
 *   supplier_invoices      فواتير الشراء من المورد
 *   supplier_installments  جدول الأقساط اللي المعرض هيدفعها للمورد
 *   supplier_returns       العربيات اللي اترجعت للمورد
 *
 * الدفعات نفسها بتتسجل في جدول payments الموجود (payable = القسط).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('supplier_reference')->nullable()->comment('رقم الفاتورة عند المورد');
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->date('invoice_date');
            $table->decimal('subtotal', 15, 3);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('total_amount', 15, 3);
            $table->decimal('paid_amount', 15, 3)->default(0);

            $table->enum('status', ['unpaid', 'partial', 'paid', 'cancelled'])->default('unpaid');
            $table->string('attachment')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();

            $table->unsignedSmallInteger('sequence');
            $table->decimal('amount', 15, 3);
            $table->date('due_date');
            $table->decimal('paid_amount', 15, 3)->default(0);
            $table->date('paid_at')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid'])->default('pending');

            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('due_date');
        });

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();

            $table->date('return_date');
            $table->enum('reason', ['defect', 'not_as_described', 'damaged', 'documents', 'other'])->default('other');
            $table->text('reason_details')->nullable();
            $table->decimal('refund_amount', 15, 3)->default(0)->comment('المبلغ اللي بيتخصم من حساب المورد');

            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('supplier_invoice_id')->nullable()->after('supplier_id')
                  ->constrained('supplier_invoices')->nullOnDelete();
        });

        DB::statement("ALTER TABLE vehicles MODIFY status ENUM('available','reserved','sold','damaged','maintenance','returned') NOT NULL DEFAULT 'available'");

        // طرق دفع افتراضية لحد ما صفحة طرق الدفع تتعمل
        if (DB::table('payment_methods')->count() === 0) {
            $now = now();
            DB::table('payment_methods')->insert([
                ['name_ar' => 'كاش',          'name_en' => 'Cash',          'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name_ar' => 'تحويل بنكي',   'name_en' => 'Bank Transfer', 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['name_ar' => 'شيك',          'name_en' => 'Cheque',        'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('vehicles')->where('status', 'returned')->update(['status' => 'damaged']);
        DB::statement("ALTER TABLE vehicles MODIFY status ENUM('available','reserved','sold','damaged','maintenance') NOT NULL DEFAULT 'available'");

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['supplier_invoice_id']);
            $table->dropColumn('supplier_invoice_id');
        });

        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('supplier_installments');
        Schema::dropIfExists('supplier_invoices');
    }
};
