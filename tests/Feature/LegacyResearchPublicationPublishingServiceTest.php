<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyResearchPublicationPublishingServiceInterface;
use App\Models\Research\ResearchPublication;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyResearchPublicationPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_is_gated_replay_safe_and_makes_undated_research_public(): void
    {
        $actor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $publication = ResearchPublication::query()->create([
            'legacy_source_table' => 'jx_member_categories',
            'legacy_source_id' => 9001,
            'extraction_status' => 'metadata_review',
            'is_enabled' => false,
            'published_at' => null,
        ]);
        $publication->translations()->create([
            'locale' => 'en',
            'title' => 'Legacy public research',
            'abstract' => 'Source-backed abstract.',
        ]);
        MigrationLog::query()->create([
            'module' => 'research',
            'batch_name' => 'structured-import',
            'source_table' => 'jx_member_categories',
            'source_id' => 9001,
            'target_table' => 'research_publications',
            'target_id' => $publication->getKey(),
            'status' => 'success',
        ]);

        $service = app(LegacyResearchPublicationPublishingServiceInterface::class);
        $dryRun = $service->publishImported((int) $actor->getKey(), batch: 'public-research');
        $this->assertSame(1, $dryRun->eligibleRows);
        $this->assertFalse((bool) $publication->fresh()->is_enabled);

        try {
            $service->publishImported((int) $actor->getKey(), true, 'wrong-token', 'public-research');
            $this->fail('Expected publication approval token.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('publish-legacy-research', $exception->getMessage());
        }

        $written = $service->publishImported((int) $actor->getKey(), true, 'publish-legacy-research', 'public-research');
        $this->assertSame(1, $written->publishedRows);
        $this->assertDatabaseHas('research_publications', [
            'id' => $publication->getKey(),
            'is_enabled' => 1,
            'extraction_status' => 'published',
        ]);

        $replay = $service->publishImported((int) $actor->getKey(), true, 'publish-legacy-research', 'public-research');
        $this->assertSame(1, $replay->alreadyPublishedRows);
        $this->assertSame(1, MigrationLog::query()->where('module', 'research_publication')->count());
    }
}
