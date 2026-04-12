<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\ContactSubmissionDTO;
use App\DTOs\LeadCaptureDTO;
use App\DTOs\NotificationDTO;
use Illuminate\Support\Collection;

/**
 * Defines notification delivery and read-state operations.
 */
interface NotifServiceInterface
{
    /**
     * Send a notification.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(string $channel, int|string $recipientId, array $payload): bool;

    /**
     * Mark a notification as read.
     */
    public function markAsRead(int|string $notificationId): bool;

    /**
     * Get unread notifications for a recipient.
     *
     * @return Collection<int, NotificationDTO>
     */
    public function unread(int|string $recipientId): Collection;

    /**
     * Count unread notifications for a recipient.
     */
    public function unreadCount(int|string $recipientId): int;

    /**
     * Send contact-form confirmation to the submitter.
     */
    public function sendContactConfirmation(ContactSubmissionDTO $dto): void;

    /**
     * Notify admins about a newly captured lead.
     */
    public function notifyAdminNewLead(LeadCaptureDTO $dto): void;

    /**
     * Send an email using a template and payload data.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendEmail(string $to, string $template, array $data): bool;
}
