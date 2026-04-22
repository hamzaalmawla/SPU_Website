<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comments (polymorphic - can be attached to any content)
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable'); // pages, news, events, etc.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            
            // Commenter info (if not logged in)
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->string('author_ip')->nullable();
            
            // Comment content
            $table->text('content');
            $table->string('status')->default('pending'); // pending, approved, rejected, spam
            
            // Moderation
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_notes')->nullable();
            
            // Engagement
            $table->integer('likes_count')->default(0);
            $table->integer('dislikes_count')->default(0);
            $table->integer('replies_count')->default(0);
            
            // Flags
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('reports_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['commentable_type', 'commentable_id', 'status']);
            $table->index(['parent_id', 'status']);
            $table->index('is_pinned');
        });

        // Comment reactions
        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reaction_type'); // like, dislike, love, helpful, etc.
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->unique(['comment_id', 'user_id', 'reaction_type']);
            $table->index(['comment_id', 'reaction_type']);
        });

        // Comment reports
        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason'); // spam, offensive, inappropriate, etc.
            $table->text('details')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, actioned, dismissed
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            $table->index(['comment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
        Schema::dropIfExists('comment_reactions');
        Schema::dropIfExists('comments');
    }
};
