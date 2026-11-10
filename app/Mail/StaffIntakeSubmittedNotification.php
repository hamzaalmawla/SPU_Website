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

/** Tells whoever made a staff intake link that a teacher sent their details. */
final class StaffIntakeSubmittedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $reference,
        public readonly string $submitterName,
        public readonly string $facultyName,
        public readonly bool $isNewProfile,
        public readonly bool $possibleDuplicate,
        public readonly string $contentLocale,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Lang::get('staff_intake.mail.subject', ['reference' => $this->reference], $this->contentLocale));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.staff-intake-submitted',
            with: [
                'locale' => $this->contentLocale,
                'reference' => $this->reference,
                'submitterName' => $this->submitterName,
                'facultyName' => $this->facultyName,
                'isNewProfile' => $this->isNewProfile,
                'possibleDuplicate' => $this->possibleDuplicate,
            ],
        );
    }
}
