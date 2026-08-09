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

final class FormSubmissionAdminNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $referenceNumber,
        public readonly string $formLabel,
        public readonly string $applicantName,
        public readonly string $applicantEmail,
        public readonly string $contentLocale,
        public readonly ?string $contextTitle = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Lang::get('mail.form.admin.subject', ['reference' => $this->referenceNumber], $this->contentLocale));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.form-submission.admin-notification',
            with: [
                'locale' => $this->contentLocale,
                'referenceNumber' => $this->referenceNumber,
                'formLabel' => $this->formLabel,
                'applicantName' => $this->applicantName,
                'applicantEmail' => $this->applicantEmail,
                'contextTitle' => $this->contextTitle,
            ],
        );
    }
}
