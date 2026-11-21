<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->string('group_key')->index();
            $table->string('type');
            $table->string('locale', 5)->default('')->index();
            $table->json('value_json')->nullable();
            $table->text('value_text')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['group_key', 'key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
