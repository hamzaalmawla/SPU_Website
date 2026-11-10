<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Content\PersonIdentityServiceInterface;
use App\DTOs\Content\PersonIdentityDTO;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use App\Support\MediaUrlResolver;

final class PersonIdentityService implements PersonIdentityServiceInterface
{
    public function profileMemberIds(array $personIds): array
    {
        $personIds = array_values(array_unique(array_map('intval', $personIds)));

        if ($personIds === []) {
            return [];
        }

        $direct = Person::withTrashed()->whereKey($personIds)->whereNotNull('faculty_member_id')->pluck('faculty_member_id', 'id')->all();
        // The older link, until people:unify has filled persons.faculty_member_id.
        $legacy = FacultyMember::query()
            ->whereIn('person_id', array_values(array_diff($personIds, array_keys($direct))))
            ->orderBy('updated_at')
            ->pluck('id', 'person_id')
            ->all();

        $result = [];
        foreach ($personIds as $personId) {
            $memberId = $direct[$personId] ?? $legacy[$personId] ?? null;
            if ($memberId !== null) {
                $result[$personId] = (int) $memberId;
            }
        }

        return $result;
    }

    public function publicIdentities(array $personIds, string $locale): array
    {
        $memberIds = $this->profileMemberIds($personIds);

        if ($memberIds === []) {
            return [];
        }

        $members = FacultyMember::query()
            ->public()
            ->whereKey(array_values(array_unique($memberIds)))
            ->whereHas('translations', fn ($query) => $query->whereIn('locale', ['ar', 'en']))
            ->with(['translations', 'photoMedia'])
            ->get()
            ->keyBy('id');
        $persons = Person::query()->whereKey(array_keys($memberIds))->with(['translations', 'photoMedia'])->get()->keyBy('id');

        $identities = [];
        foreach ($memberIds as $personId => $memberId) {
            $member = $members->get($memberId);
            $person = $persons->get($personId);

            if ($member instanceof FacultyMember && $person instanceof Person) {
                $identities[$personId] = $this->identity($person, $member, $locale);
            }
        }

        return $identities;
    }

    public function personIdsForMember(int $memberId): array
    {
        $legacyPersonId = FacultyMember::query()->whereKey($memberId)->value('person_id');

        return Person::query()
            ->where(fn ($query) => $query
                ->where('faculty_member_id', $memberId)
                ->when($legacyPersonId !== null, fn ($inner) => $inner->orWhere(fn ($legacy) => $legacy->whereKey((int) $legacyPersonId)->whereNull('faculty_member_id'))))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function identity(Person $person, FacultyMember $member, string $locale): PersonIdentityDTO
    {
        // The profile's own locale first, then the leadership row's, then any
        // other locale of the profile: an English page keeps an English name
        // the profile lacks until people:unify has copied it over.
        $memberExact = $member->translations->firstWhere('locale', $locale);
        $personExact = $person->translations->firstWhere('locale', $locale);
        $memberAny = $member->translations->firstWhere('locale', 'ar') ?? $member->translations->firstWhere('locale', 'en');
        $personAny = $person->translations->firstWhere('locale', 'ar') ?? $person->translations->firstWhere('locale', 'en');

        $pick = function (string $memberField, string $personField) use ($memberExact, $personExact, $memberAny, $personAny): ?string {
            foreach ([[$memberExact, $memberField], [$personExact, $personField], [$memberAny, $memberField], [$personAny, $personField]] as [$translation, $field]) {
                $value = $translation instanceof FacultyMemberTranslation || $translation instanceof PersonTranslation ? $translation->getAttribute($field) : null;
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }

            return null;
        };

        $specializations = $memberExact?->specializations ?? $memberAny?->specializations ?? $personExact?->specializations ?? [];

        return new PersonIdentityDTO(
            personId: (int) $person->getKey(),
            memberId: (int) $member->getKey(),
            slug: (string) $member->slug,
            name: (string) $pick('full_name', 'name'),
            title: $pick('title', 'title'),
            position: $pick('position', 'position'),
            bio: $pick('bio', 'bio'),
            image: $this->memberImage($member) ?? $this->personImage($person),
            email: $this->filled($member->email) ?? $this->filled($person->email),
            phone: $this->filled($member->phone) ?? $this->filled($person->phone),
            specializations: is_array($specializations) ? array_values(array_filter(array_map(fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', $specializations))) : [],
            profileUrl: '/'.$locale.'/about/profile/'.$member->slug,
        );
    }

    private function memberImage(FacultyMember $member): ?string
    {
        if ($member->photoMedia instanceof MediaAsset) {
            return MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk);
        }

        return MediaUrlResolver::resolveLegacy($member->legacy_photo_path);
    }

    private function personImage(Person $person): ?string
    {
        if ($person->photoMedia instanceof MediaAsset) {
            return MediaUrlResolver::resolveImage($person->photoMedia->webp_path, $person->photoMedia->path, $person->photoMedia->disk);
        }

        return $this->filled($person->image) ?? MediaUrlResolver::resolveLegacy($person->legacy_photo_path);
    }

    private function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
