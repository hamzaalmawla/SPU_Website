<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\DTOs\Content\EducationDataDTO;
use App\DTOs\Content\FacultyMemberDataDTO;
use App\DTOs\Content\FacultyMemberTranslationDataDTO;
use App\DTOs\Content\LocalizedEducationDataDTO;
use App\DTOs\Content\PersonDataDTO;
use App\DTOs\Content\PersonTranslationDataDTO;

final class ProfileFormDataMapper
{
    /** @param array<string, mixed> $data */
    public static function personFromArray(array $data, ?int $id = null): PersonDataDTO
    {
        return new PersonDataDTO(
            id: $id,
            slug: (string) ($data['slug'] ?? ''),
            category: (string) ($data['category'] ?? ''),
            title: self::nullableString($data['title'] ?? null),
            position: self::nullableString($data['position'] ?? null),
            facultyScopeSlug: self::nullableString($data['faculty_scope_slug'] ?? null),
            image: self::nullableString($data['image'] ?? null),
            email: self::nullableString($data['email'] ?? null),
            phone: self::nullableString($data['phone'] ?? null),
            officeLocation: self::nullableString($data['office_location'] ?? null),
            profileUrl: self::nullableString($data['profile_url'] ?? null),
            socialLinks: self::stringMap($data['social_links'] ?? null),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isEnabled: (bool) ($data['is_enabled'] ?? false),
            translations: collect(is_array($data['translations'] ?? null) ? $data['translations'] : [])
                ->map(fn (array $translation): PersonTranslationDataDTO => new PersonTranslationDataDTO(
                    locale: (string) ($translation['locale'] ?? ''),
                    name: (string) ($translation['name'] ?? ''),
                    role: (string) ($translation['role'] ?? ''),
                    bio: self::nullableString($translation['bio'] ?? null),
                    quote: self::nullableString($translation['quote'] ?? null),
                ))
                ->values()
                ->all(),
            educations: self::educationsFromArray($data['educations'] ?? null),
        );
    }

    /** @param array<string, mixed> $data */
    public static function facultyMemberFromArray(array $data, ?int $id = null): FacultyMemberDataDTO
    {
        return new FacultyMemberDataDTO(
            id: $id,
            slug: (string) ($data['slug'] ?? ''),
            facultyId: self::nullableInt($data['faculty_id'] ?? null),
            departmentId: self::nullableInt($data['department_id'] ?? null),
            email: self::nullableString($data['email'] ?? null),
            phone: self::nullableString($data['phone'] ?? null),
            officeLocation: self::nullableString($data['office_location'] ?? null),
            photoMediaId: self::nullableInt($data['photo_media_id'] ?? null),
            cvMediaId: self::nullableInt($data['cv_media_id'] ?? null),
            socialLinks: self::stringMap($data['social_links'] ?? null),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isEnabled: (bool) ($data['is_enabled'] ?? false),
            translations: collect(is_array($data['translations'] ?? null) ? $data['translations'] : [])
                ->map(fn (array $translation): FacultyMemberTranslationDataDTO => new FacultyMemberTranslationDataDTO(
                    locale: (string) ($translation['locale'] ?? ''),
                    fullName: (string) ($translation['full_name'] ?? ''),
                    title: self::nullableString($translation['title'] ?? null),
                    position: self::nullableString($translation['position'] ?? null),
                    bio: self::nullableString($translation['bio'] ?? null),
                    specializations: self::stringList($translation['specializations'] ?? null),
                ))
                ->values()
                ->all(),
            educations: self::educationsFromArray($data['educations'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public static function personToArray(PersonDataDTO $data): array
    {
        return [
            'slug' => $data->slug,
            'category' => $data->category,
            'title' => $data->title,
            'position' => $data->position,
            'faculty_scope_slug' => $data->facultyScopeSlug,
            'image' => $data->image,
            'email' => $data->email,
            'phone' => $data->phone,
            'office_location' => $data->officeLocation,
            'profile_url' => $data->profileUrl,
            'social_links' => $data->socialLinks,
            'sort_order' => $data->sortOrder,
            'is_enabled' => $data->isEnabled,
            'translations' => collect($data->translations)->map(fn (PersonTranslationDataDTO $translation): array => [
                'locale' => $translation->locale,
                'name' => $translation->name,
                'role' => $translation->role,
                'bio' => $translation->bio,
                'quote' => $translation->quote,
            ])->all(),
            'educations' => self::educationsToArray($data->educations),
        ];
    }

    /** @return array<string, mixed> */
    public static function facultyMemberToArray(FacultyMemberDataDTO $data): array
    {
        return [
            'slug' => $data->slug,
            'faculty_id' => $data->facultyId,
            'department_id' => $data->departmentId,
            'email' => $data->email,
            'phone' => $data->phone,
            'office_location' => $data->officeLocation,
            'photo_media_id' => $data->photoMediaId,
            'cv_media_id' => $data->cvMediaId,
            'social_links' => $data->socialLinks,
            'sort_order' => $data->sortOrder,
            'is_enabled' => $data->isEnabled,
            'translations' => collect($data->translations)->map(fn (FacultyMemberTranslationDataDTO $translation): array => [
                'locale' => $translation->locale,
                'full_name' => $translation->fullName,
                'title' => $translation->title,
                'position' => $translation->position,
                'bio' => $translation->bio,
                'specializations' => $translation->specializations,
            ])->all(),
            'educations' => self::educationsToArray($data->educations),
        ];
    }

    /** @return array<int, EducationDataDTO> */
    private static function educationsFromArray(mixed $educations): array
    {
        return collect(is_array($educations) ? $educations : [])
            ->map(fn (array $education): EducationDataDTO => new EducationDataDTO(
                id: self::nullableInt($education['id'] ?? null),
                sortOrder: (int) ($education['sort_order'] ?? 0),
                isEnabled: (bool) ($education['is_enabled'] ?? false),
                translations: collect(is_array($education['translations'] ?? null) ? $education['translations'] : [])
                    ->map(fn (array $translation): LocalizedEducationDataDTO => new LocalizedEducationDataDTO(
                        locale: (string) ($translation['locale'] ?? ''),
                        degree: (string) ($translation['degree'] ?? ''),
                        institution: self::nullableString($translation['institution'] ?? null),
                        fieldOfStudy: self::nullableString($translation['field_of_study'] ?? null),
                        yearStart: self::nullableInt($translation['year_start'] ?? null),
                        yearEnd: self::nullableInt($translation['year_end'] ?? null),
                        description: self::nullableString($translation['description'] ?? null),
                    ))
                    ->values()
                    ->all(),
            ))
            ->values()
            ->all();
    }

    /** @param array<int, EducationDataDTO> $educations @return array<int, array<string, mixed>> */
    private static function educationsToArray(array $educations): array
    {
        return collect($educations)->map(fn (EducationDataDTO $education): array => [
            'id' => $education->id,
            'sort_order' => $education->sortOrder,
            'is_enabled' => $education->isEnabled,
            'translations' => collect($education->translations)->map(fn (LocalizedEducationDataDTO $translation): array => [
                'locale' => $translation->locale,
                'degree' => $translation->degree,
                'institution' => $translation->institution,
                'field_of_study' => $translation->fieldOfStudy,
                'year_start' => $translation->yearStart,
                'year_end' => $translation->yearEnd,
                'description' => $translation->description,
            ])->all(),
        ])->all();
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<string, string>|null */
    private static function stringMap(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $items = collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->all();

        return $items !== [] ? $items : null;
    }

    /** @return array<int, string>|null */
    private static function stringList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $items = collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->unique()
            ->values()
            ->all();

        return $items !== [] ? $items : null;
    }
}
