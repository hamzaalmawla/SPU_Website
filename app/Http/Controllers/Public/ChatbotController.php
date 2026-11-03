<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\DTOs\Chatbot\ChatbotSourceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ChatbotMessageRequest;
use Illuminate\Http\JsonResponse;

/**
 * The public assistant's single endpoint: a question in, an answer out.
 */
final class ChatbotController extends Controller
{
    public function __invoke(ChatbotMessageRequest $request, ChatbotServiceInterface $chatbot, string $locale): JsonResponse
    {
        $reply = $chatbot->ask($request->toDto($locale));

        return response()
            ->json([
                'status' => $reply->status,
                'answer' => $reply->answer,
                'sources' => array_map(
                    static fn (ChatbotSourceDTO $source): array => ['title' => $source->title, 'pages' => $source->pages, 'url' => $source->url],
                    $reply->sources,
                ),
                'signature' => $reply->signature,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
