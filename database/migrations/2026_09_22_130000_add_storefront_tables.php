<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 3)->nullable()->after('total_cost');
            $table->decimal('discount_price', 15, 3)->nullable()->after('selling_price');
            $table->boolean('price_on_request')->default(false)->after('discount_price');

            $table->boolean('is_published')->default(false)->after('status');
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->unsignedInteger('views_count')->default(0)->after('is_featured');

            $table->string('slug')->nullable()->unique()->after('vehicle_code');

            $table->json('specifications')->nullable()->after('description_en');

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->index(['is_published', 'status']);
            $table->index('is_featured');
        });

        Schema::create('vehicle_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('inquiry_number')->unique();

            // nullable: استفسار عام من غير عربية محددة / العربية اتمسحت والـ lead يفضل
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('branch_id')->default(1)->constrained('branches')->restrictOnDelete();

            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('message')->nullable();

            $table->enum('type', ['inquiry', 'test_drive', 'reservation', 'finance'])->default('inquiry');

            $table->date('preferred_date')->nullable();

            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'closed', 'spam'])
                  ->default('new');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->text('staff_notes')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->string('source')->default('website');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('fbclid', 500)->nullable()->comment('Meta Offline Conversions / CAPI');
            $table->string('gclid', 500)->nullable()->comment('Google Offline Conversions');
            $table->string('ip_address')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['vehicle_id', 'status']);
            $table->index('source');
        });

        Schema::create('storefront_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('text');
            $table->string('label_ar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
        Schema::dropIfExists('vehicle_inquiries');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'status']);
            $table->dropIndex(['is_featured']);
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'selling_price', 'discount_price', 'price_on_request',
                'is_published', 'is_featured', 'views_count',
                'slug', 'specifications', 'meta_title', 'meta_description',
            ]);
        });
    }
};
