<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FAQ Categories
        Schema::create('faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('faq_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['faq_category_id', 'locale']);
        });

        // FAQs
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_category_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['faq_category_id', 'is_published']);
            $table->index('is_featured');
        });

        Schema::create('faq_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('question');
            $table->text('answer');
            $table->text('keywords')->nullable(); // For search
            $table->timestamps();
            
            $table->unique(['faq_id', 'locale']);
            $table->fullText(['question', 'answer', 'keywords']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_translations');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('faq_category_translations');
        Schema::dropIfExists('faq_categories');
    }
};
