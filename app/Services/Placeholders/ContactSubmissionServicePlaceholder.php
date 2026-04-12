<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\ContactSubmissionServiceInterface;
use App\DTOs\ContactSubmissionDTO;
use BadMethodCallException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Placeholder implementation for contact submission service contract.
 */
final class ContactSubmissionServicePlaceholder implements ContactSubmissionServiceInterface
{
    public function submit(ContactSubmissionDTO $submission): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function markAsResolved(int|string $submissionId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
