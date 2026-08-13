<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Media\ImageConversionServiceInterface;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class MediaWebpCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_an_authorized_user_id(): void
    {
        $this->artisan('media:convert-webp')
            ->assertExitCode(2);
    }

    public function test_command_reports_unavailable_conversion_driver(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $this->mock(ImageConversionServiceInterface::class)
            ->shouldReceive('isAvailable')
            ->once()
            ->andReturnFalse();

        $this->artisan('media:convert-webp', ['--user-id' => $user->getKey()])
            ->assertExitCode(1);
    }

    public function test_command_converts_pending_images(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $asset = MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'media/image/2026/08',
            'filename' => 'photo.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'photo'),
            'media_type' => 'image',
            'library_scope' => 'main',
            'metadata_status' => 'reviewed',
            'path' => 'media/image/2026/08/photo.jpg',
        ]);

        $this->mock(ImageConversionServiceInterface::class)
            ->shouldReceive('isAvailable')
            ->once()
            ->andReturnTrue()
            ->shouldReceive('convert')
            ->once()
            ->andReturn(new \App\DTOs\Media\WebpConversionResultDTO('media/image/2026/08/photo.webp', 80, 100, 100));

        $this->artisan('media:convert-webp', ['--user-id' => $user->getKey()])
            ->assertExitCode(0);

        $this->assertSame('media/image/2026/08/photo.webp', $asset->fresh()->webp_path);
    }
}
