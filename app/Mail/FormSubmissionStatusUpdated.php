<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\TracksFormMailDelivery;
use App\Models\Form\DynamicFormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

final class FormSubmissionStatusUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TracksFormMailDelivery;

    public int $tries = 3;

    public function __construct(
        public readonly string $referenceNumber,
        public readonly string $statusLabel,
        public readonly string $applicantName,
        public readonly string $contentLocale,
        public readonly int $submissionId,
    ) {}

    /** @return array{model: class-string<DynamicFormSubmission>, id: int} */
    protected function formDeliveryTarget(): array
    {
        return ['model' => DynamicFormSubmission::class, 'id' => $this->submissionId];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: Lang::get('mail.form.status.subject', ['reference' => $this->referenceNumber], $this->contentLocale));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.form-submission.status-updated',
            with: [
                'locale' => $this->contentLocale,
                'referenceNumber' => $this->referenceNumber,
                'statusLabel' => $this->statusLabel,
                'applicantName' => $this->applicantName,
            ],
        );
    }
}
