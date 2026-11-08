<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The research office page (target research.office, /research/office) becomes the
 * Research and Scientific Publication Council (2026-11). For the published page
 * and any open draft, in both languages, this:
 * - renames the page (hero title, and the eyebrow, summary and last breadcrumb
 *   when they still hold the old office wording);
 * - turns the leadership list into the council members, each one a typed-in
 *   member (not linked to a staff profile) that an editor can then mark as the
 *   head or link to the teaching staff;
 * - turns the services boxes into the council functions;
 * - drops the statistics and contact sections, which the page no longer has.
 * The menu items that point at the page get the new name. The new headings come
 * from resources/data/research-content.json (office), the editor's starting copy.
 *
 * Data migration: deploy with data-migrations.approved (Docs/DEPLOYING.md).
 */
return new class extends Migration
{
    /** @var array<string, array{old: string, new: string}> */
    private const MENU_LABELS = [
        'ar' => ['old' => 'مكتب البحث', 'new' => 'مجلس البحث والنشر العلمي'],
        'en' => ['old' => 'Research Office', 'new' => 'Research and Scientific Publication Council'],
    ];

    /** @var array<string, array<string, list<string>>> The office wording this replaces when an editor left it as it was. */
    private const OLD_WORDING = [
        'eyebrow' => ['ar' => ['إدارة البحث'], 'en' => ['Research Administration']],
        'summary' => [
            'ar' => ['يدعم مكتب البحث في SPU وينسق الأنشطة البحثية عبر جميع الكليات، ويعزز الابتكار والتميز الأكاديمي.'],
            'en' => ['SPU Research Office supports and coordinates research activities across all faculties, fostering innovation and academic excellence.'],
        ],
        'breadcrumb' => ['ar' => ['المكتب', 'مكتب البحث'], 'en' => ['Office', 'Research Office']],
    ];

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            // --pretend cannot read the rows, so it would show no UPDATE and the
            // deploy's data-migration gate would not ask for approval.
            DB::table('cms_target_contents')->where('target_key', 'research.office')->update(['updated_at' => now()]);
            $this->renameMenu('old', 'new');

            return;
        }

        $source = json_decode((string) file_get_contents(resource_path('data/research-content.json')), true, 512, JSON_THROW_ON_ERROR);
        $defaults = [
            'ar' => $this->localized($source['office'] ?? [], 'ar'),
            'en' => $this->localized($source['office'] ?? [], 'en'),
        ];

        foreach (['cms_target_contents', 'cms_drafts'] as $table) {
            $rows = DB::table($table)->where('target_key', 'research.office')
                ->when($table === 'cms_drafts', fn ($query) => $query->whereIn('status', ['draft', 'scheduled']))
                ->get(['id', 'payload_json']);

            foreach ($rows as $row) {
                $payload = json_decode((string) $row->payload_json, true);
                if (! is_array($payload) || ! is_array($payload['translations'] ?? null)) {
                    continue;
                }

                foreach (['ar', 'en'] as $locale) {
                    if (is_array($payload['translations'][$locale] ?? null)) {
                        $payload['translations'][$locale] = $this->converted($payload['translations'][$locale], $defaults[$locale], $locale);
                    }
                }

                DB::table($table)->where('id', $row->id)->update([
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->renameMenu('old', 'new');
    }

    public function down(): void
    {
        // Content stays in the council layout; only the menu name goes back.
        $this->renameMenu('new', 'old');
    }

    private function renameMenu(string $from, string $to): void
    {
        foreach (self::MENU_LABELS as $locale => $labels) {
            DB::table('menu_items')
                ->whereIn('url', ['/'.$locale.'/research/office', '/'.$locale.'/research/office/'])
                ->where('label', $labels[$from])
                ->update(['label' => $labels[$to], 'updated_at' => now()]);
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function converted(array $content, array $defaults, string $locale): array
    {
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $newHero = is_array($defaults['hero'] ?? null) ? $defaults['hero'] : [];
        $hero['title'] = $newHero['title'] ?? ($hero['title'] ?? '');
        foreach (['eyebrow', 'summary'] as $field) {
            if (! isset($hero[$field]) || in_array(trim((string) $hero[$field]), self::OLD_WORDING[$field][$locale], true)) {
                $hero[$field] = $newHero[$field] ?? ($hero[$field] ?? '');
            }
        }
        if (is_array($hero['breadcrumbs'] ?? null)) {
            $last = array_key_last($hero['breadcrumbs']);
            if ($last !== null && is_array($hero['breadcrumbs'][$last]) && in_array(trim((string) ($hero['breadcrumbs'][$last]['label'] ?? '')), self::OLD_WORDING['breadcrumb'][$locale], true)) {
                $hero['breadcrumbs'][$last]['label'] = $hero['title'];
            }
        }
        $content['hero'] = $hero;

        $councilDefaults = is_array($defaults['council'] ?? null) ? $defaults['council'] : [];
        unset($councilDefaults['members']);
        if (! is_array($content['council']['members'] ?? null)) {
            $leaders = array_values(array_filter(is_array($content['leadership']['items'] ?? null) ? $content['leadership']['items'] : [], 'is_array'));
            $content['council'] = [
                ...$councilDefaults,
                ...(is_array($content['council'] ?? null) ? $content['council'] : []),
                'members' => array_map(static fn (array $leader, int $index): array => [
                    'id' => 'member-legacy-'.($index + 1),
                    'role' => 'member',
                    'isStaff' => false,
                    'staffId' => null,
                    'name' => (string) ($leader['name'] ?? ''),
                    'position' => (string) ($leader['role'] ?? ''),
                    'image' => is_string($leader['image'] ?? null) && $leader['image'] !== '' ? $leader['image'] : null,
                ], $leaders, array_keys($leaders)),
            ];
        }

        if (! is_array($content['functions']['items'] ?? null)) {
            $functionDefaults = is_array($defaults['functions'] ?? null) ? $defaults['functions'] : [];
            $services = array_values(array_filter(is_array($content['services']['items'] ?? null) ? $content['services']['items'] : [], 'is_array'));
            $content['functions'] = [
                'title' => $functionDefaults['title'] ?? '',
                'subtitle' => $functionDefaults['subtitle'] ?? '',
                'items' => array_map(static fn (array $service): array => [
                    'title' => (string) ($service['title'] ?? ''),
                    'description' => (string) ($service['description'] ?? ''),
                ], $services),
            ];
        }

        unset($content['leadership'], $content['services'], $content['statistics'], $content['contact']);

        return $content;
    }

    /** The source file's `…Ar` / `…En` fields as one language. */
    private function localized(mixed $value, string $locale): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $localized = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && (str_ends_with($key, 'Ar') || str_ends_with($key, 'En'))) {
                if (str_ends_with($key, $locale === 'ar' ? 'Ar' : 'En')) {
                    $localized[substr($key, 0, -2)] = $this->localized($item, $locale);
                }

                continue;
            }

            $localized[$key] = $this->localized($item, $locale);
        }

        return $localized;
    }
};
