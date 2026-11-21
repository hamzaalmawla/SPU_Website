<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use Illuminate\Console\Command;

/**
 * Brings the website's published pages into the AI assistant's knowledge.
 * Run by every deploy; only changed pages are embedded again, so a sync with
 * nothing new costs nothing. Never fails a deploy: an unreachable site or
 * OpenAI is reported and the existing knowledge is kept.
 */
final class ChatbotSyncWebsiteCommand extends Command
{
    protected $signature = 'chatbot:sync-website';

    protected $description = 'Index the website\'s published pages (both languages) as knowledge for the AI assistant.';

    public function __construct(
        private readonly ChatbotKnowledgeServiceInterface $knowledge,
        private readonly ChatbotSettingsServiceInterface $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) config('chatbot.website.enabled', true)) {
            $this->line('Website sync is switched off (CHATBOT_WEBSITE_SYNC=false).');

            return self::SUCCESS;
        }

        if ($this->settings->apiKey() === null) {
            $this->line('No OpenAI key is saved yet; the website was not indexed.');

            return self::SUCCESS;
        }

        $result = $this->knowledge->syncWebsite(0);

        if (! $result->reachable) {
            $this->warn('No page of the website could be read; the existing knowledge was kept.');

            return self::SUCCESS;
        }

        $this->table(
            ['Pages read', 'Added', 'Updated', 'Unchanged', 'Removed', 'Failed'],
            [[$result->pages, $result->added, $result->updated, $result->unchanged, $result->removed, $result->failed]],
        );

        if ($result->failed > 0) {
            $this->warn($result->failed.' page(s) could not be indexed; see Administration › AI assistant for the reason.');
        }

        return self::SUCCESS;
    }
}
