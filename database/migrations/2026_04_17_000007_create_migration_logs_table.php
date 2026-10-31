<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('module')->index();
            $table->string('batch_name')->index();
            $table->string('source_table')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('target_table')->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('status')->index();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_logs');
    }
};
