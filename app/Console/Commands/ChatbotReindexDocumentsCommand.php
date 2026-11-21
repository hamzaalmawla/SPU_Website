<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-indexes the knowledge files in use, for when indexing itself changed
 * (contextual retrieval adds a context to every passage). Scanned pages,
 * document cards and passage contexts already written are reused, so only
 * what is new is paid for. Without --write it only lists what it would do.
 */
final class ChatbotReindexDocumentsCommand extends Command
{
    protected $signature = 'chatbot:reindex-documents
        {--source=* : upload, text or website (default: upload and text)}
        {--user= : Id of the admin user the change is recorded as}
        {--write : Re-index (without it nothing changes)}';

    protected $description = 'Re-index the AI assistant\'s files in use, reusing what was already read and written.';

    public function __construct(
        private readonly ChatbotKnowledgeServiceInterface $knowledge,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $user = (string) $this->option('user');
        if (! ctype_digit($user) || (int) $user < 1) {
            $this->error('Pass the admin user id with --user=<id>.');

            return self::INVALID;
        }

        $sources = array_values(array_filter((array) $this->option('source'), 'is_string')) ?: ['upload', 'text'];
        $documents = $this->knowledge->documents()
            ->filter(static fn ($document): bool => $document->isActive && in_array($document->sourceType, $sources, true) && $document->status === 'ready')
            ->sortBy('id')
            ->values();

        if (! (bool) $this->option('write')) {
            foreach ($documents as $document) {
                $this->line(sprintf('Would re-index: #%d %s (%d passages)', $document->id, $document->title, $document->chunkCount));
            }
            $this->line('Dry run: nothing was changed. Re-run with --write.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($documents as $document) {
            $started = microtime(true);
            try {
                $result = $this->knowledge->reindex($document->id, (int) $user);
                $this->line(sprintf('RE-INDEXED: #%d %s — %s, %d passages, %.1f s%s', $result->id, $result->title, $result->status, $result->chunkCount, microtime(true) - $started, $result->error !== null ? ' ('.$result->error.')' : ''));
                if ($result->status === 'failed') {
                    $failed++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error('FAILED: #'.$document->id.' '.$document->title.' — '.$exception->getMessage());
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
