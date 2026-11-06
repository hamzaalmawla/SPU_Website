<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Models\Content\Directorate;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DirectorateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_manager_typed_on_the_directorate_shows_name_contact_and_education(): void
    {
        $this->publishDirectorate(function (array $payload): array {
            $payload['manager_email'] = 'sami.haddad@spu.edu.sy';
            $payload['manager_phone'] = '+963 11 123 4567';
            $payload['translations']['en']['manager_name'] = 'Dr. Sami Haddad';
            // The admin textarea sends one qualification per line.
            $payload['translations']['en']['manager_education_json'] = "PhD in Computer Science, Damascus University\n\n  MSc in Information Systems  ";
            $payload['translations']['ar']['manager_name'] = 'د. سامي حداد';
            $payload['translations']['ar']['manager_education_json'] = ['دكتوراه في علوم الحاسوب، جامعة دمشق'];

            return $payload;
        });

        $directorate = Directorate::query()->where('slug', 'it-services')->with('translations')->firstOrFail();
        $this->assertSame(
            ['PhD in Computer Science, Damascus University', 'MSc in Information Systems'],
            $directorate->translations->firstWhere('locale', 'en')?->manager_education_json,
        );

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSee('Directorate Manager')
            ->assertSee('Dr. Sami Haddad')
            ->assertSee('PhD in Computer Science, Damascus University')
            ->assertSee('MSc in Information Systems')
            ->assertSee('mailto:sami.haddad@spu.edu.sy', false)
            ->assertSee('tel:+963111234567', false)
            ->assertDontSee('/about/profile/', false);

        $this->get('/ar/about/directorates/it-services')
            ->assertOk()
            ->assertSee('مدير المديرية')
            ->assertSee('د. سامي حداد')
            ->assertSee('دكتوراه في علوم الحاسوب، جامعة دمشق')
            ->assertSee('المؤهلات العلمية');
    }

    public function test_no_manager_shows_until_a_name_is_typed_and_a_missing_language_uses_the_other(): void
    {
        $this->get('/en/about/directorates/it-services')->assertOk()->assertDontSee('Directorate Manager');

        $this->publishDirectorate(function (array $payload): array {
            $payload['manager_email'] = 'manager@spu.edu.sy';
            $payload['translations']['ar']['manager_name'] = 'د. سامي حداد';

            return $payload;
        });

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSee('Directorate Manager')
            ->assertSee('د. سامي حداد');
    }

    public function test_publishing_rejects_a_bad_manager_email_or_phone(): void
    {
        $directorate = Directorate::query()->where('slug', 'it-services')->firstOrFail();
        $targetKey = 'entity.directorate.'.$directorate->getKey();
        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload ?? [];
        $payload['manager_email'] = 'not an email';
        $payload['manager_phone'] = 'call the office';

        $errors = app(AboutEntityCmsServiceInterface::class)->publishErrors($targetKey, $payload);
        $this->assertArrayHasKey('manager_email', $errors);
        $this->assertArrayHasKey('manager_phone', $errors);
    }

    public function test_task_groups_round_trip_through_draft_and_publish(): void
    {
        $this->publishDirectorate(function (array $payload): array {
            $payload['translations']['en']['task_groups_json'] = [
                ['title' => 'Edited group', 'summary' => 'Intro line', 'items' => [
                    ['title' => 'First task', 'body' => 'What it covers'],
                    ['title' => '', 'body' => 'dropped: no title'],
                ]],
                ['title' => '', 'summary' => '', 'items' => []],
            ];

            return $payload;
        });

        $directorate = Directorate::query()->where('slug', 'it-services')->with('translations')->firstOrFail();
        $this->assertSame(
            [['title' => 'Edited group', 'summary' => 'Intro line', 'items' => [['title' => 'First task', 'body' => 'What it covers']]]],
            $directorate->translations->firstWhere('locale', 'en')?->task_groups_json,
        );

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSee('Edited group')
            ->assertSee('Intro line')
            ->assertSee('First task')
            ->assertDontSee('dropped: no title');
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $edit */
    private function publishDirectorate(callable $edit): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $directorate = Directorate::query()->where('slug', 'it-services')->firstOrFail();
        $targetKey = 'entity.directorate.'.$directorate->getKey();
        $workflow = app(CmsWorkflowServiceInterface::class);

        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload;
        $this->assertIsArray($payload);

        $workflow->saveDraft($targetKey, $edit($payload), (int) $author->getKey());
        $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
    }
}
