<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\DTOs\Staff\StaffIntakeLinkDTO;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;
use App\Models\Faculty\Faculty;
use App\Models\Staff\StaffIntakeLink;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StaffIntakeLinkService implements StaffIntakeLinkServiceInterface
{
    public function __construct(
        private readonly ProfileAdminServiceInterface $profiles,
        private readonly AuditServiceInterface $auditService,
    ) {}

    public function create(StaffIntakeLinkInputDTO $input, int $actorId): StaffIntakeLinkCreatedDTO
    {
        if (! array_key_exists($input->facultyId, $this->facultyOptions($actorId))) {
            throw new AuthorizationException('You may only make links for your own faculties.');
        }

        if ($input->expiresAt !== null && $input->expiresAt <= now()) {
            throw ValidationException::withMessages(['expires_at' => __('staff_intake.admin.errors.expiry_in_past')]);
        }

        if ($input->maxSubmissions !== null && $input->maxSubmissions < 1) {
            throw ValidationException::withMessages(['max_submissions' => __('staff_intake.admin.errors.cap_too_small')]);
        }

        $token = self::newToken();
        $label = $input->label !== null && trim($input->label) !== '' ? trim($input->label) : null;
        $link = StaffIntakeLink::query()->create([
            'faculty_id' => $input->facultyId,
            'token_hash' => hash('sha256', $token),
            'label' => $label !== null ? mb_substr($label, 0, 255) : null,
            'expires_at' => $input->expiresAt,
            'max_submissions' => $input->maxSubmissions,
            'created_by' => $actorId,
        ]);

        $this->auditService->log('staff_intake_link.created', $actorId, StaffIntakeLink::class, (int) $link->getKey(), [
            'faculty_id' => $input->facultyId,
            'expires_at' => $input->expiresAt?->format(DATE_ATOM),
            'max_submissions' => $input->maxSubmissions,
        ]);

        return $this->created($link, $token);
    }

    public function rotate(int $linkId, int $actorId): StaffIntakeLinkCreatedDTO
    {
        $link = $this->manageable($linkId, $actorId);
        $token = self::newToken();
        $link->forceFill(['token_hash' => hash('sha256', $token)])->save();

        $this->auditService->log('staff_intake_link.rotated', $actorId, StaffIntakeLink::class, $linkId);

        return $this->created($link, $token);
    }

    public function revoke(int $linkId, int $actorId): bool
    {
        $link = $this->manageable($linkId, $actorId);
        if ($link->revoked_at !== null) {
            return false;
        }

        $link->forceFill(['revoked_at' => now()])->save();
        $this->auditService->log('staff_intake_link.revoked', $actorId, StaffIntakeLink::class, $linkId);

        return true;
    }

    public function facultyOptions(int $actorId): array
    {
        try {
            return $this->profiles->facultyOptions($actorId);
        } catch (AuthorizationException) {
            return [];
        }
    }

    public function canManage(int $linkId, int $actorId): bool
    {
        $facultyId = StaffIntakeLink::query()->whereKey($linkId)->value('faculty_id');

        return $facultyId !== null && array_key_exists((int) $facultyId, $this->facultyOptions($actorId));
    }

    private function manageable(int $linkId, int $actorId): StaffIntakeLink
    {
        if (! $this->canManage($linkId, $actorId)) {
            throw new AuthorizationException('You may only manage links of your own faculties.');
        }

        return StaffIntakeLink::query()->findOrFail($linkId);
    }

    private function created(StaffIntakeLink $link, string $token): StaffIntakeLinkCreatedDTO
    {
        return new StaffIntakeLinkCreatedDTO(
            link: self::toDto($link),
            urlAr: route('public.staff-intake.show', ['locale' => 'ar', 'token' => $token]),
            urlEn: route('public.staff-intake.show', ['locale' => 'en', 'token' => $token]),
        );
    }

    public static function toDto(StaffIntakeLink $link): StaffIntakeLinkDTO
    {
        $faculty = $link->faculty()->with('translations')->first();
        $name = static fn (string $locale): string => $faculty instanceof Faculty
            ? (string) ($faculty->translations->firstWhere('locale', $locale)?->name ?? $faculty->slug)
            : '';

        return new StaffIntakeLinkDTO(
            id: (int) $link->getKey(),
            facultyId: (int) $link->faculty_id,
            facultyNameAr: $name('ar'),
            facultyNameEn: $name('en'),
            label: $link->label,
            expiresAt: $link->expires_at?->toIso8601String(),
            revokedAt: $link->revoked_at?->toIso8601String(),
            maxSubmissions: $link->max_submissions,
            submissionsCount: (int) $link->submissions_count,
            active: $link->isActive(),
            createdBy: $link->created_by !== null ? (int) $link->created_by : null,
        );
    }

    /** 40 random letters and digits: what the route accepts, and too long to guess. */
    private static function newToken(): string
    {
        return Str::random(40);
    }
}
