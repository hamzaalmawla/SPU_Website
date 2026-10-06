<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class FeatureDeploymentDataPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_display_schema_upgrade_preserves_all_existing_content(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn(['focal_x', 'focal_y', 'display_fit']);
        });

        $facultyMemberId = DB::table('faculty_members')->insertGetId([
            'slug' => 'production-ai-member',
            'email' => 'member@example.test',
            'sort_order' => 17,
            'is_enabled' => true,
            'publication_status' => 'published',
            'published_at' => now()->subDay(),
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('faculty_member_translations')->insert([
            'faculty_member_id' => $facultyMemberId,
            'locale' => 'en',
            'full_name' => 'Production AI Member',
            'position' => 'Professor',
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subDay(),
        ]);
        $mediaId = DB::table('media_assets')->insertGetId([
            'disk' => 'public',
            'directory' => 'media/image',
            'filename' => 'existing.jpg',
            'original_name' => 'existing.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1234,
            'checksum' => hash('sha256', 'existing-media'),
            'media_type' => 'image',
            'library_scope' => 'main',
            'metadata_status' => 'reviewed',
            'title_ar' => 'صورة قائمة',
            'title_en' => 'Existing image',
            'alt_text_ar' => 'وصف قائم',
            'alt_text_en' => 'Existing description',
            'path' => 'media/image/existing.jpg',
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subDay(),
        ]);

        $before = [
            'member' => DB::table('faculty_members')->where('id', $facultyMemberId)->first(),
            'translation' => DB::table('faculty_member_translations')->where('faculty_member_id', $facultyMemberId)->first(),
            'media' => DB::table('media_assets')->where('id', $mediaId)->first(),
        ];

        $migration = require database_path('migrations/2026_10_06_000001_add_display_settings_to_media_assets.php');
        $migration->up();

        $afterMember = DB::table('faculty_members')->where('id', $facultyMemberId)->first();
        $afterTranslation = DB::table('faculty_member_translations')->where('faculty_member_id', $facultyMemberId)->first();
        $afterMedia = DB::table('media_assets')->where('id', $mediaId)->first();

        $this->assertEquals($before['member'], $afterMember);
        $this->assertEquals($before['translation'], $afterTranslation);
        foreach ((array) $before['media'] as $column => $value) {
            $this->assertEquals($value, $afterMedia->{$column});
        }
        $this->assertSame(50.0, (float) $afterMedia->focal_x);
        $this->assertSame(50.0, (float) $afterMedia->focal_y);
        $this->assertSame('cover', $afterMedia->display_fit);
    }

    public function test_legacy_achievement_upgrade_preserves_homepage_json_and_is_idempotent(): void
    {
        $sectionId = DB::table('homepage_sections')->insertGetId([
            'key' => 'achievements_highlights',
            'type' => 'listing',
            'sort_order' => 3,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $payloads = [
            'ar' => [
                'title' => 'التكريم والتميز',
                'custom' => ['must_survive' => true],
                'items' => [[
                    'title' => 'إنجاز قائم',
                    'typeTag' => 'بحث',
                    'summary' => 'ملخص قائم',
                    'imageUrl' => '/images/existing.webp',
                    'meta' => 'بيانات قائمة',
                    'action' => ['label' => 'تفاصيل', 'url' => '/ar/research'],
                ]],
            ],
            'en' => [
                'title' => 'Honor & Excellence',
                'custom' => ['must_survive' => true],
                'items' => [[
                    'title' => 'Existing achievement',
                    'typeTag' => 'Research',
                    'summary' => 'Existing summary',
                    'imageUrl' => '/images/existing.webp',
                    'meta' => 'Existing metadata',
                    'action' => ['label' => 'Details', 'url' => '/en/research'],
                ]],
            ],
        ];

        foreach ($payloads as $locale => $payload) {
            DB::table('homepage_section_translations')->insert([
                'section_id' => $sectionId,
                'locale' => $locale,
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_09_28_000002_migrate_legacy_homepage_achievements.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('achievements', 1);
        $this->assertDatabaseCount('achievement_translations', 2);
        $this->assertDatabaseCount('achievement_categories', 1);
        $this->assertDatabaseHas('achievements', ['legacy_image_path' => '/images/existing.webp']);

        foreach ($payloads as $locale => $original) {
            $stored = $this->homepagePayload($sectionId, $locale);
            $this->assertSame($original['title'], $stored['title']);
            $this->assertSame($original['custom'], $stored['custom']);
            $this->assertSame($original['items'], $stored['items']);
            $this->assertSame('/'.$locale.'/achievements', $stored['sectionAction']['url']);
        }
    }

    public function test_society_events_upgrade_preserves_existing_news_and_editorial_values(): void
    {
        $categoryId = (int) DB::table('news_categories')->where('slug', 'society-events')->value('id');
        DB::table('news_categories')->where('id', $categoryId)->update(['is_enabled' => false]);
        DB::table('news_category_translations')
            ->where('news_category_id', $categoryId)
            ->where('locale', 'en')
            ->update(['name' => 'Editorial Society Label']);
        $articleId = DB::table('news_articles')->insertGetId([
            'news_category_id' => $categoryId,
            'slug' => 'existing-society-story',
            'status' => 'published',
            'published_at' => now(),
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 17,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = DB::table('homepage_sections')->insertGetId([
            'key' => 'university_news',
            'type' => 'listing',
            'sort_order' => 6,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $original = ['title' => 'Custom News', 'content' => ['custom_setting' => 'keep-me']];
        DB::table('homepage_section_translations')->insert([
            'section_id' => $sectionId,
            'locale' => 'en',
            'payload_json' => json_encode($original, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_28_000001_add_society_events_news_category.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('news_articles', ['id' => $articleId, 'news_category_id' => $categoryId, 'sort_order' => 17]);
        $this->assertDatabaseHas('news_categories', ['id' => $categoryId, 'is_enabled' => false]);
        $this->assertDatabaseHas('news_category_translations', ['news_category_id' => $categoryId, 'locale' => 'en', 'name' => 'Editorial Society Label']);
        $this->assertSame(1, DB::table('news_categories')->where('slug', 'society-events')->count());

        $stored = $this->homepagePayload($sectionId, 'en');
        $this->assertSame('Custom News', $stored['title']);
        $this->assertSame('keep-me', $stored['content']['custom_setting']);
        $this->assertSame('/en/news/society-events', $stored['content']['society_cta_url']);
    }

    public function test_category_collision_aborts_without_overwriting_existing_data(): void
    {
        DB::table('news_categories')->where('slug', 'society-events')->update(['type' => 'announcement']);
        $migration = require database_path('migrations/2026_09_28_000001_add_society_events_news_category.php');

        try {
            $migration->up();
            $this->fail('Expected incompatible category collision to abort migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('stopped without overwriting', $exception->getMessage());
        }

        $this->assertDatabaseHas('news_categories', ['slug' => 'society-events', 'type' => 'announcement']);
    }

    public function test_malformed_legacy_payload_aborts_without_partial_import(): void
    {
        $sectionId = DB::table('homepage_sections')->insertGetId([
            'key' => 'achievements_highlights',
            'type' => 'listing',
            'sort_order' => 3,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('homepage_section_translations')->insert([
            'section_id' => $sectionId,
            'locale' => 'en',
            'payload_json' => '{invalid-json',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_09_28_000002_migrate_legacy_homepage_achievements.php');

        try {
            $migration->up();
            $this->fail('Expected malformed legacy payload to abort migration.');
        } catch (\JsonException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('achievements', 0);
        $this->assertSame('{invalid-json', DB::table('homepage_section_translations')->where('section_id', $sectionId)->value('payload_json'));
    }

    public function test_achievement_schema_rollback_is_blocked_to_protect_managed_content(): void
    {
        $migration = require database_path('migrations/2026_09_28_000001_create_achievement_domain_tables.php');

        try {
            $migration->down();
            $this->fail('Expected managed-content rollback to be blocked.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cannot be removed automatically', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('achievements'));
    }

    /** @return array<string, mixed> */
    private function homepagePayload(int $sectionId, string $locale): array
    {
        $payload = DB::table('homepage_section_translations')
            ->where('section_id', $sectionId)
            ->where('locale', $locale)
            ->value('payload_json');

        return is_array($payload) ? $payload : json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);
    }
}
