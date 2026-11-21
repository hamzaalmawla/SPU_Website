<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyFacultyResearchImportServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Services\Legacy\LegacyFacultyResearchImportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The old research portal listed research per faculty; the first import only read staff-profile
 * publications, so most papers had no faculty and papers missing from staff pages never arrived.
 */
final class LegacyFacultyResearchImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->path = storage_path('framework/testing/faculty-research.json');
        File::ensureDirectoryExists(dirname($this->path));
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    public function test_a_dry_run_stores_nothing(): void
    {
        $this->writeData([$this->entry(10, 27, 'A New Paper')]);

        $result = $this->importer()->import($this->path);

        self::assertFalse($result->written);
        self::assertSame(1, $result->created);
        self::assertSame(0, ResearchPublication::query()->where('legacy_source_table', 'jx_categories')->count());
    }

    public function test_writing_needs_the_approval_token(): void
    {
        $this->writeData([$this->entry(10, 27, 'A New Paper')]);

        $this->expectException(InvalidArgumentException::class);
        $this->importer()->import($this->path, true, 'wrong');
    }

    public function test_a_new_entry_becomes_a_public_publication_of_its_faculty_with_its_files(): void
    {
        $this->writeData([[...$this->entry(10, 47, 'A New Paper', [['id' => 5, 'visible' => true, 'en_file' => '123_paper.pdf']]), 'photo' => '1_cover.png']]);

        $this->importer()->import($this->path, true, LegacyFacultyResearchImportService::APPROVAL_TOKEN);

        $publication = ResearchPublication::query()->where('legacy_source_table', 'jx_categories')->firstOrFail();
        self::assertSame('pharmacy', $publication->faculty?->public_slug);
        self::assertSame('a-new-paper-10', $publication->slug);
        self::assertTrue(ResearchPublication::query()->public()->whereKey($publication->getKey())->exists());
        self::assertSame(['downloads/files/123_paper.pdf'], $publication->legacyFileReferences()->pluck('legacy_path')->all());
        self::assertSame('downloads/files/1_cover.png', $publication->legacy_image_path);

        $this->get('/en/research/publications?faculty=pharmacy')->assertOk()->assertSee('A New Paper');
        $this->get('/en/research/publications?faculty=medicine')->assertOk()->assertDontSee('A New Paper');
    }

    public function test_an_entry_already_imported_from_a_staff_page_only_gets_its_faculty(): void
    {
        $existing = $this->staffPublication('Shared Paper', ownerId: 99);
        $this->writeData([$this->entry(10, 67, 'Shared  paper')]);

        $result = $this->importer()->import($this->path, true, LegacyFacultyResearchImportService::APPROVAL_TOKEN);

        self::assertSame(1, $result->linkedToExisting);
        self::assertSame(0, ResearchPublication::query()->where('legacy_source_table', 'jx_categories')->count());
        self::assertSame('petroleum', $existing->refresh()->faculty?->public_slug);
    }

    public function test_bare_image_names_move_to_the_photo_directory_and_a_missing_one_comes_from_the_list(): void
    {
        $bare = $this->staffPublication('Bare Image', ownerId: 1);
        $bare->forceFill(['legacy_image_path' => '9_old.JPG'])->save();
        $notImage = $this->staffPublication('Pdf Photo', ownerId: 3);
        $notImage->forceFill(['legacy_image_path' => '7_paper.pdf'])->save();
        $missing = $this->staffPublication('No Image', ownerId: 2);
        $this->writeData([[...$this->entry(10, 27, 'No Image'), 'photo' => '5_list.png']]);

        $result = $this->importer()->import($this->path, true, LegacyFacultyResearchImportService::APPROVAL_TOKEN);

        self::assertSame('downloads/files/9_old.JPG', $bare->refresh()->legacy_image_path);
        self::assertSame('downloads/files/5_list.png', $missing->refresh()->legacy_image_path);
        self::assertNull($notImage->refresh()->legacy_image_path);
        self::assertSame(2, $result->imagesRepaired);
    }

    public function test_a_held_duplicate_the_faculty_list_published_becomes_public(): void
    {
        $held = $this->staffPublication('Held Paper', ownerId: 1, status: 'duplicate_review', enabled: false);
        $this->writeData([$this->entry(10, 27, 'Held Paper')]);

        $this->importer()->import($this->path, true, LegacyFacultyResearchImportService::APPROVAL_TOKEN);

        self::assertTrue(ResearchPublication::query()->public()->whereKey($held->getKey())->exists());
    }

    public function test_staff_page_publications_outside_the_lists_take_their_owners_faculty_when_unambiguous(): void
    {
        $agreed = $this->staffPublication('Owner Paper', ownerId: 7);
        $conflicting = $this->staffPublication('Conflict Paper', ownerId: 8);
        $this->writeData([], ['jx_councils' => ['7' => 14, '8' => 4], 'jx_councils1' => ['7' => 14, '8' => 6]]);

        $this->importer()->import($this->path, true, LegacyFacultyResearchImportService::APPROVAL_TOKEN);

        self::assertSame('business-administration', $agreed->refresh()->faculty?->public_slug);
        self::assertNull($conflicting->refresh()->faculty_id);
    }

    public function test_the_same_paper_in_two_faculty_lists_is_kept_once_and_a_rerun_creates_nothing(): void
    {
        $this->writeData([$this->entry(10, 27, 'Twice Listed'), $this->entry(11, 37, 'Twice Listed')]);

        $this->importer()->import($this->path, true, LegacyFacultyResearchImportService::APPROVAL_TOKEN);
        $rerun = $this->importer()->import($this->path, true, LegacyFacultyResearchImportService::APPROVAL_TOKEN);

        self::assertSame(1, ResearchPublication::query()->where('legacy_source_table', 'jx_categories')->count());
        self::assertSame(0, $rerun->created);
        self::assertSame(1, $rerun->duplicatesInLists);
    }

    public function test_the_export_reads_a_phpmyadmin_dump(): void
    {
        $dump = storage_path('framework/testing/legacy.sql');
        File::put($dump, implode("\n", [
            'INSERT INTO `jx_categories` (`id`, `en_name`, `ar_name`, `en_data`, `ar_data`, `service_type`, `category_order`, `photo`, `is_visible`) VALUES',
            "(10, 'It\\'s a Paper', 'بحث', NULL, '<p>نص</p>', 57, 3, '1_a.png', 1),",
            "(11, 'Other', 'آخر', NULL, NULL, 24, 1, NULL, 1);",
            'INSERT INTO `jx_items` (`id`, `category_id`, `service_type`, `en_name`, `ar_name`, `en_file`, `ar_file`, `photo`, `is_visible`, `is_accepted`) VALUES',
            "(5, 10, 57, 'file', 'ملف', '9_x.pdf', NULL, NULL, 1, 1);",
            'INSERT INTO `jx_councils` (`id`, `service_type`) VALUES',
            '(3, 10);',
        ]));

        $count = $this->importer()->export($dump, $this->path);
        File::delete($dump);

        $data = json_decode((string) file_get_contents($this->path), true);
        self::assertSame(1, $count);
        self::assertSame("It's a Paper", $data['entries'][0]['en_name']);
        self::assertSame('9_x.pdf', $data['entries'][0]['items'][0]['en_file']);
        self::assertSame(10, $data['owners']['jx_councils'][3]);
    }

    public function test_a_publication_with_its_own_faculty_ignores_its_researchers_faculty(): void
    {
        $publication = $this->staffPublication('Own Faculty', ownerId: 1);
        $publication->forceFill(['faculty_id' => Faculty::query()->where('public_slug', 'dentistry')->value('id')])->save();

        self::assertSame('dentistry', $publication->refresh()->effectiveFaculty()?->public_slug);
        self::assertSame(1, ResearchPublication::query()
            ->whereEffectiveFaculty(fn ($query) => $query->where('public_slug', 'dentistry'))
            ->whereKey($publication->getKey())
            ->count());
    }

    private function importer(): LegacyFacultyResearchImportServiceInterface
    {
        return app(LegacyFacultyResearchImportServiceInterface::class);
    }

    /** @param list<array<string, mixed>> $entries @param array<string, array<string, int>> $owners */
    private function writeData(array $entries, array $owners = ['jx_councils' => [], 'jx_councils1' => []]): void
    {
        File::put($this->path, (string) json_encode(['entries' => $entries, 'owners' => $owners]));
    }

    /** @param list<array<string, mixed>> $items @return array<string, mixed> */
    private function entry(int $id, int $service, string $title, array $items = []): array
    {
        return [
            'id' => $id,
            'service_type' => $service,
            'visible' => true,
            'order' => 1,
            'ar_name' => $title,
            'en_name' => $title,
            'ar_data' => null,
            'en_data' => '<p>Abstract of '.$title.'.</p>',
            'items' => $items,
        ];
    }

    private function staffPublication(string $title, int $ownerId, string $status = 'published', bool $enabled = true): ResearchPublication
    {
        $publication = ResearchPublication::query()->create([
            'legacy_source_table' => 'jx_member_categories',
            'legacy_source_id' => $ownerId * 100,
            'legacy_owner_id' => $ownerId,
            'extraction_status' => $status,
            'is_enabled' => $enabled,
            'sort_order' => 1,
        ]);
        ResearchPublicationTranslation::query()->create(['research_publication_id' => $publication->getKey(), 'locale' => 'en', 'title' => $title]);

        return $publication;
    }
}
