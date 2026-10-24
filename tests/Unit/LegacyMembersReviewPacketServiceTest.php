<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyMembersReviewPacketServiceInterface;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyMembersReviewPacketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('old_database.connection_name', (string) config('database.default'));
        config()->set('old_database.connection', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        app(OldDatabaseConnection::class)->connection();
        $this->createLegacyTables();
        $this->seedLegacyEvidence();
    }

    public function test_exports_metadata_only_reconciliation_evidence_for_both_services(): void
    {
        DB::table('migration_logs')->insert([
            ['module' => 'staff', 'batch_name' => 'old', 'source_table' => 'jx_councils', 'source_id' => 5, 'target_table' => 'faculty_members', 'target_id' => 50, 'status' => 'success'],
            ['module' => 'staff', 'batch_name' => 'old', 'source_table' => 'jx_councils1', 'source_id' => 5, 'target_table' => 'faculty_members', 'target_id' => 51, 'status' => 'success'],
            ['module' => 'research', 'batch_name' => 'unsafe-old', 'source_table' => 'jx_member_categories', 'source_id' => 10, 'target_table' => 'research_publications', 'target_id' => 70, 'status' => 'success'],
            ['module' => 'research', 'batch_name' => 'unsafe-old', 'source_table' => 'jx_member_items', 'source_id' => 20, 'target_table' => 'research_files', 'target_id' => 80, 'status' => 'success'],
        ]);

        $result = app(LegacyMembersReviewPacketServiceInterface::class)->export(directory: 'members-evidence');

        $this->assertSame([1, 2], $result->selectedServices);
        $this->assertSame(4, $result->categorySourceRows);
        $this->assertSame(4, $result->categoryOutputRows);
        $this->assertSame(5, $result->itemSourceRows);
        $this->assertSame(5, $result->itemOutputRows);
        $this->assertSame(4, $result->packetCount);
        $this->assertSame(['both_sources' => 1, 'councils1_only' => 1, 'councils_only' => 1, 'missing' => 1], $result->ownerStatusCounts);
        $this->assertSame(1, $result->ownerMappedRows);
        $this->assertSame(3, $result->ownerUnmappedRows);
        $this->assertSame(1, $result->categoryMappedRows);
        $this->assertSame(1, $result->itemMappedRows);
        $this->assertSame(1, $result->orphanItems);
        $this->assertSame(1, $result->serviceMismatchItems);
        $this->assertSame(2, $result->duplicateFileRows);
        $this->assertCount(6, $result->paths);

        $categoryRows = $this->csvRows($this->path($result->paths, 'service_1_categories.csv'));
        $mapped = $this->row($categoryRows, 10);
        $this->assertSame('member_research_output', $mapped['context_semantic']);
        $this->assertSame('both_sources', $mapped['owner_evidence_status']);
        $this->assertSame('1', $mapped['councils_exists']);
        $this->assertSame('1', $mapped['councils1_exists']);
        $this->assertSame('2', $mapped['owner_mapping_success_count']);
        $this->assertSame('2', $mapped['child_item_total']);
        $this->assertSame('1', $mapped['research_publication_mapping_success_count']);
        $this->assertSame('mapped_reconciliation_review', $mapped['review_status']);
        $this->assertStringContainsString('owner_source_ambiguous', $mapped['blockers']);
        $this->assertStringContainsString('existing_target_mapping', $mapped['blockers']);
        $this->assertSame('', $mapped['approval_decision']);
        $this->assertSame('', $mapped['approved_target']);
        $this->assertSame('/members/index.php?page=show&ex=2&dir=items&lang=1&ser=1&cat_id=10', $mapped['legacy_ar_category_url_candidate']);

        $serviceTwoCategories = $this->csvRows($this->path($result->paths, 'service_2_categories.csv'));
        $this->assertSame('councils1_only', $this->row($serviceTwoCategories, 12)['owner_evidence_status']);
        $missing = $this->row($serviceTwoCategories, 13);
        $this->assertSame('missing', $missing['owner_evidence_status']);
        $this->assertStringContainsString('owner_not_found', $missing['blockers']);

        $itemRows = [
            ...$this->csvRows($this->path($result->paths, 'service_1_items.csv')),
            ...$this->csvRows($this->path($result->paths, 'service_2_items.csv')),
        ];
        $duplicate = $this->row($itemRows, 20);
        $this->assertSame('2', $duplicate['en_file_duplicate_path_count']);
        $this->assertStringContainsString('duplicate_file_path', $duplicate['blockers']);
        $this->assertSame('mapped_reconciliation_review', $duplicate['review_status']);
        $this->assertSame('needs_runtime_evidence', $duplicate['legacy_url_status']);
        $this->assertSame('', $duplicate['legacy_url']);
        $this->assertStringContainsString('missing_parent_category', $this->row($itemRows, 23)['blockers']);
        $this->assertStringContainsString('parent_service_mismatch', $this->row($itemRows, 24)['blockers']);

        foreach ($result->paths as $path) {
            $contents = Storage::disk('local')->get($path);
            $this->assertStringNotContainsString('SECRET_CATEGORY_HTML', $contents);
            $this->assertStringNotContainsString('SECRET_ITEM_HTML', $contents);
        }
        $this->assertSame(4, DB::table('migration_logs')->count());

        $manifest = json_decode(Storage::disk('local')->get($this->path($result->paths, 'manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($manifest['read_only']);
        $this->assertFalse($manifest['import_supported']);
        $this->assertFalse($manifest['redirects_supported']);
        $this->assertSame('staff_owner_identity', $manifest['parent_semantics']);
        $this->assertSame('unresolved_product_decision', $manifest['target_policy']);
    }

    public function test_service_filter_is_applied_to_categories_and_items(): void
    {
        $result = app(LegacyMembersReviewPacketServiceInterface::class)->export(services: [2]);

        $this->assertSame([2], $result->selectedServices);
        $this->assertSame(2, $result->categoryOutputRows);
        $this->assertSame(3, $result->itemOutputRows);
        $this->assertSame(2, $result->packetCount);
        $this->assertCount(4, $result->paths);
    }

    public function test_invalid_filter_creates_no_files(): void
    {
        try {
            app(LegacyMembersReviewPacketServiceInterface::class)->export(services: [3]);
            $this->fail('Expected invalid service filter rejection.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Allowed values: 1, 2', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function createLegacyTables(): void
    {
        Schema::connection('legacy_mysql')->create('jx_member_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent');
            $table->unsignedInteger('service_type');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->text('ar_brief')->nullable();
            $table->text('en_brief')->nullable();
            $table->text('ar_data')->nullable();
            $table->text('en_data')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_link')->default(false);
            $table->string('url')->nullable();
            $table->string('photo')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->unsignedInteger('member_category_order')->default(0);
        });
        Schema::connection('legacy_mysql')->create('jx_member_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('member_category_id')->nullable();
            $table->unsignedInteger('service_type');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->text('ar_brief')->nullable();
            $table->text('en_brief')->nullable();
            $table->text('ar_description')->nullable();
            $table->text('en_description')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_accepted')->default(true);
            $table->boolean('is_archive')->default(false);
            $table->boolean('is_main')->default(false);
            $table->unsignedInteger('member_item_order')->default(0);
            $table->string('post_date')->nullable();
            $table->string('video_link')->nullable();
            $table->string('photo')->nullable();
            $table->string('large_photo')->nullable();
            $table->string('ar_file')->nullable();
            $table->string('en_file')->nullable();
        });
        foreach (['jx_councils', 'jx_councils1'] as $name) {
            Schema::connection('legacy_mysql')->create($name, function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('service_type')->nullable();
                $table->string('ar_name')->nullable();
                $table->string('en_name')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_visible')->default(true);
            });
        }
    }

    private function seedLegacyEvidence(): void
    {
        $legacy = app('db')->connection('legacy_mysql');
        $legacy->table('jx_member_categories')->insert([
            ['id' => 10, 'parent' => 5, 'service_type' => 1, 'ar_name' => 'بحث', 'en_name' => 'Research', 'ar_brief' => 'brief', 'en_brief' => null, 'ar_data' => '<p>SECRET_CATEGORY_HTML</p>', 'en_data' => null, 'is_visible' => 1, 'is_link' => 0, 'url' => null, 'photo' => 'category.jpg', 'start_date' => '2020-01-01', 'end_date' => null, 'member_category_order' => 1],
            ['id' => 11, 'parent' => 6, 'service_type' => 1, 'ar_name' => 'بحث آخر', 'en_name' => 'Other', 'ar_brief' => null, 'en_brief' => null, 'ar_data' => null, 'en_data' => null, 'is_visible' => 0, 'is_link' => 1, 'url' => 'https://example.test', 'photo' => null, 'start_date' => '0000-00-00', 'end_date' => null, 'member_category_order' => 2],
            ['id' => 12, 'parent' => 7, 'service_type' => 2, 'ar_name' => 'محاضرة', 'en_name' => 'Lecture', 'ar_brief' => null, 'en_brief' => null, 'ar_data' => null, 'en_data' => null, 'is_visible' => 1, 'is_link' => 0, 'url' => null, 'photo' => null, 'start_date' => null, 'end_date' => null, 'member_category_order' => 3],
            ['id' => 13, 'parent' => 8, 'service_type' => 2, 'ar_name' => null, 'en_name' => 'under construction', 'ar_brief' => null, 'en_brief' => null, 'ar_data' => null, 'en_data' => null, 'is_visible' => 1, 'is_link' => 0, 'url' => null, 'photo' => null, 'start_date' => null, 'end_date' => 'bad-date', 'member_category_order' => 4],
        ]);
        $base = ['ar_name' => 'اسم', 'en_name' => 'Name', 'ar_brief' => null, 'en_brief' => null, 'ar_description' => '<p>SECRET_ITEM_HTML</p>', 'en_description' => null, 'is_visible' => 1, 'is_accepted' => 1, 'is_archive' => 0, 'is_main' => 0, 'member_item_order' => 1, 'post_date' => '2020-01-01', 'video_link' => null, 'photo' => null, 'large_photo' => null, 'ar_file' => null];
        $legacy->table('jx_member_items')->insert([
            [...$base, 'id' => 20, 'member_category_id' => 10, 'service_type' => 1, 'en_file' => 'duplicate.pdf'],
            [...$base, 'id' => 21, 'member_category_id' => 10, 'service_type' => 1, 'en_file' => null, 'photo' => 'item.jpg'],
            [...$base, 'id' => 22, 'member_category_id' => 12, 'service_type' => 2, 'en_file' => 'duplicate.pdf', 'is_archive' => 1],
            [...$base, 'id' => 23, 'member_category_id' => 999, 'service_type' => 2, 'en_file' => null, 'ar_description' => null, 'is_accepted' => 0],
            [...$base, 'id' => 24, 'member_category_id' => 11, 'service_type' => 2, 'en_file' => 'mismatch.pdf'],
        ]);
        $legacy->table('jx_councils')->insert([
            ['id' => 5, 'service_type' => 4, 'ar_name' => 'مالك', 'en_name' => 'Owner', 'email' => 'both@example.test', 'is_visible' => 1],
            ['id' => 6, 'service_type' => 4, 'ar_name' => 'مالك 2', 'en_name' => 'Owner 2', 'email' => 'c@example.test', 'is_visible' => 1],
        ]);
        $legacy->table('jx_councils1')->insert([
            ['id' => 5, 'service_type' => 4, 'ar_name' => 'مالك', 'en_name' => 'Owner', 'email' => 'both@example.test', 'is_visible' => 1],
            ['id' => 7, 'service_type' => 4, 'ar_name' => 'مالك 3', 'en_name' => 'Owner 3', 'email' => 'c1@example.test', 'is_visible' => 1],
        ]);
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $path): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, Storage::disk('local')->get($path));
        rewind($stream);
        $headers = fgetcsv($stream);
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);

        return $rows;
    }

    /** @param array<int, string> $paths */
    private function path(array $paths, string $suffix): string
    {
        return array_values(array_filter($paths, fn (string $path): bool => str_ends_with($path, $suffix)))[0];
    }

    /** @param array<int, array<string, string>> $rows @return array<string, string> */
    private function row(array $rows, int $id): array
    {
        return array_values(array_filter($rows, fn (array $row): bool => (int) $row['source_id'] === $id))[0];
    }
}
