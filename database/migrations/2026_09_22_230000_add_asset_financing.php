<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شراء الأصول بالتقسيط: من المورد مباشرة أو من بنك/شركة تمويل (بفايدة).
 * المقدم بيتخصم من الخزنة يوم الشراء، وكل قسط بيتدفع يدوي من الخزنة اللي تختارها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->enum('payment_type', ['cash', 'installments'])->default('cash')->after('financial_account_id');
            $table->enum('financier_type', ['supplier', 'bank'])->nullable()->after('payment_type');
            $table->string('lender_name')->nullable()->after('financier_type')->comment('اسم البنك / شركة التمويل');
            $table->decimal('down_payment', 15, 3)->default(0)->after('lender_name');
            $table->decimal('interest_rate', 6, 3)->default(0)->after('down_payment')->comment('نسبة سنوية ثابتة %');
            $table->unsignedSmallInteger('installments_count')->nullable()->after('interest_rate');
            $table->date('first_due_date')->nullable()->after('installments_count');
        });

        Schema::create('asset_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->date('due_date');
            $table->decimal('amount', 15, 3);
            $table->decimal('principal', 15, 3);
            $table->decimal('interest', 15, 3)->default(0);
            $table->date('paid_date')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['paid_date', 'due_date']);
            $table->unique(['asset_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_installments');
        Schema::table('assets', fn (Blueprint $t) => $t->dropColumn(['payment_type', 'financier_type', 'lender_name', 'down_payment', 'interest_rate', 'installments_count', 'first_due_date']));
    }
};
