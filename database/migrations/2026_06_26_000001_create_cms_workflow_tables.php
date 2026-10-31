<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('target_key')->index();
            $table->json('payload_json');
            $table->string('status')->index();
            $table->text('draft_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['target_key', 'status', 'updated_at'], 'idx_cms_drafts_lookup');
        });

        Schema::create('cms_target_contents', function (Blueprint $table): void {
            $table->id();
            $table->string('target_key')->unique();
            $table->json('payload_json')->nullable();
            $table->string('status')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_target_contents');
        Schema::dropIfExists('cms_drafts');
    }
};
