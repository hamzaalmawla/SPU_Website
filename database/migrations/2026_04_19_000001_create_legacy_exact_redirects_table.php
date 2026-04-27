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
        Schema::create('legacy_exact_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_path', 2048);
            $table->string('destination_url', 2048);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('locale', 5)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active'], 'idx_is_active');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('CREATE INDEX idx_legacy_path ON legacy_exact_redirects (legacy_path(191))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_exact_redirects');
    }
};
