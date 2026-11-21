<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Chatbot\ChatbotVoiceServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ChatbotLiveSessionRequest;
use Illuminate\Http\JsonResponse;

/**
 * A short-lived key for live transcription. The key opens one
 * transcription-only session and expires within a minute if unused.
 */
final class ChatbotLiveSessionController extends Controller
{
    public function __invoke(ChatbotLiveSessionRequest $request, ChatbotVoiceServiceInterface $voice, string $locale): JsonResponse
    {
        $session = $voice->liveSession($request->toDto($locale));

        return response()
            ->json([
                'status' => $session->status,
                'key' => $session->key,
                'expiresAt' => $session->expiresAt,
                'url' => $session->url,
                'session' => $session->session,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
