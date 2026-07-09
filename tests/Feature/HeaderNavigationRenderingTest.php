<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HeaderNavigationRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_header_renders_frontend_navigation_additions(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Accreditation')
            ->assertSee('Why SPU')
            ->assertSee('Filling Vacancies')
            ->assertSee('Graduation &amp; National Exams', false)
            ->assertSee('Research Themes')
            ->assertSee('Machine Learning in Pharmaceutical Quality Control')
            ->assertSee('Job Board')
            ->assertSee('Damascus Research Center')
            ->assertSee('Announcements')
            ->assertSee('Media Gallery')
            ->assertSee('site-nav-dropdown-group-header', false)
            ->assertSee('site-nav-dropdown-featured', false)
            ->assertSee('/en/campus-life/career-development/jobs')
            ->assertSee('/en/research/publications/machine-learning-pharmaceutical-quality-control');
    }

    public function test_arabic_header_renders_frontend_navigation_additions(): void
    {
        $this->get('/ar')
            ->assertOk()
            ->assertSee('الاعتمادية')
            ->assertSee('لماذا SPU')
            ->assertSee('ملء الشواغر')
            ->assertSee('مجالات البحث')
            ->assertSee('لوحة الوظائف')
            ->assertSee('منشورات مركز دمشق للأبحاث')
            ->assertSee('الإعلانات')
            ->assertSee('معرض الوسائط');
    }
}
