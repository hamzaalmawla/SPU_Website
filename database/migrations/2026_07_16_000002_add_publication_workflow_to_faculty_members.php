<?php

declare(strict_types=1);

use App\Enums\PublicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->string('publication_status')->default(PublicationStatus::Draft->value)->index();
            $table->timestamp('published_at')->nullable()->index();
        });

        DB::table('faculty_members')->update([
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->dropIndex(['publication_status']);
            $table->dropIndex(['published_at']);
            $table->dropColumn(['publication_status', 'published_at']);
        });
    }
};
