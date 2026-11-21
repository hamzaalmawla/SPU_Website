<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Media\MediaServiceInterface;
use App\Models\User\User;
use App\Support\MediaUploadFailure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Media upload refusals used to show "details were logged" while Laravel
 * logged nothing (authorization and validation exceptions are never reported).
 */
class MediaUploadFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_that_may_not_add_media_is_told_so_and_it_is_logged_with_its_role(): void
    {
        $user = User::factory()->create(['role_slug' => 'hr']);
        $this->actingAs($user, 'web');
        Log::spy();

        try {
            app(MediaServiceInterface::class)->upload([
                'file' => UploadedFile::fake()->image('campus.jpg', 400, 300),
                'title_en' => 'Campus',
                'alt_text_en' => 'Campus view',
                'require_alt_text' => true,
                'uploaded_by' => $user->id,
            ]);
            $this->fail('The upload should have been refused.');
        } catch (AuthorizationException $exception) {
            MediaUploadFailure::record($exception, 'media picker upload');

            $this->assertSame(__('admin.media_picker.failure.not_allowed'), MediaUploadFailure::message($exception));
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Media upload refused')
                && $context['role'] === 'hr'
                && $context['user_id'] === $user->id);
    }

    public function test_a_faculty_editor_without_a_faculty_gets_the_specific_reason(): void
    {
        $exception = new AuthorizationException('Faculty editors must have a faculty scope to upload media.');

        $this->assertSame(__('admin.media_picker.failure.no_faculty'), MediaUploadFailure::message($exception));
    }

    public function test_validation_failures_show_their_reason(): void
    {
        $exception = ValidationException::withMessages([
            'title' => ['A title is required for main media uploads.'],
            'alt_text' => ['Alt text is required for CMS image uploads.'],
        ]);

        $message = MediaUploadFailure::message($exception);

        $this->assertStringContainsString(__('admin.media_picker.failure.title_required'), $message);
        $this->assertStringContainsString(__('admin.media_picker.failure.alt_required'), $message);
    }

    public function test_unexpected_errors_are_still_reported_in_full_and_shown_generically(): void
    {
        $exception = new \RuntimeException('Disk exploded');
        Log::spy();

        MediaUploadFailure::record($exception, 'media picker');

        $this->assertSame(__('admin.media_picker.safe_error'), MediaUploadFailure::message($exception));
        Log::shouldHaveReceived('error')->once();
    }
}
