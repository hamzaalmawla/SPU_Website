<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_name')->unique();
            $table->string('module')->index();
            $table->string('mode', 40)->index();
            $table->string('status', 40)->index();
            $table->unsignedInteger('estimated_source_rows')->default(0);
            $table->json('summary_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_batches');
    }
};
