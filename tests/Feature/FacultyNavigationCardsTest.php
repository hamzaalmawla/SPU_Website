<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The faculty main page ("Highlights") and the overview ("Pathways") both list
 * the faculty's subpage cards, and must list exactly what the admin manages.
 */
final class FacultyNavigationCardsTest extends TestCase
{
    use RefreshDatabase;

    private const FACULTIES = ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_faculty_lists_the_same_cards_on_its_main_and_overview_pages(): void
    {
        foreach (self::FACULTIES as $faculty) {
            foreach (['ar', 'en'] as $locale) {
                $highlights = $this->highlights($faculty, $locale);

                $this->assertNotSame([], $highlights, "{$faculty} ({$locale}) lists no cards");
                $this->assertSame($highlights, $this->pathways($faculty, $locale), "{$faculty} ({$locale}) lists differ");

                foreach ($highlights as $card) {
                    $this->assertStringNotContainsString('/study-plan/course', $card['href']);
                }
            }
        }
    }

    public function test_medicine_lists_every_seeded_card_in_admin_order(): void
    {
        $expected = [
            ['title' => 'Overview', 'href' => '/en/faculties/medicine/overview'],
            ['title' => 'Departments', 'href' => '/en/faculties/medicine/departments'],
            ['title' => 'Study Plan', 'href' => '/en/faculties/medicine/study-plan'],
            ['title' => 'Laboratories', 'href' => '/en/faculties/medicine/labs'],
            ['title' => 'Projects', 'href' => '/en/faculties/medicine/projects'],
            ['title' => 'Research', 'href' => '/en/faculties/medicine/research'],
            ['title' => 'Alumni', 'href' => '/en/faculties/medicine/alumni'],
            ['title' => 'Honor List', 'href' => '/en/faculties/medicine/valedictorians'],
            ['title' => 'Faculty Members', 'href' => '/en/faculties/medicine/members'],
        ];

        $this->assertSame($expected, $this->highlights('medicine', 'en'));
        $this->assertSame($expected, $this->pathways('medicine', 'en'));
        $this->assertCount(9, $this->highlights('medicine', 'ar'));
        $this->assertCount(9, $this->pathways('medicine', 'ar'));
    }

    public function test_admin_sort_order_controls_the_public_order_on_both_pages(): void
    {
        $ids = FacultySubpageCard::query()->where('faculty_slug', 'medicine')->orderBy('sort_order')->pluck('id')->all();
        $members = FacultySubpageCard::query()->where('faculty_slug', 'medicine')->where('subpage_slug', 'members')->value('id');
        $research = FacultySubpageCard::query()->where('faculty_slug', 'medicine')->where('subpage_slug', 'research')->value('id');
        $reordered = [$members, $research, ...array_values(array_diff($ids, [$members, $research]))];

        $this->highlights('medicine', 'en');
        $this->assertTrue($this->cards()->reorder($reordered, $this->editor()->id));

        foreach (['ar', 'en'] as $locale) {
            foreach ([$this->highlights('medicine', $locale), $this->pathways('medicine', $locale)] as $cards) {
                $this->assertSame("/{$locale}/faculties/medicine/members", $cards[0]['href']);
                $this->assertSame("/{$locale}/faculties/medicine/research", $cards[1]['href']);
                $this->assertSame("/{$locale}/faculties/medicine/overview", $cards[2]['href']);
            }
        }
    }

    public function test_hidden_unpublished_and_scheduled_cards_disappear_from_both_pages(): void
    {
        $editor = $this->editor();
        $card = fn (string $slug): int => (int) FacultySubpageCard::query()->where('faculty_slug', 'medicine')->where('subpage_slug', $slug)->value('id');

        $this->highlights('medicine', 'en');
        $this->assertTrue($this->cards()->toggleVisibility($card('labs'), $editor->id));
        $this->assertTrue($this->cards()->unpublish($card('alumni'), $editor->id));
        FacultySubpageCard::query()->whereKey($card('projects'))->update(['publish_at' => now()->addDay()]);
        Cache::flush();

        foreach (['ar', 'en'] as $locale) {
            $highlights = $this->highlights('medicine', $locale);

            $this->assertSame($highlights, $this->pathways('medicine', $locale));
            $this->assertCount(6, $highlights);

            foreach (['labs', 'alumni', 'projects'] as $hidden) {
                $this->assertNotContains("/{$locale}/faculties/medicine/{$hidden}", array_column($highlights, 'href'));
            }
        }
    }

    public function test_title_overrides_show_in_the_matching_locale_on_both_pages(): void
    {
        $id = (int) FacultySubpageCard::query()->where('faculty_slug', 'medicine')->where('subpage_slug', 'departments')->value('id');

        $this->highlights('medicine', 'en');
        $this->assertTrue($this->cards()->updateCard($id, [
            'title_override_ar' => 'أقسامنا الطبية',
            'title_override_en' => 'Our Medical Departments',
        ], $this->editor()->id));

        foreach ([$this->highlights('medicine', 'en'), $this->pathways('medicine', 'en')] as $cards) {
            $this->assertSame(['title' => 'Our Medical Departments', 'href' => '/en/faculties/medicine/departments'], $cards[1]);
            $this->assertNotContains('أقسامنا الطبية', array_column($cards, 'title'));
        }

        foreach ([$this->highlights('medicine', 'ar'), $this->pathways('medicine', 'ar')] as $cards) {
            $this->assertSame(['title' => 'أقسامنا الطبية', 'href' => '/ar/faculties/medicine/departments'], $cards[1]);
            $this->assertNotContains('Our Medical Departments', array_column($cards, 'title'));
        }
    }

    public function test_a_card_for_a_subpage_without_a_public_page_is_not_listed(): void
    {
        $service = $this->cards();
        $editor = $this->editor();
        $custom = $service->createCard('medicine', 'careers', $editor->id, 'وظائف', 'Careers');
        $this->assertTrue($service->publish($custom->id, $editor->id));
        $course = (int) FacultySubpageCard::query()->where('faculty_slug', 'medicine')->where('subpage_slug', 'study-plan-course')->value('id');
        $this->assertTrue($service->publish($course, $editor->id));

        $hrefs = array_column($this->highlights('medicine', 'en'), 'href');

        $this->assertCount(9, $hrefs);
        $this->assertNotContains('/en/faculties/medicine/careers', $hrefs);
        $this->assertSame($hrefs, array_column($this->pathways('medicine', 'en'), 'href'));
    }

    public function test_a_faculty_without_cards_falls_back_to_its_enabled_subpages(): void
    {
        FacultySubpageCard::query()->where('faculty_slug', 'medicine')->delete();
        FacultySubpageCard::query()->where('faculty_slug', 'pharmacy')->delete();
        Cache::flush();

        $medicine = ['overview', 'departments', 'study-plan', 'labs', 'projects', 'research', 'alumni', 'valedictorians', 'members'];

        $this->assertSame(
            array_map(fn (string $slug): string => "/en/faculties/medicine/{$slug}", $medicine),
            array_column($this->highlights('medicine', 'en'), 'href'),
        );
        $this->assertSame($this->highlights('medicine', 'en'), $this->pathways('medicine', 'en'));
        $this->assertContains('/en/faculties/pharmacy/training', array_column($this->highlights('pharmacy', 'en'), 'href'));
    }

    public function test_a_faculty_whose_cards_are_all_hidden_lists_none(): void
    {
        FacultySubpageCard::query()->where('faculty_slug', 'medicine')->update(['is_visible' => false]);
        Cache::flush();

        $this->get('/en/faculties/medicine')->assertOk()->assertDontSee('id="highlights"', false);
    }

    /** @return array<int, array{title: string, href: string}> */
    private function highlights(string $faculty, string $locale): array
    {
        return $this->sectionCards("/{$locale}/faculties/{$faculty}", 'highlights');
    }

    /** @return array<int, array{title: string, href: string}> */
    private function pathways(string $faculty, string $locale): array
    {
        return $this->sectionCards("/{$locale}/faculties/{$faculty}/overview", 'faculty-pathways');
    }

    /** @return array<int, array{title: string, href: string}> */
    private function sectionCards(string $url, string $sectionId): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $cards = [];

        foreach ((new DOMXPath($document))->query("//section[@id='{$sectionId}']//a[h3]") ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $cards[] = [
                'title' => trim((string) $link->getElementsByTagName('h3')->item(0)?->textContent),
                'href' => $link->getAttribute('href'),
            ];
        }

        return $cards;
    }

    private function cards(): FacultySubpageCardServiceInterface
    {
        return app(FacultySubpageCardServiceInterface::class);
    }

    private function editor(): User
    {
        return User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
