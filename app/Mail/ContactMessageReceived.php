<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\TracksFormMailDelivery;
use App\Models\Contact\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

final class ContactMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TracksFormMailDelivery;

    public int $tries = 3;

    public function __construct(
        public readonly string $referenceNumber,
        public readonly string $applicantName,
        public readonly string $messageSubject,
        public readonly string $contentLocale,
        public readonly int $messageId,
    ) {}

    /** @return array{model: class-string<ContactMessage>, id: int} */
    protected function formDeliveryTarget(): array
    {
        return ['model' => ContactMessage::class, 'id' => $this->messageId];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: Lang::get('mail.contact.received.subject', [], $this->contentLocale));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.form-submission.contact-received', with: [
            'locale' => $this->contentLocale,
            'referenceNumber' => $this->referenceNumber,
            'applicantName' => $this->applicantName,
            'subject' => $this->messageSubject,
        ]);
    }
}
