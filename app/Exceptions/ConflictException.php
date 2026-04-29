<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when an optimistic locking conflict is detected during draft save operations.
 *
 * Indicates that the resource has been modified by another editor since it was loaded.
 */
final class ConflictException extends \RuntimeException
{
    public function __construct(
        string $message = 'Resource has been modified by another process.',
        public readonly ?int $currentVersion = null,
    ) {
        parent::__construct($message);
    }
}
