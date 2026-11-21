<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\DTOs\Chatbot\ChatbotLiveSessionRequestDTO;
use App\Http\Requests\Public\Concerns\IdentifiesChatbotVisitor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A visitor starting live transcription; it carries nothing but who asks.
 */
final class ChatbotLiveSessionRequest extends FormRequest
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
        return [];
    }

    public function toDto(string $locale): ChatbotLiveSessionRequestDTO
    {
        return new ChatbotLiveSessionRequestDTO(
            locale: $locale,
            clientKey: $this->chatbotClientKey(),
            visitorKey: $this->chatbotVisitorKey(),
        );
    }
}
