<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\DTOs\Cms\CmsTargetDTO;
use Illuminate\Support\Collection;

final class CmsTargetRegistry implements CmsTargetRegistryInterface
{
    public function __construct(
        private readonly AboutEntityCmsServiceInterface $aboutEntityCmsService,
    ) {}

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
        return $this->all()->first(fn (CmsTargetDTO $target): bool => $target->key === $key)
            ?? $this->aboutEntityCmsService->resolveTarget($key);
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
            $this->target('about.vision-mission', 'about', 'admin.cms.targets.about.vision-mission', '/about/vision-mission', 'public.about.vision-mission', 'about.landing'),
            $this->target('about.history', 'about', 'admin.cms.targets.about.history', '/about/history', 'public.about.history', 'about.landing'),
            $this->target('about.leadership', 'about', 'admin.cms.targets.about.leadership', '/about/leadership', 'public.about.leadership', 'about.landing'),
            $this->target('about.directorates', 'about', 'admin.cms.targets.about.directorates', '/about/directorates', 'public.about.directorates', 'about.landing'),
            $this->target('about.directorates_staff', 'about', 'admin.cms.targets.about.directorates_staff', '/about/directorates/staff', 'public.about.directorates.staff', 'about.directorates'),
            $this->target('about.partnerships', 'about', 'admin.cms.targets.about.partnerships', '/about/partnerships', 'public.about.partnerships', 'about.landing'),
            $this->target('about.quality-policy', 'about', 'admin.cms.targets.about.quality-policy', '/about/quality-policy', 'public.about.content', 'about.landing'),
            $this->target('about.ethical-charter', 'about', 'admin.cms.targets.about.ethical-charter', '/about/ethical-charter', 'public.about.content', 'about.landing'),
            $this->target('about.organizational-structure', 'about', 'admin.cms.targets.about.organizational-structure', '/about/organizational-structure', 'public.about.content', 'about.landing'),
            $this->target('about.accreditation', 'about', 'admin.cms.targets.about.accreditation', '/about/accreditation', 'public.about.content', 'about.landing'),
            $this->target('about.why-spu', 'about', 'admin.cms.targets.about.why-spu', '/about/why-spu', 'public.about.content', 'about.landing'),
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
            $targets[] = $this->target($facultyKey, 'facilities', 'admin.cms.targets.facilities.faculty_homepage', '/facilities/'.$facultySlug, 'public.facilities.show', 'facilities.landing', facultyScopeSlug: $facultySlug);
            $targets[] = $this->target($facultyKey.'.study_plan', 'facilities', 'admin.cms.targets.facilities.study_plan', '/facilities/'.$facultySlug.'/study-plan', 'public.facilities.study-plan', $facultyKey, facultyScopeSlug: $facultySlug);

            foreach ($this->facultySubpageSlugs($facultySlug) as $subpageSlug) {
                $targets[] = $this->target(
                    $facultyKey.'.'.$subpageSlug,
                    'facilities',
                    'admin.cms.targets.facilities.'.$subpageSlug,
                    '/facilities/'.$facultySlug.'/'.$subpageSlug,
                    'public.facilities.subpage',
                    $facultyKey,
                    facultyScopeSlug: $facultySlug,
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

        $targets[] = $this->target(
            'campus_life.jobs',
            'campus_life',
            'admin.cms.targets.campus_life.jobs',
            '/campus-life/career-development/jobs',
            'public.campus-life.career-development.jobs',
            'campus_life.career-development',
        );

        return $targets;
    }

    /** @return array<int, CmsTargetDTO> */
    private function servicePageTargets(): array
    {
        return [
            $this->target('e_services', 'e_services', 'admin.cms.targets.e_services', '/e-services', 'public.e-services'),
            $this->target('e_services.library', 'e_services', 'admin.cms.targets.e_services_pages.library', '/e-services/library', 'public.e-services.detail', 'e_services'),
            $this->target('e_services.staff-email', 'e_services', 'admin.cms.targets.e_services_pages.staff_email', '/e-services/staff-email', 'public.e-services.detail', 'e_services'),
            $this->target('e_services.it-support', 'e_services', 'admin.cms.targets.e_services_pages.it_support', '/e-services/it-support', 'public.e-services.detail', 'e_services'),
            $this->target('e_services.suggestions-complaints', 'e_services', 'admin.cms.targets.e_services_pages.suggestions_complaints', '/e-services/suggestions-complaints', 'public.e-services.suggestions-complaints', 'e_services'),
            $this->target('contact', 'contact', 'admin.cms.targets.contact', '/contact', 'public.contact'),
        ];
    }

    /** @return array<int, CmsTargetDTO> */
    private function newsTargets(): array
    {
        return [
            $this->target('news.index', 'news', 'admin.cms.targets.news.index', '/news', 'public.news.index'),
            $this->target('news.articles', 'news', 'admin.cms.targets.news.articles', '/news/articles', 'public.news.articles', 'news.index'),
            $this->target('news.announcements', 'news', 'admin.cms.targets.news.announcements', '/news/announcements', 'public.news.announcements', 'news.index'),
            $this->target('news.events', 'news', 'admin.cms.targets.news.events', '/news/events-list', 'public.news.events-list', 'news.index'),
            $this->target('news.gallery', 'news', 'admin.cms.targets.news.gallery', '/news/gallery', 'public.news.gallery', 'news.index'),
            $this->target('news.article', 'news', 'admin.cms.targets.news.article', null, 'public.news.show', 'news.index'),
        ];
    }

    /** @return array<int, CmsTargetDTO> */
    private function researchTargets(): array
    {
        return [
            $this->target('research.index', 'research', 'admin.cms.targets.research.index', '/research', 'public.research.index'),
            $this->target('research.publications', 'research', 'admin.cms.targets.research.publications', '/research/publications', 'public.research.publications.index', 'research.index'),
            $this->target('research.publication', 'research', 'admin.cms.targets.research.publication', null, 'public.research.publications.show', 'research.publications'),
            $this->target('research.centers', 'research', 'admin.cms.targets.research.centers', '/research/centers', 'public.research.centers.index', 'research.index'),
            $this->target('research.projects', 'research', 'admin.cms.targets.research.projects', '/research/projects', 'public.research.projects.index', 'research.index'),
            $this->target('research.themes', 'research', 'admin.cms.targets.research.themes', '/research/themes', 'public.research.themes.index', 'research.index'),
            $this->target('research.experts', 'research', 'admin.cms.targets.research.experts', '/research/expert-finder', 'public.research.expert-finder', 'research.index'),
            $this->target('research.expert_profile', 'research', 'admin.cms.targets.research.expert_profile', null, 'public.research.researchers.show', 'research.experts'),
            $this->target('research.conferences', 'research', 'admin.cms.targets.research.conferences', '/research/conferences', 'public.research.conferences', 'research.index'),
            $this->target('research.library', 'research', 'admin.cms.targets.research.library', '/research/library', 'public.research.library', 'research.index'),
            $this->target('research.office', 'research', 'admin.cms.targets.research.office', '/research/office', 'public.research.office', 'research.index'),
            $this->target('research.policies', 'research', 'admin.cms.targets.research.policies', '/research/policies', 'public.research.policies', 'research.index'),
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
        ?string $facultyScopeSlug = null,
    ): CmsTargetDTO {
        return new CmsTargetDTO(
            key: $key,
            area: $area,
            labelKey: $labelKey,
            publicPath: $publicPath,
            routeName: $routeName,
            parentKey: $parentKey,
            supportsDraftWorkflow: $supportsDraftWorkflow,
            facultyScopeSlug: $facultyScopeSlug,
        );
    }

    /** @return array<int, string> */
    private function facultySlugs(): array
    {
        return ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];
    }

    /** @return array<int, string> */
    private function facultySubpageSlugs(string $facultySlug): array
    {
        $slugs = ['overview', 'departments', 'labs', 'projects', 'alumni', 'valedictorians', 'research'];

        return $facultySlug === 'pharmacy' ? [...$slugs, 'training'] : $slugs;
    }

    /** @return array<int, string> */
    private function admissionsSectionSlugs(): array
    {
        return ['requirements', 'tuition', 'how-to-apply', 'faq', 'calendar', 'documents', 'transfer', 'filling-vacancies', 'graduation-exams'];
    }

    /** @return array<int, string> */
    private function campusLifeSectionSlugs(): array
    {
        return ['services', 'transport', 'clubs-activities', 'career-development', 'dental', 'hospital', 'health-insurance', 'damascus-research-pub', 'rules-regulations', 'general-rules', 'exam-instructions', 'exam-penalties'];
    }
}
