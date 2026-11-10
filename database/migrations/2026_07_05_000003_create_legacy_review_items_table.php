<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_review_items', function (Blueprint $table): void {
            $table->id();
            $table->string('module')->index();
            $table->string('source_table')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('legacy_key')->index();
            $table->string('classification')->index();
            $table->string('mapping_status')->index();
            $table->string('review_status')->index();
            $table->string('target_module')->nullable()->index();
            $table->string('target_type')->nullable()->index();
            $table->string('confidence')->nullable()->index();
            $table->string('file_dependency')->nullable()->index();
            $table->json('phase3_reasons')->nullable();
            $table->string('cleaning_status')->index();
            $table->string('decision_plan_action')->nullable()->index();
            $table->string('url_status')->nullable()->index();
            $table->json('blocked_reasons')->nullable();
            $table->text('source_identity')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_date')->nullable();
            $table->string('rule_key')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['module', 'source_table', 'legacy_key'], 'legacy_review_items_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_review_items');
    }
};
