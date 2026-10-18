<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyPageContentServiceInterface;
use App\DTOs\Legacy\LegacyPageCoverageDTO;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\SqlDumpReader;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class LegacyPageContentService implements LegacyPageContentServiceInterface
{
    /**
     * Services whose categories are static pages, by site. The others hold record lists (news,
     * events, projects, research, agreements, jobs) that were migrated as records.
     */
    private const SITES = [
        1 => 'main', 2 => 'main', 8 => 'campus-life', 16 => 'e-services',
        21 => 'medicine', 22 => 'medicine', 31 => 'dentistry', 32 => 'dentistry',
        41 => 'pharmacy', 42 => 'pharmacy', 51 => 'artificial-intelligence', 52 => 'artificial-intelligence',
        61 => 'petroleum', 62 => 'petroleum', 71 => 'business-administration', 72 => 'business-administration',
        81 => 'research', 82 => 'research', 91 => 'hospital', 92 => 'hospital', 101 => 'dental-clinics', 102 => 'dental-clinics',
        111 => 'alumni', 112 => 'alumni', 121 => 'activities', 122 => 'activities', 131 => 'university-requirements',
    ];

    /** What each record-list service held on the old site. */
    private const RECORD_LISTS = [
        3 => 'University news', 4 => 'Announcements', 5 => 'Agreements (MoUs)', 6 => 'Community service', 7 => 'Achievements',
        9 => 'Job vacancies', 10 => 'Events', 11 => 'Student card offers', 23 => 'Medicine news', 24 => 'Medicine student projects',
        25 => 'Medicine activities', 27 => 'Medicine research', 33 => 'Dentistry news', 34 => 'Dentistry clinical cases',
        35 => 'Dentistry activities', 36 => 'Dentistry schedules', 37 => 'Dentistry research', 43 => 'Pharmacy news',
        44 => 'Pharmacy student projects', 45 => 'Pharmacy activities', 47 => 'Pharmacy research', 53 => 'AI news',
        54 => 'AI student projects', 55 => 'AI activities', 57 => 'AI research', 63 => 'Petroleum news', 64 => 'Petroleum student projects',
        65 => 'Petroleum activities', 66 => 'Petroleum exam programmes', 67 => 'Petroleum research', 73 => 'Business news',
        74 => 'Business student projects', 75 => 'Business activities', 76 => 'Business schedules', 77 => 'Business research',
        89 => 'SPU student publications', 113 => 'Alumni news', 114 => 'Alumni success stories', 115 => 'Alumni training offers',
        117 => 'Alumni training courses', 119 => 'Alumni job offers',
    ];

    private const FACULTIES = ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'petroleum', 'business-administration'];

    /** Main-site and research-portal pages whose new home was chosen page by page. */
    private const MAPPED = [
        12 => '/', 2029 => '/', 16 => '/about', 18 => '/contact', 58 => '/contact', 3415 => '/contact',
        322 => '/about/history', 23 => '/about/vision-mission', 22 => '/about/why-spu', 24 => '/about/leadership',
        5796 => '/about/quality-policy', 5794 => '/about/ethical-charter', 5797 => '/about/organizational-structure',
        5289 => '/about', 5798 => '/about/accreditation', 2396 => '/about/leadership',
        13 => '/admissions', 45 => '/admissions/documents', 1235 => '/admissions/documents', 1237 => '/admissions/documents',
        42 => '/admissions/documents', 1239 => '/admissions/graduation-exams', 5263 => '/admissions/tuition',
        5278 => '/admissions/tuition', 5276 => '/admissions/filling-vacancies', 5277 => '/admissions/transfer',
        39 => '/admissions/faq', 65 => '/admissions/faq', 848 => '/admissions/calendar',
        10 => '/campus-life', 841 => '/e-services/library', 2218 => '/campus-life/damascus-research-pub',
        849 => '/campus-life/rules-regulations', 5360 => '/campus-life/general-rules', 1245 => '/campus-life/exam-instructions',
        5716 => '/campus-life/exam-penalties', 843 => '/campus-life/clubs-activities', 842 => '/campus-life/services',
        1618 => '/campus-life/health-insurance', 1617 => '/e-services/it-support', 60 => '/e-services/suggestions-complaints',
        19 => '/faculties', 5217 => '/faculties/building-construction-engineering',
        5265 => '/faculties/building-construction-engineering', 5268 => '/faculties/building-construction-engineering/departments',
        5282 => '/faculties/building-construction-engineering/study-plan',
        5393 => '/research/office', 126 => '/research/office', 130 => '/research/office', 5411 => '/research/office',
        5394 => '/research', 5367 => '/research/publications', 5368 => '/research/publications', 123 => '/research/publications',
        5401 => '/research/policies', 5400 => '/research/policies', 5405 => '/research/policies',
        5409 => '/research/conferences', 5410 => '/research/researchers',
        5406 => '/research/library', 5408 => '/research/library', 5407 => '/research/library',
        5396 => '/research/projects', 5399 => '/research/projects', 5398 => '/research',
        27 => '/about/directorates', 64 => '/campus-life/career-development/jobs', 2219 => '/campus-life/damascus-research-pub',
    ];

    /** Faculty-site page titles to the faculty subpage that carries them, first match wins. */
    private const FACULTY_RULES = [
        'كلمة' => '', 'الرؤية' => '', 'الرسالة' => '', 'حول الكلية' => '/overview', 'الدراسة في الكلية' => '/overview',
        'الهيكل التنظيمي' => '/overview', 'أسئلة' => '/overview', 'التخصصات' => '/departments', 'الأقسام' => '/departments',
        'الخطة' => '/study-plan', 'توصيف' => '/study-plan', 'المحاضرات' => '/study-plan', 'مخابر' => '/labs', 'مخبر' => '/labs',
        'الملازمة' => '/training', 'التدريب' => '/training', 'الطلبة الأوائل' => '/valedictorians', 'الخريج' => '/alumni',
        'مشاريع' => '/projects', 'الأبحاث' => '/research', 'أعضاء' => '/members', 'مجلس الكلية' => '/members',
    ];

    private const SITE_PATHS = [
        'campus-life' => '/campus-life/clubs-activities', 'e-services' => '/e-services/staff-email', 'research' => '/research/office',
        'hospital' => '/campus-life/hospital', 'dental-clinics' => '/campus-life/dental', 'alumni' => '/alumni',
        'activities' => '/campus-life/clubs-activities', 'university-requirements' => '/admissions/requirements',
    ];

    /** Shorter segments are labels and menu words that prove nothing. */
    private const MIN_SEGMENT = 20;

    /** @var array<string, string> */
    private array $rendered = [];

    public function __construct(
        private readonly SqlDumpReader $dumpReader,
        private readonly Kernel $kernel,
    ) {}

    public function export(string $dumpPath, string $jsonPath): int
    {
        $categories = [];
        $items = [];
        $records = [];
        foreach ($this->dumpReader->rows($dumpPath, ['jx_categories', 'jx_items']) as [$table, $row]) {
            $service = (int) ($row['service_type'] ?? 0);
            if (! isset(self::SITES[$service])) {
                // Record lists (news, events, projects, research): the visible, titled ids.
                if ($table === 'jx_categories' && (int) ($row['is_visible'] ?? 0) === 1 && $this->title($row['ar_name'] ?? $row['en_name'] ?? '') !== '') {
                    $records[$service][] = (int) $row['id'];
                }

                continue;
            }
            if ($table === 'jx_categories') {
                $categories[(int) $row['id']] = $row;
            } elseif ((int) ($row['is_visible'] ?? 0) === 1 && (int) ($row['is_accepted'] ?? 1) === 1) {
                $items[(int) $row['category_id']][] = $row;
            }
        }

        $pages = [];
        foreach ($categories as $id => $row) {
            if ((int) $row['is_visible'] !== 1) {
                continue;
            }

            $service = (int) $row['service_type'];
            $site = self::SITES[$service];
            if ($service === 1 && $this->ancestorIds($id, $categories) !== [] && in_array(5217, $this->ancestorIds($id, $categories), true)) {
                $site = 'building-construction-engineering';
            }
            $trail = array_reverse(array_map(fn (int $ancestor): array => $categories[$ancestor], $this->ancestorIds($id, $categories)));
            $text = ['ar' => [], 'en' => []];
            $files = 0;
            foreach (['ar', 'en'] as $locale) {
                $text[$locale][] = $this->plain($row[$locale.'_data'] ?? null);
            }
            foreach ($items[$id] ?? [] as $item) {
                foreach (['ar', 'en'] as $locale) {
                    $text[$locale][] = $this->plain($item[$locale.'_brief'] ?? null);
                    $text[$locale][] = $this->plain($item[$locale.'_description'] ?? null);
                }
                $files += count(array_filter([$item['ar_file'] ?? null, $item['en_file'] ?? null], static fn (?string $file): bool => is_string($file) && trim($file) !== ''));
            }

            $isLink = (int) ($row['is_link'] ?? 0) === 1;
            // Under a hidden section, or a parent that no longer exists, the page was reachable only
            // by a direct link, never from a menu.
            $parent = (int) ($row['parent'] ?? 0);
            $reachable = ($parent === 0 || $parent === $id || isset($categories[$parent]))
                && array_filter($trail, static fn (array $c): bool => (int) $c['is_visible'] !== 1) === [];
            $pages[] = [
                'id' => $id,
                'service' => $service,
                'site' => $site,
                'breadcrumb_ar' => implode(' › ', array_map(fn (array $c): string => $this->title($c['ar_name'] ?? ''), [...$trail, $row])),
                'breadcrumb_en' => implode(' › ', array_map(fn (array $c): string => $this->title($c['en_name'] ?? ''), [...$trail, $row])),
                'link' => $isLink ? trim((string) ($row['url'] ?? '')) : null,
                'in_menu' => $reachable,
                'files' => $files,
                'text_ar' => trim(implode("\n\n", array_filter($text['ar']))),
                'text_en' => trim(implode("\n\n", array_filter($text['en']))),
                'new_path' => $this->newPath($id, $site, $row, $trail),
            ];
        }

        usort($pages, static fn (array $a, array $b): int => [$a['site'], $a['breadcrumb_ar']] <=> [$b['site'], $b['breadcrumb_ar']]);
        ksort($records);
        $json = json_encode(['source' => basename($dumpPath), 'pages' => $pages, 'records' => $records], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($jsonPath, $json."\n") === false) {
            throw new RuntimeException('Could not write '.$jsonPath);
        }

        return count($pages);
    }

    public function coverage(string $jsonPath, ?string $site = null): array
    {
        $results = [];
        foreach ($this->pages($jsonPath) as $page) {
            if ($site !== null && $page['site'] !== $site) {
                continue;
            }

            $newPath = is_string($page['new_path'] ?? null) ? $page['new_path'] : null;
            $coverage = ['ar' => null, 'en' => null];
            $length = ['ar' => 0, 'en' => 0];
            $status = null;
            foreach (['ar', 'en'] as $locale) {
                $old = (string) ($page['text_'.$locale] ?? '');
                $length[$locale] = mb_strlen($old);
                $segments = $this->segments($old);
                if ($segments === []) {
                    continue;
                }
                if ($newPath === null) {
                    $coverage[$locale] = 0;

                    continue;
                }
                $haystack = $this->renderedKey($locale, $newPath);
                if (str_starts_with($haystack, 'HTTP ')) {
                    $status = $haystack;
                    $coverage[$locale] = 0;

                    continue;
                }
                $found = count(array_filter($segments, static fn (string $segment): bool => str_contains($haystack, $segment)));
                $coverage[$locale] = (int) round(100 * $found / count($segments));
            }

            $results[] = new LegacyPageCoverageDTO(
                legacyId: (int) $page['id'],
                site: (string) $page['site'],
                breadcrumb: (string) $page['breadcrumb_ar'],
                newPath: $newPath,
                legacyLink: $page['link'] ?? null,
                files: (int) ($page['files'] ?? 0),
                oldLength: $length,
                coverage: $coverage,
                status: $status ?? $this->status($page, $newPath, $coverage),
            );
        }

        return $results;
    }

    public function recordLists(string $jsonPath): array
    {
        $data = $this->data($jsonPath);
        $lists = [];
        foreach (is_array($data['records'] ?? null) ? $data['records'] : [] as $service => $ids) {
            $ids = array_map('intval', is_array($ids) ? $ids : []);
            $migrated = MigrationLog::query()
                ->where('source_table', 'jx_categories')
                ->where('status', 'success')
                ->whereIn('source_id', $ids)
                ->get(['source_id', 'target_table']);
            $lists[] = [
                'service' => (int) $service,
                'list' => self::RECORD_LISTS[(int) $service] ?? 'service '.$service,
                'old_visible' => count($ids),
                'migrated' => $migrated->pluck('source_id')->unique()->count(),
                'into' => $migrated->pluck('target_table')->unique()->values()->all(),
            ];
        }

        return $lists;
    }

    /** @param array<string, mixed> $page @param array{ar: ?int, en: ?int} $coverage */
    private function status(array $page, ?string $newPath, array $coverage): string
    {
        $values = array_filter($coverage, static fn (?int $value): bool => $value !== null);
        if ($values === []) {
            return ($page['link'] ?? null) !== null ? 'link' : (((int) ($page['files'] ?? 0)) > 0 ? 'files only' : 'no text');
        }
        if ($newPath === null) {
            return ($page['in_menu'] ?? true) ? 'no new page' : 'hidden on old site';
        }

        $lowest = min($values);

        return match (true) {
            $lowest >= 80 => 'covered',
            max($values) >= 20 => 'partial',
            default => 'missing',
        };
    }

    /** The page's new home: hand-mapped, else by its site and title, else its parent's. @param array<string, ?string> $row @param list<array<string, ?string>> $trail */
    private function newPath(int $id, string $site, array $row, array $trail): ?string
    {
        foreach ([$id, ...array_reverse(array_map(static fn (array $c): int => (int) $c['id'], $trail))] as $candidate) {
            if (isset(self::MAPPED[$candidate])) {
                return self::MAPPED[$candidate];
            }
        }

        if (in_array($site, self::FACULTIES, true) || $site === 'building-construction-engineering') {
            foreach ([$row, ...array_reverse($trail)] as $category) {
                $title = $this->title($category['ar_name'] ?? '');
                foreach (self::FACULTY_RULES as $needle => $suffix) {
                    if (str_contains($title, $needle)) {
                        return '/faculties/'.$site.$suffix;
                    }
                }
            }

            return '/faculties/'.$site.'/overview';
        }

        return self::SITE_PATHS[$site] ?? null;
    }

    /** @param array<int, array<string, ?string>> $categories @return list<int> nearest first */
    private function ancestorIds(int $id, array $categories): array
    {
        $ancestors = [];
        $parent = (int) ($categories[$id]['parent'] ?? 0);
        while ($parent !== 0 && isset($categories[$parent]) && ! in_array($parent, $ancestors, true) && count($ancestors) < 10) {
            $ancestors[] = $parent;
            $parent = (int) ($categories[$parent]['parent'] ?? 0);
        }

        return $ancestors;
    }

    private function renderedKey(string $locale, string $path): string
    {
        $key = $locale.$path;
        if (isset($this->rendered[$key])) {
            return $this->rendered[$key];
        }

        try {
            $url = '/'.$locale.($path === '/' ? '' : $path);
            $response = $this->kernel->handle(Request::create($url, 'GET'));
            for ($hops = 0; $response->isRedirect() && $hops < 3; $hops++) {
                $response = $this->kernel->handle(Request::create((string) $response->headers->get('Location'), 'GET'));
            }
            $html = (string) $response->getContent();
            $text = $response->getStatusCode() === 200
                ? $this->key(preg_match('#<main[^>]*>(.*)</main>#si', $html, $main) === 1 ? $this->plain($main[1]) : $this->plain($html))
                : 'HTTP '.$response->getStatusCode();
        } catch (Throwable $exception) {
            $text = 'HTTP error '.class_basename($exception);
        }

        return $this->rendered[$key] = $text;
    }

    /** @return list<string> */
    private function segments(string $text): array
    {
        $parts = preg_split('/[\n\.\!\?؟؛:]+/u', $text) ?: [];

        return array_values(array_unique(array_filter(
            array_map(fn (string $part): string => $this->key($part), $parts),
            static fn (string $part): bool => mb_strlen($part) >= self::MIN_SEGMENT,
        )));
    }

    /** Letters and digits only, Arabic letter variants and diacritics folded, so layout never decides a match. */
    private function key(string $value): string
    {
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', mb_strtolower($value)) ?? $value;
        $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    /** Readable text of legacy HTML: Word markup, styles, scripts and comments removed, blocks on their own lines. */
    private function plain(?string $html): string
    {
        if (! is_string($html) || trim($html) === '') {
            return '';
        }

        $html = preg_replace(['#<(style|script|xml)[^>]*>.*?</\1>#si', '#<!--.*?-->#s', '#<w:[^>]*>.*?</w:[^>]*>#si', '#</?[ovw]:[^>]*>#i'], ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/td|/table)[^>]*>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/^.*(?:Normal\s+0\s+false|MicrosoftInternetExplorer|X-NONE).*$/mu', '', $text) ?? $text;
        $lines = array_filter(array_map(static fn (string $line): string => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? $line), explode("\n", $text)));
        $text = implode("\n", $lines);

        return preg_match('/^(?:under construction|sous construction|قيد الإعداد|spu)$/iu', trim($text)) === 1
            || preg_match('/translation (?:to|in) english will|this page is (?:temporarily )?unavailable/i', $text) === 1 ? '' : $text;
    }

    private function title(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /** @return list<array<string, mixed>> */
    private function pages(string $path): array
    {
        $data = $this->data($path);

        return is_array($data['pages'] ?? null) ? array_values(array_filter($data['pages'], 'is_array')) : [];
    }

    /** @return array<string, mixed> */
    private function data(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('The page content file could not be read: '.$path);
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The page content file is not valid JSON: '.$exception->getMessage());
        }

        return is_array($data) ? $data : [];
    }
}
