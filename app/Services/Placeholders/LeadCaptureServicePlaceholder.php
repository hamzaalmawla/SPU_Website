<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\LeadCaptureServiceInterface;
use App\DTOs\LeadCaptureDTO;
use BadMethodCallException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Placeholder implementation for lead capture service contract.
 */
final class LeadCaptureServicePlaceholder implements LeadCaptureServiceInterface
{
    public function capture(LeadCaptureDTO $lead): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function markAsContacted(int|string $leadId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function exportCsv(): string
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
