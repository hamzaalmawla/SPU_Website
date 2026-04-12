<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\EventDTO;
use App\DTOs\EventWriteDTO;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Defines event management and publication operations.
 */
interface EventServiceInterface
{
    public function create(EventWriteDTO $data): EventDTO;

    /**
     * Find an event by slug.
     */
    public function findBySlug(string $slug): ?EventDTO;

    public function update(int|string $eventId, EventWriteDTO $data): bool;

    public function publish(int|string $eventId): bool;

    public function unpublish(int|string $eventId): bool;

    public function schedule(int|string $eventId, Carbon $publishAt): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;

    /**
     * @return Collection<int, EventDTO>
     */
    public function upcoming(string $locale): Collection;
}
