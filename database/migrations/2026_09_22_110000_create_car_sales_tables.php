<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نظام بيع السيارات - الجداول الأساسية
 */

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================
        // 1) الجداول المرجعية
        // =========================================================

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->unique();
            $table->string('name_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        // =========================================================
        // 2) الموردين والماركات والموديلات
        // =========================================================

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique()->comment('كود المورد (مثال: SUP-001)');

            $table->string('name_ar');
            $table->string('name_en')->nullable();

            $table->string('national_id')->nullable()->unique();
            $table->date('national_id_expiry')->nullable()->comment('تاريخ انتهاء البطاقة');
            $table->string('tax_number')->nullable()->unique();
            $table->string('commercial_register')->nullable()->unique();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            $table->integer('settlement_days')->default(30)->comment('مدة التسوية بالأيام');
            $table->text('settlement_terms')->nullable()->comment('شروط التسوية');
            $table->date('last_settlement_date')->nullable()->comment('آخر تاريخ تسوية');

            $table->string('bank_name')->nullable();
            $table->string('iban_number')->nullable();
            $table->string('account_holder_name')->nullable();

            $table->enum('status', ['active', 'inactive', 'suspended', 'blacklisted'])->default('active');
            $table->text('status_reason')->nullable()->comment('سبب التعليق أو الحظر');
            $table->decimal('credit_limit', 15, 3)->nullable()->comment('حد الائتمان');
            $table->decimal('current_balance', 15, 3)->default(0)->comment('الرصيد الحالي');

            $table->integer('total_vehicles')->default(0);
            $table->integer('sold_vehicles')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();

            $table->text('notes')->nullable();
            $table->string('contract_attachment')->nullable();
            $table->string('logo')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->comment('الفرع')->constrained('branches')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('car_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->unique();
            $table->string('name_en')->nullable();
            $table->string('logo')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('total_models')->default(0);
            $table->timestamps();
        });

        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('car_brands')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('model_code')->nullable()->unique();
            $table->integer('year')->nullable();
            $table->string('image')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('body_type')->nullable();
            $table->string('fuel_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['brand_id', 'is_active']);
        });

        Schema::create('import_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->unique();
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =========================================================
        // 3) المركبات
        // =========================================================

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_code')->unique()->comment('كود المركبة الفريد');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('branch_id')->default(1)->comment('الفرع الموجودة فيه العربية')->constrained('branches')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('car_brands')->restrictOnDelete();
            $table->foreignId('model_id')->constrained('car_models')->restrictOnDelete();
            $table->foreignId('import_source_id')->nullable()->constrained('import_sources')->nullOnDelete();

            $table->string('plate_number')->nullable()->unique()->comment('فاضي للعربيات الجديدة لحد الترخيص');
            $table->string('color');
            $table->integer('manufacturing_year');
            $table->string('chassis_number')->nullable()->unique();
            $table->string('engine_number')->nullable()->unique();
            $table->integer('mileage')->default(0);

            $table->decimal('purchase_price', 15, 3)->comment('سعر الشراء من المورد');
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('total_cost', 15, 3)->comment('الإجمالي = السعر + الضريبة');

            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->json('images')->nullable();

            $table->enum('status', ['available', 'reserved', 'sold', 'damaged', 'maintenance'])->default('available');
            $table->text('notes')->nullable();
            $table->string('condition_report')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index(['brand_id', 'model_id']);
            $table->index('status');
        });

        // =========================================================
        // 4) العملاء والعقود
        // =========================================================

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();

            $table->string('national_id')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('job_title')->nullable();
            $table->string('nationality')->nullable();

            $table->decimal('credit_limit', 15, 3)->nullable();
            $table->decimal('total_purchases', 15, 3)->default(0);

            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->text('block_reason')->nullable();

            $table->decimal('rating', 3, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('test_drives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('distance_km')->nullable();
            $table->text('feedback_ar')->nullable();
            $table->text('feedback_en')->nullable();
            $table->boolean('is_interested')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable()->comment('الموظف - اربطه بجدول hr_employees لما تعمله');
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->index(['customer_id', 'vehicle_id']);
        });

        Schema::create('dealer_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();

            $table->decimal('agreed_price', 15, 3);
            $table->decimal('commission_amount', 15, 3)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('total_amount', 15, 3);

            $table->decimal('paid_amount', 15, 3)->default(0);
            $table->decimal('remaining_amount', 15, 3);
            $table->date('contract_date');
            $table->date('expiry_date')->nullable();

            $table->foreignId('status_id')->nullable()->constrained('contract_statuses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status_id']);
        });

        Schema::create('sales_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->default(1)->comment('الفرع اللي اتعملت فيه البيعة')->constrained('branches')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();

            $table->decimal('sale_price', 15, 3);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('total_amount', 15, 3);

            $table->decimal('paid_amount', 15, 3)->default(0);
            $table->decimal('remaining_amount', 15, 3);

            $table->date('contract_date');
            $table->date('delivery_date')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('contract_statuses')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->string('customer_signature')->nullable();
            $table->string('seller_signature')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status_id']);
            $table->index('remaining_amount');
        });

        Schema::create('delivery_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('sales_contract_id')->constrained('sales_contracts')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();

            $table->dateTime('scheduled_delivery');
            $table->dateTime('actual_delivery')->nullable();
            $table->string('delivery_location')->nullable();
            $table->text('delivery_notes')->nullable();

            $table->string('delivered_by')->nullable();
            $table->string('received_by')->nullable();
            $table->string('signature_path')->nullable();

            $table->enum('status', ['pending', 'delivered', 'cancelled'])->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });

        // =========================================================
        // 5) المدفوعات والأقساط والإشعارات
        // =========================================================

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference')->unique();

            $table->morphs('payable');

            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->decimal('amount', 15, 3);
            $table->date('payment_date');

            $table->string('check_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('bank_reference')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('completed');
            $table->unsignedBigInteger('financial_account_id')->nullable()->comment('الحساب المحاسبي - اربطه بجدول financial_accounts لما تعمله');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('payment_date');
            $table->index('status');
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->string('installment_number')->unique();

            $table->foreignId('sales_contract_id')->constrained('sales_contracts')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            $table->integer('installment_sequence')->comment('رقم القسط (1، 2، 3)');
            $table->integer('total_installments');
            $table->decimal('amount', 15, 3);
            $table->date('due_date');

            $table->boolean('is_paid')->default(false);
            $table->decimal('paid_amount', 15, 3)->default(0)->comment('مجموع الدفعات من installment_payments');
            $table->date('actual_payment_date')->nullable();

            $table->enum('status', ['pending', 'paid', 'overdue', 'partially_paid', 'cancelled'])->default('pending');
            $table->integer('days_overdue')->default(0);
            $table->decimal('late_fees', 15, 3)->default(0);
            $table->decimal('late_fee_percent', 5, 2)->nullable();

            $table->boolean('first_reminder_sent')->default(false);
            $table->datetime('first_reminder_date')->nullable();
            $table->boolean('second_reminder_sent')->default(false);
            $table->datetime('second_reminder_date')->nullable();
            $table->boolean('final_notice_sent')->default(false);
            $table->datetime('final_notice_date')->nullable();

            $table->text('notes')->nullable();
            $table->text('customer_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sales_contract_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('due_date');
            $table->index('is_paid');
            $table->index('status');
        });

        // القسط الواحد ممكن يتدفع على كذا دفعة، والدفعة الواحدة ممكن تغطي كذا قسط
        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained('installments')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->decimal('amount', 15, 3)->comment('الجزء من الدفعة اللي راح للقسط ده');
            $table->timestamps();

            $table->unique(['installment_id', 'payment_id']);
        });

        // اسم مختلف عن جدول notifications بتاع Laravel
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            $table->string('notification_type');
            $table->morphs('notifiable');

            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->text('message_ar');
            $table->text('message_en')->nullable();
            $table->string('action_url')->nullable();

            $table->morphs('recipient');
            $table->boolean('is_read')->default(false);
            $table->datetime('read_at')->nullable();

            $table->json('channels')->nullable();
            $table->json('channel_status')->nullable();
            $table->datetime('sent_at')->nullable();

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->integer('retry_count')->default(0);
            $table->datetime('next_retry_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_read');
            $table->index('notification_type');
            $table->index('created_at');
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->string('channel');
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->text('error_message')->nullable();
            $table->string('external_id')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'channel']);
        });

        // =========================================================
        // 6) التمويل والإلغاء
        // =========================================================

        Schema::create('finance_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->unique();
            $table->string('name_en')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->text('terms_ar')->nullable();
            $table->text('terms_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('sales_contract_id')->constrained('sales_contracts')->cascadeOnDelete();
            $table->foreignId('finance_company_id')->constrained('finance_companies')->restrictOnDelete();

            $table->decimal('requested_amount', 15, 3);
            $table->decimal('approved_amount', 15, 3)->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->integer('installment_period')->nullable();
            $table->decimal('monthly_payment', 15, 3)->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'paid_off'])->default('pending');
            $table->date('request_date');
            $table->date('approval_date')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('sales_contract_id');
        });

        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('cancellation_number')->unique();
            $table->foreignId('sales_contract_id')->constrained('sales_contracts')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            $table->date('cancellation_date');
            $table->string('reason_ar');
            $table->string('reason_en')->nullable();
            $table->text('additional_notes')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->decimal('refund_amount', 15, 3)->default(0);
            $table->decimal('penalty_amount', 15, 3)->default(0);
            $table->decimal('net_refund', 15, 3)->default(0);
            $table->date('refund_date')->nullable();

            $table->text('approval_notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // =========================================================
        // 7) الإعدادات والسجلات
        // =========================================================

        Schema::create('sales_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('setting_type')->default('text');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('company_info', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('commercial_register_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('address_ar')->nullable();
            $table->text('address_en')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('seal')->nullable();

            $table->longText('terms_and_conditions_ar')->nullable();
            $table->longText('terms_and_conditions_en')->nullable();
            $table->longText('privacy_policy_ar')->nullable();
            $table->longText('privacy_policy_en')->nullable();
            $table->longText('dealer_contract_terms_ar')->nullable();
            $table->longText('dealer_contract_terms_en')->nullable();
            $table->longText('sales_contract_terms_ar')->nullable();
            $table->longText('sales_contract_terms_en')->nullable();
            $table->longText('delivery_contract_terms_ar')->nullable();
            $table->longText('delivery_contract_terms_en')->nullable();

            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->morphs('subject');
            $table->nullableMorphs('causer');

            $table->json('properties')->nullable();
            $table->string('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
        });

        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('report_type');
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('custom_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('alert_type');
            $table->string('trigger_condition');
            $table->json('configuration')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('notification_day')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_alerts');
        Schema::dropIfExists('report_templates');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('company_info');
        Schema::dropIfExists('sales_settings');

        Schema::dropIfExists('cancellation_requests');
        Schema::dropIfExists('finance_requests');
        Schema::dropIfExists('finance_companies');

        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('payments');

        Schema::dropIfExists('delivery_contracts');
        Schema::dropIfExists('sales_contracts');
        Schema::dropIfExists('dealer_contracts');
        Schema::dropIfExists('test_drives');
        Schema::dropIfExists('customers');

        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('import_sources');
        Schema::dropIfExists('car_models');
        Schema::dropIfExists('car_brands');
        Schema::dropIfExists('suppliers');

        Schema::dropIfExists('contract_statuses');
        Schema::dropIfExists('payment_statuses');
        Schema::dropIfExists('payment_methods');
    }
};
