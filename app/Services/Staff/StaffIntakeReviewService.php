<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\PersonIdentityServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\DTOs\Staff\StaffIntakeApprovalResultDTO;
use App\DTOs\Staff\StaffIntakeRequestDetailDTO;
use App\DTOs\Staff\StaffIntakeUploadDTO;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Staff\StaffIntakeAccessLog;
use App\Models\Staff\StaffIntakeRequest;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Reviews staff intake requests. Approving builds the same CMS payload an
 * editor's form would and saves it through the CMS workflow, so validation,
 * faculty scope, cache invalidation and audit are the ones every profile edit
 * already gets. A faculty editor's approval leaves a draft for an editor to
 * publish, as their own edits do.
 */
final class StaffIntakeReviewService implements StaffIntakeReviewServiceInterface
{
    public function __construct(
        private readonly ProfileAdminServiceInterface $profiles,
        private readonly AboutEntityCmsServiceInterface $aboutEntityCms,
        private readonly CmsWorkflowServiceInterface $cmsWorkflow,
        private readonly MediaServiceInterface $mediaService,
        private readonly SlugServiceInterface $slugService,
        private readonly AuditServiceInterface $auditService,
        private readonly AdminAccessServiceInterface $access,
        private readonly StaffIntakeLinkServiceInterface $links,
        private readonly PersonIdentityServiceInterface $identities,
    ) {}

    public function reviewableFacultyIds(int $actorId): array
    {
        return array_map('intval', array_keys($this->links->facultyOptions($actorId, 'staff_intake_requests.view')));
    }

    public function canDecide(int $requestId, int $actorId): bool
    {
        $facultyId = StaffIntakeRequest::query()->whereKey($requestId)->value('faculty_id');

        return $facultyId !== null
            && array_key_exists((int) $facultyId, $this->links->facultyOptions($actorId, 'staff_intake_requests.update'));
    }

    public function pendingCount(int $actorId): int
    {
        $facultyIds = $this->reviewableFacultyIds($actorId);

        return $facultyIds === [] ? 0 : StaffIntakeRequest::query()
            ->whereIn('faculty_id', $facultyIds)
            ->where('status', StaffIntakeRequest::STATUS_PENDING)
            ->count();
    }

    public function detail(int $requestId, string $locale, int $actorId): StaffIntakeRequestDetailDTO
    {
        $request = $this->authorizedRequest($requestId, $actorId);
        $request->load(['faculty.translations', 'facultyMember.translations', 'relatedFacultyMember.translations', 'relatedFacultyMember.faculty.translations', 'reviewer']);

        $payload = is_array($request->payload) ? $request->payload : [];
        $snapshot = is_array($request->original_snapshot) ? $request->original_snapshot : [];
        $after = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $before = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : StaffIntakeFields::blank();
        $educationsAfter = is_array($payload['educations'] ?? null) ? $payload['educations'] : [];
        $educationsBefore = is_array($snapshot['educations'] ?? null) ? $snapshot['educations'] : [];
        $uploads = is_array($payload['uploads'] ?? null) ? $payload['uploads'] : [];

        $changes = [];
        foreach (StaffIntakeFields::changedKeys($before, $after, $educationsBefore, $educationsAfter) as $key) {
            $changes[] = $key === 'educations'
                ? ['field' => $key, 'before' => $this->educationsText($educationsBefore), 'after' => $this->educationsText($educationsAfter)]
                : ['field' => $key, 'before' => $this->valueText($key, $before[$key] ?? null, $locale), 'after' => $this->valueText($key, $after[$key] ?? null, $locale)];
        }

        $candidateIds = array_map('intval', is_array($request->duplicate_candidates) ? $request->duplicate_candidates : []);
        $candidates = FacultyMember::query()->whereKey($candidateIds)->with(['translations', 'faculty.translations'])->get()
            ->map(fn (FacultyMember $member): array => [
                'id' => (int) $member->getKey(),
                'name' => $this->memberName($member, $locale),
                'faculty' => $member->faculty instanceof Faculty ? $this->facultyName($member->faculty, $locale) : '',
                'sameFaculty' => (int) $member->faculty_id === (int) $request->faculty_id,
            ])
            ->values()
            ->all();

        $claimed = $request->facultyMember;
        $related = $request->relatedFacultyMember;
        // A leadership role held by this profile shows the approved change too.
        $leadershipRoleId = $claimed instanceof FacultyMember ? ($this->identities->personIdsForMember((int) $claimed->getKey())[0] ?? null) : null;
        $linkedPersonSlug = $leadershipRoleId !== null ? Person::query()->whereKey($leadershipRoleId)->value('slug') : null;

        $accessLog = StaffIntakeAccessLog::query()
            ->where(function ($query) use ($request): void {
                $query->where('staff_intake_request_id', $request->getKey());
                if ($request->faculty_member_id !== null && $request->staff_intake_link_id !== null) {
                    $query->orWhere(fn ($claims) => $claims
                        ->where('staff_intake_link_id', $request->staff_intake_link_id)
                        ->where('faculty_member_id', $request->faculty_member_id));
                }
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (StaffIntakeAccessLog $log): array => [
                'event' => (string) $log->event,
                'name' => $log->submitter_name,
                'changedFields' => is_array($log->changed_fields) ? array_values(array_map('strval', $log->changed_fields)) : [],
                'ip' => $log->ip_address,
                'userAgent' => $log->user_agent,
                'at' => (string) $log->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return new StaffIntakeRequestDetailDTO(
            id: (int) $request->getKey(),
            reference: (string) $request->reference,
            status: (string) $request->status,
            type: (string) $request->type,
            facultyName: $request->faculty instanceof Faculty ? $this->facultyName($request->faculty, $locale) : '',
            submitterName: (string) $request->submitter_name,
            submittedAt: (string) $request->created_at?->toDateTimeString(),
            possibleDuplicate: (bool) $request->possible_duplicate,
            changes: $changes,
            candidates: $candidates,
            claimedMember: $claimed instanceof FacultyMember ? ['id' => (int) $claimed->getKey(), 'name' => $this->memberName($claimed, $locale)] : null,
            relatedMember: $related instanceof FacultyMember ? [
                'id' => (int) $related->getKey(),
                'name' => $this->memberName($related, $locale),
                'faculty' => $related->faculty instanceof Faculty ? $this->facultyName($related->faculty, $locale) : '',
            ] : null,
            linkedPersonSlug: is_string($linkedPersonSlug) ? $linkedPersonSlug : null,
            hasNewPhoto: is_array($uploads['photo'] ?? null),
            hasNewCv: is_array($uploads['cv'] ?? null),
            reviewerName: $request->reviewer instanceof User ? (string) $request->reviewer->name : null,
            reviewedAt: $request->reviewed_at?->toDateTimeString(),
            reviewNote: $request->review_note,
            appliedMemberId: $request->applied_faculty_member_id !== null ? (int) $request->applied_faculty_member_id : null,
            accessLog: $accessLog,
        );
    }

    public function approve(int $requestId, int $actorId, ?int $targetMemberId = null, ?string $note = null): StaffIntakeApprovalResultDTO
    {
        $request = $this->decidableRequest($requestId, $actorId);
        $actor = User::query()->findOrFail($actorId);

        $result = DB::transaction(function () use ($request, $actor, $actorId, $targetMemberId, $note): StaffIntakeApprovalResultDTO {
            $locked = StaffIntakeRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== StaffIntakeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => __('staff_intake.admin.errors.not_pending')]);
            }

            $targetId = $targetMemberId ?? ($locked->type === StaffIntakeRequest::TYPE_UPDATE ? $locked->faculty_member_id : null);
            if ($targetId !== null && ! FacultyMember::query()->whereKey($targetId)->where('faculty_id', $locked->faculty_id)->exists()) {
                throw ValidationException::withMessages(['target' => __('staff_intake.admin.errors.target_faculty')]);
            }

            $payload = is_array($locked->payload) ? $locked->payload : [];
            $cmsPayload = $this->cmsPayload($locked, $payload, $targetId !== null ? (int) $targetId : null, $actorId);

            $prepared = $this->aboutEntityCms->prepareDraft(
                new AboutEntityCmsDataDTO('faculty-member', $targetId !== null ? (int) $targetId : null, $cmsPayload),
                $actorId,
            );
            $targetKey = $prepared->targetKey ?? throw new \RuntimeException('The faculty member draft target was not created.');
            $this->cmsWorkflow->saveDraft($targetKey, $prepared->payload, $actorId);

            // Published at once when the reviewer may publish and the profile
            // is complete enough; otherwise it waits as a draft, like an
            // editor's own unpublished change.
            $published = false;
            $publishErrors = [];
            if ($this->access->can($actor, 'faculty_members.publish')) {
                $readiness = $this->cmsWorkflow->readiness($targetKey);
                foreach ($readiness->errors as $key => $messages) {
                    foreach ($messages as $message) {
                        $publishErrors[] = strtoupper((string) $key).': '.$message;
                    }
                }
                $published = $readiness->isReady && $this->cmsWorkflow->publish($targetKey, $actorId);
            }

            $memberId = (int) $prepared->entityId;
            $locked->forceFill([
                'status' => StaffIntakeRequest::STATUS_APPROVED,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'applied_faculty_member_id' => $memberId,
            ])->save();

            return new StaffIntakeApprovalResultDTO(facultyMemberId: $memberId, published: $published, publishErrors: $published ? [] : $publishErrors);
        });

        $this->auditService->log('staff_intake_request.approved', $actorId, StaffIntakeRequest::class, $requestId, [
            'faculty_member_id' => $result->facultyMemberId,
            'published' => $result->published,
            'target_chosen_by_reviewer' => $targetMemberId !== null,
        ]);

        return $result;
    }

    public function reject(int $requestId, int $actorId, ?string $note = null): bool
    {
        $request = $this->decidableRequest($requestId, $actorId);
        if ($request->status !== StaffIntakeRequest::STATUS_PENDING) {
            return false;
        }

        $request->forceFill([
            'status' => StaffIntakeRequest::STATUS_REJECTED,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ])->save();

        $this->auditService->log('staff_intake_request.rejected', $actorId, StaffIntakeRequest::class, $requestId);

        return true;
    }

    public function upload(int $requestId, string $kind, int $actorId): StaffIntakeUploadDTO
    {
        $request = $this->authorizedRequest($requestId, $actorId);
        $upload = is_array($request->payload['uploads'][$kind] ?? null) ? $request->payload['uploads'][$kind] : null;
        if (! in_array($kind, ['photo', 'cv'], true) || $upload === null || ! Storage::disk('local')->exists((string) $upload['path'])) {
            throw new \RuntimeException('This request has no such file.');
        }

        return new StaffIntakeUploadDTO(
            disk: 'local',
            path: (string) $upload['path'],
            downloadName: (string) ($upload['name'] ?? $kind),
            mimeType: (string) ($upload['mime'] ?? 'application/octet-stream'),
            size: (int) Storage::disk('local')->size((string) $upload['path']),
        );
    }

    private function authorizedRequest(int $requestId, int $actorId): StaffIntakeRequest
    {
        $request = StaffIntakeRequest::query()->findOrFail($requestId);
        if (! in_array((int) $request->faculty_id, $this->reviewableFacultyIds($actorId), true)) {
            throw new AuthorizationException('You may only review requests of your own faculties.');
        }

        return $request;
    }

    private function decidableRequest(int $requestId, int $actorId): StaffIntakeRequest
    {
        if (! $this->canDecide($requestId, $actorId)) {
            throw new AuthorizationException('You may not review this request.');
        }

        return StaffIntakeRequest::query()->findOrFail($requestId);
    }

    /**
     * The CMS payload for the profile: the profile as it stands (or an
     * editor's pending draft of it) with the teacher's values laid over it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsPayload(StaffIntakeRequest $request, array $payload, ?int $targetId, int $actorId): array
    {
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $educations = is_array($payload['educations'] ?? null) ? $payload['educations'] : [];
        $uploads = is_array($payload['uploads'] ?? null) ? $payload['uploads'] : [];

        if ($targetId !== null) {
            $targetKey = 'entity.faculty-member.'.$targetId;
            $base = $this->cmsWorkflow->latestEditableDraftPayload($targetKey, $actorId)
                ?? $this->aboutEntityCms->getStoredData($targetKey)?->payload
                ?? [];
        } else {
            $base = [
                'slug' => $this->slugService->generate((string) ($fields['name_en'] ?? '') ?: 'faculty-member', FacultyMember::class, 'en'),
                'faculty_id' => (int) $request->faculty_id,
                'sort_order' => $this->profiles->nextFacultyMemberSortOrder(),
                'is_enabled' => true,
                'translations' => [],
                'educations' => [],
                'social_links' => [],
            ];
        }

        foreach (['ar', 'en'] as $locale) {
            $translation = is_array($base['translations'][$locale] ?? null) ? $base['translations'][$locale] : [];
            $translation['locale'] = $locale;
            foreach (StaffIntakeFields::TRANSLATED as $field => $column) {
                $value = $fields[$field.'_'.$locale] ?? null;
                $translation[$column] = $field === 'specializations' ? (StaffIntakeFields::list($value) ?: null) : $value;
            }
            $base['translations'][$locale] = $translation;
        }

        $base['department_id'] = $fields['department_id'] ?? null;

        // The link asks only for the department; the profile keeps the specialization an editor
        // set. One that is no longer a program of the faculty's plan is cleared rather than
        // blocking the approval.
        $specializationId = is_string($base['specialization_id'] ?? null) && $base['specialization_id'] !== '' ? $base['specialization_id'] : null;
        $facultyId = is_numeric($base['faculty_id'] ?? null) ? (int) $base['faculty_id'] : null;
        $base['specialization_id'] = $specializationId !== null && array_key_exists($specializationId, $this->profiles->specializationOptions($facultyId))
            ? $specializationId
            : null;
        foreach (StaffIntakeFields::CONTACT as $field) {
            $base[$field] = $fields[$field] ?? null;
        }

        $links = is_array($base['social_links'] ?? null) ? $base['social_links'] : [];
        foreach (StaffIntakeFields::LINKS as $field) {
            if (is_string($fields[$field] ?? null) && $fields[$field] !== '') {
                $links[$field] = $fields[$field];
            } else {
                unset($links[$field]);
            }
        }
        $base['social_links'] = $links !== [] ? $links : null;

        $base['educations'] = $this->cmsEducations(is_array($base['educations'] ?? null) ? $base['educations'] : [], $educations, $targetId !== null);

        $name = (string) ($fields['name_en'] ?? '') ?: (string) ($fields['name_ar'] ?? '');
        $related = $targetId === null && $request->related_faculty_member_id !== null
            ? FacultyMember::query()->find($request->related_faculty_member_id)
            : null;
        foreach (['photo' => 'photo_media_id', 'cv' => 'cv_media_id'] as $kind => $column) {
            if (is_array($uploads[$kind] ?? null)) {
                $base[$column] = $this->importUpload($uploads[$kind], $request, $name, $fields, $actorId);
            } elseif ($related instanceof FacultyMember && $related->getAttribute($column) !== null) {
                // A new profile started from one in another faculty keeps its files.
                $base[$column] = (int) $related->getAttribute($column);
            }
        }

        return $base;
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function cmsEducations(array $existing, array $rows, bool $keepIds): array
    {
        $byId = [];
        foreach ($existing as $education) {
            if (is_array($education) && is_numeric($education['id'] ?? null)) {
                $byId[(int) $education['id']] = $education;
            }
        }

        $educations = [];
        foreach (array_values($rows) as $index => $row) {
            $id = $keepIds && is_numeric($row['id'] ?? null) && isset($byId[(int) $row['id']]) ? (int) $row['id'] : null;
            $education = $id !== null ? $byId[$id] : ['is_enabled' => true];
            $education['id'] = $id;
            $education['sort_order'] = ($index + 1) * 10;

            foreach (['ar', 'en'] as $locale) {
                $translation = is_array($education['translations'][$locale] ?? null) ? $education['translations'][$locale] : ['description' => null];
                $education['translations'][$locale] = [
                    ...$translation,
                    'locale' => $locale,
                    'degree' => $row['degree_'.$locale] ?? null,
                    'institution' => $row['institution_'.$locale] ?? null,
                    'field_of_study' => $row['field_'.$locale] ?? null,
                    'year_start' => $row['year_start'] ?? null,
                    'year_end' => $row['year_end'] ?? null,
                ];
            }

            $educations[] = $education;
        }

        return $educations;
    }

    /**
     * Moves a file the teacher sent into the media library, under the
     * approving editor, so the profile can use it like any other upload.
     *
     * @param  array<string, mixed>  $upload
     * @param  array<string, mixed>  $fields
     */
    private function importUpload(array $upload, StaffIntakeRequest $request, string $name, array $fields, int $actorId): int
    {
        $disk = Storage::disk('local');
        $path = (string) ($upload['path'] ?? '');
        if ($path === '' || ! $disk->exists($path)) {
            throw ValidationException::withMessages(['upload' => __('staff_intake.admin.errors.upload_missing')]);
        }

        $faculty = Faculty::query()->find($request->faculty_id);
        $fileName = $this->uploadFileName($upload, $path);
        $media = $this->mediaService->upload([
            'file' => new UploadedFile($disk->path($path), $fileName, (string) ($upload['mime'] ?? null), null, true),
            'uploaded_by' => $actorId,
            'original_name' => $fileName,
            'title_ar' => (string) ($fields['name_ar'] ?? '') ?: $name,
            'title_en' => (string) ($fields['name_en'] ?? '') ?: $name,
            'alt_text_ar' => (string) ($fields['name_ar'] ?? '') ?: $name,
            'alt_text_en' => (string) ($fields['name_en'] ?? '') ?: $name,
            'faculty_scope_slug' => $faculty instanceof Faculty ? ($faculty->faculty_scope_slug ?? $faculty->public_slug ?? $faculty->slug) : null,
        ]);

        return $media->mediaId;
    }

    /**
     * The teacher's file name ending in the extension of the stored file,
     * which was picked from the file's content when it was sent. The name
     * as sent may have none, another one, or lost it to the length limit,
     * and the media library refuses a name that does not match the content.
     *
     * @param  array<string, mixed>  $upload
     */
    private function uploadFileName(array $upload, string $storedPath): string
    {
        // Regexes rather than pathinfo(), which can drop Arabic letters under some server locales.
        $extension = preg_match('/\.([A-Za-z0-9]+)$/', $storedPath, $match) === 1 ? strtolower($match[1]) : '';
        $stem = trim((string) preg_replace('/\.[A-Za-z0-9]{1,5}$/', '', trim((string) ($upload['name'] ?? ''))), " .\t");
        if ($stem === '') {
            $stem = (string) preg_replace('/\.[A-Za-z0-9]+$/', '', basename($storedPath));
        }

        return $stem.($extension !== '' ? '.'.$extension : '');
    }

    private function valueText(string $key, mixed $value, string $locale): string
    {
        if ($key === 'department_id') {
            if (! is_numeric($value)) {
                return '';
            }
            $department = Department::query()->with('translations')->find((int) $value);

            return $department instanceof Department
                ? (string) ($department->translations->firstWhere('locale', $locale)?->name ?? $department->translations->first()?->name ?? '#'.$value)
                : '#'.$value;
        }

        return is_array($value) ? implode('، ', StaffIntakeFields::list($value)) : trim((string) $value);
    }

    /** @param list<array<string, mixed>> $educations */
    private function educationsText(array $educations): string
    {
        return implode("\n", array_map(static function (array $row): string {
            $years = trim(($row['year_start'] ?? '').'–'.($row['year_end'] ?? ''), '–');

            return trim(implode(' · ', array_filter([
                ($row['degree_ar'] ?? '').' / '.($row['degree_en'] ?? ''),
                ($row['institution_en'] ?? '') ?: ($row['institution_ar'] ?? ''),
                $years,
            ], static fn (string $part): bool => trim($part, ' /') !== '')));
        }, $educations));
    }

    private function memberName(FacultyMember $member, string $locale): string
    {
        return (string) ($member->translations->firstWhere('locale', $locale)?->full_name
            ?? $member->translations->first()?->full_name
            ?? $member->slug);
    }

    private function facultyName(Faculty $faculty, string $locale): string
    {
        return (string) ($faculty->translations->firstWhere('locale', $locale)?->name
            ?? $faculty->translations->first()?->name
            ?? $faculty->slug);
    }
}
