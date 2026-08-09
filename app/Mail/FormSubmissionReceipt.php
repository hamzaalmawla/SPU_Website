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

final class FormSubmissionReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TracksFormMailDelivery;

    public int $tries = 3;

    public function __construct(
        public readonly string $referenceNumber,
        public readonly string $formLabel,
        public readonly string $applicantName,
        public readonly string $contentLocale,
        public readonly int $submissionId,
        public readonly ?string $contextTitle = null,
    ) {}

    /** @return array{model: class-string<DynamicFormSubmission>, id: int} */
    protected function formDeliveryTarget(): array
    {
        return ['model' => DynamicFormSubmission::class, 'id' => $this->submissionId];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: Lang::get('mail.form.receipt.subject', [], $this->contentLocale));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.form-submission.receipt',
            with: [
                'locale' => $this->contentLocale,
                'referenceNumber' => $this->referenceNumber,
                'formLabel' => $this->formLabel,
                'applicantName' => $this->applicantName,
                'contextTitle' => $this->contextTitle,
            ],
        );
    }
}
