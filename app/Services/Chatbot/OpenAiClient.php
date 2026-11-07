<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\OpenAiClientInterface;
use App\DTOs\Chatbot\ChatbotAudioTranscriptDTO;
use App\DTOs\Chatbot\ChatbotCompletionDTO;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotEmbeddingResultDTO;
use App\DTOs\Chatbot\ChatbotRealtimeSecretDTO;
use App\DTOs\Chatbot\ChatbotSpeechSynthesisRequestDTO;
use App\DTOs\Chatbot\ChatbotTranscriptionCallDTO;
use App\Exceptions\ChatbotProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI's Responses, Embeddings, Moderation and Audio endpoints over
 * Laravel's HTTP client.
 *
 * Requests use `store: false`, so OpenAI keeps no copy of the conversation
 * for later retrieval. Nothing here logs the key, the prompt, the answer or
 * the audio.
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
        $response = $this->send(
            fn (): Response => $this->request($request->apiKey, $request->timeout ?? (int) config('chatbot.openai.timeout', 45))
                ->post('/responses', $this->responsePayload($request))
        );

        return $this->completion($response);
    }

    public function respondMany(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $keys = array_keys($requests);
        $responses = Http::pool(function (Pool $pool) use ($requests): array {
            $pending = [];
            foreach ($requests as $key => $request) {
                $pending[] = $this->configure($pool->as((string) $key), $request->apiKey, $request->timeout ?? (int) config('chatbot.openai.timeout', 45))
                    ->post('/responses', $this->responsePayload($request));
            }

            return $pending;
        });

        $results = [];
        foreach ($keys as $key) {
            $response = $responses[(string) $key] ?? null;

            try {
                if (! $response instanceof Response) {
                    throw new ChatbotProviderException(ChatbotProviderException::UNAVAILABLE, 'OpenAI could not be reached.');
                }
                if (! $response->successful()) {
                    throw $this->failure($response);
                }
                $results[$key] = $this->completion($response);
            } catch (ChatbotProviderException $exception) {
                $results[$key] = $exception;
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(ChatbotCompletionRequestDTO $request): array
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

        if ($request->jsonSchema !== null) {
            $payload['text'] = ['format' => [
                'type' => 'json_schema',
                'name' => 'assistant_reply',
                'strict' => true,
                'schema' => $request->jsonSchema,
            ]];
        }

        return $payload;
    }

    private function completion(Response $response): ChatbotCompletionDTO
    {
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

    public function moderate(string $input, string $apiKey): bool
    {
        $response = $this->send(
            fn (): Response => $this->request($apiKey, 10)->post('/moderations', $this->moderationPayload($input))
        );

        return $this->flagged($response);
    }

    public function moderateAndRespond(string $input, string $apiKey, ChatbotCompletionRequestDTO $request): array
    {
        $responses = Http::pool(fn (Pool $pool): array => [
            $this->configure($pool->as('moderation'), $apiKey, 10)->post('/moderations', $this->moderationPayload($input)),
            $this->configure($pool->as('completion'), $request->apiKey, $request->timeout ?? (int) config('chatbot.openai.timeout', 45))
                ->post('/responses', $this->responsePayload($request)),
        ]);

        $results = [];
        foreach (['moderation', 'completion'] as $key) {
            $response = $responses[$key] ?? null;

            try {
                if (! $response instanceof Response) {
                    throw new ChatbotProviderException(ChatbotProviderException::UNAVAILABLE, 'OpenAI could not be reached.');
                }
                if (! $response->successful()) {
                    throw $this->failure($response);
                }
                $results[$key] = $key === 'moderation' ? $this->flagged($response) : $this->completion($response);
            } catch (ChatbotProviderException $exception) {
                $results[$key] = $exception;
            }
        }

        /** @var array{moderation: bool|ChatbotProviderException, completion: ChatbotCompletionDTO|ChatbotProviderException} $results */
        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function moderationPayload(string $input): array
    {
        return [
            'model' => (string) config('chatbot.guard.moderation_model', 'omni-moderation-latest'),
            'input' => $input,
        ];
    }

    private function flagged(Response $response): bool
    {
        $results = $response->json('results');
        if (! is_array($results) || $results === []) {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'Moderation response had no results.');
        }

        foreach ($results as $result) {
            if (is_array($result) && ($result['flagged'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    public function transcribe(ChatbotTranscriptionCallDTO $call): ChatbotAudioTranscriptDTO
    {
        $handle = @fopen($call->path, 'rb');
        if ($handle === false) {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'The recording could not be read.');
        }

        $parts = [
            ['name' => 'model', 'contents' => $call->model],
            ['name' => 'response_format', 'contents' => 'json'],
        ];
        if ($call->prompt !== '') {
            $parts[] = ['name' => 'prompt', 'contents' => $call->prompt];
        }
        foreach ($call->languages as $language) {
            $parts[] = ['name' => 'languages[]', 'contents' => $language];
        }
        foreach ($call->keywords as $keyword) {
            $parts[] = ['name' => 'keywords[]', 'contents' => $keyword];
        }
        $parts[] = ['name' => 'file', 'contents' => $handle, 'filename' => $call->filename, 'headers' => ['Content-Type' => $call->mimeType]];

        try {
            $response = $this->send(
                fn (): Response => $this->base(Http::withOptions([]), $call->apiKey, $call->timeout)
                    ->asMultipart()
                    ->post('/audio/transcriptions', $parts)
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $text = $response->json('text');
        if (! is_string($text)) {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'Transcription response had no text.');
        }

        $languages = [];
        foreach ((array) $response->json('languages', []) as $language) {
            if (is_array($language) && is_string($language['code'] ?? null)) {
                $languages[] = $language['code'];
            }
        }
        $usage = (array) $response->json('usage', []);

        return new ChatbotAudioTranscriptDTO(
            text: trim($text),
            languages: $languages,
            seconds: ($usage['type'] ?? null) === 'duration' && is_numeric($usage['seconds'] ?? null) ? (float) $usage['seconds'] : null,
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
        );
    }

    public function speak(ChatbotSpeechSynthesisRequestDTO $request): string
    {
        $payload = [
            'model' => $request->model,
            'input' => $request->input,
            'voice' => $request->voice,
            'response_format' => $request->format,
        ];
        if ($request->instructions !== '') {
            $payload['instructions'] = $request->instructions;
        }

        $response = $this->send(
            fn (): Response => $this->base(Http::withOptions([]), $request->apiKey, $request->timeout)
                ->accept('*/*')
                ->asJson()
                ->post('/audio/speech', $payload)
        );

        $body = $response->body();
        if ($body === '' || str_contains(strtolower($response->header('Content-Type')), 'json')) {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'The speech response carried no audio.');
        }

        return $body;
    }

    public function speakStream(ChatbotSpeechSynthesisRequestDTO $request): iterable
    {
        $payload = [
            'model' => $request->model,
            'input' => $request->input,
            'voice' => $request->voice,
            'response_format' => $request->format,
            'stream_format' => 'audio',
        ];
        if ($request->instructions !== '') {
            $payload['instructions'] = $request->instructions;
        }

        $response = $this->send(
            fn (): Response => $this->base(Http::withOptions(['stream' => true]), $request->apiKey, $request->timeout)
                ->accept('*/*')
                ->asJson()
                ->post('/audio/speech', $payload)
        );

        if (str_contains(strtolower($response->header('Content-Type')), 'json')) {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'The speech response carried no audio.');
        }

        $body = $response->toPsrResponse()->getBody();

        return (static function () use ($body): \Generator {
            while (! $body->eof()) {
                $chunk = $body->read(8192);
                if ($chunk !== '') {
                    yield $chunk;
                }
            }
            $body->close();
        })();
    }

    public function createRealtimeSecret(string $apiKey, array $session, int $expiresSeconds): ChatbotRealtimeSecretDTO
    {
        $response = $this->send(
            fn (): Response => $this->request($apiKey, 15)->post('/realtime/client_secrets', [
                'expires_after' => ['anchor' => 'created_at', 'seconds' => max(10, min(7200, $expiresSeconds))],
                'session' => $session,
            ])
        );

        $value = $response->json('value') ?? $response->json('client_secret.value');
        $expiresAt = $response->json('expires_at') ?? $response->json('client_secret.expires_at');
        if (! is_string($value) || $value === '') {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'The client secret response had no key.');
        }

        return new ChatbotRealtimeSecretDTO($value, is_numeric($expiresAt) ? (int) $expiresAt : now()->addSeconds($expiresSeconds)->getTimestamp());
    }

    private function request(string $apiKey, int $timeout): PendingRequest
    {
        return $this->configure(Http::withOptions([]), $apiKey, $timeout);
    }

    private function configure(PendingRequest $request, string $apiKey, int $timeout): PendingRequest
    {
        return $this->base($request, $apiKey, $timeout)->acceptJson()->asJson();
    }

    private function base(PendingRequest $request, string $apiKey, int $timeout): PendingRequest
    {
        return $request->baseUrl((string) config('chatbot.openai.base_url'))
            ->withToken($apiKey)
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

        throw $this->failure($response);
    }

    private function failure(Response $response): ChatbotProviderException
    {
        $status = $response->status();
        $code = (string) $response->json('error.code', '');
        $type = (string) $response->json('error.type', '');

        return match (true) {
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
