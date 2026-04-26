<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('type');
            $table->string('label');
            $table->string('locale', 5)->nullable();
            $table->string('target_kind');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('url')->nullable();
            $table->string('target')->nullable();
            $table->string('route_name')->nullable();
            $table->string('css_token')->nullable();
            $table->string('icon')->nullable();
            $table->string('group_key')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_utility')->default(false)->index();
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('depth')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
