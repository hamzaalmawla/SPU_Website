<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotScanReaderInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\OpenAiClientInterface;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotScanBatchDTO;
use App\DTOs\Chatbot\ChatbotScanImageDTO;
use App\Exceptions\ChatbotProviderException;
use App\Support\Chatbot\DocumentTextExtractor;
use App\Support\Chatbot\TableRows;

/**
 * Reads scanned pages with an OpenAI vision model: each page image goes in
 * its own Responses call, `chatbot.ocr.concurrency` calls at a time, and the
 * model writes the page out as Markdown. Its tables are plain Markdown tables;
 * TableRows then rewrites each row as "column: value" pairs (the shape Excel
 * and CSV rows get), which costs nothing, where asking the model to repeat
 * the column names on every row would double what it writes, and output
 * tokens are most of what a scanned page costs.
 *
 * Images are sent with `store: false` and the page's text is the only thing
 * kept. The scan reader never decides what to do with the text; the
 * knowledge service stores and indexes it.
 */
final class ChatbotScanReader implements ChatbotScanReaderInterface
{
    private const INSTRUCTIONS = <<<'TEXT'
You transcribe one scanned page of a university document so it can be searched. Output only the page's content, as Markdown, in the page's own language: Arabic stays Arabic, English stays English. Never translate, summarise, explain, correct or add anything.

Copy every word, name, number, percentage, date, code and amount exactly as printed. Keep the reading order of the page.

- Headings and titles: "#" or "##" lines.
- Bulleted or numbered points: "- " lines, keeping their numbering or letters (أ، ب، 1، 2).
- Tables: a Markdown table, "| title | title |" for the column titles, then one "| value | value |" line per row, every row with the same number of cells. A cell merged over several rows applies to each of those rows: repeat its value in each. If the page shows no column titles for a table (it continues from an earlier page), start it with a title line of empty cells, "| | |".
- Leave out what is not content: the letterhead repeated on every page, logos, stamps, signatures, scanner watermarks (such as "CamScanner"), and page numbers.

If the page has no readable text, output nothing at all.
TEXT;

    public function __construct(
        private readonly OpenAiClientInterface $openAi,
        private readonly ChatbotSettingsServiceInterface $settings,
    ) {}

    public function read(string $path, string $extension, array $pages, string $title, string $apiKey, float $deadline): ChatbotScanBatchDTO
    {
        $model = $this->model();
        $concurrency = max(1, (int) config('chatbot.ocr.concurrency', 6));
        $batchSeconds = max(1, (int) config('chatbot.ocr.batch_seconds', 60));

        $texts = [];
        $failed = [];
        $inputTokens = 0;
        $outputTokens = 0;
        $queue = array_values(array_unique(array_map('intval', $pages)));
        $first = true;

        // At least one batch per call, then only while another fits.
        while ($queue !== [] && ($first || microtime(true) + $batchSeconds <= $deadline)) {
            $first = false;
            $batch = array_splice($queue, 0, $concurrency);
            $images = DocumentTextExtractor::pageImages($path, $extension, $batch);

            $requests = [];
            foreach ($batch as $page) {
                if (! isset($images[$page])) {
                    // Nothing a vision model can take (JBIG2, CCITT, ...):
                    // asking again would not help.
                    $texts[$page] = '';

                    continue;
                }
                $requests['p'.$page] = $this->request($images[$page], $page, $title, $model, $apiKey);
            }

            foreach ($this->openAi->respondMany($requests) as $key => $result) {
                $page = (int) substr((string) $key, 1);

                if ($result instanceof ChatbotProviderException) {
                    if (in_array($result->reason, [ChatbotProviderException::AUTH, ChatbotProviderException::QUOTA], true)) {
                        throw $result;
                    }
                    $failed[] = $page;

                    continue;
                }

                $texts[$page] = $this->tidy($result->text);
                $inputTokens += $result->inputTokens;
                $outputTokens += $result->outputTokens;
            }
        }

        ksort($texts);

        return new ChatbotScanBatchDTO($texts, $failed, $model, $inputTokens, $outputTokens);
    }

    /**
     * @param  list<ChatbotScanImageDTO>  $images
     */
    private function request(array $images, int $page, string $title, string $model, string $apiKey): ChatbotCompletionRequestDTO
    {
        $content = [[
            'type' => 'input_text',
            'text' => 'Page '.$page.' of "'.mb_substr($title, 0, 200).'". Transcribe it.',
        ]];
        foreach ($images as $image) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:'.$image->mime.';base64,'.base64_encode($image->bytes),
                'detail' => 'high',
            ];
        }

        return new ChatbotCompletionRequestDTO(
            apiKey: $apiKey,
            model: $model,
            instructions: self::INSTRUCTIONS,
            messages: [['role' => 'user', 'content' => $content]],
            maxOutputTokens: max(1000, (int) config('chatbot.ocr.max_output_tokens', 8000)),
            reasoningEffort: $this->reasoningEffort($model),
            timeout: max(10, (int) config('chatbot.ocr.timeout', 120)),
        );
    }

    /**
     * The model to read with: the configured OCR model, or the assistant's
     * own (vision-capable) model.
     */
    private function model(): string
    {
        $configured = trim((string) config('chatbot.ocr.model', ''));

        return $configured !== '' ? $configured : $this->settings->get()->model;
    }

    private function reasoningEffort(string $model): ?string
    {
        // Model ids contain dots (gpt-5.4-mini), so never address them with a config dot path.
        if (! (bool) (((array) config('chatbot.models', []))[$model]['reasoning'] ?? false)) {
            return null;
        }

        $efforts = (array) config('chatbot.reasoning_effort', []);

        return (string) ($efforts[$model] ?? $efforts['default'] ?? 'minimal');
    }

    /**
     * Drops a Markdown code fence the model may wrap the page in.
     */
    private function tidy(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^```[a-z]*\s*\n(.*)\n```$/su', $text, $matches) === 1) {
            $text = trim($matches[1]);
        }

        return TableRows::expandMarkdown(DocumentTextExtractor::clean($text));
    }
}
