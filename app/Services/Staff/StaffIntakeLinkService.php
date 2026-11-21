<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\DTOs\Staff\StaffIntakeLinkDTO;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;
use App\Models\Faculty\Faculty;
use App\Models\Staff\StaffIntakeLink;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StaffIntakeLinkService implements StaffIntakeLinkServiceInterface
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
        private readonly AuditServiceInterface $auditService,
        private readonly CenterCatalogServiceInterface $centers,
    ) {}

    public function create(StaffIntakeLinkInputDTO $input, int $actorId): StaffIntakeLinkCreatedDTO
    {
        $centerSlug = $input->centerSlug !== null && trim($input->centerSlug) !== '' ? trim($input->centerSlug) : null;
        if ($centerSlug !== null) {
            if (! $this->canUseCenters($actorId, 'create')) {
                throw new AuthorizationException('You may not make center staff links.');
            }
            if (! $this->centers->isCenter($centerSlug)) {
                throw ValidationException::withMessages(['center_slug' => __('staff_intake.admin.errors.center')]);
            }
        } elseif ($input->facultyId === null || ! array_key_exists($input->facultyId, $this->facultyOptions($actorId, 'staff_intake_links.create'))) {
            throw new AuthorizationException('You may only make links for your own faculties.');
        }

        if ($input->expiresAt !== null && $input->expiresAt <= now()) {
            throw ValidationException::withMessages(['expires_at' => __('staff_intake.admin.errors.expiry_in_past')]);
        }

        if ($input->maxSubmissions !== null && $input->maxSubmissions < 1) {
            throw ValidationException::withMessages(['max_submissions' => __('staff_intake.admin.errors.cap_too_small')]);
        }

        $isCenter = $centerSlug !== null;
        $requiredFields = $input->requiredFields !== null
            ? StaffIntakeFields::required($input->requiredFields, $isCenter)
            : StaffIntakeFields::defaultRequired($isCenter);

        $token = self::newToken();
        $label = $input->label !== null && trim($input->label) !== '' ? trim($input->label) : null;
        $link = StaffIntakeLink::query()->create([
            'faculty_id' => $centerSlug === null ? $input->facultyId : null,
            'center_slug' => $centerSlug,
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => Crypt::encryptString($token),
            'label' => $label !== null ? mb_substr($label, 0, 255) : null,
            'expires_at' => $input->expiresAt,
            'max_submissions' => $input->maxSubmissions,
            'required_fields' => $requiredFields,
            'created_by' => $actorId,
        ]);

        $this->auditService->log('staff_intake_link.created', $actorId, StaffIntakeLink::class, (int) $link->getKey(), [
            'faculty_id' => $centerSlug === null ? $input->facultyId : null,
            'center_slug' => $centerSlug,
            'expires_at' => $input->expiresAt?->format(DATE_ATOM),
            'max_submissions' => $input->maxSubmissions,
            'required_fields' => $requiredFields,
        ]);

        return $this->created($link, $token);
    }

    public function rotate(int $linkId, int $actorId): StaffIntakeLinkCreatedDTO
    {
        $link = $this->manageable($linkId, $actorId);
        $token = self::newToken();
        $link->forceFill(['token_hash' => hash('sha256', $token), 'token_encrypted' => Crypt::encryptString($token)])->save();

        $this->auditService->log('staff_intake_link.rotated', $actorId, StaffIntakeLink::class, $linkId);

        return $this->created($link, $token);
    }

    public function address(int $linkId, int $actorId): ?StaffIntakeLinkCreatedDTO
    {
        $link = $this->manageable($linkId, $actorId);

        try {
            $token = is_string($link->token_encrypted) ? Crypt::decryptString($link->token_encrypted) : null;
        } catch (DecryptException) {
            $token = null;
        }

        if (! is_string($token) || $token === '' || ! hash_equals((string) $link->token_hash, hash('sha256', $token))) {
            return null;
        }

        $this->auditService->log('staff_intake_link.address_viewed', $actorId, StaffIntakeLink::class, $linkId);

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

    public function facultyOptions(int $actorId, string $permission = 'staff_intake_links.view'): array
    {
        $user = User::query()->find($actorId);
        if (! $user instanceof User || ! $this->access->can($user, $permission)) {
            return [];
        }

        $query = Faculty::query()->enabled()->with('translations')->orderBy('sort_order');
        if ($this->access->hasFacultyLimit($user)) {
            $scopes = $this->access->facultyScopeValues($user);
            $query->where(fn ($faculties) => $faculties
                ->whereIn('faculty_scope_slug', $scopes)
                ->orWhereIn('public_slug', $scopes)
                ->orWhereIn('slug', $scopes));
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return $query->get()->mapWithKeys(fn (Faculty $faculty): array => [
            (int) $faculty->getKey() => (string) ($faculty->translations->firstWhere('locale', $locale)?->name
                ?? $faculty->translations->first()?->name
                ?? $faculty->slug),
        ])->all();
    }

    public function canManage(int $linkId, int $actorId): bool
    {
        $link = StaffIntakeLink::query()->whereKey($linkId)->first(['id', 'faculty_id', 'center_slug']);
        if (! $link instanceof StaffIntakeLink) {
            return false;
        }

        if ($link->isForCenter()) {
            return $this->canUseCenters($actorId, 'update');
        }

        return $link->faculty_id !== null && array_key_exists((int) $link->faculty_id, $this->facultyOptions($actorId, 'staff_intake_links.update'));
    }

    public function canUseCenters(int $actorId, string $action = 'view'): bool
    {
        $user = User::query()->find($actorId);

        return $user instanceof User && $this->access->can($user, 'center_staff_intake.'.$action);
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
            link: self::toDto($link, $this->centers),
            urlAr: route('public.staff-intake.show', ['locale' => 'ar', 'token' => $token]),
            urlEn: route('public.staff-intake.show', ['locale' => 'en', 'token' => $token]),
        );
    }

    public static function toDto(StaffIntakeLink $link, CenterCatalogServiceInterface $centers): StaffIntakeLinkDTO
    {
        $faculty = $link->isForCenter() ? null : $link->faculty()->with('translations')->first();
        $name = static fn (string $locale): string => match (true) {
            $link->isForCenter() => (string) ($centers->centerName((string) $link->center_slug, $locale) ?? $link->center_slug),
            $faculty instanceof Faculty => (string) ($faculty->translations->firstWhere('locale', $locale)?->name ?? $faculty->slug),
            default => '',
        };

        return new StaffIntakeLinkDTO(
            id: (int) $link->getKey(),
            facultyId: $link->faculty_id !== null ? (int) $link->faculty_id : null,
            facultyNameAr: $name('ar'),
            facultyNameEn: $name('en'),
            label: $link->label,
            expiresAt: $link->expires_at?->toIso8601String(),
            revokedAt: $link->revoked_at?->toIso8601String(),
            maxSubmissions: $link->max_submissions,
            submissionsCount: (int) $link->submissions_count,
            active: $link->isActive(),
            createdBy: $link->created_by !== null ? (int) $link->created_by : null,
            centerSlug: $link->isForCenter() ? (string) $link->center_slug : null,
            requiredFields: StaffIntakeFields::required($link->required_fields, $link->isForCenter()),
        );
    }

    /** 40 random letters and digits: what the route accepts, and too long to guess. */
    private static function newToken(): string
    {
        return Str::random(40);
    }

    public function requirableFields(bool $isCenter): array
    {
        return StaffIntakeFields::requirable($isCenter);
    }

    public function defaultRequiredFields(bool $isCenter): array
    {
        return StaffIntakeFields::defaultRequired($isCenter);
    }

    public function requiredFields(mixed $stored, bool $isCenter): array
    {
        return StaffIntakeFields::required($stored, $isCenter);
    }
}
