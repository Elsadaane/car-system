<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * محتوى المتجر من لوحة الإدارة:
 *  - site_blocks: البانرات، آراء العملاء، الأسئلة الشائعة، مميزات "ليه إحنا"
 *  - عروض العربيات بعداد تنازلي (offer_label / offer_ends_at)
 *  - أنواع طلبات جديدة: تقييم/بدل عربيتك + تواصل عام
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_blocks', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['banner', 'testimonial', 'faq', 'feature']);
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->string('button_text')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('offer_label')->nullable()->after('discount_price');
            $table->date('offer_ends_at')->nullable()->after('offer_label');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE vehicle_inquiries MODIFY type ENUM('inquiry','test_drive','reservation','finance','trade_in','contact') NOT NULL DEFAULT 'inquiry'");
        }

        $now = now();
        $blocks = [
            ['feature', 'عربيات مفحوصة', 'كل عربية بتتفحص قبل ما تتعرض، ومعاها تقرير حالتها.', '🛡️'],
            ['feature', 'تقسيط من غير بنوك', 'قسّط مع المعرض مباشرة بمقدم بسيط وأقساط تناسبك.', '💳'],
            ['feature', 'أسعار واضحة', 'السعر اللي تشوفه هو اللي تدفعه — من غير رسوم مخفية.', '🏷️'],
            ['feature', 'بدّل عربيتك', 'قيّم عربيتك القديمة وخدها جزء من تمن الجديدة.', '🔁'],
            ['faq', 'هل ينفع أقسّط من غير تحويل راتب؟', 'أيوه، التقسيط مع المعرض مباشرة بعقد ومقدم، ومن غير ما تحتاج بنك.', null],
            ['faq', 'أقدر أجرّب العربية قبل الشراء؟', 'أكيد، احجز تجربة قيادة من صفحة العربية أو كلّمنا واتساب.', null],
            ['faq', 'إيه الأوراق المطلوبة للتقسيط؟', 'البطاقة المدنية، رخصة القيادة، وإثبات دخل أو ضامن حسب الحالة.', null],
            ['faq', 'بتاخدوا عربيتي القديمة؟', 'أيوه، ابعت بياناتها من صفحة "قيّم عربيتك" وهنرد عليك بعرض سعر.', null],
        ];
        foreach ($blocks as $i => [$type, $title, $body, $icon]) {
            DB::table('site_blocks')->insert(['type' => $type, 'title' => $title, 'body' => $body, 'icon' => $icon, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_blocks');
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn(['offer_label', 'offer_ends_at']));
    }
};
