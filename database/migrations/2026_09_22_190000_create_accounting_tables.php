<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الحسابات: الخزائن والبنوك، المصروفات والإيرادات، الأصول، التحويلات بين الخزائن،
 * + إعدادات إضافية للشركة (الهيدر، شروط تجربة القيادة، نص الفوتر).
 *
 * رصيد أي خزنة = الرصيد الافتتاحي + كل اللي دخلها − كل اللي طلع منها
 * (دفعات العملاء/الملاك/الموردين بتتربط بالخزنة عن طريق طريقة الدفع)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->enum('type', ['cash', 'bank', 'wallet', 'other'])->default('cash');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->decimal('opening_balance', 15, 3)->default(0);
            $table->date('opening_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('is_active')
                ->comment('الخزنة اللي فلوس الطريقة دي بتروحلها')
                ->constrained('financial_accounts')->nullOnDelete();
        });

        // تصنيفات المصروفات والإيرادات والأصول (رئيسي + فرعي في نفس الجدول)
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['expense', 'revenue', 'asset']);
            $table->foreignId('parent_id')->nullable()->constrained('finance_categories')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'parent_id']);
        });

        // سندات الصرف والقبض (مصروف / إيراد) في جدول واحد
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('kind', ['expense', 'revenue']);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->comment('مصروف على عربية (صيانة/تجهيز) بيدخل في تكلفتها')->constrained('vehicles')->nullOnDelete();
            $table->string('invoice_no')->nullable();
            $table->decimal('amount', 15, 3);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('total_amount', 15, 3);
            $table->date('entry_date');
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('check_number')->nullable();
            $table->string('details');
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'entry_date']);
            $table->index(['branch_id', 'kind']);
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('from_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 3);
            $table->date('transfer_date');
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('financial_account_id')->nullable()->comment('اتدفع تمنه منين')->constrained('financial_accounts')->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->string('custodian')->nullable()->comment('العهدة مع مين (هتتربط بالموظفين في الـ HR)');
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 3);
            $table->decimal('salvage_value', 15, 3)->default(0)->comment('قيمته في آخر عمره');
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->enum('status', ['in_use', 'maintenance', 'disposed', 'sold'])->default('in_use');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 15, 3)->default(0);
            $table->text('notes')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'branch_id']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('sales_target', 15, 3)->nullable()->comment('تارجت المبيعات الشهري');
        });

        Schema::table('company_info', function (Blueprint $table) {
            $table->string('header_image')->nullable()->after('seal');
            $table->string('whatsapp')->nullable()->after('phone');
            $table->longText('test_drive_terms_ar')->nullable();
            $table->longText('test_drive_terms_en')->nullable();
            $table->text('receipt_notes')->nullable()->comment('ملاحظة بتتطبع تحت الإيصالات');
            $table->string('footer_text')->nullable();
        });

        // خزنة افتراضية + ربط طرق الدفع الموجودة بيها عشان الأرصدة تتحسب من أول يوم
        $now = now();
        $cash = DB::table('financial_accounts')->insertGetId(['name_ar' => 'الخزنة الرئيسية', 'name_en' => 'Main Cash', 'type' => 'cash', 'created_at' => $now, 'updated_at' => $now]);
        $bank = DB::table('financial_accounts')->insertGetId(['name_ar' => 'الحساب البنكي', 'name_en' => 'Bank Account', 'type' => 'bank', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('payment_methods')->where('name_ar', 'كاش')->update(['financial_account_id' => $cash]);
        DB::table('payment_methods')->whereNull('financial_account_id')->update(['financial_account_id' => $bank]);

        $cats = [
            'expense' => ['مصروفات إدارية' => ['إيجار', 'كهرباء ومياه', 'إنترنت واتصالات', 'أدوات مكتبية'],
                          'مصروفات العربيات' => ['صيانة', 'تجهيز وتلميع', 'ترخيص وتأمين', 'نقل وشحن'],
                          'تسويق' => ['إعلانات ممولة', 'تصوير', 'مطبوعات']],
            'revenue' => ['إيرادات أخرى' => ['عمولات', 'خدمات ترخيص', 'إيجار مساحات']],
            'asset'   => ['أثاث ومعدات' => ['أثاث مكتبي', 'أجهزة كمبيوتر', 'كاميرات مراقبة'], 'سيارات الشركة' => []],
        ];
        foreach ($cats as $kind => $mains) {
            foreach ($mains as $main => $subs) {
                $id = DB::table('finance_categories')->insertGetId(['kind' => $kind, 'name_ar' => $main, 'created_at' => $now, 'updated_at' => $now]);
                foreach ($subs as $sub) {
                    DB::table('finance_categories')->insert(['kind' => $kind, 'parent_id' => $id, 'name_ar' => $sub, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropColumn(['header_image', 'whatsapp', 'test_drive_terms_ar', 'test_drive_terms_en', 'receipt_notes', 'footer_text']);
        });
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('sales_target'));
        Schema::dropIfExists('assets');
        Schema::dropIfExists('account_transfers');
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('finance_categories');
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropForeign(['financial_account_id']);
            $table->dropColumn('financial_account_id');
        });
        Schema::dropIfExists('financial_accounts');
    }
};
