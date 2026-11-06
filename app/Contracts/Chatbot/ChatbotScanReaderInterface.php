<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotScanBatchDTO;
use App\Exceptions\ChatbotProviderException;

/**
 * Reads the text of scanned pages (page images in a PDF, or a photo) with an
 * OpenAI vision model, several pages at a time.
 */
interface ChatbotScanReaderInterface
{
    /**
     * Read as many of `$pages` as fit before `$deadline` (a microtime(true)
     * value). Pages not reached or not read this time are left out of the
     * result's texts, so a later call can carry on.
     *
     * @param  list<int>  $pages  1-based page numbers
     *
     * @throws ChatbotProviderException when the key is rejected or the account has no credit
     */
    public function read(string $path, string $extension, array $pages, string $title, string $apiKey, float $deadline): ChatbotScanBatchDTO;
}
