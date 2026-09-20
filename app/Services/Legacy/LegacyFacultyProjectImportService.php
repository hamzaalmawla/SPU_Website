<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyFacultyProjectImportServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyFacultyProjectImportResultDTO;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultyStudentProjectTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\HtmlSanitizer;
use App\Support\LegacyImport\TextCleaner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class LegacyFacultyProjectImportService implements LegacyFacultyProjectImportServiceInterface
{
    private const APPROVAL_TOKEN = 'faculty-projects-20260827';

    private const LEGACY_BASE_URL = 'https://www.spu.edu.sy';

    /** @var array<int, string> */
    private const FACULTY_SLUGS = [
        24 => 'medicine',
        34 => 'dentistry',
        44 => 'pharmacy',
        54 => 'artificial-intelligence',
        64 => 'petroleum',
        74 => 'business-administration',
    ];

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly TextCleaner $textCleaner,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function import(
        string $dumpPath,
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
        bool $enableVisible = false,
        bool $verifyMedia = false,
    ): LegacyFacultyProjectImportResultDTO {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing faculty projects requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $resolvedPath = $this->resolvedDumpPath($dumpPath);
        $batch = is_string($batch) && trim($batch) !== '' ? trim($batch) : 'faculty-projects-'.now()->format('Ymd_His');
        [$projects, $children] = $this->parseDump($resolvedPath);
        $facultyCounts = array_fill_keys(array_values(self::FACULTY_SLUGS), 0);
        $skipReasonCounts = [];
        $importableProjects = 0;
        $importedProjects = 0;
        $visibleProjects = 0;
        $hiddenProjects = 0;
        $verifiedMedia = 0;
        $failedMedia = 0;
        $sortOrders = $this->sortOrders($projects);
        $facultyIds = $write ? $this->facultyIds() : [];
        $verifiedReferences = $write && $verifyMedia
            ? $this->verifyMediaReferences($projects, $children, $verifiedMedia, $failedMedia)
            : [];

        foreach ($projects as $project) {
            $serviceType = (int) $project['service_type'];
            $facultySlug = self::FACULTY_SLUGS[$serviceType];
            $facultyCounts[$facultySlug]++;
            $visible = (int) $project['is_visible'] !== 0;
            $visible ? $visibleProjects++ : $hiddenProjects++;
            $projectChildren = $children[(int) $project['id']] ?? [];
            $titles = $this->titles($project, $projectChildren);

            if ($titles === null) {
                $skipReasonCounts['missing_title'] = ($skipReasonCounts['missing_title'] ?? 0) + 1;

                continue;
            }

            $importableProjects++;

            if (! $write) {
                continue;
            }

            $facultyId = $facultyIds[$facultySlug] ?? null;
            if (! is_int($facultyId)) {
                throw new RuntimeException('Target faculty is missing: '.$facultySlug);
            }

            $payload = $this->projectPayload(
                project: $project,
                children: $projectChildren,
                facultySlug: $facultySlug,
                verifyMedia: $verifyMedia,
                verifiedReferences: $verifiedReferences,
            );

            $targetId = DB::transaction(function () use ($project, $payload, $titles, $facultyId, $facultySlug, $serviceType, $visible, $enableVisible, $sortOrders): int {
                $sourceId = (int) $project['id'];
                $target = FacultyStudentProject::query()->updateOrCreate(
                    ['legacy_source_id' => $sourceId],
                    [
                        'faculty_id' => $facultyId,
                        'legacy_service_type' => $serviceType,
                        'slug' => $facultySlug.'-project-'.$sourceId,
                        'image' => $payload['image'],
                        'gallery_json' => $payload['gallery'],
                        'documents_json' => $payload['documents'],
                        'sort_order' => $sortOrders[$sourceId] ?? 0,
                        'is_enabled' => $enableVisible && $visible,
                    ],
                );

                $contributors = $this->contributors($payload['body']['ar'], $titles['ar']);
                foreach (['ar', 'en'] as $locale) {
                    $body = $payload['body'][$locale];
                    FacultyStudentProjectTranslation::query()->updateOrCreate(
                        ['faculty_student_project_id' => (int) $target->getKey(), 'locale' => $locale],
                        [
                            'title' => $titles[$locale],
                            'summary' => $body !== [] ? Str::limit($body[0], 240, '') : null,
                            'body_json' => $body,
                            'tag' => $locale === 'ar' ? 'مشروع طلابي' : 'Student Project',
                            'team' => $contributors['team'],
                            'supervisor' => $contributors['supervisor'],
                        ],
                    );
                }

                return (int) $target->getKey();
            });

            MigrationLog::query()->updateOrCreate(
                [
                    'module' => 'faculty_projects',
                    'source_table' => 'jx_categories',
                    'source_id' => (int) $project['id'],
                    'target_table' => 'faculty_student_projects',
                ],
                [
                    'batch_name' => $batch,
                    'target_id' => $targetId,
                    'status' => 'success',
                    'message' => 'Imported legacy faculty student project.',
                    'metadata' => [
                        'legacy_service_type' => $serviceType,
                        'faculty_slug' => $facultySlug,
                        'legacy_visible' => $visible,
                        'enabled' => $enableVisible && $visible,
                        'attachment_count' => count($projectChildren),
                    ],
                ],
            );
            $importedProjects++;
        }

        if ($importedProjects > 0 && ! $this->cacheService->flushTags(['facilities', 'public-pages', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }

        return new LegacyFacultyProjectImportResultDTO(
            written: $write,
            batch: $batch,
            enabledVisibleProjects: $enableVisible,
            scannedProjects: count($projects),
            importableProjects: $importableProjects,
            importedProjects: $importedProjects,
            visibleProjects: $visibleProjects,
            hiddenProjects: $hiddenProjects,
            verifiedMedia: $verifiedMedia,
            failedMedia: $failedMedia,
            facultyCounts: $facultyCounts,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function resolvedDumpPath(string $dumpPath): string
    {
        $candidate = trim($dumpPath);
        if ($candidate === '') {
            throw new InvalidArgumentException('A SQL dump path is required.');
        }

        $candidate = $this->isAbsolutePath($candidate) ? $candidate : base_path($candidate);
        $resolved = realpath($candidate);
        if (! is_string($resolved) || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new InvalidArgumentException('The SQL dump could not be read: '.$candidate);
        }

        return $resolved;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /** @return array{0: list<array<string, mixed>>, 1: array<int, list<array<string, mixed>>>} */
    private function parseDump(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the SQL dump.');
        }

        $projects = [];
        $children = [];
        $table = null;

        try {
            while (($line = fgets($handle)) !== false) {
                if (str_starts_with($line, 'INSERT INTO `jx_categories`')) {
                    $table = 'categories';

                    continue;
                }
                if (str_starts_with($line, 'INSERT INTO `jx_items`')) {
                    $table = 'items';

                    continue;
                }
                if (str_starts_with($line, 'INSERT INTO `')) {
                    $table = null;

                    continue;
                }

                $line = trim($line);
                if ($table === null || $line === '' || $line[0] !== '(') {
                    continue;
                }

                $values = str_getcsv(substr(rtrim($line, ',;'), 1, -1), ',', "'", '\\');
                if ($table === 'categories' && count($values) >= 38) {
                    $serviceType = (int) $values[22];
                    if (isset(self::FACULTY_SLUGS[$serviceType])) {
                        $projects[] = [
                            'id' => (int) $values[0],
                            'en_name' => $this->sqlString($values[1]),
                            'ar_name' => $this->sqlString($values[2]),
                            'en_data' => $this->sqlString($values[16]),
                            'ar_data' => $this->sqlString($values[17]),
                            'service_type' => $serviceType,
                            'category_order' => is_numeric(trim($values[23])) ? (int) $values[23] : 0,
                            'photo' => $this->sqlString($values[24]),
                            'is_visible' => (int) $values[25],
                        ];
                    }
                } elseif ($table === 'items' && count($values) >= 34) {
                    $serviceType = (int) $values[2];
                    if (isset(self::FACULTY_SLUGS[$serviceType])) {
                        $children[(int) $values[1]][] = [
                            'id' => (int) $values[0],
                            'en_name' => $this->sqlString($values[3]),
                            'ar_name' => $this->sqlString($values[4]),
                            'photo' => $this->sqlString($values[8]),
                            'item_order' => is_numeric(trim($values[10])) ? (int) $values[10] : 0,
                            'en_file' => $this->sqlString($values[19]),
                            'ar_file' => $this->sqlString($values[20]),
                            'is_visible' => (int) $values[18],
                            'is_accepted' => (int) $values[30],
                        ];
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        $projectIds = array_fill_keys(array_map(static fn (array $project): int => (int) $project['id'], $projects), true);
        $children = array_intersect_key($children, $projectIds);
        foreach ($children as &$rows) {
            usort($rows, static fn (array $left, array $right): int => ((int) $right['item_order'] <=> (int) $left['item_order']) ?: ((int) $left['id'] <=> (int) $right['id']));
        }

        return [$projects, $children];
    }

    private function sqlString(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }

        return $this->textCleaner->clean(str_replace(['\\r', '\\n', '\\t'], ["\r", "\n", "\t"], $value));
    }

    /** @param list<array<string, mixed>> $projects @return array<int, int> */
    private function sortOrders(array $projects): array
    {
        $grouped = [];
        foreach ($projects as $project) {
            $grouped[(int) $project['service_type']][] = $project;
        }

        $orders = [];
        foreach ($grouped as $rows) {
            usort($rows, static fn (array $left, array $right): int => (int) $right['id'] <=> (int) $left['id']);
            foreach ($rows as $index => $row) {
                $orders[(int) $row['id']] = $index + 1;
            }
        }

        return $orders;
    }

    /** @return array<string, int> */
    private function facultyIds(): array
    {
        return Faculty::query()
            ->whereIn('public_slug', array_values(self::FACULTY_SLUGS))
            ->pluck('id', 'public_slug')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param array<string, mixed> $project @param list<array<string, mixed>> $children @return array{ar: string, en: string}|null */
    private function titles(array $project, array $children): ?array
    {
        $arabic = $this->usableTitle($project['ar_name'] ?? null);
        $english = $this->usableEnglish($project['en_name'] ?? null);

        if ($arabic === null && $english === null) {
            foreach ($children as $child) {
                $arabic = $this->usableTitle($child['ar_name'] ?? null) ?? $this->usableTitle($child['en_name'] ?? null);
                if ($arabic !== null) {
                    break;
                }
            }
        }

        $arabic ??= $english;
        if ($arabic === null) {
            return null;
        }

        return ['ar' => $arabic, 'en' => $english ?? $arabic];
    }

    private function usableTitle(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? preg_replace('/\s+/u', ' ', $value) : null;
    }

    private function usableEnglish(mixed $value): ?string
    {
        $value = $this->usableTitle($value);
        if ($value === null || preg_match('/^(?:under|sous) construction$/iu', $value) === 1) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  list<array<string, mixed>>  $children
     * @return array{image: ?string, gallery: list<string>, documents: list<array{file: string}>, body: array{ar: list<string>, en: list<string>}}
     */
    private function projectPayload(
        array $project,
        array $children,
        string $facultySlug,
        bool $verifyMedia,
        array $verifiedReferences,
    ): array {
        $images = [];
        $documents = [];
        $sourceId = (int) $project['id'];
        $this->classifyMedia($project['photo'] ?? null, $images, $documents);

        foreach ($children as $child) {
            if ((int) $child['is_visible'] === 0 || (int) $child['is_accepted'] === 0) {
                continue;
            }
            $this->classifyMedia($child['photo'] ?? null, $images, $documents);
            $this->classifyMedia($child['en_file'] ?? null, $images, $documents);
            $this->classifyMedia($child['ar_file'] ?? null, $images, $documents);
        }

        $images = array_values(array_unique(array_filter($images)));
        $documents = array_values(array_unique(array_filter($documents)));
        $localizedImages = array_values(array_filter(array_map(fn (string $path): string => $this->mediaReference($path, $facultySlug, $sourceId, $verifyMedia, $verifiedReferences), $images)));
        $localizedDocuments = array_values(array_filter(array_map(fn (string $path): array => [
            'file' => $this->mediaReference($path, $facultySlug, $sourceId, $verifyMedia, $verifiedReferences),
        ], $documents), static fn (array $document): bool => $document['file'] !== ''));

        return [
            'image' => $localizedImages[0] ?? null,
            'gallery' => $localizedImages,
            'documents' => $localizedDocuments,
            'body' => [
                'ar' => $this->paragraphs($project['ar_data'] ?? null),
                'en' => $this->paragraphs($this->usableEnglishBody($project['en_data'] ?? null)),
            ],
        ];
    }

    /** @param list<string> $images @param list<string> $documents */
    private function classifyMedia(mixed $path, array &$images, array &$documents): void
    {
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        $path = trim(str_replace('\\', '/', $path));
        $extension = strtolower(pathinfo((string) parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            $documents[] = $path;
        } elseif (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $images[] = $path;
        }
    }

    private function usableEnglishBody(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $plain = mb_strtolower(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = preg_replace('/[\s\x{00A0}]+/u', ' ', trim($plain)) ?? trim($plain);
        foreach (['under construction', 'sous construction', 'sorry, this page is temporarily unavailable', 'translation to english will', 'translation in english will', 'a translation in english will'] as $placeholder) {
            if (str_contains($plain, $placeholder)) {
                return null;
            }
        }

        return $value;
    }

    /** @return list<string> */
    private function paragraphs(mixed $html): array
    {
        if (! is_string($html) || trim($html) === '') {
            return [];
        }

        $sanitized = $this->htmlSanitizer->sanitize($html);
        if (! is_string($sanitized) || trim($sanitized) === '') {
            return [];
        }

        $withBreaks = preg_replace('/<\/?(?:p|div|h[1-6]|li|blockquote)[^>]*>|<br\s*\/?>/iu', "\n", $sanitized) ?? $sanitized;
        $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $paragraphs = preg_split('/[\r\n]+/u', $plain) ?: [];

        return array_values(array_filter(array_map(function (string $paragraph): ?string {
            $paragraph = preg_replace('/\s+/u', ' ', trim($paragraph)) ?? trim($paragraph);

            return $paragraph !== '' && ! in_array(mb_strtolower($paragraph), ['spu', 'قيد الإعداد'], true) ? $paragraph : null;
        }, $paragraphs)));
    }

    /** @param list<string> $paragraphs @return array{team: ?string, supervisor: ?string} */
    private function contributors(array $paragraphs, string $title): array
    {
        $team = null;
        $supervisor = null;

        if (preg_match('/^حالة الطالب(?:ة)?\s+(.+)$/u', trim($title), $matches) === 1) {
            $team = trim($matches[1]);
        }

        foreach ($paragraphs as $index => $paragraph) {
            if ($team === null && preg_match('/^إعداد(?:\s+الطالب(?:ة|ات|ين)?|\s+الطلاب)?\s*[:：]?\s*(.*)$/u', $paragraph, $matches) === 1) {
                $candidate = $this->cleanContributor($matches[1]);
                $team = $candidate !== null ? $candidate : $this->nextContributor($paragraphs, $index);
            }

            if ($team === null && preg_match('/مشروع تخرج الطالب(?:ة)?\s+(.+?)\s+من كلية/u', $paragraph, $matches) === 1) {
                $team = $this->cleanContributor($matches[1]);
            }

            if ($supervisor === null && preg_match('/^إشراف\s*[:：]?\s*(.*)$/u', $paragraph, $matches) === 1) {
                $candidate = $this->cleanContributor($matches[1]);
                $supervisor = $candidate !== null ? $candidate : $this->nextContributor($paragraphs, $index);
            }

            if ($supervisor === null && preg_match('/بإشراف\s+(.+?)(?:[،.]|$)/u', $paragraph, $matches) === 1) {
                $supervisor = $this->cleanContributor($matches[1]);
            }
        }

        return ['team' => $team, 'supervisor' => $supervisor];
    }

    /** @param list<string> $paragraphs */
    private function nextContributor(array $paragraphs, int $index): ?string
    {
        return isset($paragraphs[$index + 1]) ? $this->cleanContributor($paragraphs[$index + 1]) : null;
    }

    private function cleanContributor(string $value): ?string
    {
        $value = trim(preg_replace('/\s*\/\s*\d{4}\s*\/?$/u', '', $value) ?? $value, " \t\n\r\0\x0B:：");
        if ($value === '' || preg_match('/^(?:العام الدراسي|\d{4}(?:-\d{4})?)$/u', $value) === 1) {
            return null;
        }

        return $value;
    }

    private function mediaReference(
        string $sourcePath,
        string $facultySlug,
        int $sourceId,
        bool $verifyMedia,
        array $verifiedReferences = [],
    ): string {
        if (! $verifyMedia) {
            return $this->legacyMediaPath($sourcePath);
        }

        $referenceKey = $this->mediaReferenceKey($facultySlug, $sourceId, $sourcePath);

        return $verifiedReferences[$referenceKey] ?? '';
    }

    /**
     * @param  list<array<string, mixed>>  $projects
     * @param  array<int, list<array<string, mixed>>>  $children
     * @return array<string, string>
     */
    private function verifyMediaReferences(array $projects, array $children, int &$verified, int &$failed): array
    {
        $references = [];
        $pending = [];

        foreach ($projects as $project) {
            $sourceId = (int) $project['id'];
            $serviceType = (int) $project['service_type'];
            $facultySlug = self::FACULTY_SLUGS[$serviceType];
            $projectChildren = $children[$sourceId] ?? [];
            $titles = $this->titles($project, $projectChildren);
            if ($titles === null) {
                continue;
            }

            $images = [];
            $documents = [];
            $this->classifyMedia($project['photo'] ?? null, $images, $documents);
            foreach ($projectChildren as $child) {
                if ((int) $child['is_visible'] === 0 || (int) $child['is_accepted'] === 0) {
                    continue;
                }
                $this->classifyMedia($child['photo'] ?? null, $images, $documents);
                $this->classifyMedia($child['en_file'] ?? null, $images, $documents);
                $this->classifyMedia($child['ar_file'] ?? null, $images, $documents);
            }

            foreach (array_values(array_unique([...$images, ...$documents])) as $sourcePath) {
                $url = $this->legacyMediaUrl($sourcePath);
                $referenceKey = $this->mediaReferenceKey($facultySlug, $sourceId, $sourcePath);
                $requestKey = sha1($referenceKey);
                $pending[$requestKey] = compact('referenceKey', 'sourcePath', 'url');
            }
        }

        foreach (array_chunk($pending, 5, true) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk): array {
                $requests = [];
                foreach ($chunk as $key => $item) {
                    $requests[] = $pool->as($key)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SPU-Migration/1.0)'])
                        ->timeout(30)
                        ->get($item['url']);
                }

                return $requests;
            });

            $retry = [];
            foreach ($chunk as $key => $item) {
                $response = $responses[$key] ?? null;
                $contents = $response instanceof Response && $response->successful() ? $response->body() : '';
                if (! $this->validMediaContents($contents, basename((string) parse_url($item['url'], PHP_URL_PATH)))) {
                    if (! $response instanceof Response || $response->status() !== 404) {
                        $retry[] = $item;
                    } else {
                        $references[$item['referenceKey']] = '';
                        $failed++;
                    }

                    continue;
                }

                $references[$item['referenceKey']] = $this->legacyMediaPath($item['sourcePath']);
                $verified++;
            }

            foreach ($retry as $item) {
                try {
                    $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SPU-Migration/1.0)'])
                        ->timeout(30)
                        ->get($item['url']);
                } catch (ConnectionException) {
                    $response = null;
                }

                $contents = $response instanceof Response && $response->successful() ? $response->body() : '';
                if (! $this->validMediaContents($contents, basename((string) parse_url($item['url'], PHP_URL_PATH)))) {
                    $references[$item['referenceKey']] = '';
                    $failed++;

                    continue;
                }

                $references[$item['referenceKey']] = $this->legacyMediaPath($item['sourcePath']);
                $verified++;
            }
        }

        return $references;
    }

    private function mediaReferenceKey(string $facultySlug, int $sourceId, string $sourcePath): string
    {
        return $facultySlug.'|'.$sourceId.'|'.trim(str_replace('\\', '/', $sourcePath));
    }

    private function legacyMediaUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return self::LEGACY_BASE_URL.'/'.$this->legacyMediaPath($path);
    }

    private function legacyMediaPath(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($urlPath) ? $urlPath : $path;
        }

        $path = ltrim(str_replace('../', '', str_replace('\\', '/', $path)), '/');

        return str_contains($path, '/') ? $path : 'downloads/files/'.$path;
    }

    private function validMediaContents(string $contents, string $filename): bool
    {
        if ($contents === '') {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            return str_starts_with($contents, '%PDF-');
        }

        return in_array($extension, self::IMAGE_EXTENSIONS, true) && @getimagesizefromstring($contents) !== false;
    }
}
