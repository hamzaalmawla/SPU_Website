<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Chatbot\ChatbotVoiceServiceInterface;
use App\DTOs\Chatbot\ChatbotSpeechDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ChatbotSpeechRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * One segment of a reply as audio. Any status but ok answers with JSON (and
 * a non-2xx code), and the browser reads the reply with its own voice.
 */
final class ChatbotSpeechController extends Controller
{
    public function __invoke(ChatbotSpeechRequest $request, ChatbotVoiceServiceInterface $voice, string $locale): Response|JsonResponse
    {
        $speech = $voice->speak($request->toDto($locale));

        if ($speech->status !== ChatbotSpeechDTO::STATUS_OK || $speech->audio === null) {
            return response()
                ->json(['status' => $speech->status, 'segments' => $speech->segments], match ($speech->status) {
                    ChatbotSpeechDTO::STATUS_FORBIDDEN => 403,
                    ChatbotSpeechDTO::STATUS_INVALID => 422,
                    'limited' => 429,
                    default => 503,
                })
                ->header('Cache-Control', 'no-store, private')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response($speech->audio, 200, [
            'Content-Type' => $speech->mimeType,
            'Content-Length' => (string) strlen($speech->audio),
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Speech-Segment' => (string) $speech->segment,
            'X-Speech-Segments' => (string) $speech->segments,
        ]);
    }
}
