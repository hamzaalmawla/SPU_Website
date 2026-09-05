<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use Illuminate\Console\Command;

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
 * Deliberately advisory. What to publish, retire or leave empty is SPU's
 * decision, not a deploy's, so this reports and does not fail - unless asked
 * to with --fail-on-empty, which is there for a future gate rather than for
 * today's.
 */
final class CmsContentStatusCommand extends Command
{
    protected $signature = 'cms:content-status
        {--area= : Only report one area (news, facilities, research, about, ...)}
        {--empty : List only the sections with nothing published}
        {--json : Machine-readable output}
        {--fail-on-empty : Exit non-zero when any section has nothing published}';

    protected $description = 'Report which CMS sections have published content and which render empty';

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

            $rows[] = [
                'key' => $target->key,
                'area' => $target->area,
                'path' => $target->publicPath ?? '—',
                'state' => $worst,
                'locales' => $locales,
            ];
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

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($visible, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
            $this->line('  <fg=gray>✓ published   ○ published but holds no items   ✗ nothing published</>');
        }

        $this->newLine();
        $this->line('  <fg=gray>An empty section is not a fault. Public pages no longer fall back to</>');
        $this->line('  <fg=gray>the development fixtures, so a section renders empty until real content</>');
        $this->line('  <fg=gray>is published through the admin - where the fixture is usually already</>');
        $this->line('  <fg=gray>loaded as the editor default, waiting to be reviewed.</>');
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
