<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Research\LegacyResearchFileReference;
use App\Models\Research\ResearchPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Legacy research attachments were stored as bare filenames, and
 * ResearchPageService::publicationDownloads() renders those references straight
 * into the page — so each one became a download link pointing at "/<file>" on the
 * web root, which 404s. A broken download is worse than a missing one.
 */
final class LegacyResearchFilePathRepairTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/legacy-root');
        File::ensureDirectoryExists($this->root.'/downloads/files');
        File::put($this->root.'/downloads/files/paper.pdf', '%PDF-1.4 test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function reference(string $path): LegacyResearchFileReference
    {
        $publication = ResearchPublication::query()->create([
            'category_key' => 'journal',
            'is_enabled' => true,
            'sort_order' => 1,
            'published_at' => now()->subDay(),
        ]);

        return LegacyResearchFileReference::query()->create([
            'research_publication_id' => $publication->getKey(),
            'legacy_source_table' => 'jx_member_items',
            'legacy_source_id' => 1,
            'legacy_path' => $path,
            'sort_order' => 0,
            'status' => 'deferred',
        ]);
    }

    public function test_a_bare_filename_is_resolved_to_its_real_directory(): void
    {
        $reference = $this->reference('paper.pdf');

        $this->artisan('legacy-import:repair-research-file-paths', ['--root' => $this->root, '--write' => true])
            ->assertSuccessful();

        self::assertSame('downloads/files/paper.pdf', $reference->refresh()->legacy_path);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $reference = $this->reference('paper.pdf');

        $this->artisan('legacy-import:repair-research-file-paths', ['--root' => $this->root])
            ->assertSuccessful();

        self::assertSame('paper.pdf', $reference->refresh()->legacy_path);
    }

    public function test_a_reference_with_no_file_is_left_alone_unless_pruned(): void
    {
        $reference = $this->reference('does-not-exist.pdf');

        $this->artisan('legacy-import:repair-research-file-paths', ['--root' => $this->root, '--write' => true])
            ->assertSuccessful();

        self::assertSame('does-not-exist.pdf', $reference->refresh()->legacy_path);
        self::assertDatabaseHas('legacy_research_file_references', ['id' => $reference->getKey()]);
    }

    public function test_pruning_removes_a_reference_whose_file_is_gone(): void
    {
        $reference = $this->reference('does-not-exist.pdf');

        $this->artisan('legacy-import:repair-research-file-paths', [
            '--root' => $this->root, '--write' => true, '--prune' => true,
        ])->assertSuccessful();

        self::assertDatabaseMissing('legacy_research_file_references', ['id' => $reference->getKey()]);
    }

    public function test_an_already_qualified_path_is_not_touched(): void
    {
        $reference = $this->reference('downloads/files/paper.pdf');

        $this->artisan('legacy-import:repair-research-file-paths', ['--root' => $this->root, '--write' => true])
            ->assertSuccessful();

        self::assertSame('downloads/files/paper.pdf', $reference->refresh()->legacy_path);
    }
}
