<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotWebPageDTO;
use Illuminate\Support\Collection;

/**
 * Reads the website's own published pages for the assistant's knowledge.
 */
interface ChatbotWebsiteCrawlerInterface
{
    /**
     * Published pages in both languages, within config chatbot.website.
     * Empty when the site cannot be reached.
     *
     * @return Collection<int, ChatbotWebPageDTO>
     */
    public function pages(): Collection;
}
