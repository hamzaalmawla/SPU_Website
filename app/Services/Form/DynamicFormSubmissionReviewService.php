<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Contracts\Form\DynamicFormSubmissionReviewServiceInterface;
use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\Contracts\Form\FormSubmissionNotificationServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\DTOs\Form\DynamicFormSubmissionAttachmentDTO;
use App\DTOs\Form\DynamicFormSubmissionDetailDTO;
use App\DTOs\Form\DynamicFormSubmissionDetailFieldDTO;
use App\DTOs\Form\DynamicFormSubmissionDetailSectionDTO;
use App\DTOs\Form\SecureFormSubmissionDownloadDTO;
use App\Enums\FormSubmissionInbox;
use App\Enums\FormSubmissionStatus;
use App\Exceptions\ConflictException;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

final class DynamicFormSubmissionReviewService implements DynamicFormSubmissionReviewServiceInterface
{
    /** @var array<string, string> */
    private const ATTACHMENT_FIELDS = [
        'job-application' => 'cvFile',
        'suggestions-complaints' => 'attachment',
    ];

    public function __construct(
        private readonly DynamicFormSubmissionServiceInterface $submissionService,
        private readonly AuditServiceInterface $auditService,
        private readonly FormSubmissionNotificationServiceInterface $notificationService,
    ) {}

    public function getDetails(int $submissionId, string $adminLocale, int $actorId): DynamicFormSubmissionDetailDTO
    {
        $locale = $adminLocale === 'ar' ? 'ar' : 'en';
        $submission = DynamicFormSubmission::query()->findOrFail($submissionId);
        $this->authorizedActor($actorId, 'view', $submission);
        $formId = (string) $submission->form_id;
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        $inbox = FormSubmissionInbox::tryFromFormId($formId);
        $rawStatus = (string) $submission->status;
        $status = FormSubmissionStatus::tryFrom($rawStatus);

        return new DynamicFormSubmissionDetailDTO(
            id: (int) $submission->getKey(),
            formId: $formId,
            formLabel: $this->translation('forms.'.$formId, $locale, $formId),
            inbox: $inbox,
            inboxLabel: $inbox instanceof FormSubmissionInbox
                ? $this->translation('inboxes.'.$inbox->value, $locale, $inbox->value)
                : $this->translation('inboxes.unknown', $locale, $formId),
            status: $status,
            rawStatus: $rawStatus,
            statusLabel: $status instanceof FormSubmissionStatus
                ? $this->translation('statuses.'.$status->value, $locale, $status->value)
                : $rawStatus,
            submissionLocale: (string) $submission->locale,
            applicantName: is_string($submission->applicant_name) ? $submission->applicant_name : null,
            applicantEmail: is_string($submission->applicant_email) ? $submission->applicant_email : null,
            submittedAt: $submission->created_at?->toIso8601String() ?? '',
            sections: $this->detailSections($formId, $payload, $submission, $locale),
            attachments: $this->attachments($formId, $submission->files_json, $locale),
            referenceNumber: is_string($submission->reference_number) ? $submission->reference_number : null,
            readAt: $submission->read_at?->toIso8601String(),
            assignedToUserId: is_numeric($submission->assigned_to_user_id) ? (int) $submission->assigned_to_user_id : null,
            assignedToName: $submission->assignedTo?->name,
            internalNotes: is_string($submission->internal_notes) ? $submission->internal_notes : null,
            statusChangedAt: $submission->status_changed_at?->toIso8601String(),
            emailDeliveryStatus: is_string($submission->email_delivery_status) ? $submission->email_delivery_status : null,
        );
    }

    public function transitionStatus(
        int $submissionId,
        FormSubmissionStatus $expectedStatus,
        FormSubmissionStatus $newStatus,
        int $actorId,
        ?string $reason = null,
    ): bool {
        $changed = DB::transaction(function () use ($submissionId, $expectedStatus, $newStatus, $actorId, $reason): bool {
            $submission = DynamicFormSubmission::query()->findOrFail($submissionId);
            $actor = $this->authorizedActor($actorId, 'transitionStatus', $submission);
            $formId = (string) $submission->form_id;
            $inbox = FormSubmissionInbox::tryFromFormId($formId);
            $currentStatus = (string) $submission->status;

            if ($currentStatus !== $expectedStatus->value) {
                throw new ConflictException('The submission status changed after it was loaded.');
            }

            if (! $inbox instanceof FormSubmissionInbox || ! $expectedStatus->canTransitionTo($newStatus, $inbox)) {
                throw new \DomainException('The requested status transition is not legal for this inbox.');
            }

            $updated = DynamicFormSubmission::query()
                ->whereKey($submissionId)
                ->where('status', $expectedStatus->value)
                ->update([
                    'status' => $newStatus->value,
                    'status_changed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new ConflictException('The submission status changed during this update.');
            }

            $logged = $this->auditService->log(
                'dynamic_form_submission.status_changed',
                (int) $actor->getKey(),
                DynamicFormSubmission::class,
                $submissionId,
                [
                    'actor' => (int) $actor->getKey(),
                    'from' => $expectedStatus->value,
                    'to' => $newStatus->value,
                    'form' => $formId,
                    'inbox' => $inbox->value,
                    'reason' => $reason,
                ],
            );

            if (! $logged) {
                throw new \RuntimeException('The submission status audit event could not be recorded.');
            }

            return true;
        });

        if ($changed) {
            $this->notificationService->queueDynamicStatusChanged($submissionId, $expectedStatus, $newStatus);
        }

        return $changed;
    }

    public function markAsRead(int $submissionId, int $actorId): bool
    {
        $submission = DynamicFormSubmission::query()->findOrFail($submissionId);
        $actor = $this->authorizedActor($actorId, 'updateReview', $submission);

        if ($submission->read_at !== null) {
            return true;
        }

        $submission->forceFill(['read_at' => now(), 'read_by_user_id' => $actor->getKey()])->save();
        $this->auditService->log('dynamic_form_submission.read', (int) $actor->getKey(), DynamicFormSubmission::class, $submissionId);

        return true;
    }

    public function markAsUnread(int $submissionId, int $actorId): bool
    {
        $submission = DynamicFormSubmission::query()->findOrFail($submissionId);
        $actor = $this->authorizedActor($actorId, 'updateReview', $submission);
        $submission->forceFill(['read_at' => null, 'read_by_user_id' => null])->save();
        $this->auditService->log('dynamic_form_submission.unread', (int) $actor->getKey(), DynamicFormSubmission::class, $submissionId);

        return true;
    }

    public function assign(int $submissionId, ?int $assigneeId, int $actorId): bool
    {
        $submission = DynamicFormSubmission::query()->findOrFail($submissionId);
        $actor = $this->authorizedActor($actorId, 'updateReview', $submission);

        if ($assigneeId !== null && ! User::query()->whereKey($assigneeId)->whereIn('role_slug', ['super_admin', 'editor', 'hr'])->exists()) {
            throw new \InvalidArgumentException('The selected reviewer is not eligible.');
        }

        $submission->forceFill([
            'assigned_to_user_id' => $assigneeId,
            'assigned_at' => $assigneeId === null ? null : now(),
            'assigned_by_user_id' => $assigneeId === null ? null : $actor->getKey(),
        ])->save();
        $this->auditService->log('dynamic_form_submission.assigned', (int) $actor->getKey(), DynamicFormSubmission::class, $submissionId, ['assignee' => $assigneeId]);

        return true;
    }

    public function updateInternalNotes(int $submissionId, ?string $notes, int $actorId): bool
    {
        $submission = DynamicFormSubmission::query()->findOrFail($submissionId);
        $actor = $this->authorizedActor($actorId, 'updateReview', $submission);
        $submission->forceFill(['internal_notes' => $notes !== null ? trim($notes) : null])->save();
        $this->auditService->log('dynamic_form_submission.notes_updated', (int) $actor->getKey(), DynamicFormSubmission::class, $submissionId);

        return true;
    }

    public function resolveAttachment(
        int $submissionId,
        string $field,
        int $actorId,
    ): SecureFormSubmissionDownloadDTO {
        $submission = DynamicFormSubmission::query()->findOrFail($submissionId);
        $this->authorizedActor($actorId, 'downloadAttachment', $submission);

        $formId = (string) $submission->form_id;

        if ((self::ATTACHMENT_FIELDS[$formId] ?? null) !== $field) {
            throw new \InvalidArgumentException('This field is not a downloadable submission attachment.');
        }

        $files = is_array($submission->files_json) ? $submission->files_json : [];
        $metadata = $files[$field] ?? null;

        if (! is_array($metadata) || ($metadata['disk'] ?? null) !== 'local') {
            throw new \RuntimeException('The requested attachment is unavailable.');
        }

        $path = is_string($metadata['path'] ?? null) ? $metadata['path'] : '';
        $prefix = 'dynamic-form-submissions/'.$formId.'/';

        if (! $this->isSafeAttachmentPath($path, $prefix)) {
            throw new \RuntimeException('The requested attachment is unavailable.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw new \RuntimeException('The requested attachment is unavailable.');
        }

        $mimeType = $disk->mimeType($path);
        $originalName = is_string($metadata['original_name'] ?? null)
            ? $metadata['original_name']
            : basename($path);

        return new SecureFormSubmissionDownloadDTO(
            submissionId: $submissionId,
            field: $field,
            disk: 'local',
            path: $path,
            downloadName: $this->sanitizedFilename($originalName, $path),
            mimeType: is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream',
            size: $disk->size($path),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<DynamicFormSubmissionDetailSectionDTO>
     */
    private function detailSections(
        string $formId,
        array $payload,
        DynamicFormSubmission $submission,
        string $locale,
    ): array {
        $fields = [];
        $knownFields = [];

        foreach ($this->submissionService->formSchema($formId) as $definition) {
            $key = is_string($definition['name'] ?? null) ? $definition['name'] : '';
            $type = is_string($definition['type'] ?? null) ? $definition['type'] : 'text';

            if ($key === '') {
                continue;
            }

            $knownFields[] = $key;

            if ($type === 'file' || ! array_key_exists($key, $payload)) {
                continue;
            }

            [$displayValue, $isLegacyValue] = $this->displayValue($payload[$key], $type, $definition, $locale);
            $fields[] = new DynamicFormSubmissionDetailFieldDTO(
                key: $key,
                label: $this->translation('fields.'.$key, $locale, $key),
                rawValue: $payload[$key],
                displayValue: $displayValue,
                isLegacyValue: $isLegacyValue,
            );
        }

        foreach ($payload as $key => $value) {
            if ($key === '_context' || in_array($key, $knownFields, true)) {
                continue;
            }

            $fields[] = new DynamicFormSubmissionDetailFieldDTO(
                key: (string) $key,
                label: $this->translation('fields.legacy', $locale, (string) $key, ['field' => (string) $key]),
                rawValue: $value,
                displayValue: $this->stringValue($value),
                isLegacyField: true,
            );
        }

        $sections = [new DynamicFormSubmissionDetailSectionDTO(
            key: 'submitted_fields',
            label: $this->translation('sections.submitted_fields', $locale, 'Submitted fields'),
            fields: $fields,
        )];

        $context = is_array($payload['_context'] ?? null) ? $payload['_context'] : [];

        if ($context !== []) {
            $sections[] = new DynamicFormSubmissionDetailSectionDTO(
                key: 'context',
                label: $this->translation('sections.context', $locale, 'Context'),
                fields: $this->contextFields($context, $locale),
            );
        }

        $sections[] = new DynamicFormSubmissionDetailSectionDTO(
            key: 'technical_request',
            label: $this->translation('sections.technical_request', $locale, 'Technical request details'),
            fields: [
                new DynamicFormSubmissionDetailFieldDTO(
                    key: 'ip_address',
                    label: $this->translation('technical.ip_address', $locale, 'IP address'),
                    rawValue: $submission->ip_address,
                    displayValue: $this->stringValue($submission->ip_address),
                ),
                new DynamicFormSubmissionDetailFieldDTO(
                    key: 'user_agent',
                    label: $this->translation('technical.user_agent', $locale, 'User agent'),
                    rawValue: $submission->user_agent,
                    displayValue: $this->stringValue($submission->user_agent),
                ),
            ],
            isTechnical: true,
        );

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{string, bool}
     */
    private function displayValue(mixed $value, string $type, array $definition, string $locale): array
    {
        if ($type === 'checkbox') {
            $checked = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($checked === null) {
                return [$this->stringValue($value), true];
            }

            return [$this->translation('boolean.'.($checked ? 'yes' : 'no'), $locale, $checked ? 'Yes' : 'No'), false];
        }

        if ($type !== 'select') {
            return [$this->stringValue($value), false];
        }

        $rawValue = $this->stringValue($value);
        $options = array_values(array_filter(array_map(
            static fn (mixed $option): string => is_array($option) && is_string($option['value'] ?? null)
                ? $option['value']
                : '',
            is_array($definition['options'] ?? null) ? $definition['options'] : [],
        )));
        $isLegacy = ! in_array($rawValue, $options, true);

        return [
            $isLegacy ? $rawValue : $this->translation('options.'.$rawValue, $locale, $rawValue),
            $isLegacy,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<DynamicFormSubmissionDetailFieldDTO>
     */
    private function contextFields(array $context, string $locale): array
    {
        $fields = [];
        $orderedKeys = ['source', 'event_title', 'event_id', 'job_title', 'job_id', 'job_slug'];

        foreach (array_merge($orderedKeys, array_keys($context)) as $key) {
            if (! array_key_exists($key, $context) || isset($fields[$key])) {
                continue;
            }

            $value = $context[$key];
            $displayValue = $key === 'source'
                ? $this->translation('sources.'.$this->stringValue($value), $locale, $this->stringValue($value))
                : $this->stringValue($value);
            $fields[$key] = new DynamicFormSubmissionDetailFieldDTO(
                key: (string) $key,
                label: $this->translation('context.'.$key, $locale, (string) $key),
                rawValue: $value,
                displayValue: $displayValue,
                isLegacyField: ! in_array($key, $orderedKeys, true),
            );
        }

        return array_values($fields);
    }

    /**
     * @return list<DynamicFormSubmissionAttachmentDTO>
     */
    private function attachments(string $formId, mixed $files, string $locale): array
    {
        $field = self::ATTACHMENT_FIELDS[$formId] ?? null;

        if ($field === null || ! is_array($files) || ! is_array($files[$field] ?? null)) {
            return [];
        }

        $metadata = $files[$field];
        $storedPath = is_string($metadata['path'] ?? null) ? $metadata['path'] : '';
        $originalName = is_string($metadata['original_name'] ?? null)
            ? $metadata['original_name']
            : basename($storedPath);

        return [new DynamicFormSubmissionAttachmentDTO(
            field: $field,
            label: $this->translation('fields.'.$field, $locale, $field),
            originalName: $this->sanitizedFilename($originalName, $storedPath),
            mimeType: is_string($metadata['mime_type'] ?? null) ? $metadata['mime_type'] : null,
            size: is_int($metadata['size'] ?? null) ? $metadata['size'] : null,
        )];
    }

    private function authorizedActor(int $actorId, string $ability, DynamicFormSubmission $submission): User
    {
        $actor = User::query()->find($actorId);

        if (! $actor instanceof User || $actor->isAccountLocked() || Gate::forUser($actor)->denies($ability, $submission)) {
            throw new AuthorizationException('This user is not authorized to review form submissions.');
        }

        return $actor;
    }

    private function isSafeAttachmentPath(string $path, string $prefix): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || ! str_starts_with($path, $prefix)) {
            return false;
        }

        $segments = explode('/', $path);

        return ! in_array('', $segments, true)
            && ! in_array('.', $segments, true)
            && ! in_array('..', $segments, true)
            && $path !== $prefix;
    }

    private function sanitizedFilename(string $originalName, string $storedPath): string
    {
        $filename = basename(str_replace('\\', '/', $originalName));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename) ?? '';
        $filename = preg_replace('/[^\pL\pN._() -]+/u', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        if ($filename !== '' && $filename !== '.' && $filename !== '..') {
            return mb_substr($filename, 0, 180);
        }

        $extension = pathinfo($storedPath, PATHINFO_EXTENSION);

        return 'attachment'.($extension !== '' ? '.'.$extension : '');
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /** @param array<string, string> $replace */
    private function translation(
        string $key,
        string $locale,
        string $fallback,
        array $replace = [],
    ): string {
        $translationKey = 'form_submissions.'.$key;
        $translated = Lang::get($translationKey, $replace, $locale);

        return is_string($translated) && $translated !== $translationKey ? $translated : $fallback;
    }
}
