<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\DTOs\Chatbot\ChatbotTranscriptionRequestDTO;
use App\Http\Requests\Public\Concerns\IdentifiesChatbotVisitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A spoken question recorded in the visitor's browser (MediaRecorder).
 */
final class ChatbotTranscriptionRequest extends FormRequest
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
        $mimes = array_values(array_filter((array) config('chatbot.voice.transcription.mimes', []), 'is_string'));

        return [
            // The type is read from the file itself, not from what the browser says.
            'audio' => ['required', 'file', 'max:'.max(64, (int) config('chatbot.voice.transcription.max_kb', 3072)), 'mimetypes:'.implode(',', $mimes)],
            'duration' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ];
    }

    public function toDto(string $locale): ChatbotTranscriptionRequestDTO
    {
        /** @var UploadedFile $audio */
        $audio = $this->file('audio');

        return new ChatbotTranscriptionRequestDTO(
            locale: $locale,
            path: (string) $audio->getRealPath(),
            mimeType: (string) ($audio->getMimeType() ?? 'audio/webm'),
            sizeBytes: (int) $audio->getSize(),
            durationMs: (int) $this->validated('duration', 0),
            clientKey: $this->chatbotClientKey(),
            visitorKey: $this->chatbotVisitorKey(),
        );
    }
}
