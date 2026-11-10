<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use App\DTOs\Chatbot\ChatbotConversationMemoryDTO;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The conversation notes as a sealed token the visitor's browser keeps.
 *
 * The assistant stores nothing about a conversation on the server. Its
 * long-term memory (what the visitor is looking for, what was already
 * answered) is written by the model with each answer and handed to the
 * browser encrypted and authenticated with APP_KEY, so it can come back with
 * the next question but cannot be read, edited or forged on the way. A token
 * is bound to its purpose and language and expires after
 * `chatbot.conversation.memory_hours`.
 */
final class ConversationMemory
{
    private const PURPOSE = 'spu-assistant-memory';

    private const VERSION = 1;

    public static function seal(string $locale, string $notes, int $exchanges): string
    {
        return Crypt::encryptString((string) json_encode([
            'p' => self::PURPOSE,
            'v' => self::VERSION,
            'l' => self::locale($locale),
            'n' => self::clean($notes),
            'x' => max(0, $exchanges),
            'e' => now()->addHours(max(1, (int) config('chatbot.conversation.memory_hours', 24)))->getTimestamp(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * The notes in a token this server sealed for this language, or null for
     * anything else (missing, tampered, expired, another language).
     */
    public static function open(?string $token, string $locale): ?ChatbotConversationMemoryDTO
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)
            || ($payload['p'] ?? null) !== self::PURPOSE
            || ($payload['v'] ?? null) !== self::VERSION
            || ($payload['l'] ?? null) !== self::locale($locale)
            || ! is_string($payload['n'] ?? null)
            || ! is_int($payload['x'] ?? null)
            || ! is_int($payload['e'] ?? null)
            || $payload['e'] < now()->getTimestamp()) {
            return null;
        }

        return new ChatbotConversationMemoryDTO(self::clean($payload['n']), max(0, $payload['x']));
    }

    /**
     * Notes as they may be kept: personal data removed, one paragraph per
     * line, within the configured length.
     */
    public static function clean(string $notes): string
    {
        $notes = ChatbotGuard::redactPersonalData($notes);
        $notes = (string) preg_replace('/[^\S\n]+/u', ' ', $notes);
        $notes = (string) preg_replace('/\n{2,}/u', "\n", $notes);

        return trim(mb_substr(trim($notes), 0, max(100, (int) config('chatbot.conversation.memory_characters', 900))));
    }

    private static function locale(string $locale): string
    {
        return $locale === 'en' ? 'en' : 'ar';
    }
}
