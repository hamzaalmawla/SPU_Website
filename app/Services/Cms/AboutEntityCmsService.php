<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\Content\DirectorateDTO;
use App\DTOs\Content\EducationDTO;
use App\DTOs\Content\PartnershipDTO;
use App\DTOs\Content\ProfilePageDTO;
use App\Enums\PublicationStatus;
use App\Models\Content\Directorate;
use App\Models\Content\DirectorateTranslation;
use App\Models\Content\Partnership;
use App\Models\Content\PartnershipTranslation;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberEducation;
use App\Models\Person\FacultyMemberEducationTranslation;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonEducation;
use App\Models\Person\PersonTranslation;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AboutEntityCmsService implements AboutEntityCmsServiceInterface
{
    private const TYPES = ['person', 'faculty-member', 'directorate', 'partnership'];

    public function __construct(
        private readonly MediaServiceInterface $mediaService,
    ) {}

    public function prepareDraft(AboutEntityCmsDataDTO $data, int $userId): AboutEntityCmsDataDTO
    {
        $this->assertSupportedType($data->entityType);

        $payload = $this->normalizePayload($data->entityType, $data->payload);
        $entityId = $data->entityId;

        if ($entityId !== null && ! $this->entityExists($data->entityType, $entityId)) {
            throw ValidationException::withMessages(['entity' => ['The CMS entity no longer exists.']]);
        }

        $this->authorizeManagement($userId, $data->entityType, $entityId, $payload);

        if ($entityId === null) {
            $entityId = DB::transaction(fn (): int => $this->createShell($data->entityType, $payload));
        }

        $payload['entity_type'] = $data->entityType;
        $payload['entity_id'] = $entityId;

        return new AboutEntityCmsDataDTO(
            entityType: $data->entityType,
            entityId: $entityId,
            payload: $payload,
            targetKey: $this->targetKey($data->entityType, $entityId),
        );
    }

    public function getStoredData(string $targetKey): ?AboutEntityCmsDataDTO
    {
        $target = $this->parseTargetKey($targetKey);
        if ($target === null) {
            return null;
        }

        [$type, $id] = $target;
        $payload = match ($type) {
            'person' => $this->storedPersonPayload($id),
            'faculty-member' => $this->storedFacultyMemberPayload($id),
            'directorate' => $this->storedDirectoratePayload($id),
            'partnership' => $this->storedPartnershipPayload($id),
        };

        return $payload === null ? null : new AboutEntityCmsDataDTO($type, $id, $payload, $targetKey);
    }

    public function resolveTarget(string $targetKey): ?CmsTargetDTO
    {
        $target = $this->parseTargetKey($targetKey);
        if ($target === null || ! $this->entityExists($target[0], $target[1])) {
            return null;
        }

        [$type, $id] = $target;
        $slug = $this->entitySlug($type, $id);
        $facultyScopeSlug = null;
        if ($type === 'faculty-member') {
            $faculty = $this->findFacultyMember($id)?->faculty;
            $facultyScopeSlug = $faculty !== null
                ? (string) ($faculty->faculty_scope_slug ?: $faculty->public_slug ?: $faculty->slug)
                : null;
        }
        $path = match ($type) {
            'person' => '/about/profile/person/'.$slug,
            'faculty-member' => '/about/profile/faculty-member/'.$slug,
            'directorate' => '/about/directorates/'.$slug,
            'partnership' => '/about/partnerships',
        };
        $route = match ($type) {
            'person', 'faculty-member' => 'public.about.profile',
            'directorate' => 'public.about.directorates.show',
            'partnership' => 'public.about.partnerships',
        };

        return new CmsTargetDTO(
            key: $targetKey,
            area: $type === 'faculty-member' ? 'faculty' : 'about',
            labelKey: 'admin.cms.targets.entity.'.$type,
            publicPath: $path,
            routeName: $route,
            parentKey: match ($type) {
                'person' => 'about.leadership',
                'faculty-member' => 'about.directorates_staff',
                'directorate' => 'about.directorates',
                'partnership' => 'about.partnerships',
            },
            supportsDraftWorkflow: true,
            facultyScopeSlug: $facultyScopeSlug,
        );
    }

    public function authorizeTarget(string $targetKey, int $userId, ?array $payload = null): bool
    {
        $target = $this->parseTargetKey($targetKey);
        if ($target === null) {
            return true;
        }

        $this->authorizeManagement(
            $userId,
            $target[0],
            $target[1],
            $payload !== null ? $this->normalizePayload($target[0], $payload) : null,
        );

        return true;
    }

    public function publishTarget(string $targetKey, array $payload, DateTimeInterface $publishedAt): bool
    {
        $target = $this->parseTargetKey($targetKey);
        if ($target === null) {
            return false;
        }

        [$type, $id] = $target;
        $payload = $this->normalizePayload($type, $payload);
        $errors = $this->validationErrors($type, $payload);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($type, $id, $payload, $publishedAt): bool {
            match ($type) {
                'person' => $this->publishPerson($id, $payload, $publishedAt),
                'faculty-member' => $this->publishFacultyMember($id, $payload, $publishedAt),
                'directorate' => $this->publishDirectorate($id, $payload, $publishedAt),
                'partnership' => $this->publishPartnership($id, $payload, $publishedAt),
            };

            return true;
        });
    }

    public function publishErrors(string $targetKey, array $payload): array
    {
        $target = $this->parseTargetKey($targetKey);
        if ($target === null) {
            return [];
        }

        return $this->validationErrors($target[0], $this->normalizePayload($target[0], $payload));
    }

    public function markDraft(string $targetKey): bool
    {
        return $this->updatePublicationState($targetKey, PublicationStatus::Draft, false, true);
    }

    public function markScheduled(string $targetKey): bool
    {
        $model = $this->findEntity($targetKey);
        if (! $model instanceof Model) {
            return false;
        }

        if ($model->getAttribute('publication_status') !== PublicationStatus::Published->value) {
            $model->forceFill(['publication_status' => PublicationStatus::Scheduled->value])->save();
        }

        return true;
    }

    public function unpublishTarget(string $targetKey): bool
    {
        return $this->updatePublicationState($targetKey, PublicationStatus::Draft, true);
    }

    public function buildPersonPreview(array $payload, string $locale): ?ProfilePageDTO
    {
        $payload = $this->normalizePayload('person', $payload);
        $translation = $this->localized($payload, $locale);
        if ($translation === null) {
            return null;
        }

        $slug = $this->stringValue($payload['slug'] ?? null);
        $name = $this->stringValue($translation['name'] ?? null);
        if ($slug === '' || $name === '') {
            return null;
        }

        $educations = [];
        foreach ($this->listValue($payload['educations'] ?? null) as $education) {
            if (! is_array($education) || ! (bool) ($education['is_enabled'] ?? false)) {
                continue;
            }

            $educationTranslation = $this->localized($education, $locale);
            if ($educationTranslation === null || $this->stringValue($educationTranslation['degree'] ?? null) === '') {
                continue;
            }

            $educations[] = new EducationDTO(
                degree: $this->stringValue($educationTranslation['degree']),
                institution: $this->nullableString($educationTranslation['institution'] ?? null),
                fieldOfStudy: $this->nullableString($educationTranslation['field_of_study'] ?? null),
                yearStart: $this->nullableInt($educationTranslation['year_start'] ?? null),
                yearEnd: $this->nullableInt($educationTranslation['year_end'] ?? null),
                description: $this->nullableString($educationTranslation['description'] ?? null),
            );
        }

        $bio = $this->nullableString($translation['bio'] ?? null);

        return new ProfilePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sourceType: 'person',
            slug: $slug,
            name: $name,
            title: $this->nullableString($payload['title'] ?? null),
            position: $this->nullableString($payload['position'] ?? null) ?? $this->nullableString($translation['role'] ?? null),
            category: $this->nullableString($payload['category'] ?? null),
            facultyName: $this->nullableString($payload['faculty_scope_slug'] ?? null),
            departmentName: null,
            email: $this->nullableString($payload['email'] ?? null),
            phone: $this->nullableString($payload['phone'] ?? null),
            image: $this->nullableString($payload['image'] ?? null),
            bio: $bio,
            quote: $this->nullableString($translation['quote'] ?? null),
            specializations: null,
            officeLocation: $this->nullableString($payload['office_location'] ?? null),
            socialLinks: is_array($payload['social_links'] ?? null) ? $payload['social_links'] : null,
            educations: $educations,
            publications: [],
            councilMemberships: [],
            cvUrl: null,
            profileUrl: $this->nullableString($payload['profile_url'] ?? null),
            seoTitle: $name.' - '.config('app.name', 'SPU'),
            seoDescription: $bio ?? $name,
            seoImage: $this->nullableString($payload['image'] ?? null),
            path: '/'.$locale.'/about/profile/person/'.$slug,
        );
    }

    public function buildFacultyMemberPreview(array $payload, string $locale): ?ProfilePageDTO
    {
        $payload = $this->normalizePayload('faculty-member', $payload);
        $translation = $this->localized($payload, $locale);
        if ($translation === null) {
            return null;
        }

        $slug = $this->stringValue($payload['slug'] ?? null);
        $name = $this->stringValue($translation['full_name'] ?? null);
        if ($slug === '' || $name === '') {
            return null;
        }

        $educations = [];
        foreach ($this->listValue($payload['educations'] ?? null) as $education) {
            if (! is_array($education) || ! (bool) ($education['is_enabled'] ?? false)) {
                continue;
            }

            $educationTranslation = $this->localized($education, $locale);
            if ($educationTranslation === null || $this->stringValue($educationTranslation['degree'] ?? null) === '') {
                continue;
            }

            $educations[] = new EducationDTO(
                degree: $this->stringValue($educationTranslation['degree']),
                institution: $this->nullableString($educationTranslation['institution'] ?? null),
                fieldOfStudy: $this->nullableString($educationTranslation['field_of_study'] ?? null),
                yearStart: $this->nullableInt($educationTranslation['year_start'] ?? null),
                yearEnd: $this->nullableInt($educationTranslation['year_end'] ?? null),
                description: $this->nullableString($educationTranslation['description'] ?? null),
            );
        }

        $bio = $this->nullableString($translation['bio'] ?? null);
        $image = $this->mediaUrl($this->nullableInt($payload['photo_media_id'] ?? null));

        return new ProfilePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sourceType: 'faculty_member',
            slug: $slug,
            name: $name,
            title: $this->nullableString($translation['title'] ?? null),
            position: $this->nullableString($translation['position'] ?? null),
            category: null,
            facultyName: $this->facultyName($this->nullableInt($payload['faculty_id'] ?? null), $locale),
            departmentName: $this->departmentName($this->nullableInt($payload['department_id'] ?? null), $locale),
            email: $this->nullableString($payload['email'] ?? null),
            phone: $this->nullableString($payload['phone'] ?? null),
            image: $image,
            bio: $bio,
            quote: null,
            specializations: $this->stringList($translation['specializations'] ?? null),
            officeLocation: $this->nullableString($payload['office_location'] ?? null),
            socialLinks: is_array($payload['social_links'] ?? null) ? $payload['social_links'] : null,
            educations: $educations,
            publications: [],
            councilMemberships: [],
            cvUrl: $this->mediaUrl($this->nullableInt($payload['cv_media_id'] ?? null)),
            profileUrl: null,
            seoTitle: $name.' - '.config('app.name', 'SPU'),
            seoDescription: $bio ?? $name,
            seoImage: $image,
            path: '/'.$locale.'/about/profile/faculty-member/'.$slug,
        );
    }

    public function buildDirectoratePreview(array $payload, string $locale): ?DirectorateDTO
    {
        $payload = $this->normalizePayload('directorate', $payload);
        $translation = $this->localized($payload, $locale);
        if ($translation === null) {
            return null;
        }

        return new DirectorateDTO(
            id: (int) ($payload['entity_id'] ?? 0),
            slug: $this->stringValue($payload['slug'] ?? null),
            title: $this->stringValue($translation['title'] ?? null),
            summary: $this->stringValue($translation['summary'] ?? null),
            description: $this->stringValue($translation['description'] ?? null),
            services: array_values(array_filter($this->listValue($translation['services_json'] ?? null), 'is_string')),
            icon: $this->nullableString($payload['icon'] ?? null),
            email: $this->nullableString($payload['email'] ?? null),
            location: $this->nullableString($payload['location'] ?? null),
        );
    }

    public function buildPartnershipPreview(array $payload, string $locale): ?PartnershipDTO
    {
        $payload = $this->normalizePayload('partnership', $payload);
        $translation = $this->localized($payload, $locale);
        if ($translation === null) {
            return null;
        }

        return new PartnershipDTO(
            id: (int) ($payload['entity_id'] ?? 0),
            slug: $this->stringValue($payload['slug'] ?? null),
            categoryKey: $this->stringValue($payload['category_key'] ?? null),
            statusKey: $this->stringValue($payload['status_key'] ?? 'active'),
            name: $this->stringValue($translation['name'] ?? null),
            category: $this->stringValue($translation['category'] ?? null),
            status: $this->stringValue($translation['status'] ?? null),
            establishedLabel: $this->stringValue($translation['established_label'] ?? null),
            description: $this->stringValue($translation['description'] ?? null),
            logo: $this->nullableString($payload['logo'] ?? null),
            websiteUrl: $this->nullableString($payload['website_url'] ?? null),
            scope: $this->nullableString($translation['scope'] ?? null),
            signedAt: $this->nullableString($payload['signed_at'] ?? null),
        );
    }

    /** @param array<string, mixed> $payload */
    private function createShell(string $type, array $payload): int
    {
        $slug = $this->stringValue($payload['slug'] ?? null);
        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => ['The slug is required.']]);
        }

        $model = match ($type) {
            'person' => Person::query()->create([
                'slug' => $slug,
                'category' => $this->requiredString($payload, 'category'),
                'publication_status' => PublicationStatus::Draft->value,
                'published_at' => null,
                'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            ]),
            'faculty-member' => FacultyMember::query()->create([
                'slug' => $slug,
                'faculty_id' => $this->nullableInt($payload['faculty_id'] ?? null),
                'department_id' => $this->nullableInt($payload['department_id'] ?? null),
                'publication_status' => PublicationStatus::Draft->value,
                'published_at' => null,
                'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            ]),
            'directorate' => Directorate::query()->create([
                'slug' => $slug,
                'publication_status' => PublicationStatus::Draft->value,
                'published_at' => null,
                'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            ]),
            'partnership' => Partnership::query()->create([
                'slug' => $slug,
                'category_key' => $this->nullableString($payload['category_key'] ?? null),
                'status_key' => $this->nullableString($payload['status_key'] ?? null) ?? 'active',
                'publication_status' => PublicationStatus::Draft->value,
                'published_at' => null,
                'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            ]),
        };

        return (int) $model->getKey();
    }

    /** @param array<string, mixed> $payload */
    private function publishPerson(int $id, array $payload, DateTimeInterface $publishedAt): void
    {
        $person = Person::query()->findOrFail($id);
        $person->forceFill([
            'slug' => $this->requiredString($payload, 'slug'),
            'category' => $this->requiredString($payload, 'category'),
            'title' => $this->nullableString($payload['title'] ?? null),
            'position' => $this->nullableString($payload['position'] ?? null),
            'faculty_scope_slug' => $this->nullableString($payload['faculty_scope_slug'] ?? null),
            'email' => $this->nullableString($payload['email'] ?? null),
            'phone' => $this->nullableString($payload['phone'] ?? null),
            'office_location' => $this->nullableString($payload['office_location'] ?? null),
            'image' => $this->nullableString($payload['image'] ?? null),
            'profile_url' => $this->nullableString($payload['profile_url'] ?? null),
            'social_links' => is_array($payload['social_links'] ?? null) ? $payload['social_links'] : null,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => $publishedAt,
        ])->save();

        foreach ($this->translations($payload) as $locale => $translation) {
            $person->translations()->updateOrCreate(['locale' => $locale], [
                'name' => $this->requiredString($translation, 'name'),
                'role' => $this->requiredString($translation, 'role'),
                'bio' => $this->nullableString($translation['bio'] ?? null),
                'quote' => $this->nullableString($translation['quote'] ?? null),
            ]);
        }

        $keptEducationIds = [];
        foreach ($this->listValue($payload['educations'] ?? null) as $educationPayload) {
            if (! is_array($educationPayload)) {
                continue;
            }

            $educationId = $this->nullableInt($educationPayload['id'] ?? null);
            $education = $educationId !== null
                ? PersonEducation::query()->where('person_id', $id)->findOrFail($educationId)
                : new PersonEducation(['person_id' => $id]);
            $education->fill([
                'sort_order' => (int) ($educationPayload['sort_order'] ?? 0),
                'is_enabled' => (bool) ($educationPayload['is_enabled'] ?? false),
            ]);
            $education->person()->associate($person);
            $education->save();
            $keptEducationIds[] = (int) $education->getKey();

            foreach ($this->translations($educationPayload) as $locale => $translation) {
                $education->translations()->updateOrCreate(['locale' => $locale], [
                    'degree' => $this->requiredString($translation, 'degree'),
                    'institution' => $this->nullableString($translation['institution'] ?? null),
                    'field_of_study' => $this->nullableString($translation['field_of_study'] ?? null),
                    'year_start' => $this->nullableInt($translation['year_start'] ?? null),
                    'year_end' => $this->nullableInt($translation['year_end'] ?? null),
                    'description' => $this->nullableString($translation['description'] ?? null),
                ]);
            }
        }

        $educationQuery = $person->educations();
        if ($keptEducationIds !== []) {
            $educationQuery->whereNotIn('id', $keptEducationIds);
        }
        $educationQuery->delete();
    }

    /** @param array<string, mixed> $payload */
    private function publishFacultyMember(int $id, array $payload, DateTimeInterface $publishedAt): void
    {
        $member = FacultyMember::query()->findOrFail($id);
        $member->forceFill([
            'slug' => $this->requiredString($payload, 'slug'),
            'faculty_id' => $this->nullableInt($payload['faculty_id'] ?? null),
            'department_id' => $this->nullableInt($payload['department_id'] ?? null),
            'email' => $this->nullableString($payload['email'] ?? null),
            'phone' => $this->nullableString($payload['phone'] ?? null),
            'office_location' => $this->nullableString($payload['office_location'] ?? null),
            'photo_media_id' => $this->nullableInt($payload['photo_media_id'] ?? null),
            'cv_media_id' => $this->nullableInt($payload['cv_media_id'] ?? null),
            'social_links' => is_array($payload['social_links'] ?? null) ? $payload['social_links'] : null,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => $publishedAt,
        ])->save();

        foreach ($this->translations($payload) as $locale => $translation) {
            $member->translations()->updateOrCreate(['locale' => $locale], [
                'full_name' => $this->requiredString($translation, 'full_name'),
                'title' => $this->nullableString($translation['title'] ?? null),
                'position' => $this->nullableString($translation['position'] ?? null),
                'bio' => $this->nullableString($translation['bio'] ?? null),
                'specializations' => $this->stringList($translation['specializations'] ?? null),
            ]);
        }

        $keptEducationIds = [];
        foreach ($this->listValue($payload['educations'] ?? null) as $educationPayload) {
            if (! is_array($educationPayload)) {
                continue;
            }

            $educationId = $this->nullableInt($educationPayload['id'] ?? null);
            $education = $educationId !== null
                ? FacultyMemberEducation::query()->where('faculty_member_id', $id)->findOrFail($educationId)
                : new FacultyMemberEducation(['faculty_member_id' => $id]);
            $education->fill([
                'sort_order' => (int) ($educationPayload['sort_order'] ?? 0),
                'is_enabled' => (bool) ($educationPayload['is_enabled'] ?? false),
            ]);
            $education->facultyMember()->associate($member);
            $education->save();
            $keptEducationIds[] = (int) $education->getKey();

            foreach ($this->translations($educationPayload) as $locale => $translation) {
                $education->translations()->updateOrCreate(['locale' => $locale], [
                    'degree' => $this->requiredString($translation, 'degree'),
                    'institution' => $this->nullableString($translation['institution'] ?? null),
                    'field_of_study' => $this->nullableString($translation['field_of_study'] ?? null),
                    'year_start' => $this->nullableInt($translation['year_start'] ?? null),
                    'year_end' => $this->nullableInt($translation['year_end'] ?? null),
                    'description' => $this->nullableString($translation['description'] ?? null),
                ]);
            }
        }

        $educationQuery = $member->educations();
        if ($keptEducationIds !== []) {
            $educationQuery->whereNotIn('id', $keptEducationIds);
        }
        $educationQuery->delete();
    }

    /** @param array<string, mixed> $payload */
    private function publishDirectorate(int $id, array $payload, DateTimeInterface $publishedAt): void
    {
        $directorate = Directorate::query()->findOrFail($id);
        $directorate->forceFill([
            'slug' => $this->requiredString($payload, 'slug'),
            'icon' => $this->nullableString($payload['icon'] ?? null),
            'email' => $this->nullableString($payload['email'] ?? null),
            'location' => $this->nullableString($payload['location'] ?? null),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => $publishedAt,
        ])->save();

        foreach ($this->translations($payload) as $locale => $translation) {
            $directorate->translations()->updateOrCreate(['locale' => $locale], [
                'title' => $this->requiredString($translation, 'title'),
                'summary' => $this->nullableString($translation['summary'] ?? null),
                'description' => $this->nullableString($translation['description'] ?? null),
                'services_json' => $this->listValue($translation['services_json'] ?? null),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function publishPartnership(int $id, array $payload, DateTimeInterface $publishedAt): void
    {
        $partnership = Partnership::query()->findOrFail($id);
        $partnership->forceFill([
            'slug' => $this->requiredString($payload, 'slug'),
            'category_key' => $this->requiredString($payload, 'category_key'),
            'status_key' => $this->requiredString($payload, 'status_key'),
            'logo' => $this->nullableString($payload['logo'] ?? null),
            'website_url' => $this->nullableString($payload['website_url'] ?? null),
            'signed_at' => $this->nullableString($payload['signed_at'] ?? null),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => $publishedAt,
        ])->save();

        foreach ($this->translations($payload) as $locale => $translation) {
            $partnership->translations()->updateOrCreate(['locale' => $locale], [
                'name' => $this->requiredString($translation, 'name'),
                'category' => $this->nullableString($translation['category'] ?? null),
                'status' => $this->nullableString($translation['status'] ?? null),
                'established_label' => $this->nullableString($translation['established_label'] ?? null),
                'description' => $this->nullableString($translation['description'] ?? null),
                'scope' => $this->nullableString($translation['scope'] ?? null),
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function storedPersonPayload(int $id): ?array
    {
        $person = Person::query()->with(['translations', 'educations.translations'])->find($id);
        if (! $person instanceof Person) {
            return null;
        }

        return [
            'entity_type' => 'person',
            'entity_id' => $id,
            'slug' => $person->slug,
            'category' => $person->category,
            'title' => $person->title,
            'position' => $person->position,
            'faculty_scope_slug' => $person->faculty_scope_slug,
            'email' => $person->email,
            'phone' => $person->phone,
            'office_location' => $person->office_location,
            'image' => $person->image,
            'profile_url' => $person->profile_url,
            'social_links' => $person->social_links,
            'sort_order' => (int) $person->sort_order,
            'is_enabled' => (bool) $person->is_enabled,
            'translations' => $person->translations->mapWithKeys(fn (PersonTranslation $translation): array => [(string) $translation->locale => [
                'locale' => (string) $translation->locale,
                'name' => (string) $translation->name,
                'role' => (string) $translation->role,
                'bio' => $translation->bio,
                'quote' => $translation->quote,
            ]])->all(),
            'educations' => $person->educations->map(fn (PersonEducation $education): array => [
                'id' => (int) $education->getKey(),
                'sort_order' => (int) $education->sort_order,
                'is_enabled' => (bool) $education->is_enabled,
                'translations' => $education->translations->mapWithKeys(fn ($translation): array => [(string) $translation->locale => [
                    'locale' => (string) $translation->locale,
                    'degree' => (string) $translation->degree,
                    'institution' => $translation->institution,
                    'field_of_study' => $translation->field_of_study,
                    'year_start' => $translation->year_start,
                    'year_end' => $translation->year_end,
                    'description' => $translation->description,
                ]])->all(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function storedFacultyMemberPayload(int $id): ?array
    {
        $member = FacultyMember::query()->with(['translations', 'educations.translations'])->find($id);
        if (! $member instanceof FacultyMember) {
            return null;
        }

        return [
            'entity_type' => 'faculty-member',
            'entity_id' => $id,
            'slug' => $member->slug,
            'faculty_id' => $member->faculty_id,
            'department_id' => $member->department_id,
            'email' => $member->email,
            'phone' => $member->phone,
            'office_location' => $member->office_location,
            'photo_media_id' => $member->photo_media_id,
            'cv_media_id' => $member->cv_media_id,
            'social_links' => $member->social_links,
            'sort_order' => (int) $member->sort_order,
            'is_enabled' => (bool) $member->is_enabled,
            'translations' => $member->translations->mapWithKeys(fn (FacultyMemberTranslation $translation): array => [(string) $translation->locale => [
                'locale' => (string) $translation->locale,
                'full_name' => (string) $translation->full_name,
                'title' => $translation->title,
                'position' => $translation->position,
                'bio' => $translation->bio,
                'specializations' => $translation->specializations,
            ]])->all(),
            'educations' => $member->educations->map(fn (FacultyMemberEducation $education): array => [
                'id' => (int) $education->getKey(),
                'sort_order' => (int) $education->sort_order,
                'is_enabled' => (bool) $education->is_enabled,
                'translations' => $education->translations->mapWithKeys(fn (FacultyMemberEducationTranslation $translation): array => [(string) $translation->locale => [
                    'locale' => (string) $translation->locale,
                    'degree' => (string) $translation->degree,
                    'institution' => $translation->institution,
                    'field_of_study' => $translation->field_of_study,
                    'year_start' => $translation->year_start,
                    'year_end' => $translation->year_end,
                    'description' => $translation->description,
                ]])->all(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function storedDirectoratePayload(int $id): ?array
    {
        $directorate = Directorate::query()->with('translations')->find($id);
        if (! $directorate instanceof Directorate) {
            return null;
        }

        return [
            'entity_type' => 'directorate',
            'entity_id' => $id,
            'slug' => $directorate->slug,
            'icon' => $directorate->icon,
            'email' => $directorate->email,
            'location' => $directorate->location,
            'sort_order' => (int) $directorate->sort_order,
            'is_enabled' => (bool) $directorate->is_enabled,
            'translations' => $directorate->translations->mapWithKeys(fn (DirectorateTranslation $translation): array => [(string) $translation->locale => [
                'locale' => (string) $translation->locale,
                'title' => (string) $translation->title,
                'summary' => $translation->summary,
                'description' => $translation->description,
                'services_json' => $translation->services_json,
            ]])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function storedPartnershipPayload(int $id): ?array
    {
        $partnership = Partnership::query()->with('translations')->find($id);
        if (! $partnership instanceof Partnership) {
            return null;
        }

        return [
            'entity_type' => 'partnership',
            'entity_id' => $id,
            'slug' => $partnership->slug,
            'category_key' => $partnership->category_key,
            'status_key' => $partnership->status_key,
            'logo' => $partnership->logo,
            'website_url' => $partnership->website_url,
            'signed_at' => $partnership->signed_at?->toDateString(),
            'sort_order' => (int) $partnership->sort_order,
            'is_enabled' => (bool) $partnership->is_enabled,
            'translations' => $partnership->translations->mapWithKeys(fn (PartnershipTranslation $translation): array => [(string) $translation->locale => [
                'locale' => (string) $translation->locale,
                'name' => (string) $translation->name,
                'category' => $translation->category,
                'status' => $translation->status,
                'established_label' => $translation->established_label,
                'description' => $translation->description,
                'scope' => $translation->scope,
            ]])->all(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function normalizePayload(string $type, array $payload): array
    {
        $payload['translations'] = $this->normalizeTranslations($payload['translations'] ?? null);

        if (in_array($type, ['person', 'faculty-member'], true)) {
            $payload['educations'] = collect($this->listValue($payload['educations'] ?? null))
                ->filter(static fn (mixed $education): bool => is_array($education))
                ->map(function (array $education): array {
                    $education['translations'] = $this->normalizeTranslations($education['translations'] ?? null);

                    return $education;
                })
                ->values()
                ->all();
        }

        return $payload;
    }

    /** @return array<string, array<string, mixed>> */
    private function normalizeTranslations(mixed $translations): array
    {
        if (! is_array($translations)) {
            return [];
        }

        $normalized = [];
        foreach ($translations as $key => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $locale = is_string($key) && in_array($key, ['ar', 'en'], true)
                ? $key
                : $this->stringValue($translation['locale'] ?? null);
            if (in_array($locale, ['ar', 'en'], true)) {
                $translation['locale'] = $locale;
                $normalized[$locale] = $translation;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $payload */
    private function validationErrors(string $type, array $payload): array
    {
        $errors = [];
        if ($this->stringValue($payload['slug'] ?? null) === '') {
            $errors['slug'][] = 'The slug is required.';
        }
        if ($type === 'person' && $this->stringValue($payload['category'] ?? null) === '') {
            $errors['category'][] = 'The category is required.';
        }
        if ($type === 'partnership') {
            if (! in_array($payload['category_key'] ?? null, ['academic', 'research', 'clinical'], true)) {
                $errors['category_key'][] = 'A valid partnership category is required.';
            }
            if (! in_array($payload['status_key'] ?? null, ['active', 'historical'], true)) {
                $errors['status_key'][] = 'A valid partnership status is required.';
            }
        }
        if ($type === 'faculty-member') {
            $facultyId = $this->nullableInt($payload['faculty_id'] ?? null);
            $departmentId = $this->nullableInt($payload['department_id'] ?? null);

            if ($facultyId !== null && ! Faculty::query()->whereKey($facultyId)->exists()) {
                $errors['faculty_id'][] = 'The selected faculty does not exist.';
            }
            if ($departmentId !== null && ($facultyId === null || ! Department::query()->whereKey($departmentId)->where('faculty_id', $facultyId)->exists())) {
                $errors['department_id'][] = 'The selected department must belong to the selected faculty.';
            }

            $this->appendMediaErrors($errors, 'photo_media_id', $payload['photo_media_id'] ?? null, 'image/');
            $this->appendMediaErrors($errors, 'cv_media_id', $payload['cv_media_id'] ?? null, 'application/');
        }

        foreach (['ar', 'en'] as $locale) {
            $translation = $payload['translations'][$locale] ?? null;
            if (! is_array($translation)) {
                $errors['translations.'.$locale][] = 'A translation is required.';

                continue;
            }

            $requiredFields = match ($type) {
                'person' => ['name', 'role'],
                'faculty-member' => ['full_name'],
                'directorate' => ['title'],
                'partnership' => ['name'],
            };
            foreach ($requiredFields as $field) {
                if ($this->stringValue($translation[$field] ?? null) === '') {
                    $errors['translations.'.$locale.'.'.$field][] = 'This field is required.';
                }
            }
        }

        if (in_array($type, ['person', 'faculty-member'], true)) {
            foreach ($this->listValue($payload['educations'] ?? null) as $index => $education) {
                if (! is_array($education)) {
                    continue;
                }

                foreach (['ar', 'en'] as $locale) {
                    $translation = $education['translations'][$locale] ?? null;
                    if (! is_array($translation) || $this->stringValue($translation['degree'] ?? null) === '') {
                        $errors['educations.'.$index.'.translations.'.$locale.'.degree'][] = 'This field is required.';
                    }
                }
            }
        }

        return $errors;
    }

    /** @param array<string, array<int, string>> $errors */
    private function appendMediaErrors(array &$errors, string $field, mixed $value, string $expectedMimePrefix): void
    {
        $mediaId = $this->nullableInt($value);
        if ($mediaId === null) {
            return;
        }

        $media = MediaAsset::query()->find($mediaId);
        if (! $media instanceof MediaAsset || $media->library_scope !== 'main' || ! str_starts_with((string) $media->mime_type, $expectedMimePrefix)) {
            $errors[$field][] = 'The selected media file is unavailable or has the wrong type.';
        }
    }

    private function updatePublicationState(string $targetKey, PublicationStatus $status, bool $clearPublishedAt, bool $scheduledOnly = false): bool
    {
        $model = $this->findEntity($targetKey);
        if (! $model instanceof Model) {
            return false;
        }
        if ($scheduledOnly && $model->getAttribute('publication_status') !== PublicationStatus::Scheduled->value) {
            return true;
        }

        $attributes = ['publication_status' => $status->value];
        if ($clearPublishedAt) {
            $attributes['published_at'] = null;
        }
        $model->forceFill($attributes)->save();

        return true;
    }

    private function findEntity(string $targetKey): ?Model
    {
        $target = $this->parseTargetKey($targetKey);
        if ($target === null) {
            return null;
        }

        return match ($target[0]) {
            'person' => Person::query()->find($target[1]),
            'faculty-member' => FacultyMember::query()->find($target[1]),
            'directorate' => Directorate::query()->find($target[1]),
            'partnership' => Partnership::query()->find($target[1]),
        };
    }

    private function entityExists(string $type, int $id): bool
    {
        return match ($type) {
            'person' => Person::query()->whereKey($id)->exists(),
            'faculty-member' => FacultyMember::query()->whereKey($id)->exists(),
            'directorate' => Directorate::query()->whereKey($id)->exists(),
            'partnership' => Partnership::query()->whereKey($id)->exists(),
        };
    }

    private function entitySlug(string $type, int $id): string
    {
        return match ($type) {
            'person' => (string) Person::query()->whereKey($id)->value('slug'),
            'faculty-member' => (string) FacultyMember::query()->whereKey($id)->value('slug'),
            'directorate' => (string) Directorate::query()->whereKey($id)->value('slug'),
            'partnership' => (string) Partnership::query()->whereKey($id)->value('slug'),
        };
    }

    /** @return array{0: string, 1: int}|null */
    private function parseTargetKey(string $targetKey): ?array
    {
        if (preg_match('/^entity\.(person|faculty-member|directorate|partnership)\.([1-9][0-9]*)$/', $targetKey, $matches) !== 1) {
            return null;
        }

        return [$matches[1], (int) $matches[2]];
    }

    private function targetKey(string $type, int $id): string
    {
        return 'entity.'.$type.'.'.$id;
    }

    private function assertSupportedType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported About CMS entity type.');
        }
    }

    /** @param array<string, mixed>|null $payload */
    private function authorizeManagement(int $userId, string $type, ?int $entityId, ?array $payload): void
    {
        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            throw new AuthorizationException('This user is not authorized to manage About entities.');
        }

        if ($type !== 'faculty-member') {
            if (Gate::forUser($user)->denies('manage-pages')) {
                throw new AuthorizationException('This user is not authorized to manage About entities.');
            }

            return;
        }

        $member = $entityId !== null ? $this->findFacultyMember($entityId) : null;
        $allowed = $entityId === null
            ? Gate::forUser($user)->allows('create', FacultyMember::class)
            : ($member instanceof FacultyMember && Gate::forUser($user)->allows('update', $member));
        if (! $allowed) {
            throw new AuthorizationException('This user is not authorized to manage this faculty member.');
        }

        if ($payload !== null) {
            $this->authorizeFacultyMemberPayload($user, $payload);
            $this->validateFacultyMemberMedia($payload, $userId);
        }
    }

    private function findFacultyMember(int $id): ?FacultyMember
    {
        $member = FacultyMember::query()->with('faculty')->find($id);

        return $member instanceof FacultyMember ? $member : null;
    }

    /** @param array<string, mixed> $payload */
    private function authorizeFacultyMemberPayload(User $user, array $payload): void
    {
        $facultyId = $this->nullableInt($payload['faculty_id'] ?? null);
        $departmentId = $this->nullableInt($payload['department_id'] ?? null);

        if ($departmentId !== null && ($facultyId === null || ! Department::query()->whereKey($departmentId)->where('faculty_id', $facultyId)->exists())) {
            throw ValidationException::withMessages([
                'department_id' => ['The selected department must belong to the selected faculty.'],
            ]);
        }

        if ($user->role_slug !== 'faculty_editor') {
            return;
        }

        $scope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';
        $inScope = $scope !== '' && $facultyId !== null && Faculty::query()
            ->whereKey($facultyId)
            ->where(function ($query) use ($scope): void {
                $query->where('faculty_scope_slug', $scope)
                    ->orWhere('public_slug', $scope)
                    ->orWhere('slug', $scope);
            })
            ->exists();

        if (! $inScope) {
            throw new AuthorizationException('You may only manage faculty members in your assigned faculty.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function validateFacultyMemberMedia(array $payload, int $userId): void
    {
        foreach ([
            'photo_media_id' => [$payload['photo_media_id'] ?? null, 'image/'],
            'cv_media_id' => [$payload['cv_media_id'] ?? null, 'application/'],
        ] as $field => [$value, $expectedMimePrefix]) {
            $mediaId = $this->nullableInt($value);
            if ($mediaId === null) {
                continue;
            }

            $media = $this->mediaService->find($mediaId, $userId);
            if ($media === null || $media->libraryScope !== 'main' || ! str_starts_with($media->mimeType, $expectedMimePrefix)) {
                throw ValidationException::withMessages([
                    $field => ['The selected media file is unavailable or has the wrong type.'],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $payload @return array<string, array<string, mixed>> */
    private function translations(array $payload): array
    {
        return is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function localized(array $payload, string $locale): ?array
    {
        $translations = $this->translations($payload);
        $translation = $translations[$locale] ?? $translations[$locale === 'ar' ? 'en' : 'ar'] ?? null;

        return is_array($translation) ? $translation : null;
    }

    private function facultyName(?int $facultyId, string $locale): ?string
    {
        if ($facultyId === null) {
            return null;
        }

        $faculty = Faculty::query()->with('translations')->find($facultyId);
        if (! $faculty instanceof Faculty) {
            return null;
        }

        $translation = $faculty->translations->firstWhere('locale', $locale)
            ?? $faculty->translations->firstWhere('locale', 'ar')
            ?? $faculty->translations->firstWhere('locale', 'en');

        return is_string($translation?->name) ? $translation->name : null;
    }

    private function departmentName(?int $departmentId, string $locale): ?string
    {
        if ($departmentId === null) {
            return null;
        }

        $department = Department::query()->with('translations')->find($departmentId);
        if (! $department instanceof Department) {
            return null;
        }

        $translation = $department->translations->firstWhere('locale', $locale)
            ?? $department->translations->firstWhere('locale', 'ar')
            ?? $department->translations->firstWhere('locale', 'en');

        return is_string($translation?->name) ? $translation->name : null;
    }

    private function mediaUrl(?int $mediaId): ?string
    {
        if ($mediaId === null) {
            return null;
        }

        $media = MediaAsset::query()->find($mediaId);

        return $media instanceof MediaAsset ? MediaUrlResolver::resolve($media->path, $media->disk) : null;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        $value = $this->stringValue($payload[$key] ?? null);
        if ($value === '') {
            throw ValidationException::withMessages([$key => ['This field is required.']]);
        }

        return $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<int, mixed> */
    private function listValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /** @return array<int, string> */
    private function stringList(mixed $value): array
    {
        $items = [];
        foreach ($this->listValue($value) as $item) {
            $candidate = is_array($item) ? ($item['name'] ?? null) : $item;
            $candidate = $this->nullableString($candidate);
            if ($candidate !== null) {
                $items[] = $candidate;
            }
        }

        return array_values(array_unique($items));
    }
}
