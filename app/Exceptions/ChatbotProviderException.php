<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An OpenAI call failed. The message is safe to log (no key, no prompt) and
 * `reason` says what kind of failure it was.
 */
final class ChatbotProviderException extends RuntimeException
{
    public const AUTH = 'auth';

    public const RATE_LIMITED = 'rate_limited';

    public const QUOTA = 'quota';

    public const UNAVAILABLE = 'unavailable';

    public const INVALID = 'invalid';

    public function __construct(public readonly string $reason, string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
