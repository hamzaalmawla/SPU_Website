<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyPageContentServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class LegacyPageContentCoverageTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->path = storage_path('framework/testing/page-content.json');
        File::ensureDirectoryExists(dirname($this->path));
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    public function test_the_export_reads_pages_and_record_lists_from_a_dump_and_skips_word_markup(): void
    {
        $dump = storage_path('framework/testing/pages.sql');
        File::put($dump, implode("\n", [
            'INSERT INTO `jx_categories` (`id`, `parent`, `en_name`, `ar_name`, `en_data`, `ar_data`, `service_type`, `is_visible`, `is_link`, `url`) VALUES',
            "(23, 0, 'Vision', 'الرؤية', NULL, '<style>p{}</style><p>Normal 0 false false</p><p>رؤية الجامعة أن تكون مركزاً علمياً متميزاً</p>', 1, 1, 0, NULL),",
            "(82, 0, 'Dean', 'كلمة عميد الكلية', NULL, '<p>نص كلمة العميد</p>', 21, 1, 0, NULL),",
            "(900, 0, 'News', 'خبر', NULL, NULL, 3, 1, 0, NULL);",
        ]));

        $count = app(LegacyPageContentServiceInterface::class)->export($dump, $this->path);
        File::delete($dump);

        $data = json_decode((string) file_get_contents($this->path), true);
        $pages = collect($data['pages'])->keyBy('id');
        self::assertSame(2, $count);
        self::assertSame('رؤية الجامعة أن تكون مركزاً علمياً متميزاً', $pages[23]['text_ar']);
        self::assertSame('/about/vision-mission', $pages[23]['new_path']);
        self::assertSame('/faculties/medicine', $pages[82]['new_path']);
        self::assertSame([900], $data['records']['3']);
    }

    public function test_coverage_reports_text_missing_from_the_new_page_and_text_present_on_it(): void
    {
        File::put($this->path, (string) json_encode(['pages' => [
            ['id' => 1, 'site' => 'main', 'breadcrumb_ar' => 'غائب', 'new_path' => '/contact', 'text_ar' => 'هذه جملة طويلة غير موجودة إطلاقاً في الموقع الجديد', 'text_en' => ''],
            ['id' => 2, 'site' => 'main', 'breadcrumb_ar' => 'بلا صفحة', 'new_path' => null, 'in_menu' => false, 'text_ar' => 'جملة أخرى طويلة بما يكفي للمقارنة هنا', 'text_en' => ''],
        ], 'records' => []]));

        $rows = collect(app(LegacyPageContentServiceInterface::class)->coverage($this->path))->keyBy('legacyId');

        self::assertSame(0, $rows[1]->coverage['ar']);
        self::assertNull($rows[1]->coverage['en']);
        self::assertSame('missing', $rows[1]->status);
        self::assertSame('hidden on old site', $rows[2]->status);
    }
}
