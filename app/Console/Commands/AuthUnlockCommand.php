<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Auth\AuthServiceInterface;
use Illuminate\Console\Command;

/**
 * Lifts a sign-in lock from the console, for when every administrator who
 * could lift it in the panel is locked out too.
 */
final class AuthUnlockCommand extends Command
{
    protected $signature = 'auth:unlock {email : Email address of the account to unlock}';

    protected $description = 'Unlock an admin account and reset its failed sign-in count';

    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! $this->authService->unlockAccount($email)) {
            $this->error('No account uses that email address.');

            return self::FAILURE;
        }

        $this->info('The account is unlocked and its failed sign-in count is reset.');

        return self::SUCCESS;
    }
}
