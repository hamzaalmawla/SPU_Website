<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DynamicFormPageRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_job_application_page_renders_frontend_dynamic_form_shell(): void
    {
        $this->get('/en/campus-life/career-development/jobs/apply?job=lecturer-computer-science')
            ->assertOk()
            ->assertSee('Apply for Job')
            ->assertSee('Application Information')
            ->assertSee('Lecturer in Computer Science')
            ->assertSee('x-data="dynamicFormShell()"', false)
            ->assertSee('x-if="$store.dynamicForm.schema"', false)
            ->assertSee('data-form-id="job-application"', false)
            ->assertDontSee('Event Not Found');
    }

    public function test_conference_registration_page_uses_conference_form_schema(): void
    {
        $this->get('/en/research/conferences/register?event=conf-001')
            ->assertOk()
            ->assertSee('International Conference on AI in Healthcare 2026')
            ->assertSee('Registration Information')
            ->assertSee('x-data="dynamicFormShell()"', false)
            ->assertSee('data-form-id="conference-registration"', false)
            ->assertSee('data-event-source="research-conferences"', false)
            ->assertSee('data-event-id="conf-001"', false)
            ->assertDontSee('Event Not Found');
    }

    public function test_symposium_registration_page_uses_symposium_form_schema(): void
    {
        $this->get('/en/research/conferences/register?event=conf-002')
            ->assertOk()
            ->assertSee('Symposium on Pharmaceutical Innovation')
            ->assertSee('data-form-id="symposium-registration"', false)
            ->assertSee('data-event-source="research-conferences"', false)
            ->assertSee('data-event-id="conf-002"', false)
            ->assertDontSee('Event Not Found');
    }

    public function test_unknown_research_registration_event_renders_not_found_state(): void
    {
        $this->get('/en/research/conferences/register?event=missing-event')
            ->assertOk()
            ->assertSee('Event Not Found')
            ->assertSee('Back to Conferences')
            ->assertDontSee('data-form-id="conference-registration"', false);
    }
}
