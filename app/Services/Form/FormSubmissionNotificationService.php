<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Contracts\Form\FormSubmissionNotificationServiceInterface;
use App\Enums\FormSubmissionStatus;
use App\Mail\ContactMessageAdminNotification;
use App\Mail\ContactMessageReceived;
use App\Mail\ContactMessageStatusUpdated;
use App\Mail\EventRegistrationReceived;
use App\Mail\FormSubmissionAdminNotification;
use App\Mail\FormSubmissionReceipt;
use App\Mail\FormSubmissionStatusUpdated;
use App\Models\Contact\ContactMessage;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class FormSubmissionNotificationService implements FormSubmissionNotificationServiceInterface
{
    public function queueDynamicReceived(int $submissionId): bool
    {
        $submission = DynamicFormSubmission::query()->find($submissionId);

        if (! $submission instanceof DynamicFormSubmission || ! is_string($submission->applicant_email) || $submission->applicant_email === '') {
            return false;
        }

        $locale = $this->locale((string) $submission->locale);
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        $context = is_array($payload['_context'] ?? null) ? $payload['_context'] : [];
        $name = (string) ($submission->applicant_name ?: $submission->applicant_email);
        $formLabel = Lang::get('form_submissions.forms.'.(string) $submission->form_id, [], $locale);
        $contextTitle = $this->contextTitle($context);

        try {
            $submission->forceFill([
                'email_delivery_status' => 'queued',
                'email_queued_at' => now(),
            ])->save();

            if (($context['source'] ?? null) === 'news-events') {
                Mail::to($submission->applicant_email)->queue(new EventRegistrationReceived(
                    applicantName: $name,
                    eventTitle: $contextTitle ?? '',
                    contentLocale: $locale,
                    submissionId: (int) $submission->getKey(),
                ));
            } else {
                Mail::to($submission->applicant_email)->queue(new FormSubmissionReceipt(
                    referenceNumber: (string) ($submission->reference_number ?: 'SPU-'.$submission->getKey()),
                    formLabel: $formLabel,
                    applicantName: $name,
                    contentLocale: $locale,
                    submissionId: (int) $submission->getKey(),
                    contextTitle: $contextTitle,
                ));
            }

            $this->queueDynamicAdminNotifications($submission, $locale, $formLabel, $name, $contextTitle);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $submission->forceFill([
                'email_delivery_status' => 'failed',
                'email_failed_at' => now(),
                'email_failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            return false;
        }
    }

    public function queueDynamicStatusChanged(int $submissionId, FormSubmissionStatus $from, FormSubmissionStatus $to): bool
    {
        $submission = DynamicFormSubmission::query()->find($submissionId);

        if (! $submission instanceof DynamicFormSubmission || ! is_string($submission->applicant_email) || $submission->applicant_email === '') {
            return false;
        }

        $locale = $this->locale((string) $submission->locale);

        try {
            $submission->forceFill(['email_delivery_status' => 'queued', 'email_queued_at' => now()])->save();
            Mail::to($submission->applicant_email)->queue(new FormSubmissionStatusUpdated(
                referenceNumber: (string) ($submission->reference_number ?: 'SPU-'.$submission->getKey()),
                statusLabel: Lang::get('form_submissions.statuses.'.$to->value, [], $locale),
                applicantName: (string) ($submission->applicant_name ?: $submission->applicant_email),
                contentLocale: $locale,
                submissionId: (int) $submission->getKey(),
            ));

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $submission->forceFill([
                'email_delivery_status' => 'failed',
                'email_failed_at' => now(),
                'email_failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            return false;
        }
    }

    public function queueContactReceived(int $messageId): bool
    {
        $message = ContactMessage::query()->find($messageId);

        if (! $message instanceof ContactMessage || ! is_string($message->email) || $message->email === '') {
            return false;
        }

        $locale = $this->locale((string) $message->locale);

        try {
            $message->forceFill(['email_delivery_status' => 'queued', 'email_queued_at' => now()])->save();
            Mail::to($message->email)->queue(new ContactMessageReceived(
                referenceNumber: (string) ($message->reference_number ?: 'SPU-CONTACT-'.$message->getKey()),
                applicantName: (string) $message->name,
                messageSubject: (string) $message->subject,
                contentLocale: $locale,
                messageId: (int) $message->getKey(),
            ));
            $this->queueContactAdminNotifications($message, $locale);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $message->forceFill([
                'email_delivery_status' => 'failed',
                'email_failed_at' => now(),
                'email_failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            return false;
        }
    }

    public function queueContactStatusChanged(int $messageId, string $from, string $to): bool
    {
        $message = ContactMessage::query()->find($messageId);

        if (! $message instanceof ContactMessage || ! is_string($message->email) || $message->email === '') {
            return false;
        }

        $locale = $this->locale((string) $message->locale);

        try {
            $message->forceFill(['email_delivery_status' => 'queued', 'email_queued_at' => now()])->save();
            Mail::to($message->email)->queue(new ContactMessageStatusUpdated(
                referenceNumber: (string) ($message->reference_number ?: 'SPU-CONTACT-'.$message->getKey()),
                statusLabel: Lang::get('contact_messages.statuses.'.$to, [], $locale),
                applicantName: (string) $message->name,
                contentLocale: $locale,
                messageId: (int) $message->getKey(),
            ));

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $message->forceFill([
                'email_delivery_status' => 'failed',
                'email_failed_at' => now(),
                'email_failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            return false;
        }
    }

    private function queueDynamicAdminNotifications(DynamicFormSubmission $submission, string $locale, string $formLabel, string $name, ?string $contextTitle): void
    {
        $recipients = $this->adminRecipients((string) $submission->form_id);

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->queue(new FormSubmissionAdminNotification(
                referenceNumber: (string) ($submission->reference_number ?: 'SPU-'.$submission->getKey()),
                formLabel: $formLabel,
                applicantName: $name,
                applicantEmail: (string) $submission->applicant_email,
                contentLocale: $locale,
                contextTitle: $contextTitle,
            ));
        }
    }

    private function queueContactAdminNotifications(ContactMessage $message, string $locale): void
    {
        foreach ($this->adminRecipients() as $recipient) {
            Mail::to($recipient)->queue(new ContactMessageAdminNotification(
                referenceNumber: (string) ($message->reference_number ?: 'SPU-CONTACT-'.$message->getKey()),
                applicantName: (string) $message->name,
                applicantEmail: (string) $message->email,
                messageSubject: (string) $message->subject,
                contentLocale: $locale,
            ));
        }
    }

    /** @return list<string> */
    private function adminRecipients(?string $formId = null): array
    {
        $configured = is_array(config('mail.form_admin_recipients')) ? config('mail.form_admin_recipients') : [];
        $roles = $formId === 'job-application'
            ? ['super_admin', 'editor', 'hr']
            : ['super_admin', 'editor'];
        $staff = User::query()->whereIn('role_slug', $roles)->pluck('email')->all();

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $email): string => is_string($email) ? trim($email) : '',
            [...$configured, ...$staff],
        ), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    /** @param array<string, mixed> $context */
    private function contextTitle(array $context): ?string
    {
        foreach (['event_title', 'job_title'] as $key) {
            if (is_string($context[$key] ?? null) && $context[$key] !== '') {
                return $context[$key];
            }
        }

        return null;
    }

    private function locale(string $locale): string
    {
        return $locale === 'ar' ? 'ar' : 'en';
    }
}
