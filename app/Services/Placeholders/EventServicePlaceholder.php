<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\EventServiceInterface;
use App\DTOs\EventDTO;
use App\DTOs\EventWriteDTO;
use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for event service contract.
 */
final class EventServicePlaceholder implements EventServiceInterface
{
    public function create(EventWriteDTO $data): EventDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function findBySlug(string $slug): ?EventDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function update(int|string $eventId, EventWriteDTO $data): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function publish(int|string $eventId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unpublish(int|string $eventId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function schedule(int|string $eventId, Carbon $publishAt): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function upcoming(string $locale): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
