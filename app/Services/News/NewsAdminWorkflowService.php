<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class NewsAdminWorkflowService implements NewsAdminWorkflowServiceInterface
{
    /** @var array<int, string> */
    private const PUBLIC_STATUSES = ['published', 'scheduled'];

    /** @var array<int, string> */
    private const VALID_STATUSES = ['draft', 'published', 'scheduled', 'archived'];

    private const ARTICLE_SLUG_MAX_LENGTH = 80;

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly SlugServiceInterface $slugService,
    ) {}

    public function prepareArticleDataForCreate(array $data, ?int $userId): array
    {
        $user = $this->user($userId);
        $status = $this->normalizedStatus($data['status'] ?? null, 'draft');

        if (! $this->canPublish($user) && in_array($status, self::PUBLIC_STATUSES, true)) {
            $status = 'draft';
        }

        $data['status'] = $status;
        $data['slug'] = $this->articleSlugForCreate($data);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        if ($user instanceof User && $user->role_slug === 'faculty_editor') {
            $data['faculty_scope_slug'] = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : null;
        }

        return $this->normalizePublicationDates($data);
    }

    public function prepareArticleDataForUpdate(int $articleId, array $data, ?int $userId): array
    {
        $article = $this->article($articleId);
        $user = $this->user($userId);
        $currentStatus = $this->normalizedStatus($article->getAttribute('status'), 'draft');
        $requestedStatus = $this->normalizedStatus($data['status'] ?? null, $currentStatus);

        if (! $this->canPublish($user)) {
            $isPublishing = in_array($requestedStatus, self::PUBLIC_STATUSES, true);
            $isUnpublishingPublicArticle = in_array($currentStatus, self::PUBLIC_STATUSES, true) && $requestedStatus !== $currentStatus;

            if ($isPublishing || $isUnpublishingPublicArticle) {
                $requestedStatus = $currentStatus;
            }

            if (in_array($currentStatus, self::PUBLIC_STATUSES, true)) {
                $data['published_at'] = $article->getAttribute('published_at');
                $data['scheduled_at'] = $article->getAttribute('scheduled_at');
            }
        }

        $data['status'] = $requestedStatus;
        $data = $this->applyArticleSlugForUpdate($article, $data);
        $data['updated_by'] = $userId;

        if ($user instanceof User && $user->role_slug === 'faculty_editor') {
            $data['faculty_scope_slug'] = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : null;
        }

        return $this->normalizePublicationDates($data);
    }

    public function recordArticleCreated(int $articleId, ?int $userId): bool
    {
        $article = $this->article($articleId);
        $this->invalidateNewsCache();

        return $this->auditService->log('news.article.created', $userId, NewsArticle::class, $articleId, [
            'slug' => $article->getAttribute('slug'),
            'status' => $article->getAttribute('status'),
            'faculty_scope_slug' => $article->getAttribute('faculty_scope_slug'),
        ]);
    }

    public function recordArticleUpdated(int $articleId, ?int $userId, array $before): bool
    {
        $article = $this->article($articleId);
        $this->invalidateNewsCache();

        $afterStatus = (string) $article->getAttribute('status');
        $beforeStatus = is_string($before['status'] ?? null) ? $before['status'] : null;
        $logged = $this->auditService->log('news.article.updated', $userId, NewsArticle::class, $articleId, [
            'slug' => $article->getAttribute('slug'),
            'before' => $before,
            'after' => [
                'status' => $afterStatus,
                'published_at' => $article->getAttribute('published_at')?->toIso8601String(),
                'scheduled_at' => $article->getAttribute('scheduled_at')?->toIso8601String(),
                'faculty_scope_slug' => $article->getAttribute('faculty_scope_slug'),
            ],
        ]);

        if ($beforeStatus !== $afterStatus) {
            $this->auditService->log('news.article.status_changed', $userId, NewsArticle::class, $articleId, [
                'slug' => $article->getAttribute('slug'),
                'from' => $beforeStatus,
                'to' => $afterStatus,
            ]);
        }

        return $logged;
    }

    public function recordCategoryCreated(int $categoryId, ?int $userId): bool
    {
        $category = $this->category($categoryId);
        $this->invalidateNewsCache();

        return $this->auditService->log('news.category.created', $userId, NewsCategory::class, $categoryId, [
            'slug' => $category->getAttribute('slug'),
            'type' => $category->getAttribute('type'),
            'is_enabled' => $category->getAttribute('is_enabled'),
        ]);
    }

    public function recordCategoryUpdated(int $categoryId, ?int $userId, array $before): bool
    {
        $category = $this->category($categoryId);
        $this->invalidateNewsCache();

        return $this->auditService->log('news.category.updated', $userId, NewsCategory::class, $categoryId, [
            'slug' => $category->getAttribute('slug'),
            'before' => $before,
            'after' => [
                'slug' => $category->getAttribute('slug'),
                'type' => $category->getAttribute('type'),
                'sort_order' => $category->getAttribute('sort_order'),
                'is_enabled' => $category->getAttribute('is_enabled'),
            ],
        ]);
    }

    public function deleteCategory(int $categoryId, ?int $userId): bool
    {
        $category = $this->category($categoryId);
        $metadata = [
            'slug' => $category->getAttribute('slug'),
            'type' => $category->getAttribute('type'),
            'sort_order' => $category->getAttribute('sort_order'),
            'is_enabled' => $category->getAttribute('is_enabled'),
        ];
        $deleted = (bool) $category->delete();

        if ($deleted) {
            $this->invalidateNewsCache();
            $this->auditService->log('news.category.deleted', $userId, NewsCategory::class, $categoryId, $metadata);
        }

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePublicationDates(array $data): array
    {
        $status = $this->normalizedStatus($data['status'] ?? null, 'draft');

        if ($status === 'published') {
            $data['published_at'] = Arr::get($data, 'published_at') ?: now();
            $data['scheduled_at'] = null;

            return $data;
        }

        if ($status === 'scheduled') {
            $data['published_at'] = null;
            $data['scheduled_at'] = Arr::get($data, 'scheduled_at') ?: now();

            return $data;
        }

        if ($status === 'draft') {
            $data['published_at'] = null;
            $data['scheduled_at'] = null;
        }

        return $data;
    }

    private function normalizedStatus(mixed $status, string $fallback): string
    {
        return is_string($status) && in_array($status, self::VALID_STATUSES, true) ? $status : $fallback;
    }

    /** @param array<string, mixed> $data */
    private function articleSlugForCreate(array $data): string
    {
        $source = $this->filledString($data['slug'] ?? null) ?? $this->articleTitleSource($data) ?? 'news-article';

        return $this->slugService->generate($source, NewsArticle::class, 'en', null, self::ARTICLE_SLUG_MAX_LENGTH);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function applyArticleSlugForUpdate(NewsArticle $article, array $data): array
    {
        if (! array_key_exists('slug', $data)) {
            return $data;
        }

        $slug = $this->filledString($data['slug'] ?? null);

        if ($slug === null) {
            unset($data['slug']);

            return $data;
        }

        if ($slug === $article->getAttribute('slug')) {
            return $data;
        }

        $data['slug'] = $this->slugService->generate($slug, NewsArticle::class, 'en', (int) $article->getKey(), self::ARTICLE_SLUG_MAX_LENGTH);

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function articleTitleSource(array $data): ?string
    {
        $translations = is_array($data['translations'] ?? null) ? $data['translations'] : [];
        $fallback = null;

        foreach ($translations as $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $title = $this->filledString($translation['title'] ?? null);

            if ($title === null) {
                continue;
            }

            if (($translation['locale'] ?? null) === 'en') {
                return $title;
            }

            $fallback ??= $title;
        }

        return $fallback;
    }

    private function filledString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function canPublish(?User $user): bool
    {
        return $user instanceof User && in_array($user->role_slug, ['super_admin', 'editor'], true);
    }

    private function user(?int $userId): ?User
    {
        return $userId !== null ? User::query()->find($userId) : null;
    }

    private function article(int $articleId): NewsArticle
    {
        $article = NewsArticle::query()->find($articleId);

        if (! $article instanceof NewsArticle) {
            throw new InvalidArgumentException('News article not found.');
        }

        return $article;
    }

    private function category(int $categoryId): NewsCategory
    {
        $category = NewsCategory::query()->find($categoryId);

        if (! $category instanceof NewsCategory) {
            throw new InvalidArgumentException('News category not found.');
        }

        return $category;
    }

    private function invalidateNewsCache(): void
    {
        if (! $this->cacheService->flushTags(['news', 'public-pages', 'public-shell', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }
    }
}
