<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Contact\ContactMessage;
use App\Models\Form\DynamicFormSubmission;
use Illuminate\Mail\Events\MessageSent;

final class TrackFormMailSent
{
    public function handle(MessageSent $event): void
    {
        $target = $event->data['__spu_form_delivery'] ?? null;

        if (! is_array($target) || ! is_string($target['model'] ?? null) || ! is_int($target['id'] ?? null)) {
            return;
        }

        $model = $target['model'];

        if (! in_array($model, [ContactMessage::class, DynamicFormSubmission::class], true)) {
            return;
        }

        $model::query()
            ->whereKey($target['id'])
            ->update([
                'email_delivery_status' => 'sent',
                'email_sent_at' => now(),
                'email_failure_reason' => null,
                'updated_at' => now(),
            ]);
    }
}
