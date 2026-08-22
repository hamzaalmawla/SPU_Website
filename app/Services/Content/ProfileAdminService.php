<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Content\EducationDataDTO;
use App\DTOs\Content\FacultyMemberDataDTO;
use App\DTOs\Content\FacultyMemberTranslationDataDTO;
use App\DTOs\Content\LocalizedEducationDataDTO;
use App\DTOs\Content\PersonDataDTO;
use App\DTOs\Content\PersonTranslationDataDTO;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberEducation;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonEducation;
use App\Models\Person\PersonTranslation;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProfileAdminService implements ProfileAdminServiceInterface
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly MediaServiceInterface $mediaService,
    ) {}

    /** @return array<int, string> */
    public function facultyOptions(int $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $query = Faculty::query()->enabled()->with('translations')->orderBy('sort_order');

        if ($user->role_slug === 'faculty_editor') {
            $scope = (string) ($user->faculty_scope_slug ?? '');
            $query->where(function ($facultyQuery) use ($scope): void {
                $facultyQuery->where('faculty_scope_slug', $scope)
                    ->orWhere('public_slug', $scope)
                    ->orWhere('slug', $scope);
            });
        } elseif (! in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            throw new AuthorizationException('You are not authorized to view faculty profile options.');
        }

        return $query->get()->mapWithKeys(fn (Faculty $faculty): array => [
            (int) $faculty->getKey() => $faculty->translations->firstWhere('locale', 'en')?->name
                ?? $faculty->translations->firstWhere('locale', 'ar')?->name
                ?? (string) $faculty->slug,
        ])->all();
    }

    /** @return array<int, string> */
    public function departmentOptions(?int $facultyId, int $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $query = Department::query()
            ->with('translations')
            ->when($facultyId !== null, fn ($departmentQuery) => $departmentQuery->where('faculty_id', $facultyId));

        if ($user->role_slug === 'faculty_editor') {
            $scope = (string) ($user->faculty_scope_slug ?? '');
            $query->whereHas('faculty', function ($facultyQuery) use ($scope): void {
                $facultyQuery->where('faculty_scope_slug', $scope)
                    ->orWhere('public_slug', $scope)
                    ->orWhere('slug', $scope);
            });
        } elseif (! in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            throw new AuthorizationException('You are not authorized to view department profile options.');
        }

        return $query->get()->mapWithKeys(fn (Department $department): array => [
            (int) $department->getKey() => $department->translations->firstWhere('locale', 'ar')?->name
                ?? $department->translations->first()?->name
                ?? '#'.$department->getKey(),
        ])->all();
    }

    public function nextPersonSortOrder(): int
    {
        return ((int) (Person::query()->max('sort_order') ?? 0)) + 10;
    }

    public function nextFacultyMemberSortOrder(): int
    {
        return ((int) (FacultyMember::query()->max('sort_order') ?? 0)) + 10;
    }

    public function getPersonData(int $id): ?PersonDataDTO
    {
        $person = Person::query()->with(['translations', 'educations.translations'])->find($id);

        return $person instanceof Person ? $this->mapPersonData($person) : null;
    }

    public function createPerson(PersonDataDTO $data, int $userId): PersonDataDTO
    {
        $this->authorizePersonManagement($userId);
        $this->validatePersonData($data);

        $person = DB::transaction(function () use ($data): Person {
            $person = Person::query()->create($this->personAttributes($data));
            $this->syncPersonTranslations($person, $data->translations);
            $this->syncPersonEducations($person, $data->educations);

            return $person;
        });

        $id = (int) $person->getKey();
        $this->auditService->log('person.created', $userId, Person::class, $id);
        $this->invalidatePublicProfiles();

        return $this->getPersonData($id) ?? throw new \RuntimeException('Created person could not be reloaded.');
    }

    public function updatePerson(int $id, PersonDataDTO $data, int $userId): bool
    {
        $this->authorizePersonManagement($userId);
        $this->validatePersonData($data);

        DB::transaction(function () use ($id, $data): void {
            $person = Person::query()->findOrFail($id);
            $person->update($this->personAttributes($data));
            $this->syncPersonTranslations($person, $data->translations);
            $this->syncPersonEducations($person, $data->educations);
        });

        $this->auditService->log('person.updated', $userId, Person::class, $id);
        $this->invalidatePublicProfiles();

        return true;
    }

    public function getFacultyMemberData(int $id): ?FacultyMemberDataDTO
    {
        $member = FacultyMember::query()->with(['translations', 'educations.translations'])->find($id);

        return $member instanceof FacultyMember ? $this->mapFacultyMemberData($member) : null;
    }

    public function createFacultyMember(FacultyMemberDataDTO $data, int $userId): FacultyMemberDataDTO
    {
        $this->validateFacultyMemberData($data);
        $this->authorizeFacultyScope($data, $userId);
        $this->validateFacultyMedia($data, $userId);

        $member = DB::transaction(function () use ($data): FacultyMember {
            $member = FacultyMember::query()->create($this->facultyMemberAttributes($data));
            $this->syncFacultyMemberTranslations($member, $data->translations);
            $this->syncFacultyMemberEducations($member, $data->educations);

            return $member;
        });

        $id = (int) $member->getKey();
        $this->auditService->log('faculty_member.created', $userId, FacultyMember::class, $id);
        $this->invalidatePublicProfiles();

        return $this->getFacultyMemberData($id) ?? throw new \RuntimeException('Created faculty member could not be reloaded.');
    }

    public function updateFacultyMember(int $id, FacultyMemberDataDTO $data, int $userId): bool
    {
        $this->validateFacultyMemberData($data);
        $this->authorizeExistingFacultyMember($id, $userId);
        $this->authorizeFacultyScope($data, $userId);
        $this->validateFacultyMedia($data, $userId);

        DB::transaction(function () use ($id, $data): void {
            $member = FacultyMember::query()->findOrFail($id);
            $member->update($this->facultyMemberAttributes($data));
            $this->syncFacultyMemberTranslations($member, $data->translations);
            $this->syncFacultyMemberEducations($member, $data->educations);
        });

        $this->auditService->log('faculty_member.updated', $userId, FacultyMember::class, $id);
        $this->invalidatePublicProfiles();

        return true;
    }

    /** @return array<string, mixed> */
    private function personAttributes(PersonDataDTO $data): array
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
            'profile_url' => '/about/profile/'.$data->slug,
            'social_links' => $data->socialLinks,
            'sort_order' => $data->sortOrder,
            'is_enabled' => $data->isEnabled,
        ];
    }

    /** @param array<int, PersonTranslationDataDTO> $translations */
    private function syncPersonTranslations(Person $person, array $translations): void
    {
        foreach ($translations as $translation) {
            $person->translations()->updateOrCreate(
                ['locale' => $translation->locale],
                [
                    'name' => $translation->name,
                    'role' => $translation->role,
                    'bio' => $translation->bio,
                    'quote' => $translation->quote,
                ],
            );
        }
    }

    /** @param array<int, EducationDataDTO> $educations */
    private function syncPersonEducations(Person $person, array $educations): void
    {
        $keptIds = [];

        foreach ($educations as $educationData) {
            $education = $educationData->id !== null
                ? PersonEducation::query()->where('person_id', $person->getKey())->findOrFail($educationData->id)
                : new PersonEducation(['person_id' => (int) $person->getKey()]);

            $education->fill([
                'sort_order' => $educationData->sortOrder,
                'is_enabled' => $educationData->isEnabled,
            ]);
            $education->person()->associate($person);
            $education->save();
            $keptIds[] = (int) $education->getKey();

            foreach ($educationData->translations as $translation) {
                $education->translations()->updateOrCreate(
                    ['locale' => $translation->locale],
                    $this->educationTranslationAttributes($translation),
                );
            }
        }

        $query = $person->educations();
        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds);
        }
        $query->delete();
    }

    /** @return array<string, mixed> */
    private function facultyMemberAttributes(FacultyMemberDataDTO $data): array
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
        ];
    }

    /** @param array<int, FacultyMemberTranslationDataDTO> $translations */
    private function syncFacultyMemberTranslations(FacultyMember $member, array $translations): void
    {
        foreach ($translations as $translation) {
            $member->translations()->updateOrCreate(
                ['locale' => $translation->locale],
                [
                    'full_name' => $translation->fullName,
                    'title' => $translation->title,
                    'position' => $translation->position,
                    'bio' => $translation->bio,
                    'specializations' => $translation->specializations,
                ],
            );
        }
    }

    /** @param array<int, EducationDataDTO> $educations */
    private function syncFacultyMemberEducations(FacultyMember $member, array $educations): void
    {
        $keptIds = [];

        foreach ($educations as $educationData) {
            $education = $educationData->id !== null
                ? FacultyMemberEducation::query()->where('faculty_member_id', $member->getKey())->findOrFail($educationData->id)
                : new FacultyMemberEducation(['faculty_member_id' => (int) $member->getKey()]);

            $education->fill([
                'sort_order' => $educationData->sortOrder,
                'is_enabled' => $educationData->isEnabled,
            ]);
            $education->facultyMember()->associate($member);
            $education->save();
            $keptIds[] = (int) $education->getKey();

            foreach ($educationData->translations as $translation) {
                $education->translations()->updateOrCreate(
                    ['locale' => $translation->locale],
                    $this->educationTranslationAttributes($translation),
                );
            }
        }

        $query = $member->educations();
        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds);
        }
        $query->delete();
    }

    /** @return array<string, mixed> */
    private function educationTranslationAttributes(LocalizedEducationDataDTO $translation): array
    {
        return [
            'degree' => $translation->degree,
            'institution' => $translation->institution,
            'field_of_study' => $translation->fieldOfStudy,
            'year_start' => $translation->yearStart,
            'year_end' => $translation->yearEnd,
            'description' => $translation->description,
        ];
    }

    private function validatePersonData(PersonDataDTO $data): void
    {
        $this->validateLocales($data->translations, 'translations');
        $this->validateEducations($data->educations);
    }

    private function validateFacultyMemberData(FacultyMemberDataDTO $data): void
    {
        $this->validateLocales($data->translations, 'translations');
        $this->validateEducations($data->educations);

        if ($data->departmentId !== null) {
            $validDepartment = $data->facultyId !== null
                && Department::query()->whereKey($data->departmentId)->where('faculty_id', $data->facultyId)->exists();

            if (! $validDepartment) {
                throw ValidationException::withMessages([
                    'department_id' => 'The selected department must belong to the selected faculty.',
                ]);
            }
        }
    }

    private function authorizeFacultyScope(FacultyMemberDataDTO $data, int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        if (in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            return;
        }

        $scope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';
        $hasScope = $user->role_slug === 'faculty_editor'
            && $scope !== ''
            && $data->facultyId !== null
            && Faculty::query()
                ->whereKey($data->facultyId)
                ->where(function ($query) use ($scope): void {
                    $query->where('faculty_scope_slug', $scope)
                        ->orWhere('public_slug', $scope)
                        ->orWhere('slug', $scope);
                })
                ->exists();

        if (! $hasScope) {
            throw new AuthorizationException('You may only manage faculty members in your assigned faculty.');
        }
    }

    private function authorizeExistingFacultyMember(int $memberId, int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        if (in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            return;
        }

        $scope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';
        $allowed = $user->role_slug === 'faculty_editor'
            && $scope !== ''
            && FacultyMember::query()
                ->whereKey($memberId)
                ->whereHas('faculty', function ($query) use ($scope): void {
                    $query->where('faculty_scope_slug', $scope)
                        ->orWhere('public_slug', $scope)
                        ->orWhere('slug', $scope);
                })
                ->exists();

        if (! $allowed) {
            throw new AuthorizationException('You may only update faculty members in your assigned faculty.');
        }
    }

    private function authorizePersonManagement(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        if (! in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            throw new AuthorizationException('You are not authorized to manage university profiles.');
        }
    }

    private function validateFacultyMedia(FacultyMemberDataDTO $data, int $userId): void
    {
        foreach ([
            'photo_media_id' => [$data->photoMediaId, 'image/'],
            'cv_media_id' => [$data->cvMediaId, 'application/'],
        ] as $field => [$mediaId, $expectedMimePrefix]) {
            if ($mediaId === null) {
                continue;
            }

            $media = $this->mediaService->find($mediaId, $userId);
            if ($media === null || $media->libraryScope !== 'main' || ! str_starts_with($media->mimeType, $expectedMimePrefix)) {
                throw ValidationException::withMessages([
                    $field => 'The selected media file is unavailable or has the wrong type.',
                ]);
            }
        }
    }

    /** @param array<int, PersonTranslationDataDTO|FacultyMemberTranslationDataDTO> $translations */
    private function validateLocales(array $translations, string $field): void
    {
        $locales = collect($translations)->map(fn ($translation): string => $translation->locale)->sort()->values()->all();

        if ($locales !== ['ar', 'en']) {
            throw ValidationException::withMessages([
                $field => 'Exactly one Arabic and one English translation are required.',
            ]);
        }
    }

    /** @param array<int, EducationDataDTO> $educations */
    private function validateEducations(array $educations): void
    {
        foreach ($educations as $index => $education) {
            $this->validateLocales($education->translations, "educations.{$index}.translations");

            foreach ($education->translations as $translationIndex => $translation) {
                if (trim($translation->degree) === '') {
                    throw ValidationException::withMessages([
                        "educations.{$index}.translations.{$translationIndex}.degree" => 'The degree is required.',
                    ]);
                }

                if ($translation->yearStart !== null && $translation->yearEnd !== null && $translation->yearEnd < $translation->yearStart) {
                    throw ValidationException::withMessages([
                        "educations.{$index}.translations.{$translationIndex}.year_end" => 'The end year must not be before the start year.',
                    ]);
                }
            }
        }
    }

    private function mapPersonData(Person $person): PersonDataDTO
    {
        return new PersonDataDTO(
            id: (int) $person->getKey(),
            slug: (string) $person->slug,
            category: (string) $person->category,
            title: $person->title,
            position: $person->position,
            facultyScopeSlug: $person->faculty_scope_slug,
            image: $person->image,
            email: $person->email,
            phone: $person->phone,
            officeLocation: $person->office_location,
            profileUrl: $person->profile_url,
            socialLinks: is_array($person->social_links) ? $person->social_links : null,
            sortOrder: (int) $person->sort_order,
            isEnabled: (bool) $person->is_enabled,
            translations: $person->translations->map(fn (PersonTranslation $translation): PersonTranslationDataDTO => new PersonTranslationDataDTO(
                locale: (string) $translation->locale,
                name: (string) $translation->name,
                role: (string) $translation->role,
                bio: $translation->bio,
                quote: $translation->quote,
            ))->values()->all(),
            educations: $person->educations->map(fn (PersonEducation $education): EducationDataDTO => $this->mapPersonEducationData($education))->values()->all(),
        );
    }

    private function mapFacultyMemberData(FacultyMember $member): FacultyMemberDataDTO
    {
        return new FacultyMemberDataDTO(
            id: (int) $member->getKey(),
            slug: (string) $member->slug,
            facultyId: $member->faculty_id !== null ? (int) $member->faculty_id : null,
            departmentId: $member->department_id !== null ? (int) $member->department_id : null,
            email: $member->email,
            phone: $member->phone,
            officeLocation: $member->office_location,
            photoMediaId: $member->photo_media_id !== null ? (int) $member->photo_media_id : null,
            cvMediaId: $member->cv_media_id !== null ? (int) $member->cv_media_id : null,
            socialLinks: is_array($member->social_links) ? $member->social_links : null,
            sortOrder: (int) $member->sort_order,
            isEnabled: (bool) $member->is_enabled,
            translations: $member->translations->map(fn (FacultyMemberTranslation $translation): FacultyMemberTranslationDataDTO => new FacultyMemberTranslationDataDTO(
                locale: (string) $translation->locale,
                fullName: (string) $translation->full_name,
                title: $translation->title,
                position: $translation->position,
                bio: $translation->bio,
                specializations: $this->normalizeSpecializations($translation->specializations),
            ))->values()->all(),
            educations: $member->educations->map(fn (FacultyMemberEducation $education): EducationDataDTO => $this->mapFacultyEducationData($education))->values()->all(),
        );
    }

    private function mapPersonEducationData(PersonEducation $education): EducationDataDTO
    {
        return new EducationDataDTO(
            id: (int) $education->getKey(),
            sortOrder: (int) $education->sort_order,
            isEnabled: (bool) $education->is_enabled,
            translations: $education->translations->map(fn ($translation): LocalizedEducationDataDTO => $this->mapEducationTranslationData($translation))->values()->all(),
        );
    }

    private function mapFacultyEducationData(FacultyMemberEducation $education): EducationDataDTO
    {
        return new EducationDataDTO(
            id: (int) $education->getKey(),
            sortOrder: (int) $education->sort_order,
            isEnabled: (bool) $education->is_enabled,
            translations: $education->translations->map(fn ($translation): LocalizedEducationDataDTO => $this->mapEducationTranslationData($translation))->values()->all(),
        );
    }

    private function mapEducationTranslationData(mixed $translation): LocalizedEducationDataDTO
    {
        return new LocalizedEducationDataDTO(
            locale: (string) $translation->locale,
            degree: (string) $translation->degree,
            institution: $translation->institution,
            fieldOfStudy: $translation->field_of_study,
            yearStart: $translation->year_start !== null ? (int) $translation->year_start : null,
            yearEnd: $translation->year_end !== null ? (int) $translation->year_end : null,
            description: $translation->description,
        );
    }

    /** @return array<int, string>|null */
    private function normalizeSpecializations(mixed $values): ?array
    {
        if (is_string($values)) {
            $values = preg_split('/[,;|،\r\n]+/u', $values) ?: [];
        }

        if (! is_array($values)) {
            return null;
        }

        $normalized = collect($values)
            ->map(static fn (mixed $value): mixed => is_array($value) ? ($value['name'] ?? null) : $value)
            ->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value))
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : null;
    }

    private function invalidatePublicProfiles(): void
    {
        if (! $this->cacheService->flushTags(['public-pages', 'public-shell', 'about', 'facilities', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }
    }
}
