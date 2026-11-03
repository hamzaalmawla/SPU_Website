<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI assistant's knowledge base, settings and usage counters.
 *
 * Schema only: nothing is inserted, so the deploy's data-migration gate does
 * not need an approval file. Defaults live in config/chatbot.php until an
 * editor saves the settings.
 *
 * Embeddings are packed float32 vectors (config chatbot.embedding.dimensions,
 * 512 => 2 KB a passage), stored base64-encoded. MariaDB 10.11 has no vector type
 * and sqlite (tests) has none either; similarity is computed in PHP over the
 * active passages, which is fast enough for a university's documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            // upload | text
            $table->string('source_type', 16)->default('upload');
            $table->string('original_name', 255)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum', 64);
            // pending | ready | failed
            $table->string('status', 16)->default('pending');
            $table->string('error', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('character_count')->default(0);
            $table->unsignedInteger('embedding_tokens')->default(0);
            $table->string('embedding_model', 64)->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique('checksum', 'uniq_chatbot_documents_checksum');
            $table->index(['status', 'is_active'], 'idx_chatbot_documents_status_active');
        });

        Schema::create('chatbot_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chatbot_document_id')->constrained('chatbot_documents')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('page')->nullable();
            $table->string('heading', 255)->nullable();
            $table->text('content');
            // SearchTextNormalizer form, for keyword re-ranking.
            $table->text('content_normalized');
            // base64 of the packed vector: portable across MariaDB and sqlite
            // drivers, which treat raw bytes in a bound string differently.
            $table->text('embedding');
            $table->timestamps();

            $table->index(['chatbot_document_id', 'position'], 'idx_chatbot_chunks_document_position');
        });

        Schema::create('chatbot_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('chatbot_usage_days', function (Blueprint $table): void {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('questions')->default(0);
            $table->unsignedInteger('answered_from_cache')->default(0);
            $table->unsignedInteger('without_sources')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('embedding_tokens')->default(0);
            // Estimated spend in millionths of a US dollar.
            $table->unsignedBigInteger('cost_micro_usd')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_usage_days');
        Schema::dropIfExists('chatbot_settings');
        Schema::dropIfExists('chatbot_chunks');
        Schema::dropIfExists('chatbot_documents');
    }
};
