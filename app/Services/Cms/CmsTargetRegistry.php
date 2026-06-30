<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\DTOs\Cms\CmsTargetDTO;
use Illuminate\Support\Collection;

final class CmsTargetRegistry implements CmsTargetRegistryInterface
{
    /** @return Collection<int, CmsTargetDTO> */
    public function all(): Collection
    {
        return collect([
            ...$this->homepageTargets(),
            ...$this->aboutTargets(),
            ...$this->facilitiesTargets(),
            ...$this->admissionsTargets(),
            ...$this->campusLifeTargets(),
            ...$this->servicePageTargets(),
            ...$this->newsTargets(),
            ...$this->researchTargets(),
        ]);
    }

    /** @return Collection<int, CmsTargetDTO> */
    public function forArea(string $area): Collection
    {
        return $this->all()
            ->filter(fn (CmsTargetDTO $target): bool => $target->area === $area)
            ->values();
    }

    public function find(string $key): ?CmsTargetDTO
    {
        return $this->all()->first(fn (CmsTargetDTO $target): bool => $target->key === $key);
    }

    /** @return array<int, CmsTargetDTO> */
    private function homepageTargets(): array
    {
        return [
            $this->target('homepage', 'homepage', 'admin.cms.targets.homepage', '/', 'public.home'),
        ];
    }

    /** @return array<int, CmsTargetDTO> */
    private function aboutTargets(): array
    {
        return [
            $this->target('about.landing', 'about', 'admin.cms.targets.about.landing', '/about', 'public.about.landing'),
            $this->target('about.history', 'about', 'admin.cms.targets.about.history', '/about/history', 'public.about.history', 'about.landing'),
            $this->target('about.leadership', 'about', 'admin.cms.targets.about.leadership', '/about/leadership', 'public.about.leadership', 'about.landing'),
            $this->target('about.directorates', 'about', 'admin.cms.targets.about.directorates', '/about/directorates', 'public.about.directorates', 'about.landing'),
            $this->target('about.directorates_staff', 'about', 'admin.cms.targets.about.directorates_staff', '/about/directorates/staff', 'public.about.directorates.staff', 'about.directorates'),
            $this->target('about.partnerships', 'about', 'admin.cms.targets.about.partnerships', '/about/partnerships', 'public.about.partnerships', 'about.landing'),
        ];
    }

    /** @return array<int, CmsTargetDTO> */
    private function facilitiesTargets(): array
    {
        $targets = [
            $this->target('facilities.landing', 'facilities', 'admin.cms.targets.facilities.landing', '/facilities', 'public.facilities.hub'),
        ];

        foreach ($this->facultySlugs() as $facultySlug) {
            $facultyKey = 'facilities.'.$facultySlug;
            $targets[] = $this->target($facultyKey, 'facilities', 'admin.cms.targets.facilities.faculty_homepage', '/facilities/'.$facultySlug, 'public.facilities.show', 'facilities.landing');
            $targets[] = $this->target($facultyKey.'.study_plan', 'facilities', 'admin.cms.targets.facilities.study_plan', '/facilities/'.$facultySlug.'/study-plan', 'public.facilities.study-plan', $facultyKey);

            foreach ($this->facultySubpageSlugs() as $subpageSlug) {
                $targets[] = $this->target(
                    $facultyKey.'.'.$subpageSlug,
                    'facilities',
                    'admin.cms.targets.facilities.'.$subpageSlug,
                    '/facilities/'.$facultySlug.'/'.$subpageSlug,
                    'public.facilities.subpage',
                    $facultyKey,
                );
            }
        }

        return $targets;
    }

    /** @return array<int, CmsTargetDTO> */
    private function admissionsTargets(): array
    {
        $targets = [
            $this->target('admissions.landing', 'admissions', 'admin.cms.targets.admissions.landing', '/admissions', 'public.admissions.landing'),
        ];

        foreach ($this->admissionsSectionSlugs() as $slug) {
            $targets[] = $this->target('admissions.'.$slug, 'admissions', 'admin.cms.targets.admissions.'.$slug, '/admissions/'.$slug, 'public.admissions.section', 'admissions.landing');
        }

        return $targets;
    }

    /** @return array<int, CmsTargetDTO> */
    private function campusLifeTargets(): array
    {
        $targets = [
            $this->target('campus_life.landing', 'campus_life', 'admin.cms.targets.campus_life.landing', '/campus-life', 'public.campus-life.landing'),
            $this->target('campus_life.virtual_tour', 'campus_life', 'admin.cms.targets.campus_life.virtual_tour', '/virtual-tour', 'public.virtual-tour', 'campus_life.landing'),
        ];

        foreach ($this->campusLifeSectionSlugs() as $slug) {
            $targets[] = $this->target('campus_life.'.$slug, 'campus_life', 'admin.cms.targets.campus_life.'.$slug, '/campus-life/'.$slug, 'public.campus-life.section', 'campus_life.landing');
        }

        return $targets;
    }

    /** @return array<int, CmsTargetDTO> */
    private function servicePageTargets(): array
    {
        return [
            $this->target('e_services', 'e_services', 'admin.cms.targets.e_services', '/e-services', 'public.e-services'),
            $this->target('contact', 'contact', 'admin.cms.targets.contact', '/contact', 'public.contact'),
        ];
    }

    /** @return array<int, CmsTargetDTO> */
    private function newsTargets(): array
    {
        return [
            $this->target('news.index', 'news', 'admin.cms.targets.news.index', '/news', 'public.news.index'),
            $this->target('news.articles', 'news', 'admin.cms.targets.news.articles', '/news/articles', 'public.news.articles', 'news.index'),
            $this->target('news.article', 'news', 'admin.cms.targets.news.article', null, 'public.news.show', 'news.index'),
        ];
    }

    /** @return array<int, CmsTargetDTO> */
    private function researchTargets(): array
    {
        return [
            $this->target('research.index', 'research', 'admin.cms.targets.research.index', null, null),
            $this->target('research.publication', 'research', 'admin.cms.targets.research.publication', null, null, 'research.index'),
        ];
    }

    private function target(
        string $key,
        string $area,
        string $labelKey,
        ?string $publicPath,
        ?string $routeName,
        ?string $parentKey = null,
        bool $supportsDraftWorkflow = true,
    ): CmsTargetDTO {
        return new CmsTargetDTO(
            key: $key,
            area: $area,
            labelKey: $labelKey,
            publicPath: $publicPath,
            routeName: $routeName,
            parentKey: $parentKey,
            supportsDraftWorkflow: $supportsDraftWorkflow,
        );
    }

    /** @return array<int, string> */
    private function facultySlugs(): array
    {
        return ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];
    }

    /** @return array<int, string> */
    private function facultySubpageSlugs(): array
    {
        return ['overview', 'departments', 'labs', 'projects', 'alumni', 'valedictorians', 'training'];
    }

    /** @return array<int, string> */
    private function admissionsSectionSlugs(): array
    {
        return ['requirements', 'tuition', 'how-to-apply', 'faq', 'calendar', 'documents', 'transfer'];
    }

    /** @return array<int, string> */
    private function campusLifeSectionSlugs(): array
    {
        return ['services', 'transport', 'clubs-activities', 'career-development', 'dental', 'hospital', 'health-insurance'];
    }
}
