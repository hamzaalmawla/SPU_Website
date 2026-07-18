<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use Tests\TestCase;

class CmsTargetRegistryTest extends TestCase
{
    public function test_registry_resolves_from_container(): void
    {
        $this->assertInstanceOf(CmsTargetRegistryInterface::class, app(CmsTargetRegistryInterface::class));
    }

    public function test_all_targets_have_unique_keys_and_bilingual_locales(): void
    {
        $targets = app(CmsTargetRegistryInterface::class)->all();
        $keys = $targets->map(fn ($target): string => $target->key)->all();

        $this->assertCount(count(array_unique($keys)), $keys);
        $this->assertGreaterThanOrEqual(90, $targets->count());

        foreach ($targets as $target) {
            $this->assertSame(['ar', 'en'], $target->locales);
            $this->assertTrue($target->supportsDraftWorkflow);
        }
    }

    public function test_virtual_tour_is_registered_under_campus_life(): void
    {
        $registry = app(CmsTargetRegistryInterface::class);
        $target = $registry->find('campus_life.virtual_tour');

        $this->assertNotNull($target);
        $this->assertSame('campus_life', $target->area);
        $this->assertSame('campus_life.landing', $target->parentKey);
        $this->assertSame('/virtual-tour', $target->publicPath);
        $this->assertSame('public.virtual-tour', $target->routeName);
        $this->assertNull($registry->find('virtual_tour'));
    }

    public function test_admissions_and_campus_life_subpages_are_registered(): void
    {
        $registry = app(CmsTargetRegistryInterface::class);

        $this->assertSame([
            'admissions.landing',
            'admissions.requirements',
            'admissions.tuition',
            'admissions.how-to-apply',
            'admissions.faq',
            'admissions.calendar',
            'admissions.documents',
            'admissions.transfer',
            'admissions.filling-vacancies',
            'admissions.graduation-exams',
        ], $registry->forArea('admissions')->pluck('key')->all());

        $this->assertContains('campus_life.virtual_tour', $registry->forArea('campus_life')->pluck('key')->all());
        $this->assertContains('campus_life.health-insurance', $registry->forArea('campus_life')->pluck('key')->all());
    }

    public function test_e_services_landing_and_detail_targets_are_registered_independently(): void
    {
        $registry = app(CmsTargetRegistryInterface::class);

        $this->assertSame([
            'e_services',
            'e_services.library',
            'e_services.staff-email',
            'e_services.it-support',
        ], $registry->forArea('e_services')->pluck('key')->all());
        $this->assertSame('e_services', $registry->find('e_services.library')?->parentKey);
        $this->assertSame('/e-services/staff-email', $registry->find('e_services.staff-email')?->publicPath);
    }

    public function test_target_labels_are_translated_in_arabic_and_english(): void
    {
        $targets = app(CmsTargetRegistryInterface::class)->all();

        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ($targets as $target) {
                $this->assertNotSame($target->labelKey, __($target->labelKey));
            }
        }
    }
}
