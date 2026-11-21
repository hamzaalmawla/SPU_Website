<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_rejections', function (Blueprint $table): void {
            $table->id();
            $table->string('module')->index();
            $table->string('source_table')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('reason_code')->index();
            $table->text('reason_message');
            $table->json('raw_summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_rejections');
    }
};
