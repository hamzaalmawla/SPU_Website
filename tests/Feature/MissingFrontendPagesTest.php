<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MissingFrontendPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_accreditation_page_renders(): void
    {
        $this->get('/en/about/accreditation')
            ->assertOk()
            ->assertSee('Accreditation &amp; Quality Assurance', false)
            ->assertSee('/images/about/hero-img.jpg');
    }

    public function test_job_board_and_detail_pages_render(): void
    {
        $this->get('/en/campus-life/career-development/jobs')
            ->assertOk()
            ->assertSee('Job Board')
            ->assertSee('Lecturer in Computer Science')
            ->assertSee('/en/campus-life/career-development/jobs/lecturer-computer-science')
            ->assertSee('/en/campus-life/career-development/jobs/apply?job=lecturer-computer-science');

        $this->get('/en/campus-life/career-development/jobs/lecturer-computer-science')
            ->assertOk()
            ->assertSee('Lecturer in Computer Science')
            ->assertSee('Job Overview')
            ->assertSee('/en/campus-life/career-development/jobs/apply?job=lecturer-computer-science');
    }

    public function test_suggestions_complaints_page_renders_and_stores_submission(): void
    {
        $this->get('/en/e-services/suggestions-complaints')
            ->assertOk()
            ->assertSee('Suggestions &amp; Complaints', false)
            ->assertSee('Submit Your Request')
            ->assertSee('/en/e-services/suggestions-complaints');

        $this->post('/en/e-services/suggestions-complaints', [
            'fullName' => 'Jane Student',
            'email' => 'jane@example.com',
            'phone' => '+963 11 000 0000',
            'requestType' => 'complaint',
            'subject' => 'Library access',
            'message' => 'Please review the library access process.',
            'consent' => '1',
        ])->assertRedirect('/en/e-services/suggestions-complaints');

        $this->assertDatabaseHas('dynamic_form_submissions', [
            'form_id' => 'suggestions-complaints',
            'locale' => 'en',
            'applicant_name' => 'Jane Student',
            'applicant_email' => 'jane@example.com',
            'status' => 'new',
        ]);
    }

    public function test_dynamic_form_submissions_table_exists_after_migrations(): void
    {
        $this->assertDatabaseCount('dynamic_form_submissions', 0);
    }

    public function test_generic_pages_do_not_expose_internal_slug_or_template_metadata(): void
    {
        $this->get('/en/events')
            ->assertOk()
            ->assertDontSee('Page Shell')
            ->assertDontSee('<dt>Slug</dt>', false)
            ->assertDontSee('<dt>Template</dt>', false);
    }
}
