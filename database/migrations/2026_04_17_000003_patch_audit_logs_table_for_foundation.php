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
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('action')->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable()->after('metadata');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->index('entity_type');
            $table->index('entity_id');
        });

        DB::table('audit_logs')->whereNotNull('actor_user_id')->update([
            'user_id' => DB::raw('actor_user_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['entity_type']);
            $table->dropIndex(['entity_id']);
            $table->dropColumn(['user_agent', 'ip_address']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
