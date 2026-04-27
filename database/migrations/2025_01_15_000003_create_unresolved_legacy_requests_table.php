<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unresolved_legacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('url', 2048);
            $table->string('query_string', 2048)->nullable();
            $table->string('method', 10)->default('GET');
            $table->string('referrer', 2048)->nullable();
            $table->string('resolved_locale', 5)->nullable();
            $table->enum('request_type', ['page', 'file'])->default('page');
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['request_type'], 'idx_request_type');
            $table->index(['last_seen_at'], 'idx_last_seen');
        });

        DB::statement('CREATE INDEX idx_url ON unresolved_legacy_requests (url(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('unresolved_legacy_requests');
    }
};
