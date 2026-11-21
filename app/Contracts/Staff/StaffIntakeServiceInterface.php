<?php

declare(strict_types=1);

namespace App\Contracts\Staff;

use App\DTOs\Staff\StaffIntakeFormDTO;
use App\DTOs\Staff\StaffIntakeHeldFileDTO;
use App\DTOs\Staff\StaffIntakeLinkDTO;
use App\DTOs\Staff\StaffIntakeSubmissionDTO;
use App\DTOs\Staff\StaffIntakeSubmitResultDTO;
use App\DTOs\Staff\StaffMatchSuggestionDTO;
use Illuminate\Http\UploadedFile;
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

    /**
     * Keeps the photo and CV of a try that came back (a missing field, or a
     * possible duplicate) so the teacher need not pick them again: each file
     * given is stored aside and its handle returned, keyed `photo_held` /
     * `cv_held` (the form's hidden fields). Valid handles of earlier tries
     * pass through while no new file replaces them. Nothing for a closed link.
     *
     * @param  array<string, mixed>  $previous  the try's `photo_held` / `cv_held` values
     * @return array<string, string>
     */
    public function holdUploads(string $token, ?UploadedFile $photo, ?UploadedFile $cv, array $previous = []): array;

    /**
     * The kept files behind the form's held handles, shown as "attached"
     * instead of an empty file field. Unknown or expired handles are left out.
     *
     * @param  array<string, mixed>  $handles  `photo_held` / `cv_held` values
     * @return array<string, StaffIntakeHeldFileDTO> keyed by kind (photo, cv)
     */
    public function heldFiles(string $token, array $handles): array;
}
