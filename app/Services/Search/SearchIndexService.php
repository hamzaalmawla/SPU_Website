<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Contracts\Search\SearchIndexServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\News\NewsArticle;
use App\Models\Page\Page;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Research\ResearchPublication;
use App\Models\Search\SearchDocument;
use App\Models\Shared\MigrationLog;
use App\Support\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds and maintains the derived public-search index.
 *
 * Every domain keeps its own idea of "public" — news uses status + is_enabled +
 * published_at, research has a two-branch legacy/native gate, people use
 * publication_status — so this service never reimplements those rules. It calls
 * each model's own public scope, which means a record can only reach the index
 * through the same gate that lets it reach its own public page.
 *
 * One document is written per (record, locale). When a record has no
 * translation in a locale, the other locale's text is indexed under it so the
 * record stays findable in both languages; the URL and display title always
 * belong to the row's own locale.
 *
 * Text is reduced to plain text and folded through SearchTextNormalizer at
 * index time. Nothing is normalized at query time except the query itself, so
 * matching never depends on the database collation.
 */
final class SearchIndexService implements SearchIndexServiceInterface
{
    private const LOCALES = ['ar', 'en'];

    /**
     * Faculties reachable through the public /faculties routes. Mirrors the
     * route constraint in routes/web.php and FacultyPageService::FACULTY_SLUGS;
     * indexing anything else would produce links that 404.
     *
     * @var list<string>
     */
    private const FACULTY_SLUGS = [
        'medicine',
        'dentistry',
        'pharmacy',
        'artificial-intelligence',
        'building-construction-engineering',
        'petroleum',
        'business-administration',
    ];

    /**
     * Faculty subpages that the /faculties/{faculty}/{subpage} route accepts.
     * 'study-plan' is deliberately absent: it has its own dedicated route.
     *
     * @var list<string>
     */
    private const FACULTY_SUBPAGE_SLUGS = [
        'overview',
        'departments',
        'labs',
        'projects',
        'alumni',
        'valedictorians',
        'training',
        'research',
        'members',
    ];

    /**
     * Filter bucket and tie-break weight per source. Weight only separates
     * documents whose text scores identically: a faculty landing page should
     * win a tie against one news article among two thousand.
     *
     * @var array<string, array{type: string, weight: int, model: class-string<Model>}>
     */
    private const SOURCE_CONFIG = [
        'news' => ['type' => 'news', 'weight' => 10, 'model' => NewsArticle::class],
        'research' => ['type' => 'research', 'weight' => 8, 'model' => ResearchPublication::class],
        'pages' => ['type' => 'pages', 'weight' => 12, 'model' => Page::class],
        'faculty-members' => ['type' => 'people', 'weight' => 9, 'model' => FacultyMember::class],
        'persons' => ['type' => 'people', 'weight' => 9, 'model' => Person::class],
        'faculties' => ['type' => 'pages', 'weight' => 14, 'model' => Faculty::class],
        'faculty-pages' => ['type' => 'pages', 'weight' => 11, 'model' => FacultyPage::class],
    ];

    /** Longest plain-text body kept per document. */
    private const MAX_BODY_LENGTH = 20000;

    /** Longest stored summary, which is also the snippet source. */
    private const MAX_SUMMARY_LENGTH = 400;

    private const CHUNK_SIZE = 200;

    /**
     * Page shell rows, keyed by id, cached for the lifetime of one rebuild so
     * that resolving a page's ancestor slug path stays a single query.
     *
     * @var array<int, array{parent_id: int|null, slug: string, is_homepage_shell: bool}>|null
     */
    private ?array $pageShells = null;

    /**
     * Legacy source ids for research publications, keyed by publication id.
     *
     * @var array<int, int>|null
     */
    private ?array $researchSourceIds = null;

    /**
     * Memoized table check. The sync hooks call this on every content write, so
     * it must not cost a schema query each time.
     */
    private ?bool $available = null;

    public function isAvailable(): bool
    {
        return $this->available ??= Schema::hasTable('search_documents');
    }

    public function rebuild(?string $source = null, bool $fresh = false): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }

        $sources = $source !== null ? [$source] : self::SOURCES;
        $written = 0;

        foreach ($sources as $sourceKey) {
            if (! array_key_exists($sourceKey, self::SOURCE_CONFIG)) {
                continue;
            }

            $written += $this->rebuildSource($sourceKey, $fresh);
        }

        $this->resetLookups();

        return $written;
    }

    public function syncRecord(string $source, int $recordId): bool
    {
        if (! $this->isAvailable() || ! array_key_exists($source, self::SOURCE_CONFIG)) {
            return false;
        }

        $record = $this->publicQuery($source)->whereKey($recordId)->first();

        if (! $record instanceof Model) {
            $this->resetLookups();

            return $this->forgetRecord($source, $recordId);
        }

        $documents = $this->documentsFor($source, $record);
        $this->resetLookups();

        if ($documents === []) {
            return $this->forgetRecord($source, $recordId);
        }

        $this->writeDocuments($documents);
        $this->pruneLocales($source, $recordId, array_column($documents, 'locale'));

        return true;
    }

    public function forgetRecord(string $source, int $recordId): bool
    {
        if (! $this->isAvailable() || ! array_key_exists($source, self::SOURCE_CONFIG)) {
            return false;
        }

        SearchDocument::query()
            ->where('searchable_type', self::SOURCE_CONFIG[$source]['model'])
            ->where('searchable_id', $recordId)
            ->delete();

        return true;
    }

    // ── Rebuild ──────────────────────────────────────────────────────────────

    private function rebuildSource(string $source, bool $fresh): int
    {
        $model = self::SOURCE_CONFIG[$source]['model'];

        if ($fresh) {
            SearchDocument::query()->where('searchable_type', $model)->delete();
        }

        // Everything written by this pass gets a fresh updated_at; anything left
        // behind with an older stamp is a record that is no longer public.
        $startedAt = Carbon::now();
        $written = 0;

        $this->publicQuery($source)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (EloquentCollection $records) use ($source, &$written): void {
                $documents = [];

                foreach ($records as $record) {
                    foreach ($this->documentsFor($source, $record) as $document) {
                        $documents[] = $document;
                    }
                }

                if ($documents !== []) {
                    $this->writeDocuments($documents);
                    $written += count($documents);
                }
            });

        SearchDocument::query()
            ->where('searchable_type', $model)
            ->where('updated_at', '<', $startedAt)
            ->delete();

        return $written;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    private function writeDocuments(array $documents): void
    {
        $now = Carbon::now();

        foreach ($documents as $index => $document) {
            $documents[$index]['created_at'] = $now;
            $documents[$index]['updated_at'] = $now;
        }

        SearchDocument::query()->upsert(
            $documents,
            ['searchable_type', 'searchable_id', 'locale'],
            ['type', 'title', 'title_normalized', 'summary', 'body_normalized', 'url', 'meta', 'published_at', 'weight', 'updated_at'],
        );
    }

    /**
     * @param  list<string>  $keptLocales
     */
    private function pruneLocales(string $source, int $recordId, array $keptLocales): void
    {
        SearchDocument::query()
            ->where('searchable_type', self::SOURCE_CONFIG[$source]['model'])
            ->where('searchable_id', $recordId)
            ->whereNotIn('locale', $keptLocales === [] ? [''] : $keptLocales)
            ->delete();
    }

    private function resetLookups(): void
    {
        $this->pageShells = null;
        $this->researchSourceIds = null;
    }

    // ── Public-record queries, one per source ────────────────────────────────

    private function publicQuery(string $source): Builder
    {
        return match ($source) {
            'news' => NewsArticle::query()->public()->with('translations'),
            'research' => ResearchPublication::query()->public()->with('translations'),
            'pages' => Page::query()
                ->where('status', 'published')
                ->where('is_enabled', true)
                ->whereNotNull('published_at')
                ->where('is_homepage_shell', false)
                ->where(function (Builder $query): void {
                    $query->whereNull('publish_at')->orWhere('publish_at', '<=', Carbon::now());
                })
                ->with('translations'),
            'faculty-members' => FacultyMember::query()->public()->with(['translations', 'faculty.translations']),
            'persons' => Person::query()->public()->with('translations'),
            'faculties' => Faculty::query()
                ->enabled()
                ->whereIn('public_slug', self::FACULTY_SLUGS)
                ->with('translations'),
            'faculty-pages' => FacultyPage::query()
                ->enabled()
                ->whereIn('slug', self::FACULTY_SUBPAGE_SLUGS)
                ->whereHas('faculty', function (Builder $query): void {
                    $query->enabled()->whereIn('public_slug', self::FACULTY_SLUGS);
                })
                ->with(['translations', 'faculty.translations']),
            default => SearchDocument::query()->whereRaw('1 = 0'),
        };
    }

    // ── Document construction ────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function documentsFor(string $source, Model $record): array
    {
        return match ($source) {
            'news' => $this->newsDocuments($record),
            'research' => $this->researchDocuments($record),
            'pages' => $this->pageDocuments($record),
            'faculty-members' => $this->facultyMemberDocuments($record),
            'persons' => $this->personDocuments($record),
            'faculties' => $this->facultyDocuments($record),
            'faculty-pages' => $this->facultyPageDocuments($record),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newsDocuments(Model $record): array
    {
        if (! $record instanceof NewsArticle) {
            return [];
        }

        $documents = [];
        // Legacy jx_categories rows carry a meaningless published_at, mirroring
        // NewsService::articlePublishedAt().
        $publishedAt = $record->legacy_source_table === 'jx_categories' ? null : $record->published_at;

        foreach (self::LOCALES as $locale) {
            $translation = $this->translationFor($record, $locale);

            if ($translation === null) {
                continue;
            }

            $title = SearchTextNormalizer::plainText((string) $translation->title);

            if ($title === '') {
                continue;
            }

            $body = SearchTextNormalizer::plainText((string) $translation->body);
            $summary = SearchTextNormalizer::plainText((string) $translation->excerpt);

            $documents[] = $this->document(
                source: 'news',
                record: $record,
                locale: $locale,
                title: $title,
                summary: $summary !== '' ? $summary : $body,
                bodyParts: [$title, $summary, $body],
                url: '/'.$locale.'/news/'.$record->getKey(),
                meta: null,
                publishedAt: $publishedAt,
            );
        }

        return $documents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function researchDocuments(Model $record): array
    {
        if (! $record instanceof ResearchPublication) {
            return [];
        }

        // A placeholder title means the legacy import never recovered any real
        // metadata for this row. ResearchPageService drops such rows from the
        // public archive, so indexing one would put a result on the page that
        // links to nothing worth reading. One placeholder title condemns the
        // whole record, not just that locale.
        if ($this->hasPlaceholderTitle($record)) {
            return [];
        }

        $slug = $this->researchSlug($record);
        $documents = [];
        $publishedAt = $record->published_at;

        foreach (self::LOCALES as $locale) {
            $translation = $this->translationFor($record, $locale);

            if ($translation === null) {
                continue;
            }

            $title = SearchTextNormalizer::plainText((string) $translation->title);

            if ($title === '') {
                continue;
            }

            $authors = SearchTextNormalizer::plainText((string) $translation->authors);
            $abstract = SearchTextNormalizer::plainText((string) $translation->abstract);
            $excerpt = SearchTextNormalizer::plainText((string) $translation->excerpt);
            $keywords = is_array($translation->keywords)
                ? implode(' ', array_map(static fn (mixed $keyword): string => is_scalar($keyword) ? (string) $keyword : '', $translation->keywords))
                : '';

            $documents[] = $this->document(
                source: 'research',
                record: $record,
                locale: $locale,
                title: $title,
                summary: $excerpt !== '' ? $excerpt : $abstract,
                bodyParts: [
                    $title,
                    $authors,
                    $keywords,
                    $excerpt,
                    $abstract,
                    SearchTextNormalizer::plainText((string) $translation->publisher),
                    SearchTextNormalizer::plainText((string) $translation->citation),
                ],
                url: '/'.$locale.'/research/publications/'.rawurlencode($slug),
                meta: $authors !== '' ? $authors : null,
                publishedAt: $publishedAt,
            );
        }

        return $documents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pageDocuments(Model $record): array
    {
        if (! $record instanceof Page) {
            return [];
        }

        $documents = [];

        foreach (self::LOCALES as $locale) {
            // Pages are only indexed for a locale they actually have, because
            // the public page renders that locale's translation directly.
            $translation = $record->translations->firstWhere('locale', $locale);

            if ($translation === null) {
                continue;
            }

            $title = SearchTextNormalizer::plainText((string) $translation->title);

            if ($title === '') {
                continue;
            }

            $excerpt = SearchTextNormalizer::plainText((string) ($translation->excerpt ?? ''));
            $body = SearchTextNormalizer::plainText((string) ($translation->body ?? ''));
            $payloadText = $this->payloadText([
                $translation->hero_payload,
                $translation->overview_cards_payload,
                $translation->body_payload,
                $translation->cta_payload,
            ]);

            $documents[] = $this->document(
                source: 'pages',
                record: $record,
                locale: $locale,
                title: $title,
                summary: $excerpt !== '' ? $excerpt : ($body !== '' ? $body : $payloadText),
                bodyParts: [
                    $title,
                    SearchTextNormalizer::plainText((string) ($translation->headline ?? '')),
                    SearchTextNormalizer::plainText((string) ($translation->subheadline ?? '')),
                    $excerpt,
                    $body,
                    $payloadText,
                ],
                url: $this->pagePath($record, $locale),
                meta: null,
                publishedAt: $record->published_at,
            );
        }

        return $documents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facultyMemberDocuments(Model $record): array
    {
        if (! $record instanceof FacultyMember) {
            return [];
        }

        $documents = [];

        foreach (self::LOCALES as $locale) {
            $translation = $this->translationFor($record, $locale);

            if ($translation === null) {
                continue;
            }

            $name = SearchTextNormalizer::plainText((string) $translation->full_name);

            if ($name === '') {
                continue;
            }

            $position = SearchTextNormalizer::plainText((string) ($translation->position ?: $translation->title));
            $bio = SearchTextNormalizer::plainText((string) $translation->bio);
            $specializations = is_array($translation->specializations)
                ? implode(', ', array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $translation->specializations))
                : '';
            $facultyName = $this->facultyName($record->faculty, $locale);

            $documents[] = $this->document(
                source: 'faculty-members',
                record: $record,
                locale: $locale,
                title: $name,
                summary: $bio !== '' ? $bio : trim($position.' '.$specializations),
                // The faculty name is indexed so that "medicine" finds the
                // faculty's staff, not only the faculty page itself.
                bodyParts: [$name, $position, $specializations, $facultyName, $bio],
                url: '/'.$locale.'/about/profile/'.$record->slug,
                meta: $this->joinMeta([$position, $facultyName]),
                publishedAt: $record->published_at,
            );
        }

        return $documents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function personDocuments(Model $record): array
    {
        if (! $record instanceof Person) {
            return [];
        }

        $documents = [];

        foreach (self::LOCALES as $locale) {
            $translation = $this->translationFor($record, $locale);

            if ($translation === null) {
                continue;
            }

            $name = SearchTextNormalizer::plainText((string) $translation->name);

            if ($name === '') {
                continue;
            }

            $role = SearchTextNormalizer::plainText((string) ($translation->role ?: $translation->position ?: $translation->title));
            $bio = SearchTextNormalizer::plainText((string) $translation->bio);
            $specializations = is_array($translation->specializations)
                ? implode(', ', array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $translation->specializations))
                : '';

            $documents[] = $this->document(
                source: 'persons',
                record: $record,
                locale: $locale,
                title: $name,
                summary: $bio !== '' ? $bio : $role,
                bodyParts: [
                    $name,
                    $role,
                    $specializations,
                    SearchTextNormalizer::plainText((string) $translation->education),
                    (string) $record->faculty_scope_slug,
                    $bio,
                ],
                url: '/'.$locale.'/about/profile/'.$record->slug,
                meta: $role !== '' ? $role : null,
                publishedAt: $record->published_at,
            );
        }

        return $documents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facultyDocuments(Model $record): array
    {
        if (! $record instanceof Faculty) {
            return [];
        }

        $slug = (string) ($record->public_slug ?: $record->slug);
        $documents = [];

        foreach (self::LOCALES as $locale) {
            $translation = $this->translationFor($record, $locale);

            if ($translation === null) {
                continue;
            }

            $name = SearchTextNormalizer::plainText((string) $translation->name);

            if ($name === '') {
                continue;
            }

            $shortDescription = SearchTextNormalizer::plainText((string) $translation->short_description);
            $description = SearchTextNormalizer::plainText((string) $translation->description);

            $documents[] = $this->document(
                source: 'faculties',
                record: $record,
                locale: $locale,
                title: $name,
                summary: $shortDescription !== '' ? $shortDescription : $description,
                bodyParts: [
                    $name,
                    SearchTextNormalizer::plainText((string) $translation->catalog_title),
                    $shortDescription,
                    $description,
                ],
                url: '/'.$locale.'/faculties/'.$slug,
                meta: null,
                publishedAt: null,
            );
        }

        return $documents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facultyPageDocuments(Model $record): array
    {
        if (! $record instanceof FacultyPage || ! $record->faculty instanceof Faculty) {
            return [];
        }

        $facultySlug = (string) ($record->faculty->public_slug ?: $record->faculty->slug);
        $documents = [];

        foreach (self::LOCALES as $locale) {
            $translation = $this->translationFor($record, $locale);

            if ($translation === null) {
                continue;
            }

            $title = SearchTextNormalizer::plainText((string) $translation->title);

            if ($title === '') {
                continue;
            }

            $summary = SearchTextNormalizer::plainText((string) $translation->summary);
            $body = SearchTextNormalizer::plainText((string) $translation->body);
            $sections = $this->payloadText([$translation->sections_json]);
            $facultyName = $this->facultyName($record->faculty, $locale);

            $documents[] = $this->document(
                source: 'faculty-pages',
                record: $record,
                locale: $locale,
                title: $title,
                summary: $summary !== '' ? $summary : ($body !== '' ? $body : $sections),
                bodyParts: [$title, $facultyName, $summary, $body, $sections],
                url: '/'.$locale.'/faculties/'.$facultySlug.'/'.$record->slug,
                meta: $facultyName !== '' ? $facultyName : null,
                publishedAt: null,
            );
        }

        return $documents;
    }

    // ── Shared document assembly ─────────────────────────────────────────────

    /**
     * @param  list<string>  $bodyParts
     * @return array<string, mixed>
     */
    private function document(
        string $source,
        Model $record,
        string $locale,
        string $title,
        string $summary,
        array $bodyParts,
        string $url,
        ?string $meta,
        mixed $publishedAt,
    ): array {
        $config = self::SOURCE_CONFIG[$source];
        $body = trim(implode(' ', array_filter(
            array_map(static fn (string $part): string => trim($part), $bodyParts),
            static fn (string $part): bool => $part !== '',
        )));

        return [
            'searchable_type' => $config['model'],
            'searchable_id' => (int) $record->getKey(),
            'type' => $config['type'],
            'locale' => $locale,
            'title' => Str::limit($title, 500, ''),
            'title_normalized' => Str::limit(SearchTextNormalizer::normalize($title), 500, ''),
            'summary' => $summary === '' ? null : Str::limit($summary, self::MAX_SUMMARY_LENGTH),
            'body_normalized' => Str::limit(SearchTextNormalizer::normalize($body), self::MAX_BODY_LENGTH, ''),
            'url' => Str::limit($url, 500, ''),
            'meta' => $meta === null || $meta === '' ? null : Str::limit($meta, 250),
            'published_at' => $publishedAt instanceof Carbon ? $publishedAt : ($publishedAt instanceof \DateTimeInterface ? Carbon::instance($publishedAt) : null),
            'weight' => $config['weight'],
        ];
    }

    /**
     * Pick a record's translation for a locale, falling back to the other
     * locale so a single-language record still appears in both languages.
     */
    private function translationFor(Model $record, string $locale): ?Model
    {
        $translations = $record->getRelationValue('translations');

        if (! $translations instanceof EloquentCollection) {
            return null;
        }

        $translation = $translations->firstWhere('locale', $locale);

        if ($translation instanceof Model) {
            return $translation;
        }

        foreach (self::LOCALES as $candidate) {
            $fallback = $translations->firstWhere('locale', $candidate);

            if ($fallback instanceof Model) {
                return $fallback;
            }
        }

        return $translations->first();
    }

    private function facultyName(?Faculty $faculty, string $locale): string
    {
        if (! $faculty instanceof Faculty) {
            return '';
        }

        $translation = $this->translationFor($faculty, $locale);

        return $translation === null ? '' : SearchTextNormalizer::plainText((string) $translation->name);
    }

    /**
     * @param  list<string>  $parts
     */
    private function joinMeta(array $parts): ?string
    {
        $parts = array_values(array_filter(
            array_map(static fn (string $part): string => trim($part), $parts),
            static fn (string $part): bool => $part !== '',
        ));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Flatten the human-readable strings out of CMS payload JSON.
     *
     * Builder payloads mix prose with URLs, icon names and layout keys. Only
     * prose is useful in an index, so anything that looks like a machine value
     * — a path, a URL, or a bare slug token — is dropped, as is any value under
     * a structural key.
     *
     * @param  list<mixed>  $payloads
     */
    private function payloadText(array $payloads): string
    {
        $collected = [];

        foreach ($payloads as $payload) {
            $this->collectPayloadStrings($payload, $collected);
        }

        return SearchTextNormalizer::plainText(implode(' ', $collected));
    }

    /**
     * @param  list<string>  $collected
     */
    private function collectPayloadStrings(mixed $payload, array &$collected, string $key = ''): void
    {
        if (count($collected) > 400) {
            return;
        }

        if (is_array($payload)) {
            foreach ($payload as $childKey => $child) {
                $this->collectPayloadStrings($child, $collected, is_string($childKey) ? $childKey : $key);
            }

            return;
        }

        if (! is_string($payload)) {
            return;
        }

        $structuralKeys = ['url', 'href', 'link', 'image', 'icon', 'slug', 'target', 'class', 'id', 'type', 'key', 'variant', 'style', 'color', 'align'];

        if (in_array(strtolower($key), $structuralKeys, true)) {
            return;
        }

        $value = trim($payload);

        if ($value === '' || str_starts_with($value, '/') || str_starts_with($value, '#') || preg_match('#^https?://#i', $value) === 1) {
            return;
        }

        // Bare slug-like tokens ("hero-primary", "col_2") are layout metadata.
        if (preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $value) === 1 && ! str_contains($value, ' ')) {
            return;
        }

        $collected[] = $value;
    }

    // ── URL resolution helpers ───────────────────────────────────────────────

    /**
     * Resolve a CMS page's public path by walking its ancestors, mirroring
     * SitemapService::buildPagePath(). The whole page shell is loaded once per
     * rebuild so this stays a single query no matter how many pages are indexed.
     */
    private function pagePath(Page $page, string $locale): string
    {
        $shells = $this->pageShells();
        $segments = [];
        $cursor = (int) $page->getKey();
        $guard = 0;

        while ($guard++ < 20) {
            $shell = $shells[$cursor] ?? null;

            if ($shell === null) {
                break;
            }

            if (! $shell['is_homepage_shell'] && $shell['slug'] !== '') {
                $segments[] = $shell['slug'];
            }

            if ($shell['parent_id'] === null) {
                break;
            }

            $cursor = $shell['parent_id'];
        }

        $segments = array_reverse($segments);

        return '/'.$locale.'/'.implode('/', $segments);
    }

    /**
     * @return array<int, array{parent_id: int|null, slug: string, is_homepage_shell: bool}>
     */
    private function pageShells(): array
    {
        if ($this->pageShells !== null) {
            return $this->pageShells;
        }

        $shells = [];

        foreach (Page::query()->get(['id', 'parent_id', 'slug', 'is_homepage_shell']) as $page) {
            $shells[(int) $page->getKey()] = [
                'parent_id' => $page->parent_id === null ? null : (int) $page->parent_id,
                'slug' => (string) $page->slug,
                'is_homepage_shell' => (bool) $page->is_homepage_shell,
            ];
        }

        return $this->pageShells = $shells;
    }

    /**
     * Whether any of a publication's titles is a legacy import placeholder,
     * mirroring the filter in ResearchPageService::databasePublicationItem().
     */
    private function hasPlaceholderTitle(ResearchPublication $publication): bool
    {
        foreach ($publication->translations as $translation) {
            $title = SearchTextNormalizer::plainText((string) $translation->title);

            if (preg_match('/^(?:Legacy research publication|Research publication)\s+\d+$/i', $title) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reproduce ResearchPageService::databasePublicationSlug() so that a search
     * result links to the same URL the research archive links to.
     */
    private function researchSlug(ResearchPublication $publication): string
    {
        $translations = $publication->translations->keyBy('locale');
        $title = (string) (($translations->get('en')?->title ?? null) ?: ($translations->get('ar')?->title ?? null) ?: 'legacy-research-publication');
        $slug = Str::slug($title);
        $sourceId = $this->researchSourceIds()[(int) $publication->getKey()] ?? null;
        $suffix = (string) ($sourceId ?? $publication->getKey());

        return ($slug !== '' ? $slug : 'legacy-research-publication').'-'.$suffix;
    }

    /**
     * @return array<int, int>
     */
    private function researchSourceIds(): array
    {
        if ($this->researchSourceIds !== null) {
            return $this->researchSourceIds;
        }

        $sourceIds = MigrationLog::query()
            ->where('module', 'research')
            ->where('source_table', 'jx_member_categories')
            ->where('target_table', 'research_publications')
            ->where('status', 'success')
            ->pluck('source_id', 'target_id')
            ->map(static fn (mixed $sourceId): int => (int) $sourceId)
            ->all();

        $normalized = [];

        foreach ($sourceIds as $targetId => $sourceId) {
            $normalized[(int) $targetId] = $sourceId;
        }

        return $this->researchSourceIds = $normalized;
    }
}
