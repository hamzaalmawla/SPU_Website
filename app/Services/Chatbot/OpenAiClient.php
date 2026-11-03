<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\OpenAiClientInterface;
use App\DTOs\Chatbot\ChatbotCompletionDTO;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotEmbeddingResultDTO;
use App\Exceptions\ChatbotProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI's Responses and Embeddings endpoints over Laravel's HTTP client.
 *
 * Requests use `store: false`, so OpenAI keeps no copy of the conversation
 * for later retrieval. Nothing here logs the key, the prompt or the answer.
 */
final class OpenAiClient implements OpenAiClientInterface
{
    public function embed(array $inputs, string $apiKey): ChatbotEmbeddingResultDTO
    {
        if ($inputs === []) {
            return new ChatbotEmbeddingResultDTO([], 0);
        }

        $response = $this->send(
            fn (): Response => $this->request($apiKey, (int) config('chatbot.openai.embedding_timeout', 60))
                // Indexing a file can hit a short rate limit; a question cannot
                // wait, but each embedding call is small, so both retry once.
                ->retry(2, 800, fn (\Throwable $exception): bool => $this->isTransient($exception), throw: false)
                ->post('/embeddings', [
                    'model' => (string) config('chatbot.embedding.model'),
                    'input' => array_values($inputs),
                    'dimensions' => (int) config('chatbot.embedding.dimensions'),
                    'encoding_format' => 'float',
                ])
        );

        $data = $response->json('data');
        if (! is_array($data) || count($data) !== count($inputs)) {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'Embedding response did not match the request.');
        }

        $vectors = [];
        foreach ($data as $row) {
            $index = is_array($row) ? ($row['index'] ?? null) : null;
            $embedding = is_array($row) ? ($row['embedding'] ?? null) : null;
            if (! is_int($index) || ! is_array($embedding) || $embedding === []) {
                throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'Embedding response had a malformed row.');
            }
            $vectors[$index] = array_map(static fn (mixed $value): float => (float) $value, array_values($embedding));
        }
        ksort($vectors);

        return new ChatbotEmbeddingResultDTO(array_values($vectors), (int) $response->json('usage.total_tokens', 0));
    }

    public function respond(ChatbotCompletionRequestDTO $request): ChatbotCompletionDTO
    {
        $payload = [
            'model' => $request->model,
            'instructions' => $request->instructions,
            'input' => array_map(
                static fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
                $request->messages,
            ),
            'max_output_tokens' => $request->maxOutputTokens,
            'store' => false,
        ];

        if ($request->reasoningEffort !== null) {
            $payload['reasoning'] = ['effort' => $request->reasoningEffort];
        }

        if ($request->cacheKey !== null) {
            $payload['prompt_cache_key'] = $request->cacheKey;
        }

        $response = $this->send(
            fn (): Response => $this->request($request->apiKey, (int) config('chatbot.openai.timeout', 45))->post('/responses', $payload)
        );

        $text = '';
        foreach ((array) $response->json('output', []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'output_text' && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }

        $truncated = $response->json('status') === 'incomplete';
        if (trim($text) === '') {
            throw new ChatbotProviderException(
                ChatbotProviderException::INVALID,
                $truncated ? 'The model used its whole output budget without answering.' : 'The model returned no text.',
            );
        }

        return new ChatbotCompletionDTO(
            text: trim($text),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            cachedInputTokens: (int) $response->json('usage.input_tokens_details.cached_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            truncated: $truncated,
        );
    }

    private function request(string $apiKey, int $timeout): PendingRequest
    {
        return Http::baseUrl((string) config('chatbot.openai.base_url'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('chatbot.openai.connect_timeout', 5))
            ->timeout($timeout);
    }

    /**
     * @param  callable(): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            throw new ChatbotProviderException(ChatbotProviderException::UNAVAILABLE, 'OpenAI could not be reached.');
        } catch (RequestException $exception) {
            $response = $exception->response;
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $code = (string) $response->json('error.code', '');
        $type = (string) $response->json('error.type', '');

        throw match (true) {
            $status === 401, $status === 403 => new ChatbotProviderException(ChatbotProviderException::AUTH, 'OpenAI rejected the API key.', $status),
            $status === 429 && ($code === 'insufficient_quota' || $type === 'insufficient_quota') => new ChatbotProviderException(ChatbotProviderException::QUOTA, 'The OpenAI account has no credit left.', $status),
            $status === 429 => new ChatbotProviderException(ChatbotProviderException::RATE_LIMITED, 'OpenAI rate limit reached.', $status),
            $status >= 500 => new ChatbotProviderException(ChatbotProviderException::UNAVAILABLE, 'OpenAI is unavailable.', $status),
            default => new ChatbotProviderException(ChatbotProviderException::INVALID, 'OpenAI refused the request ('.$status.($code !== '' ? ', '.$code : '').').', $status),
        };
    }

    private function isTransient(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();
            $code = (string) $exception->response->json('error.code', '');

            return ($status === 429 && $code !== 'insufficient_quota') || $status >= 500;
        }

        return false;
    }
}
