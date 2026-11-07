<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\DTOs\Chatbot\ChatbotSpeechRequestDTO;
use App\Http\Requests\Public\Concerns\IdentifiesChatbotVisitor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One segment of an assistant reply to read aloud: the reply as it was
 * sent, with the speech signature that came with it.
 */
final class ChatbotSpeechRequest extends FormRequest
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
        return [
            'text' => ['required', 'string', 'max:8000'],
            'signature' => ['required', 'string', 'size:64'],
            'segment' => ['required', 'integer', 'min:0', 'max:60'],
        ];
    }

    public function toDto(string $locale): ChatbotSpeechRequestDTO
    {
        return new ChatbotSpeechRequestDTO(
            locale: $locale,
            text: (string) $this->validated('text'),
            signature: (string) $this->validated('signature'),
            segment: (int) $this->validated('segment'),
            clientKey: $this->chatbotClientKey(),
            visitorKey: $this->chatbotVisitorKey(),
        );
    }
}
