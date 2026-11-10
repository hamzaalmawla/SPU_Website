<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_name');
            $table->string('source_table');
            $table->string('target_table');
            $table->bigInteger('source_id')->nullable();
            $table->bigInteger('target_id')->nullable();
            $table->string('status'); // success, failed, skipped
            $table->text('message')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->json('transformations')->nullable();
            $table->timestamp('migrated_at');
            $table->foreignId('migrated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['batch_name', 'status']);
            $table->index(['source_table', 'source_id']);
            $table->index(['target_table', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_logs');
    }
};
