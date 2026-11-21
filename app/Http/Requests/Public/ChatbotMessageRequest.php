<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\Http\Requests\Public\Concerns\IdentifiesChatbotVisitor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One visitor message to the assistant, with the conversation so far
 * (signed recent turns and the sealed notes on the turns before them).
 */
final class ChatbotMessageRequest extends FormRequest
{
    use IdentifiesChatbotVisitor;

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
            // The sealed conversation notes (ConversationMemory) of the last answer.
            'memory' => ['nullable', 'string', 'max:8000'],
            // The question was spoken and its answer will be read aloud.
            'voice' => ['sometimes', 'boolean'],
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
            clientKey: $this->chatbotClientKey(),
            visitorKey: $this->chatbotVisitorKey(),
            memory: is_string($this->validated('memory')) ? $this->validated('memory') : null,
            spoken: $this->boolean('voice'),
        );
    }
}
