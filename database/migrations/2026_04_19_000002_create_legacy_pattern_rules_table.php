<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_pattern_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('pattern', 2048);
            $table->string('replacement', 2048);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['priority'], 'idx_lpr_priority');
            $table->index(['is_active'], 'idx_lpr_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_pattern_rules');
    }
};
