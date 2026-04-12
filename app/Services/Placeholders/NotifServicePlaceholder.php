<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\NotifServiceInterface;
use App\DTOs\ContactSubmissionDTO;
use App\DTOs\LeadCaptureDTO;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for notification service contract.
 */
final class NotifServicePlaceholder implements NotifServiceInterface
{
    public function send(string $channel, int|string $recipientId, array $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function markAsRead(int|string $notificationId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unread(int|string $recipientId): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unreadCount(int|string $recipientId): int
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function sendContactConfirmation(ContactSubmissionDTO $dto): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function notifyAdminNewLead(LeadCaptureDTO $dto): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function sendEmail(string $to, string $template, array $data): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
