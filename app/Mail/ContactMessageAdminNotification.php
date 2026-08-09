<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

final class ContactMessageAdminNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $referenceNumber,
        public readonly string $applicantName,
        public readonly string $applicantEmail,
        public readonly string $messageSubject,
        public readonly string $contentLocale,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Lang::get('mail.contact.admin.subject', ['reference' => $this->referenceNumber], $this->contentLocale));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.form-submission.contact-admin-notification', with: [
            'locale' => $this->contentLocale,
            'referenceNumber' => $this->referenceNumber,
            'applicantName' => $this->applicantName,
            'applicantEmail' => $this->applicantEmail,
                'subject' => $this->messageSubject,
        ]);
    }
}
