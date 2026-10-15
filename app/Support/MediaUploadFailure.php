<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Explains and records a failed media upload in the admin.
 *
 * Every media upload path used to catch Throwable, call report() and show
 * "the technical details were logged for review". But the media service
 * refuses uploads with AuthorizationException (role not allowed, faculty
 * editor without a faculty) and ValidationException (missing title or alt
 * text, file not stored), and Laravel never logs either. An editor whose
 * account could not upload saw a generic error and the log stayed empty.
 *
 * Refusals and validation failures are now shown with their real reason and
 * logged with the account's role; anything else is still reported in full
 * and shown generically.
 */
final class MediaUploadFailure
{
    public static function message(Throwable $exception): string
    {
        if ($exception instanceof AuthorizationException) {
            return str_contains($exception->getMessage(), 'faculty scope')
                ? __('admin.media_picker.failure.no_faculty')
                : __('admin.media_picker.failure.not_allowed');
        }

        if ($exception instanceof ValidationException) {
            return collect($exception->errors())
                ->map(fn (array $messages, string $field): string => match ($field) {
                    'title' => __('admin.media_picker.failure.title_required'),
                    'alt_text' => __('admin.media_picker.failure.alt_required'),
                    // Size, dimension and type rejections carry the useful detail.
                    'file' => (string) ($messages[0] ?? __('admin.media_picker.failure.file_rejected')),
                    default => (string) ($messages[0] ?? ''),
                })
                ->filter()
                ->unique()
                ->implode(' ');
        }

        return __('admin.media_picker.safe_error');
    }

    public static function record(Throwable $exception, string $context): void
    {
        if (! $exception instanceof AuthorizationException && ! $exception instanceof ValidationException) {
            report($exception);

            return;
        }

        try {
            $user = auth()->user();

            Log::warning('Media upload refused: '.$context, [
                'reason' => $exception instanceof ValidationException
                    ? array_keys($exception->errors())
                    : $exception->getMessage(),
                'user_id' => auth()->id(),
                'role' => $user?->getAttribute('role_slug'),
                'has_faculty_scope' => is_string($user?->getAttribute('faculty_scope_slug')) && $user->getAttribute('faculty_scope_slug') !== '',
            ]);
        } catch (Throwable) {
            // Recording the failure must not replace it with another one.
        }
    }
}
