<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل النشاط: مين عمل إيه، إمتى، ومن أنهي جهاز — بيتسجل أوتوماتيك لكل إضافة/تعديل/مسح،
 * والدخول والخروج ومحاولات الدخول الغلط ومحاولات فتح صفحات من غير صلاحية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30)->comment('login, logout, login_failed, create, update, delete, action, denied');
            $table->string('route')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('description')->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
