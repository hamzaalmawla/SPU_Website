<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ResearchOfficeCouncilMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_office_page_becomes_the_council_and_the_menu_is_renamed(): void
    {
        $legacy = fn (string $title, string $eyebrow, string $leader): array => [
            'hero' => ['eyebrow' => $eyebrow, 'title' => $title, 'summary' => 'Edited summary.', 'breadcrumbs' => [['label' => 'Home', 'url' => '/'], ['label' => 'Office', 'url' => '/research/office/']]],
            'leadership' => ['title' => 'Leadership', 'items' => [['name' => $leader, 'role' => 'Research Director', 'email' => 'x@spu.edu.sy', 'image' => '/images/a.jpg']]],
            'services' => ['title' => 'Our Services', 'items' => [['title' => 'Grant Support', 'description' => 'Grant help.']]],
            'statistics' => ['title' => 'Stats', 'items' => [['value' => '1', 'label' => 'Publications']]],
            'contact' => ['title' => 'Contact', 'email' => 'research.office@spu.edu.sy'],
        ];
        DB::table('cms_target_contents')->insert([
            'target_key' => 'research.office',
            'status' => 'published',
            'payload_json' => json_encode(['translations' => [
                'ar' => $legacy('مكتب البحث', 'إدارة البحث', 'د. أيمن'),
                'en' => $legacy('Research Office', 'Research Administration', 'Dr. Ayman'),
            ]]),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['ar' => 'مكتب البحث', 'en' => 'Research Office'] as $locale => $label) {
            DB::table('menu_items')->insert(['type' => 'link', 'label' => $label, 'locale' => $locale, 'target_kind' => 'url', 'url' => '/'.$locale.'/research/office', 'created_at' => now(), 'updated_at' => now()]);
        }

        (require database_path('migrations/2026_11_08_000002_turn_research_office_into_council.php'))->up();

        $payload = json_decode((string) DB::table('cms_target_contents')->where('target_key', 'research.office')->value('payload_json'), true);
        $en = $payload['translations']['en'];
        $this->assertSame('Research and Scientific Publication Council', $en['hero']['title']);
        $this->assertSame('مجلس البحث والنشر العلمي', $payload['translations']['ar']['hero']['title']);
        $this->assertSame('Edited summary.', $en['hero']['summary']);
        $this->assertNotSame('Research Administration', $en['hero']['eyebrow']);
        $this->assertSame('Research and Scientific Publication Council', $en['hero']['breadcrumbs'][1]['label']);
        $this->assertSame('Dr. Ayman', $en['council']['members'][0]['name']);
        $this->assertSame('member', $en['council']['members'][0]['role']);
        $this->assertFalse($en['council']['members'][0]['isStaff']);
        $this->assertSame($en['council']['members'][0]['id'], $payload['translations']['ar']['council']['members'][0]['id']);
        $this->assertSame('Council Functions', $en['functions']['title']);
        $this->assertSame('Grant Support', $en['functions']['items'][0]['title']);
        $this->assertArrayNotHasKey('statistics', $en);
        $this->assertArrayNotHasKey('contact', $en);
        $this->assertArrayNotHasKey('leadership', $en);
        $this->assertSame('Research and Scientific Publication Council', DB::table('menu_items')->where('locale', 'en')->value('label'));
        $this->assertSame('مجلس البحث والنشر العلمي', DB::table('menu_items')->where('locale', 'ar')->value('label'));

    }
}
