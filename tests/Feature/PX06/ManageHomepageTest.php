<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageHomepage;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\Models\User;
use App\Support\HomepagePayloadMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Feature tests for ManageHomepage Filament page.
 *
 * Requirements: 19.1–19.5
 */
class ManageHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_manage_homepage(): void
    {
        $user = $this->createUser('super_admin');

        $this->actingAs($user);

        $this->assertTrue(ManageHomepage::canAccess());
    }

    public function test_editor_can_access_manage_homepage(): void
    {
        $user = $this->createUser('editor');

        $this->actingAs($user);

        $this->assertTrue(ManageHomepage::canAccess());
    }

    public function test_faculty_editor_cannot_access_manage_homepage(): void
    {
        $user = $this->createUser('faculty_editor');

        $this->actingAs($user);

        $this->assertFalse(ManageHomepage::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_manage_homepage(): void
    {
        $this->assertFalse(ManageHomepage::canAccess());
    }

    public function test_homepage_form_payload_preserves_public_homepage_shape(): void
    {
        $payload = $this->invokeFormArrayToPayload([
            'headline' => 'Preview Hero',
            'content' => [
                'images' => ['/images/slider-1.webp'],
                'legalLinks' => [['label' => 'Apply', 'url' => '/en/admissions']],
            ],
            'featured_items' => [
                [
                    'title' => 'Faculty of Medicine',
                    'summary' => 'Medicine summary',
                    'imageUrl' => '/images/faculty-medicine-logo.png',
                    'accent' => '#bc2428',
                    'metric' => '6 Years',
                    'action' => ['label' => 'LEARN MORE', 'url' => '/en/faculties'],
                ],
            ],
            'stats' => [
                [
                    'value' => '20',
                    'label' => 'Years Since Founding',
                    'suffix' => '+',
                    'helperText' => 'Institutional journey.',
                    'sortOrder' => 1,
                ],
            ],
            'articles' => [
                [
                    'id' => 7,
                    'locale' => 'en',
                    'title' => 'Campus Story',
                    'slug' => 'campus-story',
                    'excerpt' => 'Story excerpt',
                    'imageUrl' => '/images/story.webp',
                    'publishedAt' => '2026-03-15',
                    'categoryLabel' => 'Campus',
                    'url' => '/en/news',
                ],
            ],
            'copyright_text' => 'Copyright SPU',
        ]);

        $this->assertSame('Preview Hero', $payload->title);
        $this->assertSame(['/images/slider-1.webp'], $payload->content['images']);
        $this->assertSame('Copyright SPU', $payload->content['copyrightText']);
        $this->assertSame('/en/admissions', $payload->content['legalLinks'][0]['url']);
        $this->assertSame('/images/faculty-medicine-logo.png', $payload->items[0]['imageUrl']);
        $this->assertSame('#bc2428', $payload->items[0]['accent']);
        $this->assertSame('/en/faculties', $payload->items[0]['action']['url']);
        $this->assertSame('Institutional journey.', $payload->stats[0]->helperText);
        $this->assertSame(1, $payload->stats[0]->sortOrder);
        $this->assertSame('/images/story.webp', $payload->articles[0]->imageUrl);
        $this->assertSame('2026-03-15', $payload->articles[0]->publishedAt);
        $this->assertSame('/en/news', $payload->articles[0]->url);
    }

    public function test_homepage_form_round_trip_preserves_public_item_shape(): void
    {
        $publicPayload = HomepagePayloadMapper::sectionDataFromArray([
            'title' => 'Our Faculties',
            'items' => [
                [
                    'title' => 'Faculty of Medicine',
                    'summary' => 'Medicine summary',
                    'imageUrl' => '/images/faculty-medicine-logo.png',
                    'accent' => '#bc2428',
                    'metric' => '6 Years',
                    'action' => ['label' => 'LEARN MORE', 'url' => '/en/faculties'],
                ],
            ],
        ]);

        $form = $this->invokePayloadToFormArray($publicPayload, 'academic_faculties');

        $this->assertSame('/images/faculty-medicine-logo.png', $form['featured_items'][0]['image']);
        $this->assertSame('#bc2428', $form['featured_items'][0]['accent']);
        $this->assertSame('6 Years', $form['featured_items'][0]['metric']);
        $this->assertSame('LEARN MORE', $form['featured_items'][0]['cta_label']);

        $roundTrip = $this->invokeFormArrayToPayload($form, 'academic_faculties');

        $this->assertSame('/images/faculty-medicine-logo.png', $roundTrip->items[0]['imageUrl']);
        $this->assertSame('#bc2428', $roundTrip->items[0]['accent']);
        $this->assertSame('6 Years', $roundTrip->items[0]['metric']);
        $this->assertSame('LEARN MORE', $roundTrip->items[0]['action']['label']);
        $this->assertSame('/en/faculties', $roundTrip->items[0]['action']['url']);
    }

    public function test_homepage_form_round_trip_preserves_and_merges_hero_carousel_images(): void
    {
        $publicPayload = HomepagePayloadMapper::sectionDataFromArray([
            'title' => 'Syrian Private University',
            'subtitle' => 'Hero subtitle',
            'primaryAction' => ['label' => 'Explore', 'url' => '/en/faculties'],
            'secondaryAction' => ['label' => 'Apply', 'url' => '/en/admissions'],
            'content' => [
                'images' => [
                    '/images/slider-1.webp',
                    '/images/slider-2.webp',
                ],
            ],
        ]);

        $form = $this->invokePayloadToFormArray($publicPayload, 'hero');

        $this->assertNull($form['background_image']);
        $this->assertSame('/images/slider-1.webp', $form['content']['images'][0]['path']);
        $this->assertSame('/images/slider-2.webp', $form['content']['images'][1]['path']);

        $form['hero_carousel_uploads'] = ['homepage/hero/new-hero.webp'];

        $roundTrip = $this->invokeFormArrayToPayload($form, 'hero');

        $this->assertSame([
            '/images/slider-1.webp',
            '/images/slider-2.webp',
            'homepage/hero/new-hero.webp',
        ], $roundTrip->content['images']);
    }

    public function test_homepage_form_round_trip_preserves_required_section_actions(): void
    {
        $newsPayload = HomepagePayloadMapper::sectionDataFromArray([
            'title' => 'SPU News',
            'sectionAction' => ['label' => 'View All', 'url' => '/en/news'],
            'articles' => [
                [
                    'id' => 1,
                    'locale' => 'en',
                    'title' => 'News Story',
                    'slug' => 'news-story',
                    'imageUrl' => '/images/news.webp',
                    'publishedAt' => '2026-03-15',
                    'categoryLabel' => 'Campus',
                    'url' => '/en/news',
                ],
            ],
        ]);

        $newsForm = $this->invokePayloadToFormArray($newsPayload, 'university_news');
        $newsRoundTrip = $this->invokeFormArrayToPayload($newsForm, 'university_news');

        $this->assertSame('View All', $newsForm['section_cta_label']);
        $this->assertSame('/en/news', $newsForm['section_cta_url']);
        $this->assertSame('View All', $newsRoundTrip->sectionAction?->label);
        $this->assertSame('/en/news', $newsRoundTrip->sectionAction?->url);

        $researchPayload = HomepagePayloadMapper::sectionDataFromArray([
            'title' => 'SPU Research',
            'sectionAction' => ['label' => 'View All', 'url' => '/en/research'],
            'researchItems' => [
                [
                    'id' => 1,
                    'locale' => 'en',
                    'title' => 'Research Story',
                    'slug' => 'research-story',
                    'categoryLabel' => 'Medicine',
                    'url' => '/en/research',
                ],
            ],
        ]);

        $researchForm = $this->invokePayloadToFormArray($researchPayload, 'research_studies');
        $researchRoundTrip = $this->invokeFormArrayToPayload($researchForm, 'research_studies');

        $this->assertSame('View All', $researchForm['section_cta_label']);
        $this->assertSame('/en/research', $researchForm['section_cta_url']);
        $this->assertSame('View All', $researchRoundTrip->sectionAction?->label);
        $this->assertSame('/en/research', $researchRoundTrip->sectionAction?->url);
    }

    public function test_homepage_form_round_trip_preserves_medical_facility_stats(): void
    {
        $medicalPayload = HomepagePayloadMapper::sectionDataFromArray([
            'title' => 'SPU Healthcare Facilities',
            'items' => [
                [
                    'title' => 'SPU Hospital',
                    'summary' => 'Advanced medical diagnostics.',
                    'imageUrl' => '/images/healthcare-hospital.webp',
                ],
            ],
            'stats' => [
                ['value' => '200', 'label' => 'HOSPITAL BEDS', 'suffix' => '+', 'sortOrder' => 1],
                ['value' => '80', 'label' => 'SPECIALIST DOCTORS', 'suffix' => '+', 'sortOrder' => 2],
                ['value' => '30', 'label' => 'DENTAL CHAIRS', 'suffix' => '+', 'sortOrder' => 3],
                ['value' => '12', 'label' => 'PATIENTS ANNUALLY', 'suffix' => '+', 'sortOrder' => 4],
            ],
        ]);

        $form = $this->invokePayloadToFormArray($medicalPayload, 'medical_facilities_services');
        $roundTrip = $this->invokeFormArrayToPayload($form, 'medical_facilities_services');

        $this->assertCount(4, $form['stats']);
        $this->assertSame('200', $form['stats'][0]['value']);
        $this->assertSame('HOSPITAL BEDS', $form['stats'][0]['label']);
        $this->assertCount(4, $roundTrip->stats);
        $this->assertSame('200', $roundTrip->stats[0]->value);
        $this->assertSame('HOSPITAL BEDS', $roundTrip->stats[0]->label);
        $this->assertSame('+', $roundTrip->stats[0]->suffix);
    }

    public function test_homepage_form_round_trip_preserves_paths_and_footer_content(): void
    {
        $pathPayload = HomepagePayloadMapper::sectionDataFromArray([
            'title' => 'Choose Your Path',
            'items' => [
                [
                    'title' => 'Prospective Students',
                    'icon' => '/images/icons/book.svg',
                    'links' => [
                        ['label' => 'Admission', 'url' => '/en/admissions'],
                        'Scholarships',
                    ],
                    'action' => ['label' => 'Explore Admissions', 'url' => '/en/admissions'],
                ],
            ],
        ]);

        $pathForm = $this->invokePayloadToFormArray($pathPayload, 'choose_your_path');
        $pathRoundTrip = $this->invokeFormArrayToPayload($pathForm, 'choose_your_path');

        $this->assertSame('/images/icons/book.svg', $pathForm['path_items'][0]['icon']);
        $this->assertSame('/en/admissions', $pathForm['path_items'][0]['links'][0]['url']);
        $this->assertSame('/en/admissions', $pathRoundTrip->items[0]['links'][0]['url']);
        $this->assertSame('Explore Admissions', $pathRoundTrip->items[0]['action']['label']);

        $footerPayload = HomepagePayloadMapper::sectionDataFromArray([
            'footerColumns' => [
                ['title' => 'Explore SPU', 'links' => [['label' => 'About', 'url' => '/en/about']]],
            ],
            'contactLinks' => [
                ['type' => 'address', 'label' => 'Address', 'value' => 'Damascus'],
                ['type' => 'phone', 'label' => 'Phone', 'value' => '+963 11 9860'],
            ],
            'socialLinks' => [
                ['platform' => 'Facebook', 'url' => 'https://www.facebook.com/SPUpage.sy/'],
            ],
            'content' => [
                'brandBlock' => ['title' => 'SYRIAN PRIVATE UNIVERSITY'],
                'legalLinks' => [['label' => 'Apply Now', 'url' => '/en/admissions']],
                'copyrightText' => 'Copyright SPU',
            ],
        ]);

        $footerForm = $this->invokePayloadToFormArray($footerPayload, 'footer');
        $footerRoundTrip = $this->invokeFormArrayToPayload($footerForm, 'footer');

        $this->assertSame('SYRIAN PRIVATE UNIVERSITY', $footerForm['brand_title']);
        $this->assertSame('Damascus', $footerForm['content']['contact_address']);
        $this->assertSame('/en/admissions', $footerForm['content']['legal_links'][0]['url']);
        $this->assertSame('Address', $footerRoundTrip->contactLinks[0]->label);
        $this->assertSame('Damascus', $footerRoundTrip->contactLinks[0]->value);
        $this->assertSame('/en/admissions', $footerRoundTrip->content['legalLinks'][0]['url']);
    }

    private function invokeFormArrayToPayload(array $data, string $sectionKey = ''): HomepageSectionDataDTO
    {
        $method = new ReflectionMethod(ManageHomepage::class, 'formArrayToPayload');
        $method->setAccessible(true);

        return $method->invoke(new ManageHomepage(), $data, $sectionKey);
    }

    private function invokePayloadToFormArray(HomepageSectionDataDTO $payload, string $sectionKey): array
    {
        $method = new ReflectionMethod(ManageHomepage::class, 'payloadToFormArray');
        $method->setAccessible(true);

        return $method->invoke(
            new ManageHomepage(),
            $payload,
            new HomepageSectionTranslationDTO(locale: 'en'),
            $sectionKey,
        );
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
