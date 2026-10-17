<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Media\ImageConversionServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Filament\Resources\MediaAssetResource\Pages\BatchUploadMediaAssets;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch upload and temp-file handling that used to fail on the live host.
 */
class MediaBatchUploadHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'public']);
        Storage::fake('public');
    }

    public function test_a_batch_is_stored_a_few_images_per_request_until_done(): void
    {
        $this->actingAs(User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]));

        $component = Livewire::test(BatchUploadMediaAssets::class)
            ->set('data.files', array_map(
                static fn (int $n): UploadedFile => UploadedFile::fake()->image("photo-{$n}.jpg", 640 + $n, 480),
                range(1, 6),
            ))
            ->call('prepareImageDetails');

        foreach (array_keys($component->get('data.items')) as $key) {
            $component->set("data.items.{$key}.alt_text_en", 'A campus photo');
            $component->set("data.items.{$key}.alt_text_ar", 'صورة من الحرم');
        }

        $component->call('storeBatch');

        // First request stores four and asks the browser to continue.
        $this->assertSame(4, $component->get('uploadedCount'));
        $this->assertCount(2, $component->get('pendingUploads'));
        $this->assertSame(4, MediaAsset::query()->count());

        $component->call('continueBatch')->assertRedirect();

        $this->assertSame(6, MediaAsset::query()->count());
        $this->assertSame([], $component->get('pendingUploads'));
    }

    public function test_the_upload_button_submits_to_a_method_livewire_does_not_reserve(): void
    {
        $this->actingAs(User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]));

        // In the browser $wire.upload() is Livewire's own file-upload helper, so a
        // form submitting to "upload" never reached the page and stored nothing.
        Livewire::test(BatchUploadMediaAssets::class)
            ->assertSeeHtml('wire:submit="storeBatch"')
            ->assertDontSeeHtml('wire:submit="upload"');
    }

    public function test_the_queue_cannot_be_pointed_at_other_files_from_the_browser(): void
    {
        $this->actingAs(User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BatchUploadMediaAssets::class)
            ->set('pendingUploads', [['path' => 'media/image/someone-elses.jpg', 'item' => []]]);
    }

    public function test_a_png_named_jpg_is_stored_instead_of_refused(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->actingAs($user);
        $png = UploadedFile::fake()->image('really-a-png.png', 300, 200);
        Storage::disk('public')->putFileAs('media-tmp', $png, 'abc--holiday.jpg');

        $component = Livewire::test(BatchUploadMediaAssets::class);
        $method = new \ReflectionMethod($component->instance(), 'storeOne');
        $method->invoke($component->instance(), Storage::disk('public'), 'media-tmp/abc--holiday.jpg', [
            'original_name' => 'holiday.jpg',
            'title_en' => 'Holiday',
            'alt_text_en' => 'Holiday photo',
        ]);

        $asset = MediaAsset::query()->sole();
        $this->assertSame('image/png', $asset->mime_type);
        $this->assertSame('holiday.jpg', $asset->original_name);
    }

    public function test_webp_conversion_refuses_images_the_memory_limit_cannot_decode(): void
    {
        $service = app(ImageConversionServiceInterface::class);
        $guard = new \ReflectionMethod($service, 'hasMemoryToDecode');

        $this->assertTrue($guard->invoke($service, 1200, 800));
        // 20000x20000 needs over 3 GB to decode, beyond even the raised 1 GB cap.
        $this->assertFalse($guard->invoke($service, 20000, 20000));
    }

    public function test_abandoned_temp_uploads_are_pruned_and_recent_ones_kept(): void
    {
        $disk = Storage::disk('public');
        $disk->put('media-tmp/old.jpg', 'old');
        $disk->put('media-tmp/new.jpg', 'new');
        touch($disk->path('media-tmp/old.jpg'), now()->subDays(2)->getTimestamp());

        $this->assertSame(1, app(MediaServiceInterface::class)->pruneTemporaryUploads(24, true));
        $this->artisan('media:prune-temp')->assertSuccessful();

        $this->assertFalse($disk->exists('media-tmp/old.jpg'));
        $this->assertTrue($disk->exists('media-tmp/new.jpg'));
    }
}
