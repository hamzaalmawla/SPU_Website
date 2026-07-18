<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class EventRegistrationReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $applicantName,
        public readonly string $eventTitle,
        public readonly string $contentLocale,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->contentLocale === 'ar' ? 'استلام طلب التسجيل في الفعالية' : 'Event registration received',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-registration-received');
    }
}
