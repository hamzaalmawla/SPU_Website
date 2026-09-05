<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PublishResearchLandingMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_migration_publishes_reviewed_research_landing_content(): void
    {
        $migration = require database_path('migrations/2026_09_05_000002_publish_reviewed_research_landing_content.php');
        $migration->up();

        $published = app(CmsWorkflowServiceInterface::class)->getPublishedPayload('research.index');

        $this->assertIsArray($published);
        $this->assertNotEmpty($published['translations']['ar'] ?? null);
        $this->assertNotEmpty($published['translations']['en'] ?? null);

        // Verify copy matches reviewed payload
        $this->assertSame('Research from Damascus, published worldwide', $published['translations']['en']['hero']['title']);
        $this->assertSame('أبحاث من دمشق، منشورة عالمياً', $published['translations']['ar']['hero']['title']);

        // Verify public pages render 200 OK with the reviewed content
        $enResponse = $this->get('/en/research');
        $enResponse->assertOk();
        $enResponse->assertSee('Research from Damascus, published worldwide');
        $enResponse->assertSee('Penetrating abdominal injuries during the Syrian war');
        $enResponse->assertSee('10.1016/j.injury.2017.02.005');

        $arResponse = $this->get('/ar/research');
        $arResponse->assertOk();
        $arResponse->assertSee('أبحاث من دمشق، منشورة عالمياً');
        $arResponse->assertSee('Penetrating abdominal injuries during the Syrian war');

        // Verify audit trail exists
        $auditLog = AuditLog::query()
            ->where('action', 'cms.published')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('research.index', $auditLog->metadata['target_key'] ?? null);
    }
}
