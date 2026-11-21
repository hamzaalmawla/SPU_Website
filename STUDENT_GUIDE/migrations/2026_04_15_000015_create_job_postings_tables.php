<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Job Categories
        Schema::create('job_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['job_category_id', 'locale']);
        });

        // Job Postings
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('job_code')->unique();
            $table->foreignId('job_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type'); // full-time, part-time, contract, internship
            $table->string('experience_level'); // entry, mid, senior, executive
            $table->integer('positions_available')->default(1);
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->string('salary_currency', 3)->default('SYP');
            $table->boolean('salary_negotiable')->default(false);
            $table->date('application_deadline')->nullable();
            $table->string('status')->default('draft'); // draft, published, closed, filled
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('applications_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'application_deadline']);
            $table->index('is_featured');
        });

        Schema::create('job_posting_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('title');
            $table->text('description');
            $table->text('responsibilities')->nullable();
            $table->text('requirements')->nullable();
            $table->text('qualifications')->nullable();
            $table->text('benefits')->nullable();
            $table->text('how_to_apply')->nullable();
            $table->timestamps();

            $table->unique(['job_posting_id', 'locale']);
        });

        // Job Applications
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Applicant info
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('current_location')->nullable();

            // Documents
            $table->string('cv_path');
            $table->string('cover_letter_path')->nullable();
            $table->json('additional_documents')->nullable();

            // Application details
            $table->text('cover_letter_text')->nullable();
            $table->decimal('expected_salary', 10, 2)->nullable();
            $table->date('available_from')->nullable();
            $table->boolean('requires_visa')->default(false);

            // Status
            $table->string('status')->default('submitted'); // submitted, under_review, shortlisted, interviewed, offered, rejected, withdrawn
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['job_posting_id', 'status']);
            $table->index('application_number');
        });

        // Application status history
        Schema::create('job_application_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');

            $table->index('job_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_status_history');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_posting_translations');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('job_category_translations');
        Schema::dropIfExists('job_categories');
    }
};
