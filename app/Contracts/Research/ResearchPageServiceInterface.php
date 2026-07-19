<?php

declare(strict_types=1);

namespace App\Contracts\Research;

use App\DTOs\Research\ResearchDetailPageDTO;
use App\DTOs\Research\ResearchPageDTO;

interface ResearchPageServiceInterface
{
    public function landing(string $locale): ResearchPageDTO;

    /** @param array<string, mixed> $filters */
    public function repository(string $locale, array $filters = []): ResearchPageDTO;

    /** @param array<string, mixed> $filters */
    public function publications(string $locale, array $filters = []): ResearchPageDTO;

    public function facultyPublications(string $facultySlug, string $locale): ResearchPageDTO;

    public function publication(string $locale, string $slug): ?ResearchDetailPageDTO;

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

    public function conferenceRegistration(string $locale, ?string $eventId): ResearchPageDTO;

    public function library(string $locale): ResearchPageDTO;

    public function office(string $locale): ResearchPageDTO;

    public function policies(string $locale): ResearchPageDTO;

    public function publicationSlugForLegacyId(string $id): ?string;

    /** @param array<string, mixed> $content */
    public function buildPreviewLanding(string $locale, array $content): ResearchPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewTarget(string $targetKey, string $locale, array $content): ResearchPageDTO;
}
