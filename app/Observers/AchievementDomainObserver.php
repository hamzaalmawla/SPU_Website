<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use Illuminate\Database\Eloquent\Model;

final class AchievementDomainObserver
{
    public function created(Model $model): void
    {
        $this->handle('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->handle('updated', $model);
    }

    public function deleted(Model $model): void
    {
        $this->handle('deleted', $model);
    }

    private function handle(string $event, Model $model): void
    {
        app(CacheServiceInterface::class)->flushTags(['public-pages', 'homepage', 'achievements']);

        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        app(AuditServiceInterface::class)->log(
            action: 'achievement.'.$event,
            userId: (int) $userId,
            entityType: $model::class,
            entityId: (int) $model->getKey(),
            metadata: ['changes' => $model->getChanges()],
        );
    }
}
