<?php

declare(strict_types=1);

namespace App\Contracts\Form;

use App\Enums\ContactMessageStatus;

interface ContactMessageReviewServiceInterface
{
    public function markAsRead(int $messageId, int $actorId): bool;

    public function markAsUnread(int $messageId, int $actorId): bool;

    public function assign(int $messageId, ?int $assigneeId, int $actorId): bool;

    public function updateInternalNotes(int $messageId, ?string $notes, int $actorId): bool;

    public function transitionStatus(int $messageId, ContactMessageStatus $expectedStatus, ContactMessageStatus $newStatus, int $actorId, ?string $reason = null): bool;
}
