<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('complaint_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_category_id')->constrained(table: 'complaint_categories', indexName: 'cct_cat_fk')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['complaint_category_id', 'locale'], 'cct_cat_locale_unique');
        });

        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('complaint_category_id')->nullable()->constrained('complaint_categories')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();
            $table->string('submitter_phone')->nullable();
            $table->string('subject');
            $table->text('description');
            $table->string('priority');
            $table->string('status')->index();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('complaint_category_translations');
        Schema::dropIfExists('complaint_categories');
    }
};
