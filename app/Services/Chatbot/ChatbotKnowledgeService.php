<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Contracts\Chatbot\OpenAiClientInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Chatbot\ChatbotDocumentDTO;
use App\DTOs\Chatbot\ChatbotKnowledgeStatsDTO;
use App\DTOs\Chatbot\ChatbotPassageDTO;
use App\DTOs\Chatbot\ChatbotRetrievalDTO;
use App\Exceptions\ChatbotProviderException;
use App\Models\Chatbot\ChatbotChunk;
use App\Models\Chatbot\ChatbotDocument;
use App\Support\Chatbot\DocumentTextExtractor;
use App\Support\Chatbot\KeywordScorer;
use App\Support\Chatbot\TextChunker;
use App\Support\Chatbot\VectorMath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Indexes knowledge files into embedded passages and retrieves them.
 *
 * Indexing runs inside the editor's request: there is no queue worker on this
 * host (QUEUE_CONNECTION=sync), and a 12 MB file is a few embedding calls.
 *
 * Retrieval is a brute-force cosine scan over the active passages followed by
 * a keyword re-rank of the best candidates. That is exact (no approximate
 * index to tune or rebuild) and fast for thousands of passages.
 */
final class ChatbotKnowledgeService implements ChatbotKnowledgeServiceInterface
{
    public function __construct(
        private readonly OpenAiClientInterface $openAi,
        private readonly ChatbotSettingsServiceInterface $settings,
        private readonly ChatbotUsageServiceInterface $usage,
        private readonly AuditServiceInterface $audit,
        private readonly CacheServiceInterface $cache,
    ) {}

    public function documents(): Collection
    {
        return ChatbotDocument::query()
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ChatbotDocument $document): ChatbotDocumentDTO => $this->toDto($document))
            ->values();
    }

    public function stats(): ChatbotKnowledgeStatsDTO
    {
        $documents = ChatbotDocument::query()->get(['id', 'status', 'is_active', 'chunk_count', 'character_count', 'embedding_model', 'embedding_dimensions']);

        return new ChatbotKnowledgeStatsDTO(
            documents: $documents->count(),
            searchableDocuments: $documents->filter(fn (ChatbotDocument $document): bool => $document->status === ChatbotDocument::STATUS_READY && $document->is_active && ! $this->isStale($document))->count(),
            failedDocuments: $documents->where('status', ChatbotDocument::STATUS_FAILED)->count(),
            staleDocuments: $documents->filter(fn (ChatbotDocument $document): bool => $document->status === ChatbotDocument::STATUS_READY && $this->isStale($document))->count(),
            passages: (int) $documents->where('status', ChatbotDocument::STATUS_READY)->sum('chunk_count'),
            characters: (int) $documents->where('status', ChatbotDocument::STATUS_READY)->sum('character_count'),
        );
    }

    public function addUpload(string $storedPath, string $originalName, ?string $title, int $actorId): ChatbotDocumentDTO
    {
        $disk = $this->disk();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! $disk->exists($storedPath)) {
            throw new InvalidArgumentException('admin_chatbot.errors.unreadable');
        }

        if (! in_array($extension, (array) config('chatbot.knowledge.extensions', []), true)) {
            $disk->delete($storedPath);

            throw new InvalidArgumentException('admin_chatbot.errors.unsupported_type');
        }

        $this->assertCapacity();

        $checksum = (string) hash_file('sha256', $disk->path($storedPath));
        if (ChatbotDocument::query()->where('checksum', $checksum)->exists()) {
            $disk->delete($storedPath);

            throw new InvalidArgumentException('admin_chatbot.errors.duplicate');
        }

        $finalPath = $this->documentPath($extension);
        if ($storedPath !== $finalPath) {
            $disk->move($storedPath, $finalPath);
        }

        $document = ChatbotDocument::query()->create([
            'title' => $this->title($title, $originalName),
            'source_type' => 'upload',
            'original_name' => mb_substr(basename($originalName), 0, 255),
            'storage_path' => $finalPath,
            'extension' => $extension,
            'size_bytes' => (int) $disk->size($finalPath),
            'checksum' => $checksum,
            'status' => ChatbotDocument::STATUS_PENDING,
            'uploaded_by' => $actorId,
        ]);

        $this->audit->log('chatbot.document.uploaded', $actorId, 'chatbot_document', (int) $document->id, [
            'title' => $document->title,
            'extension' => $extension,
            'size_bytes' => $document->size_bytes,
        ]);

        return $this->index($document);
    }

    public function addText(string $title, string $text, int $actorId): ChatbotDocumentDTO
    {
        $text = DocumentTextExtractor::clean($text);
        if (mb_strlen($text) < 20) {
            throw new InvalidArgumentException('admin_chatbot.errors.text_too_short');
        }

        $this->assertCapacity();

        $checksum = hash('sha256', $text);
        if (ChatbotDocument::query()->where('checksum', $checksum)->exists()) {
            throw new InvalidArgumentException('admin_chatbot.errors.duplicate');
        }

        $path = $this->documentPath('md');
        $this->disk()->put($path, $text);

        $document = ChatbotDocument::query()->create([
            'title' => $this->title($title, 'text'),
            'source_type' => 'text',
            'storage_path' => $path,
            'extension' => 'md',
            'size_bytes' => strlen($text),
            'checksum' => $checksum,
            'status' => ChatbotDocument::STATUS_PENDING,
            'uploaded_by' => $actorId,
        ]);

        $this->audit->log('chatbot.document.text_added', $actorId, 'chatbot_document', (int) $document->id, ['title' => $document->title]);

        return $this->index($document);
    }

    public function reindex(int $documentId, int $actorId): ChatbotDocumentDTO
    {
        $document = ChatbotDocument::query()->findOrFail($documentId);
        $this->audit->log('chatbot.document.reindexed', $actorId, 'chatbot_document', $documentId, ['title' => $document->title]);

        return $this->index($document);
    }

    public function reindexStale(int $actorId): int
    {
        @set_time_limit(600);

        $rebuilt = 0;
        foreach (ChatbotDocument::query()->orderBy('id')->get() as $document) {
            if ($document->status === ChatbotDocument::STATUS_READY && ! $this->isStale($document)) {
                continue;
            }

            $this->index($document);
            $rebuilt++;
        }

        $this->audit->log('chatbot.knowledge.reindexed', $actorId, 'chatbot_document', null, ['documents' => $rebuilt]);

        return $rebuilt;
    }

    public function setActive(int $documentId, bool $active, int $actorId): bool
    {
        $before = $this->hasSearchable();
        $document = ChatbotDocument::query()->findOrFail($documentId);
        $document->update(['is_active' => $active]);

        $this->audit->log($active ? 'chatbot.document.enabled' : 'chatbot.document.disabled', $actorId, 'chatbot_document', $documentId, ['title' => $document->title]);
        $this->knowledgeChanged($before);

        return true;
    }

    public function rename(int $documentId, string $title, int $actorId): bool
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('admin_chatbot.errors.title_required');
        }

        $document = ChatbotDocument::query()->findOrFail($documentId);
        $previous = $document->title;
        $document->update(['title' => mb_substr($title, 0, 255)]);

        $this->audit->log('chatbot.document.renamed', $actorId, 'chatbot_document', $documentId, ['from' => $previous, 'to' => $document->title]);
        // Titles are shown as sources and given to the model.
        $this->knowledgeChanged(null);

        return true;
    }

    public function delete(int $documentId, int $actorId): bool
    {
        $before = $this->hasSearchable();
        $document = ChatbotDocument::query()->findOrFail($documentId);
        $path = $document->storage_path;
        $title = $document->title;

        DB::transaction(function () use ($document): void {
            $document->chunks()->delete();
            $document->delete();
        });

        if (is_string($path) && $path !== '') {
            $this->disk()->delete($path);
        }

        $this->audit->log('chatbot.document.deleted', $actorId, 'chatbot_document', $documentId, ['title' => $title]);
        $this->knowledgeChanged($before);

        return true;
    }

    public function preview(int $documentId, int $characters = 3000): string
    {
        $text = '';
        $chunks = ChatbotChunk::query()->where('chatbot_document_id', $documentId)->orderBy('position')->limit(40)->get(['content', 'page']);

        foreach ($chunks as $chunk) {
            $piece = ($chunk->page !== null ? '— '.__('admin_chatbot.preview_page', ['page' => $chunk->page])." —\n" : '').$chunk->content;
            $text .= ($text === '' ? '' : "\n\n").$piece;
            if (mb_strlen($text) >= $characters) {
                return mb_substr($text, 0, $characters).'…';
            }
        }

        return $text;
    }

    public function retrieve(string $query, string $apiKey): ChatbotRetrievalDTO
    {
        $query = trim($query);
        if ($query === '' || ! $this->hasSearchable()) {
            return new ChatbotRetrievalDTO([], 0);
        }

        [$queryVector, $tokens] = $this->queryVector($query, $apiKey);
        if ($queryVector === []) {
            return new ChatbotRetrievalDTO([], $tokens);
        }

        $candidateCount = max(1, (int) config('chatbot.retrieval.candidates', 40));
        $scores = [];

        ChatbotChunk::query()
            ->select(['chatbot_chunks.id', 'chatbot_chunks.embedding'])
            ->join('chatbot_documents', 'chatbot_documents.id', '=', 'chatbot_chunks.chatbot_document_id')
            ->where('chatbot_documents.status', ChatbotDocument::STATUS_READY)
            ->where('chatbot_documents.is_active', true)
            ->where('chatbot_documents.embedding_model', (string) config('chatbot.embedding.model'))
            ->where('chatbot_documents.embedding_dimensions', (int) config('chatbot.embedding.dimensions'))
            ->orderBy('chatbot_chunks.id')
            ->chunk(500, function (Collection $rows) use (&$scores, $queryVector, $candidateCount): void {
                foreach ($rows as $row) {
                    $vector = VectorMath::unpack((string) base64_decode((string) $row->embedding, true));
                    if ($vector !== []) {
                        $scores[(int) $row->id] = VectorMath::dot($queryVector, $vector);
                    }
                }

                // Keep memory flat on a large knowledge base.
                if (count($scores) > $candidateCount * 4) {
                    arsort($scores);
                    $scores = array_slice($scores, 0, $candidateCount, true);
                }
            });

        arsort($scores);
        $scores = array_slice($scores, 0, $candidateCount, true);
        if ($scores === []) {
            return new ChatbotRetrievalDTO([], $tokens);
        }

        $minimum = (float) config('chatbot.retrieval.min_similarity', 0.22);
        $keywordWeight = (float) config('chatbot.retrieval.keyword_weight', 0.18);
        $terms = KeywordScorer::terms($query);

        $chunks = ChatbotChunk::query()
            ->with('document:id,title')
            ->whereIn('id', array_keys($scores))
            ->get(['id', 'chatbot_document_id', 'page', 'heading', 'content', 'content_normalized']);

        $ranked = [];
        foreach ($chunks as $chunk) {
            $similarity = $scores[(int) $chunk->id] ?? 0.0;
            $keywords = KeywordScorer::score($terms, (string) $chunk->content_normalized);

            // A passage that names the exact thing asked about may score a
            // little below the cut on meaning alone.
            if ($similarity < $minimum && ! ($keywords >= 0.5 && $similarity >= $minimum - 0.06)) {
                continue;
            }

            $ranked[] = [
                'score' => $similarity + $keywordWeight * $keywords,
                'passage' => new ChatbotPassageDTO(
                    chunkId: (int) $chunk->id,
                    documentId: (int) $chunk->chatbot_document_id,
                    documentTitle: (string) ($chunk->document?->title ?? ''),
                    page: $chunk->page,
                    heading: $chunk->heading,
                    content: (string) $chunk->content,
                    score: round(max(0.0, min(1.0, $similarity)), 4),
                ),
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return new ChatbotRetrievalDTO(
            array_values(array_map(
                static fn (array $item): ChatbotPassageDTO => $item['passage'],
                array_slice($ranked, 0, max(1, (int) config('chatbot.retrieval.top_k', 6))),
            )),
            $tokens,
        );
    }

    public function version(): string
    {
        $version = $this->cache->remember(ChatbotCaches::KNOWLEDGE_VERSION, static fn (): string => Str::random(12), 31_536_000);

        return is_string($version) ? $version : 'none';
    }

    private function index(ChatbotDocument $document): ChatbotDocumentDTO
    {
        @set_time_limit(300);
        $before = $this->hasSearchable();

        try {
            $apiKey = $this->settings->apiKey();
            if ($apiKey === null) {
                throw new InvalidArgumentException('admin_chatbot.errors.no_api_key');
            }

            $path = (string) $document->storage_path;
            if ($path === '' || ! $this->disk()->exists($path)) {
                throw new InvalidArgumentException('admin_chatbot.errors.source_missing');
            }

            $sections = DocumentTextExtractor::extract($this->disk()->path($path), (string) $document->extension);
            $chunks = TextChunker::chunk(
                $sections,
                (int) config('chatbot.knowledge.chunk_characters', 1100),
                (int) config('chatbot.knowledge.chunk_overlap', 160),
            );

            if ($chunks === []) {
                throw new InvalidArgumentException('admin_chatbot.errors.no_text');
            }

            $maxChunks = (int) config('chatbot.knowledge.max_chunks_per_document', 1500);
            $truncated = count($chunks) > $maxChunks;
            $chunks = array_slice($chunks, 0, $maxChunks);

            [$vectors, $tokens] = $this->embedChunks((string) $document->title, $chunks, $apiKey);

            $now = now();
            $rows = [];
            foreach ($chunks as $position => $chunk) {
                $rows[] = [
                    'chatbot_document_id' => $document->id,
                    'position' => $position,
                    'page' => $chunk['page'],
                    'heading' => $chunk['heading'] !== null ? mb_substr($chunk['heading'], 0, 255) : null,
                    'content' => $chunk['content'],
                    'content_normalized' => KeywordScorer::normalizePassage(($chunk['heading'] ?? '').' '.$chunk['content']),
                    'embedding' => base64_encode(VectorMath::pack($vectors[$position])),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use ($document, $rows, $chunks, $tokens, $truncated, $now): void {
                $document->chunks()->delete();
                foreach (array_chunk($rows, 100) as $batch) {
                    ChatbotChunk::query()->insert($batch);
                }

                $document->update([
                    'status' => ChatbotDocument::STATUS_READY,
                    'error' => $truncated ? 'admin_chatbot.errors.truncated' : null,
                    'chunk_count' => count($rows),
                    'character_count' => array_sum(array_map(static fn (array $chunk): int => mb_strlen($chunk['content']), $chunks)),
                    'embedding_tokens' => $tokens,
                    'embedding_model' => (string) config('chatbot.embedding.model'),
                    'embedding_dimensions' => (int) config('chatbot.embedding.dimensions'),
                    'indexed_at' => $now,
                ]);
            });

            $this->usage->recordIndexing($tokens);
        } catch (InvalidArgumentException $exception) {
            $this->markFailed($document, str_starts_with($exception->getMessage(), 'admin_chatbot.') ? $exception->getMessage() : 'admin_chatbot.errors.index_failed');
        } catch (ChatbotProviderException $exception) {
            $this->markFailed($document, 'admin_chatbot.errors.provider_'.$exception->reason);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($document, 'admin_chatbot.errors.index_failed');
        }

        $this->knowledgeChanged($before);

        return $this->toDto($document->fresh(['uploader:id,name']) ?? $document);
    }

    /**
     * @param  list<array{page: ?int, heading: ?string, content: string}>  $chunks
     * @return array{0: list<list<float>>, 1: int}
     */
    private function embedChunks(string $title, array $chunks, string $apiKey): array
    {
        $vectors = [];
        $tokens = 0;
        $batchSize = max(1, (int) config('chatbot.embedding.batch_size', 96));

        foreach (array_chunk($chunks, $batchSize) as $batch) {
            // The title and heading travel with each passage's text so a
            // passage that says "the fee is 500" still matches "tuition of
            // the pharmacy faculty" when that is what its document is about.
            $inputs = array_map(
                static fn (array $chunk): string => trim($title."\n".($chunk['heading'] !== null && $chunk['heading'] !== $title ? $chunk['heading']."\n" : '').$chunk['content']),
                $batch,
            );

            $result = $this->openAi->embed($inputs, $apiKey);
            foreach ($result->vectors as $vector) {
                $vectors[] = $vector;
            }
            $tokens += $result->tokens;
        }

        if (count($vectors) !== count($chunks)) {
            throw new ChatbotProviderException(ChatbotProviderException::INVALID, 'Embedding count mismatch.');
        }

        return [$vectors, $tokens];
    }

    /**
     * @return array{0: list<float>, 1: int}
     */
    private function queryVector(string $query, string $apiKey): array
    {
        $key = 'chatbot.query-vector.'.sha1(config('chatbot.embedding.model').'|'.config('chatbot.embedding.dimensions').'|'.mb_strtolower($query));
        $tokens = 0;
        $packed = $this->cache->remember($key, function () use ($query, $apiKey, &$tokens): string {
            $result = $this->openAi->embed([$query], $apiKey);
            $tokens = $result->tokens;

            return base64_encode(VectorMath::pack($result->vectors[0] ?? []));
        }, 604_800);

        return [VectorMath::unpack((string) base64_decode((string) $packed, true)), $tokens];
    }

    private function markFailed(ChatbotDocument $document, string $errorKey): void
    {
        DB::transaction(function () use ($document, $errorKey): void {
            $document->chunks()->delete();
            $document->update([
                'status' => ChatbotDocument::STATUS_FAILED,
                'error' => mb_substr($errorKey, 0, 500),
                'chunk_count' => 0,
                'character_count' => 0,
                'indexed_at' => null,
            ]);
        });
    }

    /**
     * Retire cached answers, and refresh the widget when the knowledge base
     * went from empty to usable or back.
     */
    private function knowledgeChanged(?bool $hadSearchable): void
    {
        $this->cache->forget(ChatbotCaches::KNOWLEDGE_VERSION);
        ChatbotCaches::widgetChanged($this->cache, $hadSearchable !== null && $hadSearchable !== $this->hasSearchable());
    }

    private function hasSearchable(): bool
    {
        return ChatbotDocument::query()
            ->searchable()
            ->where('embedding_model', (string) config('chatbot.embedding.model'))
            ->where('embedding_dimensions', (int) config('chatbot.embedding.dimensions'))
            ->exists();
    }

    private function isStale(ChatbotDocument $document): bool
    {
        return $document->status === ChatbotDocument::STATUS_PENDING
            || ($document->status === ChatbotDocument::STATUS_READY
                && ($document->embedding_model !== (string) config('chatbot.embedding.model')
                    || $document->embedding_dimensions !== (int) config('chatbot.embedding.dimensions')));
    }

    private function assertCapacity(): void
    {
        if (ChatbotDocument::query()->count() >= (int) config('chatbot.knowledge.max_documents', 500)) {
            throw new InvalidArgumentException('admin_chatbot.errors.too_many_documents');
        }
    }

    private function title(?string $title, string $originalName): string
    {
        $title = trim((string) $title);
        if ($title === '') {
            $title = trim((string) preg_replace('/[_\-]+/u', ' ', pathinfo($originalName, PATHINFO_FILENAME)));
        }

        return mb_substr($title !== '' ? $title : 'Document', 0, 255);
    }

    private function documentPath(string $extension): string
    {
        return trim((string) config('chatbot.knowledge.directory', 'chatbot/documents'), '/').'/'.Str::uuid()->toString().'.'.$extension;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('chatbot.knowledge.disk', 'local'));
    }

    private function toDto(ChatbotDocument $document): ChatbotDocumentDTO
    {
        $error = $document->error;

        return new ChatbotDocumentDTO(
            id: (int) $document->id,
            title: (string) $document->title,
            sourceType: (string) $document->source_type,
            originalName: $document->original_name,
            extension: $document->extension,
            sizeBytes: (int) $document->size_bytes,
            status: (string) $document->status,
            error: is_string($error) && $error !== '' ? (string) __($error) : null,
            isActive: (bool) $document->is_active,
            chunkCount: (int) $document->chunk_count,
            characterCount: (int) $document->character_count,
            needsReindex: $this->isStale($document),
            uploadedBy: $document->uploader?->name,
            indexedAt: $document->indexed_at?->toIso8601String(),
            createdAt: (string) $document->created_at?->toIso8601String(),
        );
    }
}
