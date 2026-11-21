<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_record_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('module')->index();
            $table->string('batch_name')->index();
            $table->string('source_table')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('legacy_key')->nullable()->index();
            $table->string('classification')->nullable()->index();
            $table->string('locale', 5)->nullable()->index();
            $table->json('payload_json')->nullable();
            $table->text('payload_text')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['source_table', 'source_id', 'legacy_key'], 'legacy_snapshots_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_record_snapshots');
    }
};
