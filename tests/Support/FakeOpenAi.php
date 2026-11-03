<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Chatbot\KeywordScorer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stands in for OpenAI in tests.
 *
 * Embeddings are bag-of-words vectors (each folded term hashed into one of
 * the configured dimensions), so texts that share words are close and the
 * retrieval ranking is meaningful without a real model. The Responses
 * endpoint answers with a fixed text, or with whatever `answerWith` returns
 * for the request payload.
 */
final class FakeOpenAi
{
    /** @var list<array<string, mixed>> */
    public array $responseRequests = [];

    /** @var list<array<string, mixed>> */
    public array $embeddingRequests = [];

    /** @var (callable(array<string, mixed>): string)|null */
    public $answerWith = null;

    public int $responsesStatus = 200;

    /** @var array<string, mixed> */
    public array $responsesError = [];

    public int $embeddingsStatus = 200;

    public static function install(): self
    {
        $fake = new self;

        Http::fake(function (Request $request) use ($fake) {
            $payload = $request->data();

            if (str_ends_with($request->url(), '/embeddings')) {
                $fake->embeddingRequests[] = $payload;
                if ($fake->embeddingsStatus !== 200) {
                    return Http::response(['error' => ['message' => 'nope', 'code' => 'invalid_api_key']], $fake->embeddingsStatus);
                }

                $inputs = (array) ($payload['input'] ?? []);
                $dimensions = (int) ($payload['dimensions'] ?? 512);

                return Http::response([
                    'object' => 'list',
                    'data' => array_map(
                        static fn (string $input, int $index): array => ['object' => 'embedding', 'index' => $index, 'embedding' => self::vector($input, $dimensions)],
                        array_values($inputs),
                        array_keys(array_values($inputs)),
                    ),
                    'model' => $payload['model'] ?? 'text-embedding-3-small',
                    'usage' => ['prompt_tokens' => 10 * count($inputs), 'total_tokens' => 10 * count($inputs)],
                ]);
            }

            if (str_ends_with($request->url(), '/responses')) {
                $fake->responseRequests[] = $payload;
                if ($fake->responsesStatus !== 200) {
                    return Http::response($fake->responsesError ?: ['error' => ['message' => 'failure']], $fake->responsesStatus);
                }

                $text = $fake->answerWith !== null ? ($fake->answerWith)($payload) : 'The admission office is open **Sunday to Thursday**.';

                return Http::response([
                    'id' => 'resp_test',
                    'object' => 'response',
                    'status' => 'completed',
                    'output' => [
                        ['type' => 'reasoning', 'id' => 'rs_1', 'summary' => []],
                        ['type' => 'message', 'id' => 'msg_1', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]],
                    ],
                    'usage' => ['input_tokens' => 1200, 'input_tokens_details' => ['cached_tokens' => 200], 'output_tokens' => 150, 'total_tokens' => 1350],
                ]);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 404);
        });

        return $fake;
    }

    /**
     * @return list<float>
     */
    public static function vector(string $text, int $dimensions = 512): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        foreach (KeywordScorer::terms($text) as $term) {
            $vector[crc32($term) % $dimensions] += 1.0;
        }
        // Never all zeros: every text shares a small common component.
        $vector[0] += 0.05;

        return $vector;
    }

    /**
     * The visitor turn (sources and question) of the last Responses call.
     */
    public function lastQuestionTurn(): string
    {
        $last = end($this->responseRequests);
        $input = is_array($last) ? (array) ($last['input'] ?? []) : [];
        $turn = end($input);

        return is_array($turn) ? (string) ($turn['content'] ?? '') : '';
    }
}
