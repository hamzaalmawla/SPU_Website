<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\DTOs\Chatbot\ChatbotQuestionDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One visitor message to the assistant, with the conversation so far.
 */
final class ChatbotMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxMessage = (int) config('chatbot.conversation.max_message_characters', 1000);

        return [
            'message' => ['required', 'string', 'min:1', 'max:'.$maxMessage],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*' => ['array:role,content,signature'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:8000'],
            'history.*.signature' => ['nullable', 'string', 'size:64'],
        ];
    }

    public function toDto(string $locale): ChatbotQuestionDTO
    {
        $history = [];
        foreach ((array) $this->validated('history', []) as $turn) {
            $history[] = [
                'role' => $turn['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $turn['content'],
                'signature' => is_string($turn['signature'] ?? null) ? $turn['signature'] : null,
            ];
        }

        return new ChatbotQuestionDTO(
            locale: $locale,
            message: trim((string) $this->validated('message')),
            history: $history,
            // Keyed, so the stored value cannot be turned back into an address.
            clientKey: hash_hmac('sha256', (string) $this->ip(), 'chatbot-client|'.(string) config('app.key')),
            visitorKey: $this->hasSession() ? hash_hmac('sha256', $this->session()->getId(), 'chatbot-visitor|'.(string) config('app.key')) : null,
        );
    }
}
