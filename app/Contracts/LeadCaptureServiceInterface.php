<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\LeadCaptureDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Defines lead capture intake and management operations.
 */
interface LeadCaptureServiceInterface
{
    /**
     * Capture a new lead.
     */
    public function capture(LeadCaptureDTO $lead): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;

    /**
     * Mark a lead as contacted.
     */
    public function markAsContacted(int|string $leadId): bool;

    /**
     * Export captured leads as CSV content.
     */
    public function exportCsv(): string;
}
