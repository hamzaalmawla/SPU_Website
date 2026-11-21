<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily counters for the assistant's voice: recordings transcribed (and
 * their length), replies read aloud (and the characters spoken), and voice
 * calls that failed. Numbers only, never audio or text. Schema only: no data
 * changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_usage_days', function (Blueprint $table): void {
            $table->unsignedInteger('voice_transcriptions')->default(0)->after('paused');
            $table->unsignedInteger('voice_seconds')->default(0)->after('voice_transcriptions');
            $table->unsignedInteger('voice_spoken_answers')->default(0)->after('voice_seconds');
            $table->unsignedInteger('voice_characters')->default(0)->after('voice_spoken_answers');
            $table->unsignedInteger('voice_failures')->default(0)->after('voice_characters');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_usage_days', function (Blueprint $table): void {
            $table->dropColumn(['voice_transcriptions', 'voice_seconds', 'voice_spoken_answers', 'voice_characters', 'voice_failures']);
        });
    }
};
