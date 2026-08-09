<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Form\ContactMessageReviewServiceInterface;
use App\Mail\ContactMessageAdminNotification;
use App\Mail\ContactMessageReceived;
use App\Mail\ContactMessageStatusUpdated;
use App\Mail\FormSubmissionAdminNotification;
use App\Models\Contact\ContactMessage;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

final class ContactMessageReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_actions_track_read_assignment_notes_and_status(): void
    {
        Mail::fake();
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $message = $this->message();
        $service = app(ContactMessageReviewServiceInterface::class);

        $this->assertTrue($service->markAsRead((int) $message->getKey(), (int) $editor->getKey()));
        $this->assertTrue($service->assign((int) $message->getKey(), (int) $editor->getKey(), (int) $editor->getKey()));
        $this->assertTrue($service->updateInternalNotes((int) $message->getKey(), 'Follow up with admissions.', (int) $editor->getKey()));
        $this->assertTrue($service->transitionStatus(
            (int) $message->getKey(),
            \App\Enums\ContactMessageStatus::NEW,
            \App\Enums\ContactMessageStatus::IN_REVIEW,
            (int) $editor->getKey(),
            'Assigned to admissions.',
        ));

        $updated = $message->fresh();
        $this->assertNotNull($updated?->read_at);
        $this->assertSame((int) $editor->getKey(), $updated?->read_by_user_id);
        $this->assertSame((int) $editor->getKey(), $updated?->assigned_to_user_id);
        $this->assertSame('Follow up with admissions.', $updated?->internal_notes);
        $this->assertSame('in_review', $updated?->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact_message.status_changed']);
        Mail::assertQueued(ContactMessageStatusUpdated::class);
    }

    public function test_received_notification_queues_applicant_and_admin_messages(): void
    {
        Mail::fake();
        config()->set('mail.form_admin_recipients', ['forms@example.com']);
        $editor = User::factory()->create(['role_slug' => 'editor', 'email' => 'editor@example.com']);
        $message = $this->message();

        $this->assertTrue(app(\App\Contracts\Form\FormSubmissionNotificationServiceInterface::class)
            ->queueContactReceived((int) $message->getKey()));

        Mail::assertQueued(ContactMessageReceived::class, fn (ContactMessageReceived $mail): bool => $mail->hasTo('visitor@example.com'));
        Mail::assertQueued(ContactMessageAdminNotification::class, fn (ContactMessageAdminNotification $mail): bool => $mail->hasTo('forms@example.com'));
        $this->assertNotNull($message->fresh()?->email_queued_at);
        $this->assertSame('queued', $message->fresh()?->email_delivery_status);
    }

    public function test_queued_mailable_tracks_target_and_records_queue_failure(): void
    {
        $message = $this->message();
        $mail = new ContactMessageReceived(
            referenceNumber: (string) $message->reference_number,
            applicantName: (string) $message->name,
            messageSubject: (string) $message->subject,
            contentLocale: 'en',
            messageId: (int) $message->getKey(),
        );

        $data = $mail->buildViewData();

        $this->assertSame(ContactMessage::class, $data['__spu_form_delivery']['model'] ?? null);
        $this->assertSame((int) $message->getKey(), $data['__spu_form_delivery']['id'] ?? null);

        $mail->failed(new RuntimeException('SMTP unavailable'));

        $this->assertSame('failed', $message->fresh()?->email_delivery_status);
        $this->assertSame('SMTP unavailable', $message->fresh()?->email_failure_reason);
    }

    public function test_hr_receives_job_application_admin_notifications(): void
    {
        Mail::fake();
        $hr = User::factory()->create([
            'role_slug' => 'hr',
            'email' => 'hr@example.com',
        ]);
        $submission = DynamicFormSubmission::query()->create([
            'reference_number' => 'SPU-FORM-HR-TEST',
            'form_id' => 'job-application',
            'locale' => 'en',
            'applicant_name' => 'Applicant',
            'applicant_email' => 'applicant@example.com',
            'status' => 'new',
            'email_delivery_status' => 'pending',
            'payload_json' => ['fullName' => 'Applicant'],
            'files_json' => [],
        ]);

        $this->assertTrue(app(\App\Contracts\Form\FormSubmissionNotificationServiceInterface::class)
            ->queueDynamicReceived((int) $submission->getKey()));

        Mail::assertQueued(FormSubmissionAdminNotification::class, fn (FormSubmissionAdminNotification $mail): bool => $mail->hasTo($hr->email));
    }

    /** @return ContactMessage */
    private function message(): ContactMessage
    {
        return ContactMessage::query()->create([
            'reference_number' => 'SPU-CONTACT-TEST-'.uniqid(),
            'locale' => 'en',
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Admissions question',
            'message' => 'Please contact me.',
            'status' => 'new',
            'email_delivery_status' => 'pending',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
        ]);
    }
}
