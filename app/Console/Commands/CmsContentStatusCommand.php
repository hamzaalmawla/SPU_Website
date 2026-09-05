<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Http\Request as HttpRequest;

/**
 * Reports which CMS-managed sections actually have content published.
 *
 * This exists because of a question that keeps being asked in a form nobody can
 * answer quickly: "why is this section empty?"
 *
 * The answer is almost always the same, and it is deliberate. Public pages no
 * longer fall back to the development fixtures in resources/data - that
 * fallback was removed so invented placeholder content stops appearing on a
 * live university site. A section with nothing published therefore renders its
 * empty state, correctly and silently. The fixtures still feed the admin
 * editor, so the content is usually sitting there waiting to be reviewed and
 * published, which is exactly the state this command makes visible.
 *
 * Being silent is the right behaviour for the page and the wrong behaviour for
 * whoever has to know what is left before launch. So: one command, one list.
 *
 * The distinction that makes this useful rather than alarming: a section with
 * no published payload is usually NOT an empty page. Most of them render from
 * database records - faculty pages, publications, news articles - and treat the
 * payload as an optional override, so they show a full page while reporting
 * nothing published. Measured on the live site, 110 of 134 sections had no
 * payload and all but a handful rendered real content.
 *
 * Only rendering the page separates the two, which is what --probe does. The
 * payload report alone is a fast signal about the CMS; --probe is the one that
 * answers "what will a visitor see nothing on".
 *
 * Deliberately advisory. What to publish, retire or leave empty is SPU's
 * decision, not a deploy's, so this reports and does not fail - unless asked
 * to with --fail-on-empty, which is there for a future gate rather than for
 * today's.
 */
final class CmsContentStatusCommand extends Command
{
    protected $signature = 'cms:content-status
        {--area= : Only report one area (news, facilities, research, about, ...)}
        {--empty : List only the sections without a published payload}
        {--probe : Also render each page and report which ones are actually blank}
        {--summary : Print the counts only}
        {--json : Machine-readable output}
        {--fail-on-empty : Exit non-zero when any section has no published payload}';

    protected $description = 'Report which CMS sections have a published payload, and with --probe which pages actually render blank';

    /**
     * Below this many characters of body text, a page has nothing on it.
     *
     * Calibrated against the live site rather than guessed: the media gallery,
     * which is genuinely blank, renders 67 characters, while the thinnest page
     * that does have content on it renders 596. Anything in that gap is a
     * judgement call, so the count is always printed and this only decides
     * which rows get flagged.
     */
    private const BLANK_TEXT_THRESHOLD = 250;

    /**
     * A published payload can still be empty. The research section shipped
     * exactly that way - published, structurally valid, and holding no items -
     * so presence of a row is not evidence of content. These are the keys whose
     * emptiness actually means the page has nothing to show; anything else in a
     * payload is labels and chrome, which are always populated by the
     * normalisers and would make every section look healthy.
     */
    private const CONTENT_KEYS = [
        'items', 'cards', 'sections', 'entries', 'records', 'articles',
        'events', 'announcements', 'publications', 'projects', 'centers',
        'centres', 'themes', 'researchers', 'people', 'members', 'stats',
        'categories', 'faqs', 'links', 'documents', 'galleries', 'images',
    ];

    public function handle(CmsTargetRegistryInterface $registry, CmsWorkflowServiceInterface $workflow): int
    {
        $area = $this->option('area');
        $onlyEmpty = (bool) $this->option('empty');

        $targets = $registry->all()
            ->filter(fn ($t): bool => ! is_string($area) || $t->area === $area)
            ->values();

        if ($targets->isEmpty()) {
            $this->components->error(is_string($area)
                ? "No CMS targets in area '{$area}'."
                : 'No CMS targets are registered.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($targets as $target) {
            $published = $workflow->getPublishedPayload($target->key);
            $locales = [];

            foreach ($target->locales as $locale) {
                $payload = is_array($published['translations'][$locale] ?? null)
                    ? $published['translations'][$locale]
                    : null;

                $locales[$locale] = $payload === null
                    ? ['state' => 'missing', 'count' => 0]
                    : ['state' => $this->countContent($payload) > 0 ? 'published' : 'empty',
                        'count' => $this->countContent($payload)];
            }

            $states = array_column($locales, 'state');
            $worst = in_array('missing', $states, true)
                ? 'missing'
                : (in_array('empty', $states, true) ? 'empty' : 'published');

            $row = [
                'key' => $target->key,
                'area' => $target->area,
                'path' => $target->publicPath ?? '—',
                'state' => $worst,
                'locales' => $locales,
            ];

            if ((bool) $this->option('probe') && is_string($target->publicPath)) {
                $row['rendered'] = $this->renderedTextLength($target->publicPath, $target->locales);
                $row['blank'] = $row['rendered'] !== null && $row['rendered'] < self::BLANK_TEXT_THRESHOLD;
            }

            $rows[] = $row;
        }

        // Counted over every target, not over the filtered view, so --empty
        // narrows what is listed without changing what the totals mean.
        $publishedTotal = count(array_filter(
            $rows,
            static fn (array $r): bool => $r['state'] === 'published'
        ));

        $visible = $onlyEmpty
            ? array_values(array_filter($rows, static fn (array $r): bool => $r['state'] !== 'published'))
            : $rows;

        $blankTotal = count(array_filter($rows, static fn (array $r): bool => ($r['blank'] ?? false) === true));

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($visible, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ((bool) $this->option('summary')) {
            $this->line(sprintf(
                '  %d of %d CMS section(s) have a published payload.',
                $publishedTotal,
                count($rows),
            ));

            if ((bool) $this->option('probe')) {
                $this->line($blankTotal > 0
                    ? sprintf('  %d page(s) render blank. Run cms:content-status --probe for the list.', $blankTotal)
                    : '  No page renders blank.');
            } else {
                $this->line('  A missing payload is not an empty page: most sections render from database');
                $this->line('  records. Run cms:content-status --probe to see which pages are actually blank.');
            }
        } else {
            $this->render($visible, $publishedTotal, count($rows), $onlyEmpty);
        }

        return $publishedTotal < count($rows) && (bool) $this->option('fail-on-empty')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function render(array $rows, int $publishedTotal, int $total, bool $onlyEmpty): void
    {
        if ($rows === []) {
            $this->newLine();
            $this->components->info("All {$total} CMS section(s) have published content.");

            return;
        }

        $currentArea = null;

        foreach ($rows as $row) {
            if ($row['area'] !== $currentArea) {
                $currentArea = $row['area'];
                $this->newLine();
                $this->line("  <fg=white;options=bold>{$currentArea}</>");
            }

            $marker = match ($row['state']) {
                'published' => '<fg=green>✓</>',
                'empty' => '<fg=yellow>○</>',
                default => '<fg=red>✗</>',
            };

            $detail = collect($row['locales'])
                ->map(fn (array $l, string $locale): string => match ($l['state']) {
                    'published' => "{$locale} {$l['count']}",
                    'empty' => "{$locale} empty",
                    default => "{$locale} —",
                })
                ->implode('  ');

            if (array_key_exists('rendered', $row)) {
                $detail .= $row['rendered'] === null
                    ? '   <fg=gray>page not reachable</>'
                    : ($row['blank']
                        ? "   <fg=red;options=bold>BLANK PAGE</> <fg=gray>({$row['rendered']} chars)</>"
                        : "   <fg=green>renders {$row['rendered']} chars</>");
            }

            $this->line(sprintf(
                '    %s %-42s %-34s %s',
                $marker,
                $row['key'],
                $row['path'],
                $detail,
            ));
        }

        $this->newLine();
        $this->line(sprintf(
            '  %d of %d section(s) have published content.',
            $publishedTotal,
            $total,
        ));

        if (! $onlyEmpty) {
            $this->line('  <fg=gray>✓ payload published   ○ published but holds no items   ✗ no payload</>');
        }

        $blank = array_filter($rows, static fn (array $r): bool => ($r['blank'] ?? false) === true);

        $this->newLine();

        if ($blank !== []) {
            $this->line(sprintf(
                '  <fg=red;options=bold>%d page(s) render blank and need content before launch.</>',
                count($blank),
            ));
            $this->newLine();
        }

        // The distinction this paragraph draws is the whole point of --probe,
        // and getting it wrong in the other direction - reporting 110 healthy
        // pages as broken - is what makes a readiness list get ignored.
        $this->line('  <fg=gray>No payload is not the same as an empty page. Most sections render from</>');
        $this->line('  <fg=gray>database records - faculty pages, publications, news articles - and treat</>');
        $this->line('  <fg=gray>the CMS payload as an optional override, so they show a full page while</>');
        $this->line('  <fg=gray>reporting nothing published here.</>');

        if (! array_key_exists('rendered', $rows[0] ?? [])) {
            $this->line('  <fg=gray>Run with --probe to see which pages are actually blank.</>');
        }
    }

    /**
     * How much body text a page actually renders, across its locales.
     *
     * This exists because a missing CMS payload is not the same thing as an
     * empty page, and reporting the first as if it were the second is how a
     * readiness list becomes noise. Most sections here render from database
     * records - faculty pages, publications, news articles - and treat the CMS
     * payload as an optional override, so they show a full page while reporting
     * nothing published. The gallery, which genuinely has no other source, does
     * not. Only rendering the page tells the two apart.
     *
     * Returns the smallest length across locales, since a page that is full in
     * Arabic and blank in English is a blank page for half the audience.
     *
     * @param  array<int, string>  $locales
     */
    private function renderedTextLength(string $publicPath, array $locales): ?int
    {
        $lengths = [];

        foreach ($locales as $locale) {
            $path = '/'.$locale.($publicPath === '/' ? '' : $publicPath);

            try {
                // Accept-Encoding must be explicit. CompressPublicResponses
                // treats an absent header as a proxy having stripped it and
                // gzips anyway, which would leave this measuring binary.
                $response = app()->handle(HttpRequest::create($path, 'GET', server: [
                    'HTTP_ACCEPT_ENCODING' => 'identity',
                ]));
            } catch (\Throwable) {
                continue;
            }

            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $body = (string) $response->getContent();
            $main = preg_match('/<main\b[^>]*>(.*?)<\/main>/s', $body, $m) === 1 ? $m[1] : $body;
            $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($main)));

            $lengths[] = mb_strlen($text);
        }

        return $lengths === [] ? null : min($lengths);
    }

    /**
     * Counts only the parts of a payload that represent content a visitor would
     * see listed. Nested one level, because several payloads group their items
     * under a section wrapper rather than at the top.
     *
     * @param  array<string, mixed>  $payload
     */
    private function countContent(array $payload): int
    {
        $count = 0;

        foreach ($payload as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (in_array($key, self::CONTENT_KEYS, true)) {
                $count += count($value);

                continue;
            }

            foreach ($value as $nestedKey => $nested) {
                if (is_array($nested) && in_array((string) $nestedKey, self::CONTENT_KEYS, true)) {
                    $count += count($nested);
                }
            }
        }

        return $count;
    }
}
