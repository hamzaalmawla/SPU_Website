<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsPageTextImportServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class CmsPageTextImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->path = storage_path('framework/testing/page-texts.json');
        File::ensureDirectoryExists(dirname($this->path));
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    public function test_page_text_is_published_into_the_page_structure_and_shown(): void
    {
        $admin = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
        $this->writeTargets(['about.accreditation' => [
            'ar' => ['summary' => 'جامعة مرخصة بموجب المرسوم الجمهوري', 'sections' => [['title' => 'الاعتماد المحلي', 'body' => 'نص الاعتماد المحلي الكامل']], 'documents' => [['label' => 'الملف', 'url' => '/downloads/files/x.pdf']]],
            'en' => ['summary' => 'Licensed by presidential decree', 'sections' => [['title' => 'Local Accreditation', 'body' => 'The full local accreditation text']]],
        ]]);

        $dry = app(CmsPageTextImportServiceInterface::class)->import($this->path, (string) $admin->getKey(), false);
        self::assertStringStartsWith('dry run', $dry[0]['result']);
        self::assertNull(app(CmsWorkflowServiceInterface::class)->getPublishedPayload('about.accreditation'));

        $result = app(CmsPageTextImportServiceInterface::class)->import($this->path, (string) $admin->getKey(), true);

        self::assertStringStartsWith('published', $result[0]['result']);
        $this->get('/ar/about/accreditation')->assertOk()->assertSee('نص الاعتماد المحلي الكامل')->assertSee('/downloads/files/x.pdf', false);
        $this->get('/en/about/accreditation')->assertOk()->assertSee('The full local accreditation text');
    }

    public function test_a_page_with_an_editors_unpublished_draft_is_left_alone(): void
    {
        $admin = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = app(AboutPageServiceInterface::class)->getEditablePayload('about.accreditation');
        $workflow->saveDraft('about.accreditation', $payload, (int) $admin->getKey());
        $this->writeTargets(['about.accreditation' => ['ar' => ['summary' => 'نص جديد']]]);

        $result = app(CmsPageTextImportServiceInterface::class)->import($this->path, (string) $admin->getKey(), true);

        self::assertStringStartsWith('SKIPPED', $result[0]['result']);
        self::assertNull($workflow->getPublishedPayload('about.accreditation'));
    }

    /** @param array<string, mixed> $targets */
    private function writeTargets(array $targets): void
    {
        File::put($this->path, (string) json_encode(['targets' => $targets], JSON_UNESCAPED_UNICODE));
    }
}
