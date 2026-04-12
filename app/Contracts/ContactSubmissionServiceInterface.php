<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\ContactSubmissionDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Defines contact submission intake and moderation operations.
 */
interface ContactSubmissionServiceInterface
{
    /**
     * Store a new contact submission.
     */
    public function submit(ContactSubmissionDTO $submission): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;

    /**
     * Mark a submission as resolved.
     */
    public function markAsResolved(int|string $submissionId): bool;
}
