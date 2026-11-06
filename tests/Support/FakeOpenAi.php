<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Chatbot\KeywordScorer;
use App\Support\Chatbot\SearchRewrite;
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

    /** @var list<array<string, mixed>> search rewrites (SearchRewrite), kept apart from answers */
    public array $rewriteRequests = [];

    /** @var (callable(string): string)|null the rewrite for a question; default: none useful */
    public $rewriteWith = null;

    public int $rewriteStatus = 200;

    /** @var (callable(array<string, mixed>): string)|null */
    public $answerWith = null;

    public int $responsesStatus = 200;

    /** @var array<string, mixed> */
    public array $responsesError = [];

    public int $embeddingsStatus = 200;

    /** The category the model claims (structured output). */
    public string $category = 'university';

    /** Raw model output, bypassing the JSON wrapper (to test malformed output). */
    public ?string $rawOutput = null;

    public bool $flagged = false;

    /** @var array<string, string> website path => HTML served for it */
    public array $pages = [];

    /** @var list<string> website paths requested */
    public array $pageRequests = [];

    /** @var array<string, int> model => HTTP status it fails with */
    public array $failModels = [];

    public int $moderationStatus = 200;

    /** @var list<array<string, mixed>> */
    public array $moderationRequests = [];

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

            if (str_ends_with($request->url(), '/moderations')) {
                $fake->moderationRequests[] = $payload;
                if ($fake->moderationStatus !== 200) {
                    return Http::response(['error' => ['message' => 'down']], $fake->moderationStatus);
                }

                return Http::response(['id' => 'modr-1', 'model' => 'omni-moderation-latest', 'results' => [['flagged' => $fake->flagged, 'categories' => []]]]);
            }

            if (str_ends_with($request->url(), '/responses') && ($payload['instructions'] ?? null) === SearchRewrite::INSTRUCTIONS) {
                $fake->rewriteRequests[] = $payload;
                if ($fake->rewriteStatus !== 200) {
                    return Http::response(['error' => ['message' => 'failure']], $fake->rewriteStatus);
                }
                $question = (string) ($payload['input'][0]['content'] ?? '');
                $text = $fake->rewriteWith !== null ? ($fake->rewriteWith)($question) : $question."\n".$question;

                return Http::response([
                    'id' => 'resp_rewrite',
                    'object' => 'response',
                    'status' => 'completed',
                    'output' => [['type' => 'message', 'id' => 'msg_r', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]]],
                    'usage' => ['input_tokens' => 300, 'input_tokens_details' => ['cached_tokens' => 0], 'output_tokens' => 40, 'total_tokens' => 340],
                ]);
            }

            if (str_ends_with($request->url(), '/responses')) {
                $fake->responseRequests[] = $payload;
                if (isset($fake->failModels[$payload['model'] ?? ''])) {
                    return Http::response(['error' => ['message' => 'over quota', 'code' => 'rate_limit_exceeded']], $fake->failModels[$payload['model']]);
                }
                if ($fake->responsesStatus !== 200) {
                    return Http::response($fake->responsesError ?: ['error' => ['message' => 'failure']], $fake->responsesStatus);
                }

                $answer = $fake->answerWith !== null ? ($fake->answerWith)($payload) : 'The admission office is open **Sunday to Thursday**.';
                $structured = ($payload['text']['format']['type'] ?? null) === 'json_schema';
                $text = $fake->rawOutput ?? ($structured
                    ? (string) json_encode(['category' => $fake->category, 'answer' => in_array($fake->category, ['university', 'greeting'], true) ? $answer : ''], JSON_UNESCAPED_UNICODE)
                    : $answer);

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

            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            if (! str_contains($request->url(), 'api.openai.com')) {
                $fake->pageRequests[] = $path;

                return isset($fake->pages[$path])
                    ? Http::response($fake->pages[$path], 200, ['Content-Type' => 'text/html; charset=UTF-8'])
                    : Http::response('Not found', 404, ['Content-Type' => 'text/html']);
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
