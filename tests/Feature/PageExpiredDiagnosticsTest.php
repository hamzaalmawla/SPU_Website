<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Tests\TestCase;

/**
 * Verifies that the silent causes behind "This page has expired" are logged.
 */
class PageExpiredDiagnosticsTest extends TestCase
{
    public function test_a_csrf_mismatch_is_logged_with_its_cause_and_without_secrets(): void
    {
        Log::spy();

        report(new TokenMismatchException('CSRF token mismatch.'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'CSRF token mismatch')
                && isset($context['cause'], $context['path'])
                && ! array_key_exists('token', $context));
    }

    public function test_a_typeerror_livewire_turns_into_a_419_is_logged_in_production(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        \Livewire\trigger('exception', new \stdClass, new \TypeError('Argument #1 must be of type int'), static fn () => null);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'Livewire swallowed a TypeError'));
    }

    public function test_uploads_are_not_throttled_below_a_full_media_batch(): void
    {
        $this->assertSame('throttle:300,1', FileUploadConfiguration::middleware());
        $this->assertSame(30, FileUploadConfiguration::maxUploadTime());
    }
}
