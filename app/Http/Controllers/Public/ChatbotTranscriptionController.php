<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Chatbot\ChatbotVoiceServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ChatbotTranscriptionRequest;
use Illuminate\Http\JsonResponse;

/**
 * A spoken question in, its text out. The browser then asks it like a typed
 * question, so the visitor sees what was understood before the answer.
 */
final class ChatbotTranscriptionController extends Controller
{
    public function __invoke(ChatbotTranscriptionRequest $request, ChatbotVoiceServiceInterface $voice, string $locale): JsonResponse
    {
        $transcript = $voice->transcribe($request->toDto($locale));

        return response()
            ->json([
                'status' => $transcript->status,
                'text' => $transcript->text,
                'message' => $transcript->message,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
