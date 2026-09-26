<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جدول الفروع
 * ============
 * كل نسخة من النظام بتتسطّب بتعمل "الفرع الرئيسي" تلقائياً.
 * العميل اللي عنده فرع واحد عمره ما هيشوف كلمة "فرع" في الواجهة.
 * العميل اللي بيفتح فرع تاني بيضيفه من الإعدادات وخلاص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code')->unique()->comment('كود الفرع');

            // الاسم
            $table->string('name_ar')->comment('اسم الفرع بالعربي');
            $table->string('name_en')->nullable()->comment('اسم الفرع بالإنجليزي');

            // بيانات التواصل
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();

            // الموقع
            $table->text('address')->nullable()->comment('العنوان التفصيلي');
            $table->string('city')->nullable()->comment('المدينة');
            $table->string('region')->nullable()->comment('المنطقة / المحافظة');
            $table->string('location_url')->nullable()->comment('رابط الموقع على الخريطة');

            // الإدارة
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('مدير الفرع');

            // البيانات الرسمية (بعض الفروع بيكون ليها سجل مستقل)
            $table->string('commercial_register')->nullable()->comment('السجل التجاري');
            $table->string('tax_number')->nullable()->comment('الرقم الضريبي');

            // الحالة
            $table->boolean('is_main')->default(false)->comment('الفرع الرئيسي');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('opening_date')->nullable()->comment('تاريخ الافتتاح');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_main');
        });

        // ---------------------------------------------------------
        // الفرع الافتراضي — بيتعمل مع كل نسخة جديدة من النظام
        // العميل بيغيّر اسمه وبياناته من الإعدادات
        // ---------------------------------------------------------
        DB::table('branches')->insert([
            'branch_code' => 'BR-001',
            'name_ar'     => 'الفرع الرئيسي',
            'name_en'     => 'Main Branch',
            'is_main'     => true,
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
