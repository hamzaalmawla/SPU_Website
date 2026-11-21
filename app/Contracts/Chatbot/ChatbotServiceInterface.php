<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\DTOs\Chatbot\ChatbotReplyDTO;
use App\DTOs\Chatbot\ChatbotWidgetDTO;

/**
 * Answers visitors' questions from the knowledge base (retrieval-augmented
 * generation) and describes the public widget.
 */
interface ChatbotServiceInterface
{
    /**
     * Launcher and panel settings for one locale. Cached; no query on a warm cache.
     */
    public function widget(string $locale): ChatbotWidgetDTO;

    /**
     * Answer one question. Never throws for provider or knowledge failures:
     * those come back as a non-ok status with a message for the visitor.
     */
    public function ask(ChatbotQuestionDTO $question): ChatbotReplyDTO;
}
