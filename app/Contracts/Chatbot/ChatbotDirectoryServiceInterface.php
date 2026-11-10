<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

/**
 * The university at a glance, for the assistant's instructions: every
 * published faculty and center, by name in both languages, with its page.
 *
 * Retrieval hands the model the few passages closest to a question, so a
 * question about all of something ("what faculties are there?") finds a few
 * faculty pages and the answer lists only those. With the complete lists in
 * the instructions, such questions are answered from the whole set.
 */
interface ChatbotDirectoryServiceInterface
{
    /**
     * The directory as prompt text for one page language, or '' when nothing
     * can be read (the answer then goes ahead without it). Cached.
     */
    public function forPrompt(string $locale): string;
}
