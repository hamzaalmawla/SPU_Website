<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotDocumentDTO;
use App\DTOs\Chatbot\ChatbotKnowledgeStatsDTO;
use App\DTOs\Chatbot\ChatbotRetrievalDTO;
use App\DTOs\Chatbot\ChatbotWebsiteSyncDTO;
use Illuminate\Support\Collection;

/**
 * The assistant's knowledge base: uploaded files and typed entries, split into
 * passages and embedded, plus retrieval over them.
 *
 * Every write is audited and changes the knowledge version, which retires
 * cached answers.
 */
interface ChatbotKnowledgeServiceInterface
{
    /**
     * @return Collection<int, ChatbotDocumentDTO> newest first
     */
    public function documents(): Collection;

    public function stats(): ChatbotKnowledgeStatsDTO;

    /**
     * Index a file already stored on the knowledge disk. The returned document
     * is "ready" or "failed" (with a reason); a failed file stays listed so the
     * editor sees why.
     *
     * @throws \InvalidArgumentException for an unsupported or duplicate file
     */
    public function addUpload(string $storedPath, string $originalName, ?string $title, int $actorId): ChatbotDocumentDTO;

    /**
     * Index text typed in the admin (an FAQ, a correction, a short notice).
     *
     * @throws \InvalidArgumentException for empty or duplicate text
     */
    public function addText(string $title, string $text, int $actorId): ChatbotDocumentDTO;

    /**
     * Rebuild one document's passages and embeddings from its stored source.
     */
    public function reindex(int $documentId, int $actorId): ChatbotDocumentDTO;

    /**
     * Rebuild every document that needs it (failed, or embedded with another
     * model or size). Returns how many were rebuilt.
     */
    public function reindexStale(int $actorId): int;

    public function setActive(int $documentId, bool $active, int $actorId): bool;

    public function rename(int $documentId, string $title, int $actorId): bool;

    public function delete(int $documentId, int $actorId): bool;

    /**
     * The first characters of a document's extracted text, so an editor can
     * check what the assistant actually reads.
     */
    public function preview(int $documentId, int $characters = 3000): string;

    /**
     * Passages most relevant to a question, best first. Empty when the
     * knowledge base is empty or nothing is relevant enough.
     */
    public function retrieve(string $query, string $apiKey, ?string $locale = null): ChatbotRetrievalDTO;

    /**
     * Bring the website's published pages into the knowledge base: new pages
     * are indexed, changed ones re-indexed, unchanged ones left alone, and
     * pages that disappeared removed (only after a healthy crawl). A page an
     * editor switched off stays off.
     */
    public function syncWebsite(int $actorId): ChatbotWebsiteSyncDTO;

    /**
     * Changes whenever the searchable passages change.
     */
    public function version(): string;
}
