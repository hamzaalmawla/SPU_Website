<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('about_navigation_cards', function (Blueprint $table): void {
            $table->string('status')->default('draft')->index()->after('is_visible');
            $table->timestamp('publish_at')->nullable()->index()->after('status');
            $table->timestamp('published_at')->nullable()->after('publish_at');
        });
    }

    public function down(): void
    {
        Schema::table('about_navigation_cards', function (Blueprint $table): void {
            $table->dropColumn(['status', 'publish_at', 'published_at']);
        });
    }
};
