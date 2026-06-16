<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\AboutPageServiceInterface;
use App\DTOs\About\AboutContentPageDTO;
use App\DTOs\About\AboutLandingDTO;
use App\DTOs\Content\DirectorateDTO;
use App\DTOs\Content\PartnershipDTO;
use App\DTOs\Content\PersonDTO;
use App\Models\Content\Directorate;
use App\Models\Content\DirectorateTranslation;
use App\Models\Content\Partnership;
use App\Models\Content\PartnershipTranslation;
use App\Models\Page\AboutPage;
use App\Models\Page\AboutPageTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use Illuminate\Support\Collection;

final class AboutPageService implements AboutPageServiceInterface
{
    public function getAboutLanding(string $locale): AboutLandingDTO
    {
        $page = AboutPage::query()
            ->public()
            ->where('slug', 'about')
            ->with('translations')
            ->firstOrFail();
        $translation = $this->aboutTranslation($page, $locale);
        $payload = is_array($page->payload_json) ? $page->payload_json : [];

        return new AboutLandingDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            title: (string) $translation->title,
            headline: (string) ($translation->headline ?: $translation->title),
            summary: (string) ($translation->summary ?? ''),
            quote: (string) ($payload[$locale]['quote'] ?? ''),
            description: (string) ($payload[$locale]['description'] ?? ''),
            badge: (string) ($payload[$locale]['badge'] ?? $translation->title),
            imagePrimary: (string) ($payload['images']['primary'] ?? '/images/about-hero-1.webp'),
            imageSecondary: (string) ($payload['images']['secondary'] ?? '/images/about-hero-2.webp'),
            stats: $this->localizedArray($payload['stats'] ?? [], $locale),
            storyItems: $this->localizedArray($payload['story_items'] ?? [], $locale),
            highlights: $this->localizedArray($payload['highlights'] ?? [], $locale),
            subPages: $this->localizedArray($payload['sub_pages'] ?? [], $locale),
        );
    }

    public function getContentPage(string $slug, string $locale): ?AboutContentPageDTO
    {
        $page = AboutPage::query()
            ->public()
            ->where('slug', $slug)
            ->with('translations')
            ->first();

        if (! $page instanceof AboutPage) {
            return null;
        }

        $translation = $this->aboutTranslation($page, $locale);

        return new AboutContentPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            slug: (string) $page->slug,
            title: (string) $translation->title,
            headline: (string) ($translation->headline ?: $translation->title),
            summary: (string) ($translation->summary ?? ''),
            heroImage: (string) ($page->hero_image ?: '/images/about-hero-2.webp'),
            sections: is_array($translation->sections_json) ? $translation->sections_json : [],
        );
    }

    public function getLeadershipProfiles(string $locale): Collection
    {
        return $this->mapPersons(
            Person::query()->enabled()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    public function getDirectorates(string $locale): Collection
    {
        return $this->mapDirectorates(
            Directorate::query()->enabled()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    public function getDirectorate(string $slug, string $locale): ?DirectorateDTO
    {
        $directorate = Directorate::query()->enabled()->where('slug', $slug)->with('translations')->first();

        if (! $directorate instanceof Directorate) {
            return null;
        }

        return $this->mapDirectorate($directorate, $locale);
    }

    public function getPartnerships(string $locale): Collection
    {
        return $this->mapPartnerships(
            Partnership::query()->enabled()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    public function mapPerson(Person $person, string $locale): PersonDTO
    {
        $translation = $this->personTranslation($person, $locale);

        return new PersonDTO(
            id: (int) $person->getKey(),
            name: (string) $translation->name,
            role: (string) $translation->role,
            category: $person->category,
            facultySlug: $person->faculty_scope_slug,
            bio: $translation->bio,
            quote: $translation->quote,
            image: $person->image,
            email: $person->email,
            profileUrl: $person->profile_url,
        );
    }

    /** @param Collection<int, Person> $persons @return Collection<int, PersonDTO> */
    private function mapPersons(Collection $persons, string $locale): Collection
    {
        return $persons->map(fn (Person $person): PersonDTO => $this->mapPerson($person, $locale))->values();
    }

    /** @param Collection<int, Directorate> $directorates @return Collection<int, DirectorateDTO> */
    private function mapDirectorates(Collection $directorates, string $locale): Collection
    {
        return $directorates->map(fn (Directorate $directorate): DirectorateDTO => $this->mapDirectorate($directorate, $locale))->values();
    }

    private function mapDirectorate(Directorate $directorate, string $locale): DirectorateDTO
    {
        $translation = $this->directorateTranslation($directorate, $locale);

        return new DirectorateDTO(
            id: (int) $directorate->getKey(),
            slug: (string) $directorate->slug,
            title: (string) $translation->title,
            summary: (string) ($translation->summary ?? ''),
            description: (string) ($translation->description ?? ''),
            services: is_array($translation->services_json) ? $translation->services_json : [],
            icon: $directorate->icon,
            email: $directorate->email,
            location: $directorate->location,
        );
    }

    /** @param Collection<int, Partnership> $partnerships @return Collection<int, PartnershipDTO> */
    private function mapPartnerships(Collection $partnerships, string $locale): Collection
    {
        return $partnerships->map(function (Partnership $partnership) use ($locale): PartnershipDTO {
            $translation = $this->partnershipTranslation($partnership, $locale);

            return new PartnershipDTO(
                id: (int) $partnership->getKey(),
                name: (string) $translation->name,
                category: (string) ($translation->category ?? ''),
                status: (string) ($translation->status ?? ''),
                establishedLabel: (string) ($translation->established_label ?? ''),
                description: (string) ($translation->description ?? ''),
                logo: $partnership->logo,
                websiteUrl: $partnership->website_url,
                scope: $translation->scope,
                signedAt: $partnership->signed_at?->toDateString(),
            );
        })->values();
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, string>> */
    private function localizedArray(array $items, string $locale): array
    {
        return collect($items)->map(static function (array $item) use ($locale): array {
            $localized = [];

            foreach ($item as $key => $value) {
                if (! is_string($value) && ! is_int($value)) {
                    continue;
                }

                if (str_ends_with((string) $key, '_ar') || str_ends_with((string) $key, '_en')) {
                    continue;
                }

                $localized[(string) $key] = (string) $value;
            }

            foreach ($item as $key => $value) {
                if (! is_string($value) && ! is_int($value)) {
                    continue;
                }

                $suffix = '_'.$locale;
                if (str_ends_with((string) $key, $suffix)) {
                    $localized[substr((string) $key, 0, -strlen($suffix))] = (string) $value;
                }
            }

            return $localized;
        })->values()->all();
    }

    private function aboutTranslation(AboutPage $page, string $locale): AboutPageTranslation
    {
        return $page->translations->firstWhere('locale', $locale)
            ?? $page->translations->firstWhere('locale', 'ar')
            ?? $page->translations->first();
    }

    private function personTranslation(Person $person, string $locale): PersonTranslation
    {
        return $person->translations->firstWhere('locale', $locale)
            ?? $person->translations->firstWhere('locale', 'ar')
            ?? $person->translations->first();
    }

    private function directorateTranslation(Directorate $directorate, string $locale): DirectorateTranslation
    {
        return $directorate->translations->firstWhere('locale', $locale)
            ?? $directorate->translations->firstWhere('locale', 'ar')
            ?? $directorate->translations->first();
    }

    private function partnershipTranslation(Partnership $partnership, string $locale): PartnershipTranslation
    {
        return $partnership->translations->firstWhere('locale', $locale)
            ?? $partnership->translations->firstWhere('locale', 'ar')
            ?? $partnership->translations->first();
    }
}
