<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المدونة (للـ SEO): تصنيفات + مقالات بلغتين + ربط بالعربيات + أسئلة شائعة لكل مقال.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('excerpt_ar')->nullable();
            $table->text('excerpt_en')->nullable();
            $table->longText('body_ar')->nullable();
            $table->longText('body_en')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('cover_alt')->nullable();

            // SEO
            $table->string('focus_keyword')->nullable();
            $table->string('meta_title_ar')->nullable();
            $table->string('meta_title_en')->nullable();
            $table->string('meta_description_ar', 300)->nullable();
            $table->string('meta_description_en', 300)->nullable();
            $table->json('faq')->nullable()->comment('[{q_ar, a_ar, q_en, a_en}]');

            // ربط بالعربيات: يدوي + أوتوماتيك بالماركة/الموديل
            $table->foreignId('brand_id')->nullable()->constrained('car_brands')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->json('vehicle_ids')->nullable();

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable()->comment('لو في المستقبل = مجدول');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        $now = now();
        foreach ([['نصائح الشراء', 'Buying tips', 'buying-tips'], ['التقسيط والتمويل', 'Finance', 'finance'], ['مراجعات السيارات', 'Car reviews', 'reviews'], ['الصيانة', 'Maintenance', 'maintenance']] as $i => [$ar, $en, $slug]) {
            DB::table('blog_categories')->insert(['name_ar' => $ar, 'name_en' => $en, 'slug' => $slug, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
