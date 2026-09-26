<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الموارد البشرية - مبنية على الـ migration بتاعك مع تعديلات تناسب المعرض:
 *
 *  - نوع التوظيف: على كفالة المعرض / بعقد من غير كفالة / من غير عقد (يومية - حر)
 *  - المرتب: شهري أو يومية، بيتقبض كاش أو تحويل بنكي
 *  - الأوفر تايم والعمولة بيتفعلوا لكل موظف لوحده
 *  - الصرف بيتم من الخزائن (financial_accounts) زي المصروفات
 *    (شلنا hr_payroll_gl_mappings لأن مفيش شجرة حسابات)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── الجداول المرجعية ───
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(15)->comment('سماح التأخير');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->enum('type', ['allowance', 'deduction'])->default('allowance');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->unsignedSmallInteger('days_per_year')->default(0)->comment('0 = من غير رصيد');
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        // ─── الموظف ───
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('hr_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('hr_shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->comment('حسابه على السيستم - بيتحسب منه العمولة')->constrained('users')->nullOnDelete();

            $table->string('full_name_ar');
            $table->string('full_name_en')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('national_id')->nullable();
            $table->string('nationality')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('job_title');
            $table->date('join_date');

            // التوظيف والعقد
            $table->enum('employment_type', ['sponsored', 'contract', 'freelance'])->default('contract');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();

            // المرتب
            $table->enum('pay_basis', ['monthly', 'daily'])->default('monthly');
            $table->decimal('basic_salary', 10, 3)->default(0)->comment('المرتب الشهري أو اليومية');
            $table->unsignedTinyInteger('working_days_per_month')->default(30);
            $table->decimal('working_hours_per_day', 4, 2)->default(8);
            $table->enum('payment_method', ['cash', 'bank'])->default('cash');
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();

            // أوفر تايم وعمولة وتأمينات
            $table->boolean('overtime_eligible')->default(false);
            $table->decimal('overtime_rate', 4, 2)->default(1.25);
            $table->enum('commission_type', ['none', 'sale_percent', 'profit_percent', 'per_car'])->default('none');
            $table->decimal('commission_value', 10, 3)->default(0);
            $table->boolean('is_insured')->default(false);
            $table->string('insurance_number')->nullable();
            $table->decimal('insurance_percent', 5, 2)->default(0)->comment('نسبة خصم التأمينات من المرتب');
            $table->unsignedSmallInteger('annual_leave_days')->default(30);

            $table->enum('status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active');
            $table->date('termination_date')->nullable();
            $table->string('profile_image')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'branch_id']);
        });

        Schema::create('hr_employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('hr_allowance_types')->cascadeOnDelete();
            $table->decimal('amount', 10, 3);
            $table->timestamps();
        });

        Schema::create('hr_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamps();

            $table->index('expiry_date');
        });

        // ─── الحضور والإجازات ───
        Schema::create('hr_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'late', 'absent', 'leave', 'holiday', 'off'])->default('present');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedSmallInteger('delay_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });

        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 2);
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        // ─── السلف والحوافز والخصومات ───
        Schema::create('hr_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->decimal('amount', 10, 3);
            $table->decimal('monthly_installment', 10, 3);
            $table->date('loan_date');
            $table->date('start_month')->comment('أول شهر هيتخصم فيه');
            $table->decimal('paid_amount', 10, 3)->default(0);
            $table->foreignId('financial_account_id')->nullable()->comment('اتصرفت منين')->constrained('financial_accounts')->nullOnDelete();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('hr_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('hr_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->enum('type', ['bonus', 'deduction']);
            $table->string('reason');
            $table->decimal('days', 5, 2)->nullable()->comment('لو الخصم بالأيام');
            $table->decimal('amount', 10, 3);
            $table->date('date');
            $table->foreignId('payroll_run_id')->nullable()->constrained('hr_payroll_runs')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });

        Schema::create('hr_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('pay_basis', ['monthly', 'daily']);
            $table->decimal('days_worked', 5, 2)->default(0);
            $table->decimal('absent_days', 5, 2)->default(0);
            $table->decimal('basic', 10, 3)->default(0);
            $table->decimal('allowances', 10, 3)->default(0);
            $table->decimal('overtime_hours', 7, 2)->default(0);
            $table->decimal('overtime_amount', 10, 3)->default(0);
            $table->decimal('commission', 10, 3)->default(0);
            $table->decimal('bonuses', 10, 3)->default(0);
            $table->decimal('other_additions', 10, 3)->default(0)->comment('تعديل يدوي');
            $table->decimal('absence_deduction', 10, 3)->default(0);
            $table->decimal('fixed_deductions', 10, 3)->default(0);
            $table->decimal('adjustment_deductions', 10, 3)->default(0);
            $table->decimal('loan_deduction', 10, 3)->default(0);
            $table->decimal('insurance_deduction', 10, 3)->default(0);
            $table->decimal('other_deductions', 10, 3)->default(0)->comment('تعديل يدوي');
            $table->decimal('gross', 10, 3)->default(0);
            $table->decimal('total_deductions', 10, 3)->default(0);
            $table->decimal('net', 10, 3)->default(0);
            $table->enum('payment_method', ['cash', 'bank'])->default('cash');
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->date('paid_date')->nullable();
            $table->json('breakdown')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('hr_end_of_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('termination_date');
            $table->string('reason');
            $table->decimal('service_years', 5, 2)->default(0);
            $table->decimal('daily_rate', 10, 3)->default(0);
            $table->decimal('gratuity_days_per_year', 5, 2)->default(0);
            $table->decimal('gratuity_amount', 10, 3)->default(0);
            $table->decimal('leave_days', 5, 2)->default(0);
            $table->decimal('leave_encashment', 10, 3)->default(0);
            $table->decimal('other_additions', 10, 3)->default(0);
            $table->decimal('loan_deduction', 10, 3)->default(0);
            $table->decimal('other_deductions', 10, 3)->default(0);
            $table->decimal('net_payout', 10, 3)->default(0);
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ─── بيانات افتراضية ───
        $now = now();
        $ins = fn (string $t, array $rows) => DB::table($t)->insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $rows));
        $ins('hr_departments', [['name_ar' => 'المبيعات'], ['name_ar' => 'الإدارة والحسابات'], ['name_ar' => 'الصيانة والتجهيز'], ['name_ar' => 'التسويق']]);
        $ins('hr_shifts', [['name_ar' => 'الصباحي', 'start_time' => '09:00', 'end_time' => '17:00'], ['name_ar' => 'المسائي', 'start_time' => '16:00', 'end_time' => '23:00']]);
        $ins('hr_allowance_types', [
            ['name_ar' => 'بدل سكن', 'type' => 'allowance'], ['name_ar' => 'بدل مواصلات', 'type' => 'allowance'],
            ['name_ar' => 'بدل هاتف', 'type' => 'allowance'], ['name_ar' => 'خصم سكن الشركة', 'type' => 'deduction'],
        ]);
        $ins('hr_leave_types', [
            ['name_ar' => 'سنوية', 'days_per_year' => 30, 'is_paid' => true], ['name_ar' => 'مرضية', 'days_per_year' => 15, 'is_paid' => true],
            ['name_ar' => 'طارئة', 'days_per_year' => 6, 'is_paid' => true], ['name_ar' => 'بدون مرتب', 'days_per_year' => 0, 'is_paid' => false],
        ]);
    }

    public function down(): void
    {
        foreach (['hr_end_of_services', 'hr_payrolls', 'hr_adjustments', 'hr_payroll_runs', 'hr_loans', 'hr_leave_requests', 'hr_attendances',
                  'hr_documents', 'hr_employee_allowances', 'hr_employees', 'hr_holidays', 'hr_leave_types', 'hr_allowance_types', 'hr_shifts', 'hr_departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
