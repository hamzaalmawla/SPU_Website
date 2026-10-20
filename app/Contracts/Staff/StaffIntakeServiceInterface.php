<?php

declare(strict_types=1);

namespace App\Contracts\Staff;

use App\DTOs\Staff\StaffIntakeFormDTO;
use App\DTOs\Staff\StaffIntakeLinkDTO;
use App\DTOs\Staff\StaffIntakeSubmissionDTO;
use App\DTOs\Staff\StaffIntakeSubmitResultDTO;
use App\DTOs\Staff\StaffMatchSuggestionDTO;
use Illuminate\Support\Collection;

/**
 * The public side of a staff intake link: name suggestions, the prefilled
 * form, and saving what the teacher sends as a request for review. Nothing
 * here changes a profile.
 */
interface StaffIntakeServiceInterface
{
    /** The link behind a token, only while it is open (not revoked, expired or full). */
    public function resolveLink(string $token): ?StaffIntakeLinkDTO;

    public function recordOpened(string $token, ?string $ipAddress, ?string $userAgent): bool;

    /** @return Collection<int, StaffMatchSuggestionDTO> */
    public function suggestions(string $token, string $typedName, string $locale): Collection;

    /**
     * The form for a profile picked from the suggestions ($ref), or a blank
     * one for someone not found ($newName). Null when the link or ref is not valid.
     */
    public function form(string $token, ?string $ref, ?string $newName, string $locale, ?string $ipAddress, ?string $userAgent): ?StaffIntakeFormDTO;

    public function submit(StaffIntakeSubmissionDTO $submission): StaffIntakeSubmitResultDTO;
}
