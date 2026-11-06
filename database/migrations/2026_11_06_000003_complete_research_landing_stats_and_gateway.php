<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The research landing (target research.index) was published with three stats,
 * three "where to start" cards and a hand-picked featured publication. The
 * approved layout has four stats and six cards, and the featured slot now shows
 * the newest publication or project by itself (ResearchPageService).
 *
 * For the published page and any open draft, in both languages, this:
 * - adds a live research projects counter as the fourth stat, when the page has
 *   fewer than four stats and no projects counter yet;
 * - gives the gateway the six reviewed cards, when it has fewer than six;
 * - drops the stored featured publication and sets the latest research title.
 * Everything else an editor changed (hero, other stats) stays as it is. The
 * copy is the reviewed file database/content/research-landing.json.
 *
 * Data migration: deploy with data-migrations.approved (Docs/DEPLOYING.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending()) {
            // --pretend cannot read the rows, so it would show no UPDATE and the
            // deploy's data-migration gate would not ask for approval.
            DB::table('cms_target_contents')->where('target_key', 'research.index')->update(['updated_at' => now()]);

            return;
        }

        $reviewed = json_decode((string) file_get_contents(database_path('content/research-landing.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (['cms_target_contents', 'cms_drafts'] as $table) {
            $rows = DB::table($table)->where('target_key', 'research.index')
                ->when($table === 'cms_drafts', fn ($query) => $query->whereIn('status', ['draft', 'scheduled']))
                ->get(['id', 'payload_json']);

            foreach ($rows as $row) {
                $payload = json_decode((string) $row->payload_json, true);
                if (! is_array($payload) || ! is_array($payload['translations'] ?? null)) {
                    continue;
                }

                foreach (['ar', 'en'] as $locale) {
                    if (is_array($payload['translations'][$locale] ?? null) && is_array($reviewed['translations'][$locale] ?? null)) {
                        $payload['translations'][$locale] = $this->completed($payload['translations'][$locale], $reviewed['translations'][$locale]);
                    }
                }

                DB::table($table)->where('id', $row->id)->update([
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Content only: the hand-picked publication and the earlier cards are not kept.
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $reviewed
     * @return array<string, mixed>
     */
    private function completed(array $content, array $reviewed): array
    {
        $stats = array_values(array_filter(is_array($content['stats'] ?? null) ? $content['stats'] : [], 'is_array'));
        $hasProjects = in_array('projects', array_column($stats, 'counter'), true);
        if (! $hasProjects && count($stats) < 4) {
            foreach ($reviewed['stats'] ?? [] as $stat) {
                if (is_array($stat) && ($stat['counter'] ?? null) === 'projects') {
                    $stats[] = $stat;
                }
            }
        }
        $content['stats'] = $stats;

        $cards = $content['gateway']['cards'] ?? [];
        if (! is_array($cards) || count($cards) < 6) {
            $content['gateway'] = is_array($content['gateway'] ?? null) ? $content['gateway'] : [];
            $content['gateway']['cards'] = $reviewed['gateway']['cards'] ?? [];
        }

        unset($content['featuredPublication']);
        $content['latestResearch'] = $reviewed['latestResearch'] ?? [];

        return $content;
    }
};
