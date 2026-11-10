<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token')->unique();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('device')->nullable();
            $table->foreignId('issued_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload_json')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_tokens');
    }
};
