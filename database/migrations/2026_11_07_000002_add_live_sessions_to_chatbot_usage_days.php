<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live transcription sessions started per day (the visitor's browser streams
 * the microphone to OpenAI while they speak). A count only. Schema only: no
 * data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_usage_days', function (Blueprint $table): void {
            $table->unsignedInteger('voice_live_sessions')->default(0)->after('voice_transcriptions');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_usage_days', function (Blueprint $table): void {
            $table->dropColumn('voice_live_sessions');
        });
    }
};
