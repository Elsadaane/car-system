<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الموقع بلغتين: نسخة إنجليزي لمحتوى البانرات والأسئلة والمميزات وآراء العملاء
 * (المقالات والعربيات والماركات عندهم حقول إنجليزي من الأول)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_blocks', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->text('body_en')->nullable()->after('body');
            $table->string('button_text_en')->nullable()->after('button_text');
        });

        // ترجمة المحتوى الافتراضي
        $defaults = [
            'عربيات مفحوصة' => ['Inspected cars', 'Every car is inspected before listing and comes with a condition report.'],
            'تقسيط من غير بنوك' => ['Installments without banks', 'Pay in installments directly with the showroom — small down payment, comfortable monthly plans.'],
            'أسعار واضحة' => ['Clear prices', 'The price you see is the price you pay — no hidden fees.'],
            'بدّل عربيتك' => ['Trade in your car', 'Get your old car valued and use it toward your new one.'],
            'هل ينفع أقسّط من غير تحويل راتب؟' => ['Can I pay in installments without a salary transfer?', 'Yes. Installments are directly with the showroom through a contract and a down payment — no bank needed.'],
            'أقدر أجرّب العربية قبل الشراء؟' => ['Can I test drive the car before buying?', 'Of course — book a test drive from the car page or message us on WhatsApp.'],
            'إيه الأوراق المطلوبة للتقسيط؟' => ['What documents do I need for installments?', 'Civil ID, driving licence, and proof of income or a guarantor depending on the case.'],
            'بتاخدوا عربيتي القديمة؟' => ['Do you take my old car?', 'Yes — send its details from the "Value your car" page and we will reply with an offer.'],
        ];
        foreach ($defaults as $ar => [$title, $body]) {
            DB::table('site_blocks')->where('title', $ar)->whereNull('title_en')->update(['title_en' => $title, 'body_en' => $body]);
        }
    }

    public function down(): void
    {
        Schema::table('site_blocks', fn (Blueprint $t) => $t->dropColumn(['title_en', 'body_en', 'button_text_en']));
    }
};
