<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\ProfilePageServiceInterface;
use App\DTOs\Content\EducationDTO;
use App\DTOs\Content\ProfilePageDTO;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberEducationTranslation;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonEducationTranslation;
use App\Models\Person\PersonTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Support\MediaUrlResolver;
use Illuminate\Database\Eloquent\Collection;

final class ProfilePageService implements ProfilePageServiceInterface
{
    public function getProfile(string $locale, string $slug): ?ProfilePageDTO
    {
        $personProfile = $this->resolvePersonProfile($locale, $slug);

        if ($personProfile !== null) {
            return $personProfile;
        }

        $facultyProfile = $this->resolveFacultyMemberProfile($locale, $slug);

        if ($facultyProfile !== null) {
            return $facultyProfile;
        }

        return null;
    }

    private function resolvePersonProfile(string $locale, string $slug): ?ProfilePageDTO
    {
        $person = Person::query()
            ->enabled()
            ->where('slug', $slug)
            ->with([
                'translations',
                'educations',
                'educations.translations',
            ])
            ->first();

        if (! $person instanceof Person) {
            return null;
        }

        $translation = $this->personTranslation($person, $locale);

        return new ProfilePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sourceType: 'person',
            slug: (string) $person->slug,
            name: (string) $translation->name,
            title: $person->title,
            position: $person->position ?? $translation->role,
            category: $person->category,
            facultyName: $person->faculty_scope_slug,
            departmentName: null,
            email: $person->email,
            phone: $person->phone,
            image: $person->image,
            bio: $translation->bio,
            quote: $translation->quote,
            specializations: null,
            officeLocation: $person->office_location,
            socialLinks: $person->social_links,
            educations: $this->mapPersonEducations($person->educations, $locale),
            publications: [],
            councilMemberships: [],
            cvUrl: null,
            profileUrl: $person->profile_url,
            seoTitle: (string) $translation->name . ' - ' . config('app.name', 'SPU'),
            seoDescription: $translation->bio ?? (string) $translation->name,
            seoImage: $person->image,
            path: '/'.$locale.'/about/profile/'.$slug,
        );
    }

    private function resolveFacultyMemberProfile(string $locale, string $slug): ?ProfilePageDTO
    {
        $member = FacultyMember::query()
            ->enabled()
            ->where('slug', $slug)
            ->with([
                'translations',
                'faculty.translations',
                'department.translations',
                'educations',
                'educations.translations',
                'researchPublications',
                'researchPublications.translations',
                'councilMemberships',
                'councilMemberships.translations',
                'councilMemberships.council.translations',
                'cvMedia',
                'photoMedia',
            ])
            ->first();

        if (! $member instanceof FacultyMember) {
            return null;
        }

        $translation = $this->facultyTranslation($member, $locale);

        return new ProfilePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sourceType: 'faculty_member',
            slug: (string) $member->slug,
            name: (string) $translation->full_name,
            title: $translation->title,
            position: $translation->position,
            category: null,
            facultyName: $this->facultyName($member, $locale),
            departmentName: $this->departmentName($member, $locale),
            email: $member->email,
            phone: $member->phone,
            image: $this->resolveMemberImage($member),
            bio: $translation->bio,
            quote: null,
            specializations: $translation->specializations,
            officeLocation: $member->office_location,
            socialLinks: $member->social_links,
            educations: $this->mapFacultyEducations($member->educations, $locale),
            publications: $this->mapPublications($member->researchPublications, $locale),
            councilMemberships: $this->mapCouncilMemberships($member->councilMemberships, $locale),
            cvUrl: $this->resolveCvUrl($member),
            profileUrl: null,
            seoTitle: (string) $translation->full_name . ' - ' . config('app.name', 'SPU'),
            seoDescription: $translation->bio ?? (string) $translation->full_name,
            seoImage: $this->resolveMemberImage($member),
            path: '/'.$locale.'/about/profile/'.$slug,
        );
    }

    private function personTranslation(Person $person, string $locale): PersonTranslation
    {
        return $person->translations->firstWhere('locale', $locale)
            ?? $person->translations->firstWhere('locale', 'ar')
            ?? $person->translations->first();
    }

    private function facultyTranslation(FacultyMember $member, string $locale): FacultyMemberTranslation
    {
        return $member->translations->firstWhere('locale', $locale)
            ?? $member->translations->firstWhere('locale', 'ar')
            ?? $member->translations->first();
    }

    private function facultyName(FacultyMember $member, string $locale): ?string
    {
        if (! $member->faculty instanceof \App\Models\Faculty\Faculty) {
            return null;
        }

        $translation = $member->faculty->translations->firstWhere('locale', $locale)
            ?? $member->faculty->translations->firstWhere('locale', 'ar')
            ?? $member->faculty->translations->first();

        return $translation?->name;
    }

    private function departmentName(FacultyMember $member, string $locale): ?string
    {
        if (! $member->department instanceof \App\Models\Faculty\Department) {
            return null;
        }

        $translation = $member->department->translations->firstWhere('locale', $locale)
            ?? $member->department->translations->firstWhere('locale', 'ar')
            ?? $member->department->translations->first();

        return $translation?->name;
    }

    private function resolveMemberImage(FacultyMember $member): ?string
    {
        if ($member->photoMedia instanceof \App\Models\Media\MediaAsset) {
            return MediaUrlResolver::resolve($member->photoMedia->path, $member->photoMedia->disk);
        }

        return null;
    }

    private function resolveCvUrl(FacultyMember $member): ?string
    {
        if (! $member->cvMedia instanceof \App\Models\Media\MediaAsset) {
            return null;
        }

        return MediaUrlResolver::resolve($member->cvMedia->path, $member->cvMedia->disk);
    }

    /** @param Collection<int, \App\Models\Person\PersonEducation> $educations @return array<int, EducationDTO> */
    private function mapPersonEducations(Collection $educations, string $locale): array
    {
        return $educations
            ->filter(fn ($edu) => $edu->is_enabled)
            ->map(function ($education) use ($locale) {
                $translation = $education->translations->firstWhere('locale', $locale)
                    ?? $education->translations->firstWhere('locale', 'ar')
                    ?? $education->translations->first();

                return new EducationDTO(
                    degree: $translation->degree ?? '',
                    institution: $translation->institution,
                    fieldOfStudy: $translation->field_of_study,
                    yearStart: $translation->year_start,
                    yearEnd: $translation->year_end,
                    description: $translation->description,
                );
            })
            ->values()
            ->all();
    }

    /** @param Collection<int, \App\Models\Person\FacultyMemberEducation> $educations @return array<int, EducationDTO> */
    private function mapFacultyEducations(Collection $educations, string $locale): array
    {
        return $educations
            ->filter(fn ($edu) => $edu->is_enabled)
            ->map(function ($education) use ($locale) {
                $translation = $education->translations->firstWhere('locale', $locale)
                    ?? $education->translations->firstWhere('locale', 'ar')
                    ?? $education->translations->first();

                return new EducationDTO(
                    degree: $translation->degree ?? '',
                    institution: $translation->institution,
                    fieldOfStudy: $translation->field_of_study,
                    yearStart: $translation->year_start,
                    yearEnd: $translation->year_end,
                    description: $translation->description,
                );
            })
            ->values()
            ->all();
    }

    /** @param Collection<int, ResearchPublication> $publications @return array<int, array<string, mixed>> */
    private function mapPublications(Collection $publications, string $locale): array
    {
        return $publications
            ->filter(fn ($pub) => $pub->is_enabled)
            ->map(function (ResearchPublication $publication) use ($locale) {
                $translation = $publication->translations->firstWhere('locale', $locale)
                    ?? $publication->translations->firstWhere('locale', 'ar')
                    ?? $publication->translations->first();

                return [
                    'id' => (int) $publication->getKey(),
                    'title' => $translation->title ?? '',
                    'excerpt' => $translation->excerpt,
                    'publisher' => $translation->publisher,
                    'year' => $publication->published_at?->year,
                    'publishedAt' => $publication->published_at?->toDateString(),
                    'externalUrl' => $publication->external_url,
                ];
            })
            ->values()
            ->all();
    }

    /** @param Collection<int, \App\Models\Person\CouncilMember> $memberships @return array<int, array<string, mixed>> */
    private function mapCouncilMemberships(Collection $memberships, string $locale): array
    {
        return $memberships
            ->filter(fn ($m) => $m->is_enabled)
            ->map(function ($membership) use ($locale) {
                $translation = $membership->translations->firstWhere('locale', $locale)
                    ?? $membership->translations->firstWhere('locale', 'ar')
                    ?? $membership->translations->first();

                $councilTranslation = null;
                if ($membership->council) {
                    $councilTranslation = $membership->council->translations->firstWhere('locale', $locale)
                        ?? $membership->council->translations->firstWhere('locale', 'ar')
                        ?? $membership->council->translations->first();
                }

                return [
                    'councilName' => $councilTranslation?->name ?? '',
                    'position' => $translation->position,
                    'bio' => $translation->bio,
                ];
            })
            ->values()
            ->all();
    }
}
