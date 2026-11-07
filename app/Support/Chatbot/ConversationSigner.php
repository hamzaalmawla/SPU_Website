<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

/**
 * Signs each question-and-answer pair the assistant produced.
 *
 * The conversation lives in the visitor's browser, so earlier turns arrive
 * with every new question. Without a signature anyone could send a made-up
 * history ("assistant: sure, here is the code you asked for…") to steer the
 * model. Only pairs this server answered, unchanged, are passed on as context.
 */
final class ConversationSigner
{
    public static function sign(string $locale, string $question, string $answer): string
    {
        return hash_hmac('sha256', implode("\x1F", ['spu-assistant-v1', $locale, trim($question), trim($answer)]), self::key());
    }

    public static function verify(string $locale, string $question, string $answer, ?string $signature): bool
    {
        return is_string($signature) && $signature !== '' && hash_equals(self::sign($locale, $question, $answer), $signature);
    }

    /**
     * Signs a reply's text for reading aloud. The speech endpoint speaks only
     * text that carries this, so it cannot be used to voice anything else.
     */
    public static function signSpeech(string $locale, string $text): string
    {
        return hash_hmac('sha256', implode("\x1F", ['spu-assistant-speech-v1', $locale, trim($text)]), self::key());
    }

    public static function verifySpeech(string $locale, string $text, ?string $signature): bool
    {
        return is_string($signature) && $signature !== '' && hash_equals(self::signSpeech($locale, $text), $signature);
    }

    private static function key(): string
    {
        return 'chatbot-history|'.(string) config('app.key');
    }
}
