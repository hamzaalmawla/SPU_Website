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
use App\Models\Person\FacultyMemberEducationTranslation;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonAppointment;
use App\Models\Person\PersonAppointmentTranslation;
use App\Models\Person\PersonEducation;
use App\Models\Person\PersonEducationTranslation;
use App\Models\Person\PersonTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Shared\MigrationLog;
use App\Support\MediaUrlResolver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class ProfilePageService implements ProfilePageServiceInterface
{
    public function getProfile(string $locale, string $source, string $slug): ?ProfilePageDTO
    {
        if ($source === 'person') {
            $person = $this->findPerson($locale, $slug);

            return $person instanceof Person ? $this->buildPersonProfileDto($person, $locale) : null;
        }

        if ($source === 'faculty-member') {
            $facultyMember = $this->findFacultyMember($locale, $slug);

            return $facultyMember instanceof FacultyMember
                ? $this->buildFacultyMemberProfileDto($facultyMember, $locale)
                : null;
        }

        return $this->resolveUnifiedProfile($locale, $slug);
    }

    /**
     * Mirrors what getProfile() treats as a resolvable profile: a public Person,
     * or failing that a public FacultyMember, with this slug AND a translation
     * the builders can actually use — answered with indexed existence queries
     * rather than two hydrations of nine eager-loaded relations each.
     *
     * The translation clause is not defensive padding. buildPersonProfileDto()
     * and buildFacultyMemberProfileDto() both return null when no translation
     * resolves, and fallbackLocales() always tries the request locale, then ar,
     * then en. A public row carrying neither an ar nor an en translation is
     * therefore a profile that exists in the database and 404s on the web.
     * Without this clause the navigation would render a link to it and the
     * sitemap would publish its URL — the precise defect the availability check
     * exists to prevent.
     *
     * Locale plays no part beyond that: it selects which translation is shown,
     * never whether one resolves, because the fallback covers both locales.
     */
    public function hasPublicProfile(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        return Person::query()->public()->where('slug', $slug)->whereHas(
            'translations',
            fn ($query) => $query->whereIn('locale', ['ar', 'en']),
        )->exists()
            || FacultyMember::query()->public()->where('slug', $slug)->whereHas(
                'translations',
                fn ($query) => $query->whereIn('locale', ['ar', 'en']),
            )->exists();
    }

    public function hasAnyPublicProfile(): bool
    {
        return Person::query()->public()->whereHas(
            'translations',
            fn ($query) => $query->whereIn('locale', ['ar', 'en']),
        )->exists()
            || FacultyMember::query()->public()->whereHas(
                'translations',
                fn ($query) => $query->whereIn('locale', ['ar', 'en']),
            )->exists();
    }

    /** @return array<int, ProfilePageDTO> */
    public function getPublicProfiles(string $locale): array
    {
        $slugs = Person::query()
            ->public()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('slug')
            ->merge(
                FacultyMember::query()
                    ->public()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->pluck('slug'),
            )
            ->unique()
            ->values();

        $profiles = [];

        foreach ($slugs as $slug) {
            $profile = $this->resolveUnifiedProfile($locale, (string) $slug);

            if ($profile instanceof ProfilePageDTO) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    public function resolveLegacyProfile(string $locale, string $identifier): ?ProfilePageDTO
    {
        return $this->resolveUnifiedProfile($locale, $identifier);
    }

    private function resolveUnifiedProfile(string $locale, string $slug): ?ProfilePageDTO
    {
        $person = $this->findPerson($locale, $slug);

        if ($person instanceof Person) {
            return $this->buildPersonProfileDto($person, $locale);
        }

        $facultyMember = $this->findFacultyMember($locale, $slug);

        return $facultyMember instanceof FacultyMember
            ? $this->buildFacultyMemberProfileDto($facultyMember, $locale)
            : null;
    }

    private function findPerson(string $locale, string $slug): ?Person
    {
        return Person::query()
            ->public()
            ->where('slug', $slug)
            ->with([
                'translations' => fn ($query) => $query->whereIn('locale', $this->fallbackLocales($locale)),
                'educations' => fn ($query) => $query->enabled(),
                'educations.translations',
                'researchPublications' => fn ($query) => $query->public()->limit(20),
                'researchPublications.translations',
                'councilMemberships' => fn ($query) => $query->enabled()->whereHas('council', fn ($councilQuery) => $councilQuery->enabled()),
                'councilMemberships.translations',
                'councilMemberships.council.translations',
                'photoMedia',
                'cvMedia',
            ])
            ->first();
    }

    private function findFacultyMember(string $locale, string $slug): ?FacultyMember
    {
        return FacultyMember::query()
            ->public()
            ->where('slug', $slug)
            ->with([
                'translations' => fn ($query) => $query->whereIn('locale', $this->fallbackLocales($locale)),
                'faculty.translations',
                'department.translations',
                'educations' => fn ($query) => $query->enabled(),
                'educations.translations',
                'researchPublications' => fn ($query) => $query->public()->limit(20),
                'researchPublications.translations',
                'councilMemberships' => fn ($query) => $query->enabled()->whereHas('council', fn ($councilQuery) => $councilQuery->enabled()),
                'councilMemberships.translations',
                'councilMemberships.council.translations',
                'photoMedia',
                'cvMedia',
            ])
            ->first();
    }

    private function buildPersonProfileDto(Person $person, string $locale): ?ProfilePageDTO
    {
        $translation = $this->personTranslation($person, $locale);
        if (! $translation instanceof PersonTranslation) {
            return null;
        }

        $facultyAppointment = $this->primaryFacultyAppointment($person->appointments);
        $facultyName = $this->appointmentFacultyName($facultyAppointment, $locale);
        $departmentName = $this->appointmentDepartmentName($facultyAppointment, $locale);

        $leadershipAppointment = $this->primaryLeadershipAppointment($person->appointments);
        $position = $translation->position ?? $translation->role ?? '';
        if ($leadershipAppointment instanceof PersonAppointment) {
            $aptTrans = $this->appointmentTranslation($leadershipAppointment, $locale);
            if ($aptTrans instanceof PersonAppointmentTranslation && $aptTrans->role_override) {
                $position = $aptTrans->role_override;
            }
        }

        return new ProfilePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sourceType: 'person',
            slug: (string) $person->slug,
            name: (string) $translation->name,
            title: $translation->title ?? $person->title,
            position: $position,
            category: $leadershipAppointment?->type ?? $person->category,
            facultyName: $facultyName,
            departmentName: $departmentName,
            email: $person->email,
            phone: $person->phone,
            image: $this->resolvePersonImage($person),
            bio: $translation->bio,
            quote: $translation->quote,
            specializations: $this->normalizeStringList($translation->specializations),
            officeLocation: $person->office_location,
            socialLinks: $this->personSocialLinks($person),
            educations: $this->mapPersonEducations($person->educations, $locale),
            publications: $this->mapPublications($person->researchPublications, $locale),
            councilMemberships: $this->mapCouncilMemberships($person->councilMemberships, $locale),
            cvUrl: $this->resolveCvUrl($person),
            profileUrl: '/'.$locale.'/about/profile/'.$person->slug,
            seoTitle: (string) $translation->name.' - '.config('app.name', 'SPU'),
            seoDescription: $translation->bio ?? (string) $translation->name,
            seoImage: $this->resolvePersonImage($person),
            path: '/'.$locale.'/about/profile/'.$person->slug,
        );
    }

    private function buildFacultyMemberProfileDto(FacultyMember $member, string $locale): ?ProfilePageDTO
    {
        $translation = $this->facultyMemberTranslation($member, $locale);
        if (! $translation instanceof FacultyMemberTranslation) {
            return null;
        }

        $facultyName = null;
        if ($member->faculty instanceof Faculty) {
            $facultyTranslation = $member->faculty->translations->firstWhere('locale', $locale)
                ?? $member->faculty->translations->firstWhere('locale', 'ar')
                ?? $member->faculty->translations->first();
            $facultyName = $facultyTranslation?->name;
        }

        $departmentName = null;
        if ($member->department instanceof Department) {
            $deptTranslation = $member->department->translations->firstWhere('locale', $locale)
                ?? $member->department->translations->firstWhere('locale', 'ar')
                ?? $member->department->translations->first();
            $departmentName = $deptTranslation?->name;
        }

        return new ProfilePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sourceType: 'faculty_member',
            slug: (string) $member->slug,
            name: (string) $translation->full_name,
            title: $translation->title,
            position: $translation->position,
            category: 'faculty_member',
            facultyName: $facultyName,
            departmentName: $departmentName,
            email: $member->email,
            phone: $member->phone,
            image: $this->resolveFacultyMemberImage($member),
            bio: $translation->bio,
            quote: null,
            specializations: $this->normalizeStringList($translation->specializations),
            officeLocation: $member->office_location,
            socialLinks: $this->safeExternalLinks($member->social_links),
            educations: $this->mapFacultyMemberEducations($member->educations, $locale),
            publications: $this->mapPublications($member->researchPublications, $locale),
            councilMemberships: $this->mapCouncilMemberships($member->councilMemberships, $locale),
            cvUrl: $this->resolveFacultyMemberCvUrl($member),
            profileUrl: '/'.$locale.'/about/profile/'.$member->slug,
            seoTitle: (string) $translation->full_name.' - '.config('app.name', 'SPU'),
            seoDescription: $translation->bio ?? (string) $translation->full_name,
            seoImage: $this->resolveFacultyMemberImage($member),
            path: '/'.$locale.'/about/profile/'.$member->slug,
        );
    }

    private function personTranslation(Person $person, string $locale): ?PersonTranslation
    {
        return $person->translations->firstWhere('locale', $locale)
            ?? $person->translations->firstWhere('locale', 'ar')
            ?? $person->translations->firstWhere('locale', 'en');
    }

    private function primaryFacultyAppointment(Collection $appointments): ?PersonAppointment
    {
        return $appointments->firstWhere('type', 'faculty_member');
    }

    private function primaryLeadershipAppointment(Collection $appointments): ?PersonAppointment
    {
        $types = ['rector', 'vice_president', 'dean', 'council', 'director'];
        foreach ($types as $type) {
            $apt = $appointments->firstWhere('type', $type);
            if ($apt instanceof PersonAppointment) {
                return $apt;
            }
        }

        return null;
    }

    private function appointmentTranslation(PersonAppointment $appointment, string $locale): ?PersonAppointmentTranslation
    {
        return $appointment->translations->firstWhere('locale', $locale)
            ?? $appointment->translations->firstWhere('locale', 'ar')
            ?? $appointment->translations->firstWhere('locale', 'en');
    }

    private function appointmentFacultyName(?PersonAppointment $appointment, string $locale): ?string
    {
        if (! $appointment instanceof PersonAppointment || ! $appointment->faculty instanceof Faculty) {
            return null;
        }

        $translation = $appointment->faculty->translations->firstWhere('locale', $locale)
            ?? $appointment->faculty->translations->firstWhere('locale', 'ar')
            ?? $appointment->faculty->translations->first();

        return $translation?->name;
    }

    private function appointmentDepartmentName(?PersonAppointment $appointment, string $locale): ?string
    {
        if (! $appointment instanceof PersonAppointment || ! $appointment->department instanceof Department) {
            return null;
        }

        $translation = $appointment->department->translations->firstWhere('locale', $locale)
            ?? $appointment->department->translations->firstWhere('locale', 'ar')
            ?? $appointment->department->translations->first();

        return $translation?->name;
    }

    private function resolvePersonImage(Person $person): ?string
    {
        if ($person->photoMedia instanceof MediaAsset) {
            return MediaUrlResolver::resolveImage($person->photoMedia->webp_path, $person->photoMedia->path, $person->photoMedia->disk);
        }

        if (is_string($person->image) && $person->image !== '') {
            return $person->image;
        }

        return MediaUrlResolver::resolveLegacy($person->legacy_photo_path);
    }

    private function resolveCvUrl(Person $person): ?string
    {
        if ($person->cvMedia instanceof MediaAsset) {
            return MediaUrlResolver::resolve($person->cvMedia->path, $person->cvMedia->disk);
        }

        return MediaUrlResolver::resolveLegacy($person->legacy_cv_path ?? $person->legacy_ar_cv_path);
    }

    /** @param Collection<int, PersonEducation> $educations @return array<int, EducationDTO> */
    private function mapPersonEducations(Collection $educations, string $locale): array
    {
        return $educations
            ->map(function ($education) use ($locale) {
                $translation = $education->translations->firstWhere('locale', $locale)
                    ?? $education->translations->firstWhere('locale', 'ar')
                    ?? $education->translations->firstWhere('locale', 'en');

                if (! $translation instanceof PersonEducationTranslation) {
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
                    'slug' => $this->publicationSlug($publication),
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

    private function publicationSlug(ResearchPublication $publication): string
    {
        $translations = $publication->translations->keyBy('locale');
        $title = (string) (($translations->get('en')?->title ?? null)
            ?: ($translations->get('ar')?->title ?? null)
            ?: 'research-publication');
        $sourceId = $publication->legacy_source_id;

        if ($sourceId === null) {
            $sourceId = MigrationLog::query()
                ->where('module', 'research')
                ->where('source_table', 'jx_member_categories')
                ->where('target_table', 'research_publications')
                ->where('status', 'success')
                ->where('target_id', $publication->getKey())
                ->value('source_id');
        }

        return (Str::slug($title) ?: 'research-publication').'-'.((int) ($sourceId ?? $publication->getKey()));
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

    /** @return array<string, string>|null */
    private function personSocialLinks(Person $person): ?array
    {
        $links = is_array($person->social_links) ? $person->social_links : [];
        if (is_string($person->orcid_url) && $person->orcid_url !== '') {
            $links['orcid'] = $person->orcid_url;
        }
        if (is_string($person->scholar_url) && $person->scholar_url !== '') {
            $links['scholar'] = $person->scholar_url;
        }

        return $this->safeExternalLinks($links);
    }

    private function facultyMemberTranslation(FacultyMember $member, string $locale): ?FacultyMemberTranslation
    {
        return $member->translations->firstWhere('locale', $locale)
            ?? $member->translations->firstWhere('locale', 'ar')
            ?? $member->translations->firstWhere('locale', 'en');
    }

    private function resolveFacultyMemberImage(FacultyMember $member): ?string
    {
        if ($member->photoMedia instanceof MediaAsset) {
            return MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk);
        }

        return MediaUrlResolver::resolveLegacy($member->legacy_photo_path);
    }

    private function resolveFacultyMemberCvUrl(FacultyMember $member): ?string
    {
        if ($member->cvMedia instanceof MediaAsset) {
            return MediaUrlResolver::resolve($member->cvMedia->path, $member->cvMedia->disk);
        }

        return MediaUrlResolver::resolveLegacy($member->legacy_cv_path ?? $member->legacy_ar_cv_path);
    }

    /** @param Collection<int, FacultyMemberEducation> $educations @return array<int, EducationDTO> */
    private function mapFacultyMemberEducations(Collection $educations, string $locale): array
    {
        return $educations
            ->map(function ($education) use ($locale) {
                $translation = $education->translations->firstWhere('locale', $locale)
                    ?? $education->translations->firstWhere('locale', 'ar')
                    ?? $education->translations->firstWhere('locale', 'en');

                if (! $translation instanceof FacultyMemberEducationTranslation) {
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
}
