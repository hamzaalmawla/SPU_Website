<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\Contact\ContactMessage;
use App\Models\Form\DynamicFormSubmission;
use Throwable;

trait TracksFormMailDelivery
{
    /** @return array{model: class-string<ContactMessage|DynamicFormSubmission>, id: int} */
    abstract protected function formDeliveryTarget(): array;

    /** @return array<string, mixed> */
    protected function additionalMessageData(): array
    {
        return array_merge(parent::additionalMessageData(), [
            '__spu_form_delivery' => $this->formDeliveryTarget(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $target = $this->formDeliveryTarget();
        $model = $target['model'];

        $model::query()
            ->whereKey($target['id'])
            ->update([
                'email_delivery_status' => 'failed',
                'email_failed_at' => now(),
                'email_failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
                'updated_at' => now(),
            ]);
    }
}
