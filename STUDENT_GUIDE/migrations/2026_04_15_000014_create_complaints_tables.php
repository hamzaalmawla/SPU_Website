<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Complaint Categories
        Schema::create('complaint_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('complaint_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['complaint_category_id', 'locale'], 'complaint_cat_trans_unique');
        });

        // Complaints
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('complaint_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Submitter info (if not logged in)
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();
            $table->string('submitter_phone')->nullable();
            $table->string('submitter_type')->nullable(); // student, faculty, staff, visitor
            
            // Complaint details
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('pending'); // pending, in_progress, resolved, closed, rejected
            
            // Resolution
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Feedback
            $table->integer('satisfaction_rating')->nullable(); // 1-5
            $table->text('feedback')->nullable();
            
            // Attachments
            $table->json('attachment_paths')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'priority']);
            $table->index('ticket_number');
            $table->index('submitted_by_user_id');
            $table->index('assigned_to_user_id');
        });

        // Complaint responses/comments
        Schema::create('complaint_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->json('attachment_paths')->nullable();
            $table->boolean('is_internal')->default(false); // Internal note vs public response
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['complaint_id', 'is_internal']);
        });

        // Complaint status history
        Schema::create('complaint_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            
            $table->index('complaint_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_status_history');
        Schema::dropIfExists('complaint_responses');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('complaint_category_translations');
        Schema::dropIfExists('complaint_categories');
    }
};
