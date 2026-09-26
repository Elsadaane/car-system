<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) الشيكات المؤجلة على الأقساط   2) مندوب البيعة
 * 3) الشركاء في العربيات            4) المصاريف المشتركة
 * 5) تقارير الفحص                   6) عمولة % وحد أدنى للسعر في التفويض
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── 1) الشيكات ───
        if (! Schema::hasTable('cheques')) {
            Schema::create('cheques', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_contract_id')->constrained('sales_contracts')->cascadeOnDelete();
                $table->foreignId('installment_id')->nullable()->constrained('installments')->nullOnDelete();
                $table->string('cheque_number', 50);
                $table->string('bank_name', 100)->nullable();
                $table->decimal('amount', 15, 3);
                $table->date('due_date')->comment('تاريخ الشيك (ميعاد الإيداع)');
                $table->string('status', 20)->default('received')->comment('received / deposited / cleared / bounced / returned / cancelled');
                $table->foreignId('payment_id')->nullable()->comment('الدفعة اللي اتعملت لما الشيك اتصرف');
                $table->date('deposited_at')->nullable();
                $table->date('cleared_at')->nullable();
                $table->date('bounced_at')->nullable();
                $table->string('bounce_reason', 255)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'due_date']);
            });
        }

        // ─── 2) مندوب البيعة (غير الموظف اللي دخّل العقد) ───
        if (! Schema::hasColumn('sales_contracts', 'sales_rep_id')) {
            Schema::table('sales_contracts', function (Blueprint $table) {
                $table->foreignId('sales_rep_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            });
        }

        // ─── 3) الشركاء ───
        if (! Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone', 30)->nullable();
                $table->string('national_id', 50)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
            Schema::create('vehicle_partners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->decimal('capital', 15, 3)->default(0)->comment('رأس المال اللي دخل بيه في العربية');
                $table->decimal('profit_percent', 5, 2)->comment('نسبته من ربح العربية');
                $table->string('notes', 255)->nullable();
                $table->timestamps();
                $table->unique(['vehicle_id', 'partner_id']);
            });
            Schema::create('partner_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
                $table->string('kind', 20)->comment('capital_in (استلمنا منه) / payout (دفعناله)');
                $table->decimal('amount', 15, 3);
                $table->date('entry_date');
                $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
                $table->string('reference', 100)->nullable();
                $table->string('notes', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ─── 4) المصاريف المشتركة: مصروف واحد بيتقسم على كذا عربية ───
        if (! Schema::hasTable('shared_expenses')) {
            Schema::create('shared_expenses', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->date('entry_date');
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('financial_account_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->decimal('amount', 15, 3);
                $table->decimal('tax_percent', 5, 2)->default(0);
                $table->decimal('tax_amount', 15, 3)->default(0);
                $table->decimal('total_amount', 15, 3);
                $table->string('method', 20)->default('equal')->comment('equal (بالتساوي) / cost (حسب تكلفة العربية)');
                $table->string('details', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasColumn('cash_entries', 'shared_expense_id')) {
            Schema::table('cash_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('shared_expense_id')->nullable()->index();
            });
        }

        // ─── 5) تقارير الفحص ───
        if (! Schema::hasTable('vehicle_inspections')) {
            Schema::create('vehicle_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->string('type', 20)->default('receiving');
                $table->date('inspected_at');
                $table->string('inspector', 150)->nullable();
                $table->unsignedInteger('mileage')->nullable();
                $table->json('items')->nullable()->comment('[{part, status: ok|attention|damaged, note}]');
                $table->string('result', 20)->default('ok');
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // ─── صلاحيات الشركاء (للأدوار: محاسب ومدير فرع يشوفوا ويعدّلوا) ───
        if (class_exists(\Spatie\Permission\Models\Permission::class) && Schema::hasTable('permissions')) {
            \App\Support\Access::sync();
            foreach (['محاسب' => ['partners.view', 'partners.create', 'partners.edit'], 'مدير فرع' => ['partners.view']] as $role => $perms) {
                \Spatie\Permission\Models\Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($perms);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        // ─── 6) التفويض: عمولة نسبة + حد أدنى للسعر ───
        if (! Schema::hasColumn('dealer_contracts', 'commission_type')) {
            Schema::table('dealer_contracts', function (Blueprint $table) {
                $table->string('commission_type', 10)->default('fixed')->comment('fixed / percent');
                $table->decimal('commission_rate', 6, 3)->nullable();
                $table->decimal('min_price', 15, 3)->nullable()->comment('أقل سعر بيع يوافق عليه المالك');
            });
        }
    }

    public function down(): void
    {
        Schema::table('dealer_contracts', fn (Blueprint $t) => $t->dropColumn(['commission_type', 'commission_rate', 'min_price']));
        Schema::dropIfExists('vehicle_inspections');
        Schema::table('cash_entries', fn (Blueprint $t) => $t->dropColumn('shared_expense_id'));
        Schema::dropIfExists('shared_expenses');
        Schema::dropIfExists('partner_transactions');
        Schema::dropIfExists('vehicle_partners');
        Schema::dropIfExists('partners');
        Schema::table('sales_contracts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sales_rep_id');
        });
        Schema::dropIfExists('cheques');
    }
};
