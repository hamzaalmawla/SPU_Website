<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\ProfilePageServiceInterface;
use App\DTOs\Content\EducationDTO;
use App\DTOs\Content\ProfilePageDTO;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\CouncilMember;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberEducation;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonEducation;
use App\Models\Person\PersonTranslation;
use App\Models\Research\ResearchPublication;
use App\Support\MediaUrlResolver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class ProfilePageService implements ProfilePageServiceInterface
{
    public function getProfile(string $locale, string $source, string $slug): ?ProfilePageDTO
    {
        return match ($source) {
            'person' => $this->resolvePersonProfile($locale, $slug),
            'faculty-member' => $this->resolveFacultyMemberProfile($locale, $slug),
            default => null,
        };
    }

    private function resolvePersonProfile(string $locale, string $slug): ?ProfilePageDTO
    {
        $person = Person::query()
            ->enabled()
            ->where('slug', $slug)
            ->with([
                'translations' => fn ($query) => $query->whereIn('locale', $this->fallbackLocales($locale)),
                'educations' => fn ($query) => $query->enabled(),
                'educations.translations',
            ])
            ->first();

        if (! $person instanceof Person) {
            return null;
        }

        $translation = $this->personTranslation($person, $locale);
        if (! $translation instanceof PersonTranslation) {
            return null;
        }

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
            socialLinks: $this->safeExternalLinks($person->social_links),
            educations: $this->mapPersonEducations($person->educations, $locale),
            publications: [],
            councilMemberships: [],
            cvUrl: null,
            profileUrl: $person->profile_url,
            seoTitle: (string) $translation->name.' - '.config('app.name', 'SPU'),
            seoDescription: $translation->bio ?? (string) $translation->name,
            seoImage: $person->image,
            path: '/'.$locale.'/about/profile/person/'.$slug,
        );
    }

    private function resolveFacultyMemberProfile(string $locale, string $slug): ?ProfilePageDTO
    {
        $member = FacultyMember::query()
            ->enabled()
            ->where('slug', $slug)
            ->with([
                'translations' => fn ($query) => $query->whereIn('locale', $this->fallbackLocales($locale)),
                'faculty.translations',
                'department.translations',
                'educations' => fn ($query) => $query->enabled(),
                'educations.translations',
                'researchPublications' => fn ($query) => $query->enabled()->limit(20),
                'researchPublications.translations',
                'councilMemberships' => fn ($query) => $query->enabled()->whereHas('council', fn ($councilQuery) => $councilQuery->enabled()),
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
        if (! $translation instanceof FacultyMemberTranslation) {
            return null;
        }

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
            specializations: $this->normalizeStringList($translation->specializations),
            officeLocation: $member->office_location,
            socialLinks: $this->safeExternalLinks($member->social_links),
            educations: $this->mapFacultyEducations($member->educations, $locale),
            publications: $this->mapPublications($member->researchPublications, $locale),
            councilMemberships: $this->mapCouncilMemberships($member->councilMemberships, $locale),
            cvUrl: $this->resolveCvUrl($member),
            profileUrl: null,
            seoTitle: (string) $translation->full_name.' - '.config('app.name', 'SPU'),
            seoDescription: $translation->bio ?? (string) $translation->full_name,
            seoImage: $this->resolveMemberImage($member),
            path: '/'.$locale.'/about/profile/faculty-member/'.$slug,
        );
    }

    private function personTranslation(Person $person, string $locale): ?PersonTranslation
    {
        return $person->translations->firstWhere('locale', $locale)
            ?? $person->translations->firstWhere('locale', 'ar')
            ?? $person->translations->firstWhere('locale', 'en');
    }

    private function facultyTranslation(FacultyMember $member, string $locale): ?FacultyMemberTranslation
    {
        return $member->translations->firstWhere('locale', $locale)
            ?? $member->translations->firstWhere('locale', 'ar')
            ?? $member->translations->firstWhere('locale', 'en');
    }

    private function facultyName(FacultyMember $member, string $locale): ?string
    {
        if (! $member->faculty instanceof Faculty) {
            return null;
        }

        $translation = $member->faculty->translations->firstWhere('locale', $locale)
            ?? $member->faculty->translations->firstWhere('locale', 'ar')
            ?? $member->faculty->translations->first();

        return $translation?->name;
    }

    private function departmentName(FacultyMember $member, string $locale): ?string
    {
        if (! $member->department instanceof Department) {
            return null;
        }

        $translation = $member->department->translations->firstWhere('locale', $locale)
            ?? $member->department->translations->firstWhere('locale', 'ar')
            ?? $member->department->translations->first();

        return $translation?->name;
    }

    private function resolveMemberImage(FacultyMember $member): ?string
    {
        if ($member->photoMedia instanceof MediaAsset) {
            return MediaUrlResolver::resolve($member->photoMedia->path, $member->photoMedia->disk);
        }

        return null;
    }

    private function resolveCvUrl(FacultyMember $member): ?string
    {
        if (! $member->cvMedia instanceof MediaAsset) {
            return null;
        }

        return MediaUrlResolver::resolve($member->cvMedia->path, $member->cvMedia->disk);
    }

    /** @param Collection<int, PersonEducation> $educations @return array<int, EducationDTO> */
    private function mapPersonEducations(Collection $educations, string $locale): array
    {
        return $educations
            ->map(function ($education) use ($locale) {
                $translation = $education->translations->firstWhere('locale', $locale)
                    ?? $education->translations->firstWhere('locale', 'ar')
                    ?? $education->translations->firstWhere('locale', 'en');

                if ($translation === null) {
                    return null;
                }

                return new EducationDTO(
                    degree: $translation->degree,
                    institution: $translation->institution,
                    fieldOfStudy: $translation->field_of_study,
                    yearStart: $translation->year_start,
                    yearEnd: $translation->year_end,
                    description: $translation->description,
                );
            })
            ->filter(fn (mixed $education): bool => $education instanceof EducationDTO)
            ->values()
            ->all();
    }

    /** @param Collection<int, FacultyMemberEducation> $educations @return array<int, EducationDTO> */
    private function mapFacultyEducations(Collection $educations, string $locale): array
    {
        return $educations
            ->map(function ($education) use ($locale) {
                $translation = $education->translations->firstWhere('locale', $locale)
                    ?? $education->translations->firstWhere('locale', 'ar')
                    ?? $education->translations->firstWhere('locale', 'en');

                if ($translation === null) {
                    return null;
                }

                return new EducationDTO(
                    degree: $translation->degree,
                    institution: $translation->institution,
                    fieldOfStudy: $translation->field_of_study,
                    yearStart: $translation->year_start,
                    yearEnd: $translation->year_end,
                    description: $translation->description,
                );
            })
            ->filter(fn (mixed $education): bool => $education instanceof EducationDTO)
            ->values()
            ->all();
    }

    /** @param Collection<int, ResearchPublication> $publications @return array<int, array<string, mixed>> */
    private function mapPublications(Collection $publications, string $locale): array
    {
        return $publications
            ->map(function (ResearchPublication $publication) use ($locale) {
                $translation = $publication->translations->firstWhere('locale', $locale)
                    ?? $publication->translations->firstWhere('locale', 'ar')
                    ?? $publication->translations->firstWhere('locale', 'en');

                if ($translation === null) {
                    return null;
                }

                $publishedAt = $publication->getAttribute('published_at');

                return [
                    'id' => (int) $publication->getKey(),
                    'title' => $translation->title ?? '',
                    'excerpt' => $translation->excerpt,
                    'publisher' => $translation->publisher,
                    'year' => $publishedAt instanceof CarbonInterface ? $publishedAt->year : null,
                    'publishedAt' => $publishedAt instanceof CarbonInterface ? $publishedAt->toDateString() : null,
                    'externalUrl' => $this->safeExternalUrl($publication->external_url),
                ];
            })
            ->filter(fn (mixed $publication): bool => is_array($publication))
            ->values()
            ->all();
    }

    /** @param Collection<int, CouncilMember> $memberships @return array<int, array<string, mixed>> */
    private function mapCouncilMemberships(Collection $memberships, string $locale): array
    {
        return $memberships
            ->map(function ($membership) use ($locale) {
                $translation = $membership->translations->firstWhere('locale', $locale)
                    ?? $membership->translations->firstWhere('locale', 'ar')
                    ?? $membership->translations->firstWhere('locale', 'en');

                if ($translation === null) {
                    return null;
                }

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
            ->filter(fn (mixed $membership): bool => is_array($membership))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function fallbackLocales(string $locale): array
    {
        return array_values(array_unique([$locale, 'ar', 'en']));
    }

    /** @return array<int, string>|null */
    private function normalizeStringList(mixed $values): ?array
    {
        if (is_string($values)) {
            $values = preg_split('/[,;|،\r\n]+/u', $values) ?: [];
        }

        if (! is_array($values)) {
            return null;
        }

        $normalized = collect($values)
            ->map(static function (mixed $value): ?string {
                if (is_array($value)) {
                    $value = $value['name'] ?? null;
                }

                if (! is_string($value)) {
                    return null;
                }

                $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                return $value !== '' ? $value : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : null;
    }

    private function safeExternalUrl(?string $url): ?string
    {
        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /** @return array<string, string>|null */
    private function safeExternalLinks(mixed $links): ?array
    {
        if (! is_array($links)) {
            return null;
        }

        $safeLinks = collect($links)
            ->map(fn (mixed $url): ?string => is_string($url) ? $this->safeExternalUrl($url) : null)
            ->filter()
            ->all();

        return $safeLinks !== [] ? $safeLinks : null;
    }
}
