<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\DTOs\News\NewsArticleDTO;
use App\DTOs\News\NewsAttachmentDTO;
use App\DTOs\News\NewsCategoryDTO;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleAttachment;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use App\Support\HtmlSanitizer;
use App\Support\MediaUrlResolver;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class NewsArticleCmsService implements NewsArticleCmsServiceInterface
{
    private const TARGET_PREFIX = 'entity.news-article.';

    public function __construct(
        private readonly SlugServiceInterface $slugService,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

    public function prepareDraft(NewsArticleCmsDataDTO $data, int $userId): NewsArticleCmsDataDTO
    {
        $article = $data->articleId !== null ? NewsArticle::query()->find($data->articleId) : null;
        if ($data->articleId !== null && ! $article instanceof NewsArticle) {
            throw ValidationException::withMessages(['article' => ['The news article no longer exists.']]);
        }

        $stored = $article instanceof NewsArticle ? $this->storedPayload($article) : [];
        $payload = $this->normalizePayload($data->payload, $stored);
        $user = User::query()->find($userId);
        if (! $article instanceof NewsArticle && $user instanceof User && $user->role_slug === 'faculty_editor') {
            $payload['faculty_scope_slug'] = $this->nullableString($user->faculty_scope_slug);
        }
        $this->authorizeManagement($userId, $article, $payload);

        if (! $article instanceof NewsArticle) {
            $source = $this->stringValue($payload['slug'] ?? null)
                ?: $this->stringValue($payload['translations']['en']['title'] ?? null)
                ?: $this->stringValue($payload['translations']['ar']['title'] ?? null)
                ?: 'news-article';
            $payload['slug'] = $this->slugService->generate($source, NewsArticle::class, 'en', null, 80);
            $article = DB::transaction(fn (): NewsArticle => NewsArticle::query()->create([
                'news_category_id' => $payload['news_category_id'],
                'slug' => $payload['slug'],
                'status' => 'draft',
                'published_at' => null,
                'scheduled_at' => null,
                'is_enabled' => false,
                'is_featured' => false,
                'faculty_scope_slug' => $payload['faculty_scope_slug'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
        }

        $payload['entity_id'] = (int) $article->getKey();
        $payload['updated_by'] = $userId;

        return new NewsArticleCmsDataDTO(
            articleId: (int) $article->getKey(),
            payload: $payload,
            targetKey: $this->targetKey((int) $article->getKey()),
        );
    }

    public function getStoredData(string $targetKey): ?NewsArticleCmsDataDTO
    {
        $id = $this->articleId($targetKey);
        $article = $id !== null
            ? NewsArticle::query()->with(['translations', 'seoMeta', 'attachments'])->find($id)
            : null;

        return $article instanceof NewsArticle
            ? new NewsArticleCmsDataDTO($id, $this->storedPayload($article), $targetKey)
            : null;
    }

    public function resolveTarget(string $targetKey): ?CmsTargetDTO
    {
        $stored = $this->getStoredData($targetKey);
        if (! $stored instanceof NewsArticleCmsDataDTO || $stored->articleId === null) {
            return null;
        }

        return new CmsTargetDTO(
            key: $targetKey,
            area: 'news',
            labelKey: 'admin.cms.targets.entity.news-article',
            publicPath: '/news/'.$stored->articleId,
            routeName: 'public.news.show',
            parentKey: 'news.articles',
            supportsDraftWorkflow: true,
            locales: $this->requiredLocales($stored->payload),
            facultyScopeSlug: $this->nullableString($stored->payload['faculty_scope_slug'] ?? null),
        );
    }

    public function authorizeTarget(string $targetKey, int $userId, ?array $payload = null): bool
    {
        $id = $this->articleId($targetKey);
        if ($id === null) {
            return true;
        }

        $article = NewsArticle::query()->find($id);
        if (! $article instanceof NewsArticle) {
            throw new AuthorizationException('This news article is unavailable.');
        }

        $this->authorizeManagement($userId, $article, $payload !== null ? $this->normalizePayload($payload, $this->storedPayload($article)) : null);

        return true;
    }

    public function publishErrors(string $targetKey, array $payload): array
    {
        $id = $this->articleId($targetKey);

        return $id === null ? [] : $this->validationErrors($id, $this->normalizePayload($payload));
    }

    public function publishTarget(string $targetKey, array $payload, DateTimeInterface $publishedAt, int $userId): bool
    {
        $id = $this->articleId($targetKey);
        if ($id === null) {
            return false;
        }

        $payload = $this->normalizePayload($payload);
        $errors = $this->validationErrors($id, $payload);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($id, $payload, $publishedAt, $userId): bool {
            $article = NewsArticle::query()->lockForUpdate()->findOrFail($id);
            $article->forceFill([
                'news_category_id' => $this->nullableInt($payload['news_category_id']),
                'cover_media_id' => $this->nullableInt($payload['cover_media_id']),
                'slug' => $this->stringValue($payload['slug']),
                'status' => 'published',
                'published_at' => $publishedAt,
                'scheduled_at' => null,
                'is_enabled' => (bool) $payload['is_enabled'],
                'is_featured' => (bool) $payload['is_featured'],
                'sort_order' => (int) $payload['sort_order'],
                'faculty_scope_slug' => $this->nullableString($payload['faculty_scope_slug']),
                'updated_by' => $userId,
            ])->save();

            $keptLocales = [];
            foreach ($this->translations($payload) as $locale => $translation) {
                $keptLocales[] = $locale;
                $article->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $this->stringValue($translation['title'] ?? null),
                    'excerpt' => $this->nullableString($translation['excerpt'] ?? null),
                    'body' => $this->nullableString($this->htmlSanitizer->sanitize($this->nullableString($translation['body'] ?? null))),
                ]);
            }
            $article->translations()->whereNotIn('locale', $keptLocales)->delete();

            $keptSeoLocales = [];
            foreach ($this->seoMeta($payload) as $locale => $seo) {
                $keptSeoLocales[] = $locale;
                $article->seoMeta()->updateOrCreate(['locale' => $locale], [
                    'meta_title' => $this->nullableString($seo['meta_title'] ?? null),
                    'meta_description' => $this->nullableString($seo['meta_description'] ?? null),
                    'og_title' => $this->nullableString($seo['og_title'] ?? null),
                    'og_description' => $this->nullableString($seo['og_description'] ?? null),
                    'og_image_media_id' => $this->nullableInt($seo['og_image_media_id'] ?? null),
                    'og_image_url' => $this->nullableString($seo['og_image_url'] ?? null),
                    'robots' => $this->nullableString($seo['robots'] ?? null) ?? 'index,follow',
                ]);
            }
            $article->seoMeta()->whereNotIn('locale', $keptSeoLocales)->delete();

            $article->attachments()->delete();
            foreach ($this->listValue($payload['attachments'] ?? null) as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }
                $article->attachments()->create([
                    'media_asset_id' => $this->nullableInt($attachment['media_asset_id'] ?? null),
                    'kind' => $this->stringValue($attachment['kind'] ?? null) ?: 'file',
                    'label_ar' => $this->nullableString($attachment['label_ar'] ?? null),
                    'label_en' => $this->nullableString($attachment['label_en'] ?? null),
                    'legacy_source_table' => $this->nullableString($attachment['legacy_source_table'] ?? null),
                    'legacy_source_id' => $this->nullableInt($attachment['legacy_source_id'] ?? null),
                    'legacy_path' => $this->nullableString($attachment['legacy_path'] ?? null),
                    'sort_order' => (int) ($attachment['sort_order'] ?? 0),
                ]);
            }

            return true;
        });
    }

    public function markDraft(string $targetKey): bool
    {
        $article = $this->findArticle($targetKey);
        if (! $article instanceof NewsArticle) {
            return false;
        }
        if ($article->status !== 'published') {
            $article->forceFill(['status' => 'draft', 'scheduled_at' => null])->save();
        }

        return true;
    }

    public function markScheduled(string $targetKey): bool
    {
        $article = $this->findArticle($targetKey);
        if (! $article instanceof NewsArticle) {
            return false;
        }
        if ($article->status !== 'published') {
            $article->forceFill(['status' => 'scheduled'])->save();
        }

        return true;
    }

    public function unpublishTarget(string $targetKey): bool
    {
        $article = $this->findArticle($targetKey);
        if (! $article instanceof NewsArticle) {
            return false;
        }

        $wasPublic = $article->status === 'published' || $article->status === 'scheduled';
        $article->forceFill([
            'status' => 'draft',
            'published_at' => null,
            'scheduled_at' => null,
        ])->save();

        return $wasPublic;
    }

    public function buildPreview(array $payload, string $locale): ?NewsArticleDTO
    {
        $payload = $this->normalizePayload($payload);
        $translation = $this->localized($this->translations($payload), $locale);
        $id = $this->nullableInt($payload['entity_id'] ?? null);
        if ($id === null || $translation === null || $this->stringValue($translation['title'] ?? null) === '') {
            return null;
        }

        $seo = $this->localized($this->seoMeta($payload), $locale) ?? [];
        $cover = $this->mediaUrl($this->nullableInt($payload['cover_media_id'] ?? null));
        $ogImage = $this->nullableString($seo['og_image_url'] ?? null)
            ?? $this->mediaUrl($this->nullableInt($seo['og_image_media_id'] ?? null))
            ?? $cover;
        $attachments = [];
        foreach ($this->listValue($payload['attachments'] ?? null) as $index => $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $attachments[] = new NewsAttachmentDTO(
                id: $this->nullableInt($attachment['id'] ?? null) ?? $index + 1,
                kind: $this->stringValue($attachment['kind'] ?? null) ?: 'file',
                label: $this->nullableString($attachment[$locale === 'ar' ? 'label_ar' : 'label_en'] ?? null)
                    ?? $this->nullableString($attachment['label_ar'] ?? null),
                url: $this->mediaUrl($this->nullableInt($attachment['media_asset_id'] ?? null))
                    ?? $this->nullableString($attachment['legacy_path'] ?? null),
            );
        }

        return new NewsArticleDTO(
            id: $id,
            locale: $locale,
            slug: $this->stringValue($payload['slug'] ?? null),
            title: $this->stringValue($translation['title'] ?? null),
            excerpt: $this->nullableString($translation['excerpt'] ?? null),
            body: $this->nullableString($this->htmlSanitizer->sanitize($this->nullableString($translation['body'] ?? null))),
            imageUrl: $ogImage,
            publishedAt: $this->nullableString($payload['published_at'] ?? null),
            url: '/'.$locale.'/news/'.$id,
            category: $this->categoryDto($this->nullableInt($payload['news_category_id'] ?? null), $locale),
            attachments: $attachments,
            metaTitle: $this->nullableString($seo['meta_title'] ?? null),
            metaDescription: $this->nullableString($seo['meta_description'] ?? null),
            ogTitle: $this->nullableString($seo['og_title'] ?? null),
            ogDescription: $this->nullableString($seo['og_description'] ?? null),
            ogImage: $ogImage,
            robots: 'noindex,nofollow',
        );
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $fallback @return array<string, mixed> */
    private function normalizePayload(array $payload, array $fallback = []): array
    {
        $translations = $this->localizedRecords($payload['translations'] ?? $fallback['translations'] ?? []);
        $seo = $this->localizedRecords($payload['seo_meta'] ?? $payload['seoMeta'] ?? $fallback['seo_meta'] ?? []);

        return [
            'entity_id' => $this->nullableInt($payload['entity_id'] ?? $fallback['entity_id'] ?? null),
            'news_category_id' => $this->nullableInt($payload['news_category_id'] ?? $fallback['news_category_id'] ?? null),
            'cover_media_id' => $this->nullableInt($payload['cover_media_id'] ?? $fallback['cover_media_id'] ?? null),
            'slug' => $this->stringValue($payload['slug'] ?? $fallback['slug'] ?? null),
            'published_at' => $this->nullableString($payload['published_at'] ?? $fallback['published_at'] ?? null),
            'is_enabled' => (bool) ($payload['is_enabled'] ?? $fallback['is_enabled'] ?? true),
            'is_featured' => (bool) ($payload['is_featured'] ?? $fallback['is_featured'] ?? false),
            'sort_order' => (int) ($payload['sort_order'] ?? $fallback['sort_order'] ?? 0),
            'faculty_scope_slug' => $this->nullableString($payload['faculty_scope_slug'] ?? $fallback['faculty_scope_slug'] ?? null),
            'updated_by' => $this->nullableInt($payload['updated_by'] ?? $fallback['updated_by'] ?? null),
            'translations' => $translations,
            'attachments' => $this->listValue($payload['attachments'] ?? $fallback['attachments'] ?? []),
            'seo_meta' => $seo,
        ];
    }

    /** @return array<string, mixed> */
    private function storedPayload(NewsArticle $article): array
    {
        $article->loadMissing(['translations', 'seoMeta', 'attachments']);

        return [
            'entity_id' => (int) $article->getKey(),
            'news_category_id' => $article->news_category_id,
            'cover_media_id' => $article->cover_media_id,
            'slug' => (string) $article->slug,
            'published_at' => $article->published_at?->toIso8601String(),
            'is_enabled' => (bool) $article->is_enabled,
            'is_featured' => (bool) $article->is_featured,
            'sort_order' => (int) $article->sort_order,
            'faculty_scope_slug' => $article->faculty_scope_slug,
            'updated_by' => $article->updated_by,
            'translations' => $article->translations->mapWithKeys(fn (NewsArticleTranslation $translation): array => [(string) $translation->locale => [
                'locale' => (string) $translation->locale,
                'title' => (string) $translation->title,
                'excerpt' => $translation->excerpt,
                'body' => $translation->body,
            ]])->all(),
            'attachments' => $article->attachments->map(fn (NewsArticleAttachment $attachment): array => [
                'id' => (int) $attachment->getKey(),
                'media_asset_id' => $attachment->media_asset_id,
                'kind' => (string) $attachment->kind,
                'label_ar' => $attachment->label_ar,
                'label_en' => $attachment->label_en,
                'legacy_source_table' => $attachment->legacy_source_table,
                'legacy_source_id' => $attachment->legacy_source_id,
                'legacy_path' => $attachment->legacy_path,
                'sort_order' => (int) $attachment->sort_order,
            ])->values()->all(),
            'seo_meta' => $article->seoMeta->mapWithKeys(fn (NewsArticleSeoMeta $seo): array => [(string) $seo->locale => [
                'locale' => (string) $seo->locale,
                'meta_title' => $seo->meta_title,
                'meta_description' => $seo->meta_description,
                'og_title' => $seo->og_title,
                'og_description' => $seo->og_description,
                'og_image_media_id' => $seo->og_image_media_id,
                'og_image_url' => $seo->og_image_url,
                'robots' => (string) $seo->robots,
            ]])->all(),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, array<int, string>> */
    private function validationErrors(int $articleId, array $payload): array
    {
        $errors = [];
        $slug = $this->stringValue($payload['slug'] ?? null);
        if ($slug === '' || mb_strlen($slug) > 80) {
            $errors['slug'][] = 'A valid article slug is required.';
        } elseif (NewsArticle::query()->where('slug', $slug)->whereKeyNot($articleId)->exists()) {
            $errors['slug'][] = 'The article slug is already in use.';
        }

        $categoryId = $this->nullableInt($payload['news_category_id'] ?? null);
        if ($categoryId === null || ! NewsCategory::query()->enabled()->whereKey($categoryId)->whereIn('type', ['news', 'announcement'])->exists()) {
            $errors['news_category_id'][] = 'A News or Announcement content type is required.';
        }

        foreach ($this->requiredLocales($payload) as $locale) {
            $translation = $payload['translations'][$locale] ?? null;
            if (! is_array($translation) || $this->stringValue($translation['title'] ?? null) === '') {
                $errors['translations.'.$locale.'.title'][] = 'A localized title is required.';
            }
            if (! is_array($translation) || $this->stringValue($translation['body'] ?? null) === '') {
                $errors['translations.'.$locale.'.body'][] = 'Localized article content is required.';
            }
        }

        $this->appendMediaError($errors, 'cover_media_id', $payload['cover_media_id'] ?? null, true);
        foreach ($this->seoMeta($payload) as $locale => $seo) {
            $this->appendMediaError($errors, 'seo_meta.'.$locale.'.og_image_media_id', $seo['og_image_media_id'] ?? null, true);
        }
        foreach ($this->listValue($payload['attachments'] ?? null) as $index => $attachment) {
            if (! is_array($attachment) || ! in_array($attachment['kind'] ?? null, ['image', 'file', 'video'], true)) {
                $errors['attachments.'.$index.'.kind'][] = 'A valid attachment type is required.';

                continue;
            }
            if ($this->nullableInt($attachment['media_asset_id'] ?? null) === null && $this->nullableString($attachment['legacy_path'] ?? null) === null) {
                $errors['attachments.'.$index.'.media_asset_id'][] = 'An attachment file is required.';
            }
            $this->appendMediaError($errors, 'attachments.'.$index.'.media_asset_id', $attachment['media_asset_id'] ?? null, false);
        }

        return $errors;
    }

    /** @param array<string, mixed> $payload @return list<string> */
    private function requiredLocales(array $payload): array
    {
        $articleId = $this->nullableInt($payload['entity_id'] ?? null);
        $article = $articleId !== null ? NewsArticle::query()->find($articleId) : null;
        if (! $article instanceof NewsArticle || $article->legacy_source_table !== 'jx_categories') {
            return ['ar', 'en'];
        }

        $translations = $this->translations($payload);
        $arabic = $translations['ar'] ?? null;
        $english = $translations['en'] ?? null;
        $hasArabic = is_array($arabic)
            && $this->stringValue($arabic['title'] ?? null) !== ''
            && $this->stringValue($arabic['body'] ?? null) !== '';
        $hasEnglish = is_array($english)
            && $this->stringValue($english['title'] ?? null) !== ''
            && $this->stringValue($english['body'] ?? null) !== '';

        return $hasArabic && ! $hasEnglish ? ['ar'] : ['ar', 'en'];
    }

    /** @param array<string, array<int, string>> $errors */
    private function appendMediaError(array &$errors, string $field, mixed $value, bool $image): void
    {
        $id = $this->nullableInt($value);
        if ($id === null) {
            return;
        }
        $media = MediaAsset::query()->find($id);
        if (! $media instanceof MediaAsset || ($image && ! str_starts_with((string) $media->mime_type, 'image/'))) {
            $errors[$field][] = 'The selected media file is unavailable or has the wrong type.';
        }
    }

    /** @param array<string, mixed>|null $payload */
    private function authorizeManagement(int $userId, ?NewsArticle $article, ?array $payload): void
    {
        $user = User::query()->find($userId);
        if (! $user instanceof User || (bool) $user->is_locked) {
            throw new AuthorizationException('This user is not authorized to manage news articles.');
        }

        $allowed = $article instanceof NewsArticle
            ? Gate::forUser($user)->allows('update', $article)
            : Gate::forUser($user)->allows('create', NewsArticle::class);
        if (! $allowed) {
            throw new AuthorizationException('This user is not authorized to manage this news article.');
        }

        if ($payload !== null && $user->role_slug === 'faculty_editor') {
            $scope = $this->nullableString($user->faculty_scope_slug);
            if ($scope === null || $this->nullableString($payload['faculty_scope_slug'] ?? null) !== $scope) {
                throw new AuthorizationException('You may only manage news articles in your assigned faculty.');
            }
        }
    }

    private function categoryDto(?int $categoryId, string $locale): ?NewsCategoryDTO
    {
        $category = $categoryId !== null ? NewsCategory::query()->with('translations')->find($categoryId) : null;
        if (! $category instanceof NewsCategory) {
            return null;
        }
        $translation = $category->translations->firstWhere('locale', $locale)
            ?? $category->translations->firstWhere('locale', 'ar')
            ?? $category->translations->first();

        return new NewsCategoryDTO(
            id: (int) $category->getKey(),
            slug: (string) $category->slug,
            type: (string) $category->type,
            name: (string) ($translation?->name ?? $category->slug),
            description: $translation?->description,
        );
    }

    private function mediaUrl(?int $mediaId): ?string
    {
        $media = $mediaId !== null ? MediaAsset::query()->find($mediaId) : null;

        return $media instanceof MediaAsset ? MediaUrlResolver::resolve($media->webp_path ?: $media->path, $media->disk) : null;
    }

    private function findArticle(string $targetKey): ?NewsArticle
    {
        $id = $this->articleId($targetKey);

        return $id !== null ? NewsArticle::query()->find($id) : null;
    }

    private function articleId(string $targetKey): ?int
    {
        return preg_match('/^entity\.news-article\.([1-9][0-9]*)$/', $targetKey, $matches) === 1 ? (int) $matches[1] : null;
    }

    private function targetKey(int $articleId): string
    {
        return self::TARGET_PREFIX.$articleId;
    }

    /** @return array<string, array<string, mixed>> */
    private function localizedRecords(mixed $records): array
    {
        $normalized = [];
        foreach ($this->listValue($records) as $key => $record) {
            if (! is_array($record)) {
                continue;
            }
            $locale = is_string($record['locale'] ?? null) ? $record['locale'] : (is_string($key) ? $key : null);
            if (in_array($locale, ['ar', 'en'], true)) {
                $record['locale'] = $locale;
                $normalized[$locale] = $record;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $payload @return array<string, array<string, mixed>> */
    private function translations(array $payload): array
    {
        return is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
    }

    /** @param array<string, mixed> $payload @return array<string, array<string, mixed>> */
    private function seoMeta(array $payload): array
    {
        return is_array($payload['seo_meta'] ?? null) ? $payload['seo_meta'] : [];
    }

    /** @param array<string, array<string, mixed>> $records @return array<string, mixed>|null */
    private function localized(array $records, string $locale): ?array
    {
        $record = $records[$locale] ?? $records[$locale === 'ar' ? 'en' : 'ar'] ?? null;

        return is_array($record) ? $record : null;
    }

    /** @return array<int|string, mixed> */
    private function listValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
