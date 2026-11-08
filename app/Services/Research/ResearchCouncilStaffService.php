<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Contracts\Research\ResearchCouncilStaffServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Support\AcademicRank;
use App\Support\MediaUrlResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reads only the columns the council needs from the staff profiles: these tables
 * are wide (bios, social links, legacy paths), so nothing here selects *.
 */
final class ResearchCouncilStaffService implements ResearchCouncilStaffServiceInterface
{
    public function staffOptions(string $locale): array
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $options = [];

        $members = FacultyMember::query()
            ->select(['id', 'slug'])
            ->with(['translations' => fn (HasMany $query) => $query->select(['id', 'faculty_member_id', 'locale', 'full_name'])])
            ->get();

        foreach ($members as $member) {
            $name = trim((string) ($member->translations->firstWhere('locale', $locale)?->full_name
                ?? $member->translations->firstWhere('locale', $locale === 'ar' ? 'en' : 'ar')?->full_name
                ?? ''));
            $options[(int) $member->getKey()] = $name !== '' ? $name : (string) $member->slug;
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public function staffCards(array $ids, string $locale, bool $publicOnly = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $locale = $locale === 'ar' ? 'ar' : 'en';
        $query = FacultyMember::query()
            ->select(['id', 'slug', 'email', 'photo_media_id', 'legacy_photo_path', 'is_enabled', 'publication_status', 'published_at'])
            ->whereKey($ids)
            ->with([
                'translations' => fn (HasMany $query) => $query->select(['id', 'faculty_member_id', 'locale', 'full_name', 'position']),
                'photoMedia' => fn (BelongsTo $query) => $query->select(['id', 'path', 'webp_path', 'disk']),
            ]);

        if ($publicOnly) {
            $query->public();
        }

        $cards = [];
        foreach ($query->get() as $member) {
            $translation = $member->translations->firstWhere('locale', $locale)
                ?? $member->translations->firstWhere('locale', $locale === 'ar' ? 'en' : 'ar');
            $name = trim((string) ($translation?->full_name ?? ''));
            if ($name === '') {
                continue;
            }

            $cards[(int) $member->getKey()] = [
                'name' => $name,
                'rank' => trim((string) (AcademicRank::ofProfile($member->translations, $locale)['rank'] ?? '')),
                'image' => $member->photoMedia instanceof MediaAsset
                    ? MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk)
                    : MediaUrlResolver::resolveLegacy($member->legacy_photo_path),
                'email' => trim((string) ($member->email ?? '')),
                'url' => '/'.$locale.'/about/profile/'.$member->slug,
                'isPublic' => (bool) $member->is_enabled
                    && $member->publication_status === PublicationStatus::Published->value
                    && $member->published_at !== null
                    && $member->published_at->lte(now()),
            ];
        }

        return $cards;
    }
}
