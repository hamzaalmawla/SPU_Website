<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Chatbot\ChatbotVoiceServiceInterface;
use App\DTOs\Chatbot\ChatbotSpeechDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ChatbotSpeechRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One segment of a reply as audio. Any status but ok answers with JSON (and
 * a non-2xx code), and the browser reads the reply with its own voice.
 *
 * A streamed segment is sent on chunk by chunk as OpenAI makes it, with
 * proxy buffering off, so the voice starts as soon as the first audio exists.
 */
final class ChatbotSpeechController extends Controller
{
    public function __invoke(ChatbotSpeechRequest $request, ChatbotVoiceServiceInterface $voice, string $locale): Response|JsonResponse|StreamedResponse
    {
        $speech = $voice->speak($request->toDto($locale));

        if ($speech->status !== ChatbotSpeechDTO::STATUS_OK || ($speech->audio === null && $speech->chunks === null)) {
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

        if ($speech->chunks !== null) {
            $chunks = $speech->chunks;

            return response()->stream(static function () use ($chunks): void {
                try {
                    foreach ($chunks as $chunk) {
                        echo $chunk;
                        // Push it past PHP's output buffer (output_buffering)
                        // and the SAPI, so it leaves now.
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                        if (connection_aborted() === 1) {
                            return;
                        }
                    }
                } catch (Throwable $exception) {
                    // The headers are gone; the browser sees a short segment and goes on.
                    report($exception);
                }
            }, 200, [
                'Content-Type' => $speech->mimeType,
                'Cache-Control' => 'no-store, private',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow',
                'X-Speech-Segment' => (string) $speech->segment,
                'X-Speech-Segments' => (string) $speech->segments,
            ]);
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
