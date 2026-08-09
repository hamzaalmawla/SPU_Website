<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Contracts\Form\ContactMessageReviewServiceInterface;
use App\Contracts\Form\FormSubmissionNotificationServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Enums\ContactMessageStatus;
use App\Exceptions\ConflictException;
use App\Models\Contact\ContactMessage;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ContactMessageReviewService implements ContactMessageReviewServiceInterface
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly FormSubmissionNotificationServiceInterface $notificationService,
    ) {}

    public function markAsRead(int $messageId, int $actorId): bool
    {
        $message = ContactMessage::query()->findOrFail($messageId);
        $actor = $this->authorizedActor($actorId, $message);

        if ($message->read_at !== null) {
            return true;
        }

        $message->forceFill(['read_at' => now(), 'read_by_user_id' => $actor->getKey()])->save();
        $this->auditService->log('contact_message.read', (int) $actor->getKey(), ContactMessage::class, $messageId);

        return true;
    }

    public function markAsUnread(int $messageId, int $actorId): bool
    {
        $message = ContactMessage::query()->findOrFail($messageId);
        $actor = $this->authorizedActor($actorId, $message);
        $message->forceFill(['read_at' => null, 'read_by_user_id' => null])->save();
        $this->auditService->log('contact_message.unread', (int) $actor->getKey(), ContactMessage::class, $messageId);

        return true;
    }

    public function assign(int $messageId, ?int $assigneeId, int $actorId): bool
    {
        $message = ContactMessage::query()->findOrFail($messageId);
        $actor = $this->authorizedActor($actorId, $message);

        if ($assigneeId !== null && ! User::query()->whereKey($assigneeId)->whereIn('role_slug', ['super_admin', 'editor'])->exists()) {
            throw new \InvalidArgumentException('The selected reviewer is not eligible.');
        }

        $message->forceFill([
            'assigned_to_user_id' => $assigneeId,
            'assigned_at' => $assigneeId === null ? null : now(),
            'assigned_by_user_id' => $assigneeId === null ? null : $actor->getKey(),
        ])->save();
        $this->auditService->log('contact_message.assigned', (int) $actor->getKey(), ContactMessage::class, $messageId, ['assignee' => $assigneeId]);

        return true;
    }

    public function updateInternalNotes(int $messageId, ?string $notes, int $actorId): bool
    {
        $message = ContactMessage::query()->findOrFail($messageId);
        $actor = $this->authorizedActor($actorId, $message);
        $message->forceFill(['internal_notes' => $notes !== null ? trim($notes) : null])->save();
        $this->auditService->log('contact_message.notes_updated', (int) $actor->getKey(), ContactMessage::class, $messageId);

        return true;
    }

    public function transitionStatus(int $messageId, ContactMessageStatus $expectedStatus, ContactMessageStatus $newStatus, int $actorId, ?string $reason = null): bool
    {
        $changed = DB::transaction(function () use ($messageId, $expectedStatus, $newStatus, $actorId, $reason): bool {
            $message = ContactMessage::query()->findOrFail($messageId);
            $actor = $this->authorizedActor($actorId, $message);
            $current = ContactMessageStatus::tryFrom((string) $message->status);

            if ($current !== $expectedStatus) {
                throw new ConflictException('The contact message status changed after it was loaded.');
            }

            if (! $expectedStatus->canTransitionTo($newStatus)) {
                throw new \DomainException('The requested contact message transition is not legal.');
            }

            $updated = ContactMessage::query()
                ->whereKey($messageId)
                ->where('status', $expectedStatus->value)
                ->update([
                    'status' => $newStatus->value,
                    'status_changed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new ConflictException('The contact message status changed during this update.');
            }

            if (! $this->auditService->log('contact_message.status_changed', (int) $actor->getKey(), ContactMessage::class, $messageId, [
                'from' => $expectedStatus->value,
                'to' => $newStatus->value,
                'reason' => $reason,
            ])) {
                throw new \RuntimeException('The contact message status audit event could not be recorded.');
            }

            return true;
        });

        if ($changed) {
            $this->notificationService->queueContactStatusChanged($messageId, $expectedStatus->value, $newStatus->value);
        }

        return $changed;
    }

    private function authorizedActor(int $actorId, ContactMessage $message): User
    {
        $actor = User::query()->find($actorId);

        if (! $actor instanceof User || Gate::forUser($actor)->denies('updateReview', $message)) {
            throw new AuthorizationException('This user is not authorized to review contact messages.');
        }

        return $actor;
    }
}
