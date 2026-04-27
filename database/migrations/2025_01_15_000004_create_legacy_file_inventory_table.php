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
        Schema::create('legacy_file_inventory', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_path', 2048);
            $table->string('current_path', 2048)->nullable();
            $table->unsignedBigInteger('media_asset_id')->nullable();
            $table->enum('status', ['mapped', 'unmapped', 'missing'])->default('unmapped');
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->onDelete('set null');

            $table->index(['status'], 'idx_status');
        });

        DB::statement('CREATE INDEX idx_legacy_path ON legacy_file_inventory (legacy_path(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_file_inventory');
    }
};
