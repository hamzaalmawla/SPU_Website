<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\Research\ResearchPublicationCmsDataDTO;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use App\Support\LegacyPublicationMetadata;
use App\Support\ResearchPublicationSlug;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ResearchPublicationCmsService implements ResearchPublicationCmsServiceInterface
{
    private const TARGET_PREFIX = 'entity.research-publication.';

    private const LEGACY_SOURCE_TABLE = 'jx_member_categories';

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function prepareDraft(ResearchPublicationCmsDataDTO $data, int $userId): ResearchPublicationCmsDataDTO
    {
        $publication = $data->publicationId !== null ? ResearchPublication::query()->find($data->publicationId) : null;

        if ($data->publicationId !== null && ! $publication instanceof ResearchPublication) {
            throw ValidationException::withMessages(['publication' => ['The research publication no longer exists.']]);
        }

        $stored = $publication instanceof ResearchPublication ? $this->storedPayload($publication) : [];
        $payload = $this->normalizePayload($data->payload, $stored);
        $this->authorizeManagement($userId, $publication, $payload);

        if (! $publication instanceof ResearchPublication) {
            // A new publication is a hidden native row until its first publish.
            $publication = DB::transaction(fn (): ResearchPublication => ResearchPublication::query()->create([
                'faculty_member_id' => $payload['faculty_member_id'],
                'published_at' => null,
                'publication_year' => $payload['publication_year'],
                'is_enabled' => false,
            ]));
            $payload['slug'] = '';
        }

        $payload['entity_id'] = (int) $publication->getKey();
        $payload['is_legacy'] = $publication->legacy_source_table === self::LEGACY_SOURCE_TABLE;
        $payload['updated_by'] = $userId;

        return new ResearchPublicationCmsDataDTO(
            publicationId: (int) $publication->getKey(),
            payload: $payload,
            targetKey: $this->targetKey((int) $publication->getKey()),
        );
    }

    public function getStoredData(string $targetKey): ?ResearchPublicationCmsDataDTO
    {
        $publication = $this->findPublication($targetKey);

        return $publication instanceof ResearchPublication
            ? new ResearchPublicationCmsDataDTO((int) $publication->getKey(), $this->storedPayload($publication), $targetKey)
            : null;
    }

    public function resolveTarget(string $targetKey): ?CmsTargetDTO
    {
        $publication = $this->findPublication($targetKey);

        if (! $publication instanceof ResearchPublication) {
            return null;
        }

        $facultyScope = $this->facultyScope($publication->faculty_member_id);
        $slug = $this->storedSlug($publication);

        return new CmsTargetDTO(
            key: $targetKey,
            // Faculty-owned publications sit in the faculty area so their faculty
            // editors may manage them; the rest need full page management.
            area: $facultyScope !== null ? 'faculty' : 'research',
            labelKey: 'admin.cms.targets.entity.research-publication',
            publicPath: '/research/publications/'.$slug,
            routeName: 'public.research.publications.show',
            parentKey: 'research.publications',
            supportsDraftWorkflow: true,
            locales: ['ar', 'en'],
            facultyScopeSlug: $facultyScope,
        );
    }

    public function authorizeTarget(string $targetKey, int $userId, ?array $payload = null): bool
    {
        $id = $this->publicationId($targetKey);

        if ($id === null) {
            return true;
        }

        $publication = ResearchPublication::query()->find($id);

        if (! $publication instanceof ResearchPublication) {
            throw new AuthorizationException('This research publication is unavailable.');
        }

        $this->authorizeManagement($userId, $publication, $payload !== null ? $this->normalizePayload($payload, $this->storedPayload($publication)) : null);

        return true;
    }

    public function publishErrors(string $targetKey, array $payload): array
    {
        $id = $this->publicationId($targetKey);

        return $id === null ? [] : $this->validationErrors($id, $this->normalizePayload($payload));
    }

    public function publishTarget(string $targetKey, array $payload, DateTimeInterface $publishedAt, int $userId): bool
    {
        $id = $this->publicationId($targetKey);

        if ($id === null) {
            return false;
        }

        $payload = $this->normalizePayload($payload);
        $errors = $this->validationErrors($id, $payload);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $published = DB::transaction(function () use ($id, $payload, $publishedAt): bool {
            $publication = ResearchPublication::query()->with('translations')->lockForUpdate()->findOrFail($id);
            $isLegacy = $publication->legacy_source_table === self::LEGACY_SOURCE_TABLE;
            // The slug is fixed on first publish from the title the site already
            // used, so an edited title keeps the URL and every pick of it.
            // A new publication has no live title yet, so its draft title names it.
            $slug = $this->stringValue($payload['slug'] ?? null) ?: ($publication->translations->isEmpty() && ! (is_string($publication->slug) && $publication->slug !== '')
                ? $this->uniqueSlug(ResearchPublicationSlug::derive(
                    $this->stringValue($payload['translations']['en']['title'] ?? null),
                    $this->stringValue($payload['translations']['ar']['title'] ?? null),
                    (int) $publication->getKey(),
                ), (int) $publication->getKey())
                : $this->storedSlug($publication));
            $publishedOn = $this->nullableString($payload['published_at'] ?? null)
                ?? $publication->published_at?->toDateString()
                ?? $publishedAt->format('Y-m-d');

            $publication->forceFill([
                'slug' => $slug,
                'faculty_member_id' => $payload['faculty_member_id'],
                'publication_year' => $payload['publication_year'],
                'published_at' => $publishedOn,
                'doi' => $this->nullableString($payload['doi'] ?? null),
                'journal_rank' => $this->nullableString($payload['journal_rank'] ?? null),
                'external_url' => $this->nullableString($payload['external_url'] ?? null),
                'file_media_id' => $payload['file_media_id'],
                'image_media_id' => $payload['image_media_id'],
                'is_enabled' => true,
                // Imported rows are public only once marked published, which
                // also clears a "needs review" hold.
                ...($isLegacy ? ['extraction_status' => 'published'] : []),
            ])->save();

            foreach ($this->translations($payload) as $locale => $translation) {
                $publication->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $this->stringValue($translation['title'] ?? null),
                    'authors' => $this->nullableString($translation['authors'] ?? null),
                    'excerpt' => $this->nullableString($translation['excerpt'] ?? null),
                    'abstract' => $this->nullableString($translation['abstract'] ?? null),
                    'publisher' => $this->nullableString($translation['publisher'] ?? null),
                    'citation' => $this->nullableString($translation['citation'] ?? null),
                    'keywords' => $this->keywords($translation['keywords'] ?? []),
                ]);
            }

            return true;
        });

        $this->flushPublicationCaches();

        return $published;
    }

    public function markDraft(string $targetKey): bool
    {
        return $this->findPublication($targetKey) instanceof ResearchPublication;
    }

    public function markScheduled(string $targetKey): bool
    {
        return $this->findPublication($targetKey) instanceof ResearchPublication;
    }

    public function unpublishTarget(string $targetKey): bool
    {
        $publication = $this->findPublication($targetKey);

        if (! $publication instanceof ResearchPublication) {
            return false;
        }

        $wasPublic = (bool) $publication->is_enabled;
        $publication->forceFill(['is_enabled' => false])->save();
        $this->flushPublicationCaches();

        return $wasPublic;
    }

    public function deletePublication(string $targetKey, int $userId): bool
    {
        $publication = $this->findPublication($targetKey);

        if (! $publication instanceof ResearchPublication) {
            return false;
        }

        $this->authorizeManagement($userId, $publication, null, 'delete');
        $publication->delete();
        $this->auditService->log('research_publication.deleted', $userId, ResearchPublication::class, (int) $publication->getKey(), [
            'target_key' => $targetKey,
        ]);
        $this->flushPublicationCaches();

        return true;
    }

    public function facultyMemberOptions(int $userId, ?string $search = null, ?int $selectedId = null): array
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            return [];
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $search = trim((string) $search);
        $query = FacultyMember::query()
            ->with(['translations', 'faculty.translations'])
            ->when($selectedId !== null, fn (Builder $query): Builder => $query->whereKey($selectedId))
            ->when($selectedId === null && $search !== '', fn (Builder $query): Builder => $query->whereHas(
                'translations',
                fn (Builder $translationQuery): Builder => $translationQuery->where('full_name', 'like', '%'.$search.'%'),
            ))
            ->orderBy('id')
            ->limit(50);

        if ($user->role_slug === 'faculty_editor') {
            $scope = $this->canonicalScope((string) $user->faculty_scope_slug);
            $query->whereHas('faculty', fn (Builder $facultyQuery): Builder => $this->whereFacultyScope($facultyQuery, $scope));
        }

        return $query->get()
            ->mapWithKeys(function (FacultyMember $member) use ($locale): array {
                $name = $member->translations->firstWhere('locale', $locale)?->full_name
                    ?: $member->translations->firstWhere('locale', 'ar')?->full_name
                    ?: $member->slug;
                $faculty = $member->faculty?->translations->firstWhere('locale', $locale)?->name;

                return [(int) $member->getKey() => trim((string) $name).($faculty ? ' ('.$faculty.')' : '')];
            })
            ->all();
    }

    public function facultyFilterOptions(): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return Faculty::query()
            ->with('translations')
            ->whereNotNull('public_slug')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Faculty $faculty): array => [
                (string) $faculty->public_slug => (string) ($faculty->translations->firstWhere('locale', $locale)?->name ?? $faculty->public_slug),
            ])
            ->all();
    }

    public function yearFilterOptions(): array
    {
        return ResearchPublication::query()
            ->whereNotNull('publication_year')
            ->distinct()
            ->orderByDesc('publication_year')
            ->pluck('publication_year')
            ->mapWithKeys(fn (mixed $year): array => [(int) $year => (string) $year])
            ->all();
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $fallback @return array<string, mixed> */
    private function normalizePayload(array $payload, array $fallback = []): array
    {
        $value = fn (string $key): mixed => array_key_exists($key, $payload) ? $payload[$key] : ($fallback[$key] ?? null);
        $year = $this->nullableInt($value('publication_year'));

        return [
            'entity_id' => $this->nullableInt($value('entity_id')),
            'slug' => $this->stringValue($value('slug')),
            'is_legacy' => (bool) $value('is_legacy'),
            'faculty_member_id' => $this->nullableInt($value('faculty_member_id')),
            'publication_year' => $year,
            'published_at' => $this->nullableString($value('published_at')),
            'doi' => $this->nullableString($value('doi')),
            'journal_rank' => $this->nullableString($value('journal_rank')),
            'external_url' => $this->nullableString($value('external_url')),
            'file_media_id' => $this->nullableInt($value('file_media_id')),
            'image_media_id' => $this->nullableInt($value('image_media_id')),
            'legacy_image_path' => $this->nullableString($fallback['legacy_image_path'] ?? $payload['legacy_image_path'] ?? null),
            'updated_by' => $this->nullableInt($value('updated_by')),
            'translations' => $this->localizedRecords($payload['translations'] ?? $fallback['translations'] ?? []),
        ];
    }

    /**
     * The row as the editor should see it: legacy rows keep author, publisher
     * and keywords inside a labelled abstract, which the public page splits
     * out, so the editor starts from the same split values.
     *
     * @return array<string, mixed>
     */
    private function storedPayload(ResearchPublication $publication): array
    {
        $publication->loadMissing('translations');
        $abstracts = $publication->translations->map(fn (ResearchPublicationTranslation $translation): ?string => $translation->abstract)->values()->all();
        $translations = [];

        foreach ($publication->translations as $translation) {
            $metadata = LegacyPublicationMetadata::withFallback(LegacyPublicationMetadata::parse($translation->abstract), $abstracts);
            $keywords = is_array($translation->keywords) && $translation->keywords !== [] ? array_values(array_map('strval', $translation->keywords)) : $metadata['keywords'];

            $translations[(string) $translation->locale] = [
                'locale' => (string) $translation->locale,
                'title' => (string) $translation->title,
                'authors' => $this->plainText($translation->authors) ?: $metadata['author'],
                'excerpt' => $this->plainText($translation->excerpt),
                'abstract' => $metadata['abstract'] !== '' ? $metadata['abstract'] : $this->plainText($translation->abstract),
                'publisher' => $this->plainText($translation->publisher) ?: ($this->plainText($translation->citation) ?: $metadata['publisher']),
                'citation' => $this->plainText($translation->citation),
                'keywords' => $keywords,
            ];
        }

        return [
            'entity_id' => (int) $publication->getKey(),
            'slug' => $publication->slug ?? '',
            'is_legacy' => $publication->legacy_source_table === self::LEGACY_SOURCE_TABLE,
            'faculty_member_id' => $publication->faculty_member_id,
            'publication_year' => $publication->publication_year,
            'published_at' => $publication->published_at?->toDateString(),
            'doi' => $publication->doi,
            'journal_rank' => $publication->journal_rank,
            'external_url' => $publication->external_url,
            'file_media_id' => $publication->file_media_id,
            'image_media_id' => $publication->image_media_id,
            'legacy_image_path' => $publication->legacy_image_path,
            'updated_by' => null,
            'translations' => $translations,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, array<int, string>> */
    private function validationErrors(int $publicationId, array $payload): array
    {
        $errors = [];
        $slug = $this->stringValue($payload['slug'] ?? null);

        if ($slug !== '' && ! ResearchPublicationSlug::isValid($slug)) {
            $errors['slug'][] = 'The publication address may use lowercase letters, numbers and single hyphens only.';
        } elseif ($slug !== '' && ResearchPublication::query()->withTrashed()->where('slug', $slug)->whereKeyNot($publicationId)->exists()) {
            $errors['slug'][] = 'The publication address is already in use.';
        }

        foreach (['ar', 'en'] as $locale) {
            $translation = $payload['translations'][$locale] ?? null;

            if (! is_array($translation) || $this->stringValue($translation['title'] ?? null) === '') {
                $errors['translations.'.$locale.'.title'][] = 'A localized title is required.';
            }
        }

        $year = $this->nullableInt($payload['publication_year'] ?? null);

        if ($year !== null && ($year < 1950 || $year > (int) date('Y') + 1)) {
            $errors['publication_year'][] = 'Enter a real publication year.';
        }

        $publishedAt = $this->nullableString($payload['published_at'] ?? null);

        if ($publishedAt !== null && strtotime($publishedAt) === false) {
            $errors['published_at'][] = 'Enter a valid publication date.';
        }

        $url = $this->nullableString($payload['external_url'] ?? null);

        if ($url !== null && (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $errors['external_url'][] = 'The external link must be a full http or https address.';
        }

        $memberId = $this->nullableInt($payload['faculty_member_id'] ?? null);

        if ($memberId !== null && ! FacultyMember::query()->whereKey($memberId)->exists()) {
            $errors['faculty_member_id'][] = 'The selected researcher no longer exists.';
        }

        $this->appendMediaError($errors, 'image_media_id', $payload['image_media_id'] ?? null, true);
        $this->appendMediaError($errors, 'file_media_id', $payload['file_media_id'] ?? null, false);

        return $errors;
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
    private function authorizeManagement(int $userId, ?ResearchPublication $publication, ?array $payload, string $ability = 'update'): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || (bool) $user->is_locked) {
            throw new AuthorizationException('This user is not authorized to manage research publications.');
        }

        $allowed = $publication instanceof ResearchPublication
            ? Gate::forUser($user)->allows($ability, $publication)
            : Gate::forUser($user)->allows('create', ResearchPublication::class);

        if (! $allowed) {
            throw new AuthorizationException('This user is not authorized to manage this research publication.');
        }

        if ($payload !== null && $user->role_slug === 'faculty_editor') {
            $scope = $this->canonicalScope((string) $user->faculty_scope_slug);

            if ($scope === '' || $this->facultyScope($this->nullableInt($payload['faculty_member_id'] ?? null)) !== $scope) {
                throw new AuthorizationException('You may only manage research publications of your assigned faculty.');
            }
        }
    }

    private function facultyScope(?int $facultyMemberId): ?string
    {
        if ($facultyMemberId === null) {
            return null;
        }

        $faculty = FacultyMember::query()->with('faculty')->find($facultyMemberId)?->faculty;

        if (! $faculty instanceof Faculty) {
            return null;
        }

        $scope = (string) ($faculty->public_slug ?: $faculty->faculty_scope_slug ?: $faculty->slug);

        return $scope !== '' ? $this->canonicalScope($scope) : null;
    }

    private function canonicalScope(string $scope): string
    {
        $scope = trim($scope);

        if ($scope === '') {
            return '';
        }

        $publicSlug = Faculty::query()
            ->where(fn (Builder $query): Builder => $this->whereFacultyScope($query, $scope))
            ->value('public_slug');

        return match ($publicSlug ?: $scope) {
            'ai', 'ai-engineering' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
            'business' => 'business-administration',
            default => (string) ($publicSlug ?: $scope),
        };
    }

    private function whereFacultyScope(Builder $query, string $scope): Builder
    {
        return $query->where(fn (Builder $scopeQuery): Builder => $scopeQuery
            ->where('faculty_scope_slug', $scope)
            ->orWhere('public_slug', $scope)
            ->orWhere('slug', $scope));
    }

    private function storedSlug(ResearchPublication $publication): string
    {
        if (is_string($publication->slug) && $publication->slug !== '') {
            return $publication->slug;
        }

        $publication->loadMissing('translations');
        // Same suffix as the public site: the import's source id, else the key.
        $sourceId = MigrationLog::query()
            ->where('module', 'research')
            ->where('source_table', self::LEGACY_SOURCE_TABLE)
            ->where('target_table', 'research_publications')
            ->where('status', 'success')
            ->where('target_id', $publication->getKey())
            ->value('source_id');

        return ResearchPublicationSlug::derive(
            $publication->translations->firstWhere('locale', 'en')?->title,
            $publication->translations->firstWhere('locale', 'ar')?->title,
            (int) (is_numeric($sourceId) ? $sourceId : $publication->getKey()),
        );
    }

    private function uniqueSlug(string $slug, int $publicationId): string
    {
        $candidate = $slug;
        $attempt = 2;

        while (ResearchPublication::query()->withTrashed()->where('slug', $candidate)->whereKeyNot($publicationId)->exists()) {
            $candidate = $slug.'-'.$attempt++;
        }

        return $candidate;
    }

    private function flushPublicationCaches(): void
    {
        $this->cacheService->flushTags(['research', 'facilities', 'public-pages', 'seo', 'sitemap']);
    }

    private function findPublication(string $targetKey): ?ResearchPublication
    {
        $id = $this->publicationId($targetKey);

        return $id !== null ? ResearchPublication::query()->with('translations')->find($id) : null;
    }

    private function publicationId(string $targetKey): ?int
    {
        return preg_match('/^entity\.research-publication\.([1-9][0-9]*)$/', $targetKey, $matches) === 1 ? (int) $matches[1] : null;
    }

    private function targetKey(int $publicationId): string
    {
        return self::TARGET_PREFIX.$publicationId;
    }

    /** @return array<string, array<string, mixed>> */
    private function localizedRecords(mixed $records): array
    {
        $normalized = [];

        foreach (is_array($records) ? $records : [] as $key => $record) {
            if (! is_array($record)) {
                continue;
            }

            $locale = is_string($record['locale'] ?? null) ? $record['locale'] : (is_string($key) ? $key : null);

            if (in_array($locale, ['ar', 'en'], true)) {
                $record['locale'] = $locale;
                $record['keywords'] = $this->keywords($record['keywords'] ?? []);
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

    /** @return list<string> */
    private function keywords(mixed $value): array
    {
        $values = is_array($value) ? $value : (preg_split('/[,;،]+/u', is_string($value) ? $value : '') ?: []);

        return array_slice(array_values(array_unique(array_filter(
            array_map(fn (mixed $keyword): string => $this->stringValue($keyword), $values),
            static fn (string $keyword): bool => $keyword !== '',
        ))), 0, 20);
    }

    private function plainText(?string $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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
