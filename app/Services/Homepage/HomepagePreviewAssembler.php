<?php

declare(strict_types=1);

namespace App\Services\Homepage;

use App\Contracts\Homepage\HomepagePreviewAssemblerInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\DTOs\Homepage\HomepageDTO;
use App\Enums\PublicationStatus;
use App\Models\Homepage\HomepageDraft;
use App\Support\HomepageDraftSectionMapper;

final class HomepagePreviewAssembler implements HomepagePreviewAssemblerInterface
{
    public function __construct(
        private readonly HomepageSectionServiceInterface $homepageSectionService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function build(string $locale, ?array $snapshot = null): HomepageDTO
    {
        $draftHomepage = is_array($snapshot['homepage'] ?? null)
            ? $snapshot['homepage']
            : $snapshot;

        if (! is_array($draftHomepage)) {
            $draft = HomepageDraft::query()
                ->where('target_type', 'homepage')
                ->whereIn('status', PublicationStatus::editableValues())
                ->latest('updated_at')
                ->first();

            if (! $draft instanceof HomepageDraft || ! is_array($draft->payload_json)) {
                return $this->homepageSectionService->getPublicHomepage($locale);
            }

            $draftHomepage = is_array($draft->payload_json['homepage'] ?? null)
                ? $draft->payload_json['homepage']
                : $draft->payload_json;
        }

        $sections = is_array($draftHomepage['sections'] ?? null) ? $draftHomepage['sections'] : [];

        if ($sections === []) {
            return $this->homepageSectionService->getPublicHomepage($locale);
        }

        $fallbackHomepage = $this->homepageSectionService->getPublicHomepage($locale);
        $previewSections = HomepageDraftSectionMapper::previewSectionsFromDraft(
            $sections,
            $locale,
            $fallbackHomepage->sections,
        );

        if ($previewSections === []) {
            return $fallbackHomepage;
        }

        return new HomepageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sections: $previewSections,
        );
    }
}
