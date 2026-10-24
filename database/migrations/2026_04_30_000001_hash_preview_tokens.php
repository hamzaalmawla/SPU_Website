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
        Schema::table('preview_tokens', function (Blueprint $table): void {
            $table->string('token_hash', 64)->nullable()->unique()->after('token');
        });

        $key = (string) config('app.key');

        DB::table('preview_tokens')
            ->select(['id', 'token'])
            ->whereNotNull('token')
            ->orderBy('id')
            ->chunkById(100, function ($tokens) use ($key): void {
                foreach ($tokens as $token) {
                    DB::table('preview_tokens')
                        ->where('id', $token->id)
                        ->update([
                            'token_hash' => hash_hmac('sha256', (string) $token->token, $key),
                        ]);
                }
            });

        Schema::table('preview_tokens', function (Blueprint $table): void {
            $table->dropUnique('preview_tokens_token_unique');
            $table->dropColumn('token');
        });
    }

    public function down(): void
    {
        Schema::table('preview_tokens', function (Blueprint $table): void {
            $table->string('token')->nullable()->unique();
            $table->dropUnique('preview_tokens_token_hash_unique');
            $table->dropColumn('token_hash');
        });
    }
};
