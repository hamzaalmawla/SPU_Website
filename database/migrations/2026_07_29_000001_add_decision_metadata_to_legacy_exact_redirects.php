<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_exact_redirects', function (Blueprint $table): void {
            $table->string('query_signature', 2048)->nullable()->after('legacy_path');
            $table->string('decision_batch', 120)->nullable()->after('notes')->index();
            $table->char('evidence_sha256', 64)->nullable()->after('decision_batch');
        });

        Schema::create('legacy_redirect_decision_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id', 120)->unique();
            $table->char('evidence_sha256', 64);
            $table->string('packet_path', 2048);
            $table->string('approved_by', 255);
            $table->string('status', 32)->index();
            $table->unsignedInteger('created_redirects')->default(0);
            $table->timestamp('applied_at');
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_redirect_decision_batches');

        Schema::table('legacy_exact_redirects', function (Blueprint $table): void {
            $table->dropIndex(['decision_batch']);
            $table->dropColumn(['query_signature', 'decision_batch', 'evidence_sha256']);
        });
    }
};
