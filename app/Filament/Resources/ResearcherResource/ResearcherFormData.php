<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResearcherResource;

use App\DTOs\Research\ResearcherDataDTO;

/** The researcher form's fields as the service's data object. */
final class ResearcherFormData
{
    /** @param array<string, mixed> $data */
    public static function toDto(array $data): ResearcherDataDTO
    {
        $text = fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;
        $id = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;

        return new ResearcherDataDTO(
            fullName: (string) ($data['full_name'] ?? ''),
            email: $text('email'),
            affiliation: $text('affiliation'),
            facultyId: $id('faculty_id'),
            facultyMemberId: $id('faculty_member_id'),
            orcidUrl: $text('orcid_url'),
            scholarUrl: $text('scholar_url'),
            scopusUrl: $text('scopus_url'),
            expertise: array_values(array_filter(is_array($data['expertise'] ?? null) ? $data['expertise'] : [], 'is_string')),
            isEnabled: (bool) ($data['is_enabled'] ?? true),
            notes: $text('notes'),
        );
    }
}
