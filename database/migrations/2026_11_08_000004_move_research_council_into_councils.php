<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * About › Councils (2026-11): every council is a page built from one template,
 * and the Research and Scientific Publication Council (until now the research
 * page research.office, /research/office) becomes the first of them.
 *
 * - Adds the council `research-council` (/about/councils/research-council),
 *   with /research/office as its former address (that URL now redirects).
 * - Copies the research.office content to the council's target
 *   (entity.council.{id}): the published version as it was (published or not)
 *   and any open draft or scheduled version. The hero's research breadcrumbs
 *   and eyebrow are dropped; the About pages have their own. The old rows stay
 *   untouched. With nothing saved at all, the reviewed starting copy from
 *   resources/data/research-content.json (office) becomes a draft to review.
 * - Points the menu items that linked /research/office at the new address.
 * - Adds Councils (/about/councils) under About in the header menu, after
 *   Partnerships, and as an About navigation card.
 *
 * Data migration: deploy with data-migrations.approved (Docs/DEPLOYING.md).
 */
return new class extends Migration
{
    private const SLUG = 'research-council';

    private const FORMER_PATH = '/research/office';

    /** @var array<string, string> */
    private const MENU_LABELS = ['ar' => 'المجالس', 'en' => 'Councils'];

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            // --pretend cannot read rows, so it would show no INSERT and the
            // deploy's data-migration gate would not ask for approval.
            DB::table('university_councils')->insert(['slug' => self::SLUG, 'sort_order' => 1, 'is_enabled' => true, 'former_path' => self::FORMER_PATH, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('menu_items')->whereIn('url', ['/ar/research/office', '/en/research/office'])->update(['updated_at' => now()]);

            return;
        }

        $now = now();
        $councilId = DB::table('university_councils')->where('former_path', self::FORMER_PATH)->value('id');
        if ($councilId === null) {
            $councilId = DB::table('university_councils')->insertGetId([
                'slug' => self::SLUG,
                'sort_order' => 1,
                'is_enabled' => true,
                'former_path' => self::FORMER_PATH,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->copyContent('entity.council.'.$councilId);
        }

        $slug = (string) DB::table('university_councils')->where('id', $councilId)->value('slug');
        foreach (['ar', 'en'] as $locale) {
            DB::table('menu_items')
                ->whereIn('url', ['/'.$locale.self::FORMER_PATH, '/'.$locale.self::FORMER_PATH.'/'])
                ->update(['url' => '/'.$locale.'/about/councils/'.$slug, 'updated_at' => $now]);
        }

        $this->addAboutMenuItems();
        $this->addNavigationCard();
    }

    public function down(): void
    {
        // Councils are edited in the admin from now on; nothing is taken back.
    }

    private function copyContent(string $targetKey): void
    {
        $copied = false;

        $published = DB::table('cms_target_contents')->where('target_key', 'research.office')->first(['payload_json', 'status', 'updated_by', 'published_at', 'created_at', 'updated_at']);
        if ($published !== null && DB::table('cms_target_contents')->where('target_key', $targetKey)->doesntExist()) {
            DB::table('cms_target_contents')->insert([
                'target_key' => $targetKey,
                'payload_json' => $this->converted((string) $published->payload_json),
                'status' => $published->status,
                'updated_by' => $published->updated_by,
                'published_at' => $published->published_at,
                'created_at' => $published->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $copied = true;
        }

        $drafts = DB::table('cms_drafts')
            ->where('target_key', 'research.office')
            ->whereIn('status', ['draft', 'scheduled'])
            ->orderBy('id')
            ->get(['payload_json', 'status', 'draft_notes', 'created_by', 'updated_by', 'approved_by', 'scheduled_at', 'published_at', 'version', 'created_at']);
        foreach ($drafts as $draft) {
            DB::table('cms_drafts')->insert([
                'target_key' => $targetKey,
                'payload_json' => $this->converted((string) $draft->payload_json),
                'status' => $draft->status,
                'draft_notes' => $draft->draft_notes,
                'created_by' => $draft->created_by,
                'updated_by' => $draft->updated_by,
                'approved_by' => $draft->approved_by,
                'scheduled_at' => $draft->scheduled_at,
                'published_at' => $draft->published_at,
                'version' => $draft->version,
                'created_at' => $draft->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $copied = true;
        }

        if ($copied) {
            return;
        }

        // Never saved: the reviewed starting copy becomes a draft for an editor
        // to check and publish (a draft needs an author; none on a fresh install).
        $authorId = DB::table('users')->where('role_slug', 'super_admin')->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');
        if ($authorId === null) {
            return;
        }

        $source = json_decode((string) file_get_contents(resource_path('data/research-content.json')), true, 512, JSON_THROW_ON_ERROR);
        $office = is_array($source['office'] ?? null) ? $source['office'] : [];
        DB::table('cms_drafts')->insert([
            'target_key' => $targetKey,
            'payload_json' => $this->converted((string) json_encode(['translations' => [
                'ar' => $this->localized($office, 'ar'),
                'en' => $this->localized($office, 'en'),
            ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'status' => 'draft',
            'created_by' => $authorId,
            'updated_by' => $authorId,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** The research page's content as a council's: the About pages have their own breadcrumbs. */
    private function converted(string $json): string
    {
        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            return $json;
        }

        foreach (['ar', 'en'] as $locale) {
            if (is_array($payload['translations'][$locale]['hero'] ?? null)) {
                unset($payload['translations'][$locale]['hero']['breadcrumbs'], $payload['translations'][$locale]['hero']['eyebrow']);
            }
        }

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Councils under About in each language's header, after Partnerships; skipped where it is already there. */
    private function addAboutMenuItems(): void
    {
        $now = now();

        foreach (self::MENU_LABELS as $locale => $label) {
            $url = '/'.$locale.'/about/councils';
            // The About item itself links a page (no URL), so it is found as the
            // parent of its usual children.
            $aboutId = DB::table('menu_items')
                ->where('type', 'header')
                ->where('locale', $locale)
                ->whereNull('deleted_at')
                ->whereNotNull('parent_id')
                ->whereIn('url', array_map(static fn (string $page): string => '/'.$locale.'/about/'.$page, ['partnerships', 'directorates', 'leadership', 'vision-mission', 'history']))
                ->orderBy('id')
                ->value('parent_id');
            $about = $aboutId !== null
                ? DB::table('menu_items')->where('id', $aboutId)->whereNull('deleted_at')->first(['id', 'group_key', 'depth'])
                : null;
            if ($about === null) {
                continue;
            }

            $children = fn () => DB::table('menu_items')->where('parent_id', $about->id)->whereNull('deleted_at');
            if ($children()->whereIn('url', [$url, $url.'/'])->exists()) {
                continue;
            }

            $partnerships = $children()->whereIn('url', ['/'.$locale.'/about/partnerships', '/'.$locale.'/about/partnerships/'])->value('sort_order');
            if ($partnerships !== null) {
                $position = (int) $partnerships + 1;
                $children()->where('sort_order', '>=', $position)->increment('sort_order');
            } else {
                $position = (int) $children()->max('sort_order') + 1;
            }

            DB::table('menu_items')->insert([
                'parent_id' => $about->id,
                'type' => 'header',
                'label' => $label,
                'locale' => $locale,
                'target_kind' => 'url',
                'url' => $url,
                'group_key' => $about->group_key,
                'is_enabled' => true,
                'is_utility' => false,
                'open_in_new_tab' => false,
                'sort_order' => $position,
                'depth' => ((int) $about->depth) + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * A published About navigation card for the Councils page. With no cards
     * at all the About pages list every page of the area, Councils included.
     */
    private function addNavigationCard(): void
    {
        if (DB::table('about_navigation_cards')->doesntExist()
            || DB::table('about_navigation_cards')->where('target_key', 'about.councils')->exists()) {
            return;
        }

        $partnerships = DB::table('about_navigation_cards')->where('target_key', 'about.partnerships')->value('sort_order');
        if ($partnerships !== null) {
            $position = (int) $partnerships + 1;
            DB::table('about_navigation_cards')->where('sort_order', '>=', $position)->increment('sort_order');
        } else {
            $position = (int) DB::table('about_navigation_cards')->max('sort_order') + 1;
        }

        DB::table('about_navigation_cards')->insert([
            'target_key' => 'about.councils',
            'sort_order' => $position,
            'is_visible' => true,
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
