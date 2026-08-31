<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Content\ProfilePageDTO;

interface ProfilePageServiceInterface
{
    public function getProfile(string $locale, string $source, string $slug): ?ProfilePageDTO;

    /**
     * Whether a public profile exists for the slug, without building one.
     *
     * Callers that only need to know whether a link is safe to render should
     * use this rather than testing getProfile() for null: that path eager-loads
     * nine relations to answer a question two existence queries can answer.
     */
    public function hasPublicProfile(string $slug): bool;

    /**
     * Whether any public profile exists at all.
     *
     * getPublicProfiles() hydrates every profile it finds, so calling it just to
     * test the result for emptiness builds the entire directory to answer yes or no.
     */
    public function hasAnyPublicProfile(): bool;

    /** @return array<int, ProfilePageDTO> */
    public function getPublicProfiles(string $locale): array;

    public function resolveLegacyProfile(string $locale, string $identifier): ?ProfilePageDTO;
}
