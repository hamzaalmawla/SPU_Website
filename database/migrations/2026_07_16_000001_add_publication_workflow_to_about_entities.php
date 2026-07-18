<?php

declare(strict_types=1);

use App\Enums\PublicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['persons', 'directorates', 'partnerships'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('publication_status')->default(PublicationStatus::Draft->value)->index();
                $table->timestamp('published_at')->nullable()->index();

                if ($tableName === 'partnerships') {
                    $table->string('category_key')->nullable()->index();
                    $table->string('status_key')->default('active')->index();
                }
            });

            DB::table($tableName)->update([
                'publication_status' => PublicationStatus::Published->value,
                'published_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if ($tableName === 'partnerships') {
                    $table->dropIndex(['category_key']);
                    $table->dropIndex(['status_key']);
                    $table->dropColumn(['category_key', 'status_key']);
                }

                $table->dropIndex(['publication_status']);
                $table->dropIndex(['published_at']);
                $table->dropColumn(['publication_status', 'published_at']);
            });
        }
    }
};
