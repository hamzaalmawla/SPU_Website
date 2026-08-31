<?php

declare(strict_types=1);

namespace App\Contracts\Research;

use App\DTOs\Content\ResearchCardDTO;
use App\DTOs\Research\ResearchConferenceRegistrationDTO;
use App\DTOs\Research\ResearchDetailPageDTO;
use App\DTOs\Research\ResearchPageDTO;
use Illuminate\Support\Collection;

interface ResearchPageServiceInterface
{
    public function landing(string $locale): ResearchPageDTO;

    /** @param array<string, mixed> $filters */
    public function repository(string $locale, array $filters = []): ResearchPageDTO;

    /** @param array<string, mixed> $filters */
    public function publications(string $locale, array $filters = []): ResearchPageDTO;

    public function facultyPublications(string $facultySlug, string $locale): ResearchPageDTO;

    public function publication(string $locale, string $slug): ?ResearchDetailPageDTO;

    /** @param array<int, string> $publicationSlugs @return Collection<int, ResearchCardDTO> */
    public function getHomepagePublicationCards(string $locale, array $publicationSlugs = [], ?string $search = null, int $limit = 50): Collection;

    /** @return array<string, mixed> */
    public function getEditablePayload(string $targetKey): array;

    /** @param array<string, mixed> $content */
    public function buildPreviewPublications(string $locale, array $content): ResearchPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewExperts(string $locale, array $content): ResearchPageDTO;

    public function centers(string $locale): ResearchPageDTO;

    public function center(string $locale, string $slug): ?ResearchDetailPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewCenter(string $locale, array $content, string $slug): ?ResearchDetailPageDTO;

    /** @param array<string, mixed> $filters */
    public function projects(string $locale, array $filters = []): ResearchPageDTO;

    public function project(string $locale, string $slug): ?ResearchDetailPageDTO;

    /** @param array<string, mixed> $content @param array<string, mixed> $filters */
    public function buildPreviewProjects(string $locale, array $content, array $filters = []): ResearchPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewProject(string $locale, array $content, string $slug): ?ResearchDetailPageDTO;

    public function themes(string $locale): ResearchPageDTO;

    public function theme(string $locale, string $slug): ?ResearchDetailPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewTheme(string $locale, array $content, string $slug): ?ResearchDetailPageDTO;

    /** @param array<string, mixed> $filters */
    public function researchers(string $locale, array $filters = []): ResearchPageDTO;

    public function researcher(string $locale, string $slug): ?ResearchDetailPageDTO;

    /** @param array<string, mixed> $filters */
    public function expertFinder(string $locale, array $filters = []): ResearchPageDTO;

    public function conferences(string $locale): ResearchPageDTO;

    public function findRegisterableConference(string $eventId, string $locale): ?ResearchConferenceRegistrationDTO;

    public function conferenceRegistration(string $locale, ?string $eventId): ResearchPageDTO;

    public function library(string $locale): ResearchPageDTO;

    public function office(string $locale): ResearchPageDTO;

    public function policies(string $locale): ResearchPageDTO;

    public function publicationSlugForLegacyId(string $id): ?string;

    /**
     * Every publication slug for a locale, gathered in a single pass.
     *
     * @return array<int, string>
     */
    public function publicationSitemapSlugs(string $locale): array;

    public function isPubliclyAvailablePath(string $locale, string $path): bool;

    /** @param array<int, mixed> $columns @return array<int, mixed> */
    public function filterFooterColumns(string $locale, array $columns): array;

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    public function filterNavigationItems(array $items): array;

    /** @param array<string, mixed> $content */
    public function buildPreviewLanding(string $locale, array $content): ResearchPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewTarget(string $targetKey, string $locale, array $content): ResearchPageDTO;
}
