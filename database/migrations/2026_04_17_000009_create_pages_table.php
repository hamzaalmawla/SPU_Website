<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('type');
            $table->string('template');
            $table->string('slug')->unique();
            $table->string('status')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('show_in_breadcrumbs')->default(true);
            $table->boolean('show_in_nav')->default(false);
            $table->boolean('is_homepage_shell')->default(false);
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('last_reviewed_at')->nullable();
            $table->string('layout_key')->nullable();
            $table->unsignedInteger('builder_schema_version')->nullable();
            $table->json('content_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
