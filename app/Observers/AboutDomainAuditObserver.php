<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\AuditServiceInterface;
use Illuminate\Database\Eloquent\Model;

final class AboutDomainAuditObserver
{
    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->log('updated', $model);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    private function log(string $event, Model $model): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        app(AuditServiceInterface::class)->log(
            action: 'about.'.$event,
            userId: (int) $userId,
            entityType: $model::class,
            entityId: (int) $model->getKey(),
            metadata: ['changes' => $model->getChanges()],
        );
    }
}
