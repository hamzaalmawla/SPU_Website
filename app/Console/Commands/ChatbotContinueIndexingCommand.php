<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use Illuminate\Console\Command;

/**
 * Carries on reading the scanned pages of AI assistant knowledge files.
 *
 * Uploading a long scan reads its first pages in the editor's request; the
 * rest is read here (every minute, by the scheduler) or while the admin list
 * is open, whichever comes first. With nothing pending this is one query.
 */
final class ChatbotContinueIndexingCommand extends Command
{
    protected $signature = 'chatbot:continue-indexing {--seconds= : How long to read for (default chatbot.ocr.command_seconds)}';

    protected $description = 'Read the remaining scanned pages of AI assistant knowledge files and index them when done.';

    public function __construct(private readonly ChatbotKnowledgeServiceInterface $knowledge)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->knowledge->hasPendingScans()) {
            return self::SUCCESS;
        }

        $seconds = (int) ($this->option('seconds') ?: config('chatbot.ocr.command_seconds', 50));
        $finished = $this->knowledge->continuePending($seconds);

        $this->line($finished > 0 ? $finished.' scanned document(s) finished and indexed.' : 'Scanned pages read; more remain.');

        return self::SUCCESS;
    }
}
