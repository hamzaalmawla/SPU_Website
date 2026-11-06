<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff intake: a link per faculty that academic staff open to send in their
 * own profile data, the requests they send (reviewed before anything public
 * changes), and a log of who opened the link and what they changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_intake_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            // Only the hash is kept: the link itself is shown once, when it is made.
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('max_submissions')->nullable();
            $table->unsignedInteger('submissions_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_intake_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('staff_intake_link_id')->nullable()->constrained('staff_intake_links')->nullOnDelete();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('type', 10);
            // The profile the teacher said is theirs (an update), or, for a new
            // entry, the profile in another faculty they started from.
            $table->foreignId('faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->foreignId('related_faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->string('submitter_name');
            $table->json('payload');
            $table->json('original_snapshot')->nullable();
            $table->json('duplicate_candidates')->nullable();
            $table->boolean('possible_duplicate')->default(false);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('applied_faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['faculty_id', 'status']);
            $table->index(['faculty_member_id', 'status']);
        });

        Schema::create('staff_intake_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_intake_link_id')->nullable()->constrained('staff_intake_links')->nullOnDelete();
            $table->foreignId('staff_intake_request_id')->nullable()->constrained('staff_intake_requests')->nullOnDelete();
            $table->foreignId('faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->string('event', 20);
            $table->string('submitter_name')->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['staff_intake_link_id', 'created_at']);
            $table->index('faculty_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_intake_access_logs');
        Schema::dropIfExists('staff_intake_requests');
        Schema::dropIfExists('staff_intake_links');
    }
};
