<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\Research\ResearcherDataDTO;
use App\DTOs\Research\ResearchPublicationCmsDataDTO;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Research\Researcher;
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

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly AdminAccessServiceInterface $access,
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
                'faculty_id' => $payload['faculty_id'],
                'faculty_member_id' => $payload['faculty_member_id'],
                'published_at' => null,
                'publication_year' => $payload['publication_year'],
                'is_enabled' => false,
            ]));
            $payload['slug'] = '';
        }

        $payload['entity_id'] = (int) $publication->getKey();
        $payload['is_legacy'] = in_array($publication->legacy_source_table, ResearchPublication::LEGACY_SOURCE_TABLES, true);
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

        $facultyScope = $this->facultyScope($publication->faculty_id, $publication->faculty_member_id);
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
            $isLegacy = in_array($publication->legacy_source_table, ResearchPublication::LEGACY_SOURCE_TABLES, true);
            // The slug is fixed on first publish from the title the site already
            // used, so an edited title keeps the URL and every pick of it.
            // A new publication has no live title yet, so its draft title names it.
            $slug = $this->stringValue($payload['slug'] ?? null) ?: ($publication->translations->isEmpty() && ! (is_string($publication->slug) && $publication->slug !== '')
                ? $this->uniqueSlug(ResearchPublicationSlug::storable(ResearchPublicationSlug::derive(
                    $this->stringValue($payload['translations']['en']['title'] ?? null),
                    $this->stringValue($payload['translations']['ar']['title'] ?? null),
                    (int) $publication->getKey(),
                )), (int) $publication->getKey())
                : $this->storedSlug($publication));
            $publishedOn = $this->nullableString($payload['published_at'] ?? null)
                ?? $publication->published_at?->toDateString()
                ?? $publishedAt->format('Y-m-d');

            $publication->forceFill([
                'slug' => $slug,
                'faculty_id' => $payload['faculty_id'],
                'faculty_member_id' => $payload['faculty_member_id'],
                'publication_year' => $payload['publication_year'],
                'published_at' => $publishedOn,
                'doi' => $this->nullableString($payload['doi'] ?? null),
                'journal_rank' => $this->nullableString($payload['journal_rank'] ?? null),
                'external_url' => $this->nullableString($payload['external_url'] ?? null),
                // Every paper gets a register number, as in the research office's system.
                'research_number' => $payload['research_number'] ?? $publication->research_number ?? $this->nextResearchNumber($publishedAt),
                'publication_type' => $payload['publication_type'],
                'study_type' => $payload['study_type'],
                'issn' => $payload['issn'],
                'notes' => $payload['notes'],
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

            $publication->researchers()->sync(collect($payload['researchers'])
                ->values()
                ->mapWithKeys(fn (array $researcher, int $index): array => [$researcher['researcher_id'] => [
                    'role' => $researcher['role'],
                    'sort_order' => $index + 1,
                    'affiliation_snapshot' => $researcher['affiliation_snapshot'],
                ]])
                ->all());

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

        if ($user->isLimitedEditor()) {
            $scopes = $this->access->facultyScopeValues($user);
            $query->whereHas('faculty', fn (Builder $facultyQuery): Builder => $this->whereFacultyScope($facultyQuery, $scopes));
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

    public function facultyOptions(): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return Faculty::query()
            ->with('translations')
            ->whereNotNull('public_slug')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Faculty $faculty): array => [
                (int) $faculty->getKey() => (string) ($faculty->translations->firstWhere('locale', $locale)?->name ?? $faculty->public_slug),
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
            'faculty_id' => $this->nullableInt($value('faculty_id')),
            'faculty_member_id' => $this->nullableInt($value('faculty_member_id')),
            'publication_year' => $year,
            'published_at' => $this->nullableString($value('published_at')),
            'doi' => $this->nullableString($value('doi')),
            'journal_rank' => $this->nullableString($value('journal_rank')),
            'external_url' => $this->nullableString($value('external_url')),
            'research_number' => $this->nullableString($value('research_number')),
            'publication_type' => $this->nullableString($value('publication_type')),
            'study_type' => $this->nullableString($value('study_type')),
            'issn' => $this->nullableString($value('issn')),
            'notes' => $this->nullableString($value('notes')),
            'researchers' => $this->researcherRecords($value('researchers')),
            'file_media_id' => $this->nullableInt($value('file_media_id')),
            'image_media_id' => $this->nullableInt($value('image_media_id')),
            'legacy_image_path' => $this->nullableString($fallback['legacy_image_path'] ?? $payload['legacy_image_path'] ?? null),
            'updated_by' => $this->nullableInt($value('updated_by')),
            'translations' => $this->localizedRecords($payload['translations'] ?? $fallback['translations'] ?? []),
        ];
    }

    /**
     * The paper's researchers in author order: one row per person, each with a
     * known role.
     *
     * @return list<array{researcher_id: int, role: string, affiliation_snapshot: ?string}>
     */
    private function researcherRecords(mixed $records): array
    {
        $normalized = [];

        foreach (is_array($records) ? $records : [] as $record) {
            $id = is_array($record) ? $this->nullableInt($record['researcher_id'] ?? null) : null;

            if ($id === null || isset($normalized[$id])) {
                continue;
            }

            $role = $this->stringValue($record['role'] ?? null);
            $normalized[$id] = [
                'researcher_id' => $id,
                'role' => in_array($role, ResearchPublication::RESEARCHER_ROLES, true) ? $role : 'co_author',
                'affiliation_snapshot' => $this->nullableString($record['affiliation_snapshot'] ?? null),
            ];
        }

        return array_values($normalized);
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
        $publication->loadMissing(['translations', 'researchers']);
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
            'is_legacy' => in_array($publication->legacy_source_table, ResearchPublication::LEGACY_SOURCE_TABLES, true),
            'faculty_id' => $publication->faculty_id,
            'faculty_member_id' => $publication->faculty_member_id,
            'publication_year' => $publication->publication_year,
            'published_at' => $publication->published_at?->toDateString(),
            'doi' => $publication->doi,
            'journal_rank' => $publication->journal_rank,
            'external_url' => $publication->external_url,
            'research_number' => $publication->research_number,
            'publication_type' => $publication->publication_type,
            'study_type' => $publication->study_type,
            'issn' => $publication->issn,
            'notes' => $publication->notes,
            'researchers' => $publication->researchers->map(fn (Researcher $researcher): array => [
                'researcher_id' => (int) $researcher->getKey(),
                'role' => (string) $researcher->pivot->getAttribute('role'),
                'affiliation_snapshot' => $researcher->pivot->getAttribute('affiliation_snapshot'),
            ])->values()->all(),
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

        $facultyId = $this->nullableInt($payload['faculty_id'] ?? null);

        if ($facultyId !== null && ! Faculty::query()->whereKey($facultyId)->exists()) {
            $errors['faculty_id'][] = 'The selected faculty no longer exists.';
        }

        $memberId = $this->nullableInt($payload['faculty_member_id'] ?? null);

        if ($memberId !== null && ! FacultyMember::query()->whereKey($memberId)->exists()) {
            $errors['faculty_member_id'][] = 'The selected researcher no longer exists.';
        }

        $this->appendMediaError($errors, 'image_media_id', $payload['image_media_id'] ?? null, true);
        $this->appendMediaError($errors, 'file_media_id', $payload['file_media_id'] ?? null, false);

        $researchNumber = $this->nullableString($payload['research_number'] ?? null);

        if ($researchNumber !== null && ResearchPublication::query()->withTrashed()->where('research_number', $researchNumber)->whereKeyNot($publicationId)->exists()) {
            $errors['research_number'][] = 'This research number is already used by another publication.';
        }

        $publicationType = $this->nullableString($payload['publication_type'] ?? null);

        if ($publicationType !== null && ! in_array($publicationType, ResearchPublication::PUBLICATION_TYPES, true)) {
            $errors['publication_type'][] = 'Choose a publication type from the list.';
        }

        $studyType = $this->nullableString($payload['study_type'] ?? null);

        if ($studyType !== null && ! in_array($studyType, ResearchPublication::STUDY_TYPES, true)) {
            $errors['study_type'][] = 'Choose a study type from the list.';
        }

        $researcherIds = array_column(is_array($payload['researchers'] ?? null) ? $payload['researchers'] : [], 'researcher_id');

        if ($researcherIds !== [] && Researcher::query()->whereKey($researcherIds)->count() !== count($researcherIds)) {
            $errors['researchers'][] = 'One of the selected researchers no longer exists.';
        }

        return $errors;
    }

    /** RES + year + a three-digit sequence, the research office's numbering (RES2026001). */
    private function nextResearchNumber(DateTimeInterface $publishedAt): string
    {
        $prefix = 'RES'.$publishedAt->format('Y');
        $last = ResearchPublication::query()->withTrashed()
            ->where('research_number', 'like', $prefix.'%')
            ->pluck('research_number')
            ->map(fn (string $number): int => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    public function researcherOptions(?string $search = null, ?int $selectedId = null): array
    {
        $search = trim((string) $search);

        return Researcher::query()
            ->when($selectedId !== null, fn (Builder $query): Builder => $query->whereKey($selectedId))
            ->when($selectedId === null && $search !== '', fn (Builder $query): Builder => $query->where('full_name', 'like', '%'.$search.'%'))
            ->orderBy('full_name')
            ->limit(50)
            ->get(['id', 'full_name', 'affiliation'])
            ->mapWithKeys(fn (Researcher $researcher): array => [
                (int) $researcher->getKey() => trim($researcher->full_name).(filled($researcher->affiliation) ? ' ('.trim((string) $researcher->affiliation).')' : ''),
            ])
            ->all();
    }

    public function createResearcher(ResearcherDataDTO $data, int $userId): int
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || (bool) $user->is_locked || Gate::forUser($user)->denies('create', ResearchPublication::class)) {
            throw new AuthorizationException('This user is not authorized to register researchers.');
        }

        $name = trim($data->fullName);

        if ($name === '') {
            throw ValidationException::withMessages(['full_name' => ['The researcher\'s name is required.']]);
        }

        $researcher = Researcher::query()->create([
            'full_name' => $name,
            'email' => $this->nullableString($data->email),
            'affiliation' => $this->nullableString($data->affiliation),
            'faculty_id' => $data->facultyId,
            'faculty_member_id' => $data->facultyMemberId,
            'registration_status' => 'registered',
            'created_by' => $userId,
        ]);
        $this->auditService->log('researcher.created', $userId, Researcher::class, (int) $researcher->getKey());

        return (int) $researcher->getKey();
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

        if ($payload !== null && $user->isLimitedEditor()
            && ! $this->access->canManageFaculty($user, $this->facultyScope($this->nullableInt($payload['faculty_id'] ?? null), $this->nullableInt($payload['faculty_member_id'] ?? null)))) {
            throw new AuthorizationException('You may only manage research publications of your granted faculties.');
        }
    }

    /** The publication's own faculty decides; without one, its researcher's faculty does. */
    private function facultyScope(?int $facultyId, ?int $facultyMemberId): ?string
    {
        $faculty = match (true) {
            $facultyId !== null => Faculty::query()->find($facultyId),
            $facultyMemberId !== null => FacultyMember::query()->with('faculty')->find($facultyMemberId)?->faculty,
            default => null,
        };

        if (! $faculty instanceof Faculty) {
            return null;
        }

        $scope = (string) ($faculty->public_slug ?: $faculty->faculty_scope_slug ?: $faculty->slug);

        return $scope !== '' ? $this->access->canonicalFacultySlug($scope) : null;
    }

    /** @param  list<string>  $scopes */
    private function whereFacultyScope(Builder $query, array $scopes): Builder
    {
        return $query->where(fn (Builder $scopeQuery): Builder => $scopeQuery
            ->whereIn('faculty_scope_slug', $scopes)
            ->orWhereIn('public_slug', $scopes)
            ->orWhereIn('slug', $scopes));
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
            ->where('source_table', 'jx_member_categories')
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
