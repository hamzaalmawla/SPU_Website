<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdmissionsCampusRouteCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admissions_landing_links_imported_routes_in_both_locales(): void
    {
        $this->publishAdmissionsDefaults(['landing']);

        foreach ([
            'en' => ['Filling Vacancies', 'Graduation &amp; National Exams'],
            'ar' => ['ملء الشواغر', 'التخرج والامتحانات الوطنية'],
        ] as $locale => $labels) {
            $this->get('/'.$locale.'/admissions')
                ->assertOk()
                ->assertSee('/'.$locale.'/admissions/filling-vacancies')
                ->assertSee('/'.$locale.'/admissions/graduation-exams')
                ->assertSee($labels[0])
                ->assertSee($labels[1], false);
        }
    }

    public function test_career_development_job_board_cta_uses_dedicated_route_in_both_locales(): void
    {
        $this->publishCampusLifeDefault('career-development');

        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/campus-life/career-development')
                ->assertOk()
                ->assertSee('href="/'.$locale.'/campus-life/career-development/jobs"', false)
                ->assertDontSee('href="/'.$locale.'/campus-life/career-development#job-board"', false);
        }
    }

    public function test_legacy_documents_routes_redirect_to_localized_tab_state(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/admissions/study-system/')
                ->assertMovedPermanently()
                ->assertRedirect('/'.$locale.'/admissions/documents?tab=study-system');

            $this->get('/'.$locale.'/admissions/academic-warnings/')
                ->assertMovedPermanently()
                ->assertRedirect('/'.$locale.'/admissions/documents?tab=academic-warnings');
        }
    }

    public function test_documents_query_state_selects_accessible_tabs_and_preserves_locale_switch(): void
    {
        $this->publishAdmissionsDefaults(['documents']);

        foreach ([
            'study-system' => 'studySystem',
            'academic-warnings' => 'warnings',
        ] as $queryTab => $internalTab) {
            foreach (['en' => 'ar', 'ar' => 'en'] as $locale => $otherLocale) {
                $this->get('/'.$locale.'/admissions/documents?tab='.$queryTab)
                    ->assertOk()
                    ->assertSee('data-active-tab="'.$internalTab.'"', false)
                    ->assertSee('id="documents-tab-'.$internalTab.'" role="tab" aria-controls="documents-panel-'.$internalTab.'"', false)
                    ->assertSee('aria-selected="true"', false)
                    ->assertSee('role="tablist"', false)
                    ->assertSee('role="tabpanel"', false)
                    ->assertSee('x-on:keydown="moveTab($event)"', false)
                    ->assertSee('/'.$otherLocale.'/admissions/documents?tab='.$queryTab)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').'/'.$locale.'/admissions/documents">', false);
            }
        }
    }

    public function test_all_remaining_admissions_routes_are_localized_rtl_safe_and_free_of_inert_or_fabricated_actions(): void
    {
        $slugs = ['requirements', 'tuition', 'faq', 'how-to-apply', 'transfer', 'calendar', 'documents', 'filling-vacancies'];
        $this->publishAdmissionsDefaults($slugs);
        $forbidden = [
            'href="#"',
            '$15,000',
            'SY12345678901234567890',
            'Main National Bank',
            'Sept 15, 2026',
            'Academic Calendar 2026/2027',
        ];

        foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            foreach ($slugs as $slug) {
                $response = $this->get('/'.$locale.'/admissions/'.$slug)
                    ->assertOk()
                    ->assertSee('data-page-name="admissions-'.$slug.'"', false)
                    ->assertSee('dir="'.$direction.'"', false);

                foreach ($forbidden as $value) {
                    $response->assertDontSee($value, false);
                }
            }
        }
    }

    public function test_landing_uses_approved_media_conversion_assets_localized_alt_text_and_responsive_layout(): void
    {
        $this->publishAdmissionsDefaults(['landing']);

        $this->assertSame(
            '1aaa08459a380239cfc0da5c96184433951913b4dcc2460942e43d6602b96431',
            hash_file('sha256', public_path('images/admissions-hero-campus.webp')),
        );
        $this->assertSame(
            'fb56007128782e39fe1a6d6928f740843f532adefcfa44924b33c0ff43975e86',
            hash_file('sha256', public_path('images/admission/front-img.jpg')),
        );

        $this->get('/en/admissions')
            ->assertOk()
            ->assertSee('/images/admissions-hero-campus.webp')
            ->assertSee('/images/admission/front-img.jpg')
            ->assertSee('alt="Syrian Private University campus"', false)
            ->assertSee('alt="Syrian Private University students"', false)
            ->assertSee('lg:grid-cols-[1fr_1.2fr]', false)
            ->assertDontSee('Applications Open');

        $this->get('/ar/admissions')
            ->assertOk()
            ->assertSee('alt="حرم الجامعة السورية الخاصة"', false)
            ->assertSee('alt="طلاب الجامعة السورية الخاصة"', false)
            ->assertDontSee('التقديم مفتوح');
    }

    public function test_requirements_transfer_and_faq_render_accessible_server_fallbacks(): void
    {
        $this->publishAdmissionsDefaults(['requirements', 'transfer', 'faq']);

        foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            $this->get('/'.$locale.'/admissions/requirements')
                ->assertOk()
                ->assertSee('role="tablist"', false)
                ->assertSee('id="requirements-tab-new" role="tab"', false)
                ->assertSee('role="tabpanel"', false)
                ->assertSee('x-on:keydown="moveTab($event)"', false)
                ->assertSee('dir="'.$direction.'"', false);

            $this->get('/'.$locale.'/admissions/transfer')
                ->assertOk()
                ->assertSee('id="transfer-tab-transfer" role="tab"', false)
                ->assertSee('aria-controls="transfer-panel-transfer"', false)
                ->assertSee('x-on:keydown="moveTab($event)"', false)
                ->assertSee('dir="'.$direction.'"', false);

            $this->get('/'.$locale.'/admissions/faq')
                ->assertOk()
                ->assertSee('type="search"', false)
                ->assertSee('aria-controls="faq-answer-application-process-0"', false)
                ->assertSee('role="region"', false)
                ->assertSee('aria-expanded="true"', false)
                ->assertSee('dir="'.$direction.'"', false);
        }
    }

    public function test_unavailable_tuition_calendar_documents_and_vacancies_show_transparent_guidance(): void
    {
        $this->publishAdmissionsDefaults(['tuition', 'calendar', 'documents', 'filling-vacancies']);

        $this->get('/en/admissions/tuition')
            ->assertOk()
            ->assertSee('Verified tuition amounts are not currently published')
            ->assertSee('No bank account or online payment link is published')
            ->assertDontSee('data-tuition-row', false);

        $this->get('/en/admissions/calendar')
            ->assertOk()
            ->assertSee('No approved academic dates are currently published')
            ->assertDontSee('admissions-download-button', false);

        $this->get('/en/admissions/documents')
            ->assertOk()
            ->assertSee('No verified admissions file is currently available')
            ->assertDontSee('href="#"', false);

        $this->get('/en/admissions/filling-vacancies')
            ->assertOk()
            ->assertSee('No verified vacant-seat announcement is currently published')
            ->assertDontSee('Application Period')
            ->assertDontSee('Available Faculties');
    }

    public function test_how_to_apply_exposes_real_localized_application_form_without_self_loop(): void
    {
        $this->publishAdmissionsDefaults(['how-to-apply']);

        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/admissions/how-to-apply')
                ->assertOk()
                ->assertSee('id="application"', false)
                ->assertSee('data-form-id="admissions-application"', false)
                ->assertSee('data-locale="'.$locale.'"', false)
                ->assertSee('/'.$locale.'/admissions/how-to-apply#application')
                ->assertDontSee('admissions-step-card__button" href="/'.$locale.'/admissions/how-to-apply"', false);
        }
    }

    /** @param list<string> $slugs */
    private function publishAdmissionsDefaults(array $slugs): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $admissions = app(AdmissionsPageServiceInterface::class);

        foreach ($slugs as $slug) {
            $targetKey = 'admissions.'.$slug;
            $workflow->saveDraft($targetKey, $admissions->getEditablePayload($targetKey), (int) $author->getKey());
            $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
        }
    }

    private function publishCampusLifeDefault(string $slug): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $targetKey = 'campus_life.'.$slug;

        $workflow->saveDraft(
            $targetKey,
            app(CampusLifePageServiceInterface::class)->getEditablePayload($targetKey),
            (int) $author->getKey(),
        );
        $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
    }
}
