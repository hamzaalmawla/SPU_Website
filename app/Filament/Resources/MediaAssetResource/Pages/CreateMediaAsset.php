<?php

declare(strict_types=1);

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Contracts\Media\MediaServiceInterface;
use App\Filament\Resources\MediaAssetResource;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    private MediaServiceInterface $mediaService;

    public function boot(MediaServiceInterface $mediaService): void
    {
        $this->mediaService = $mediaService;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        $filePath = $data['file'] ?? null;

        if (is_string($filePath)) {
            $disk = Storage::disk((string) config('filesystems.media_disk', 'public'));
            $fullPath = $disk->path($filePath);

            if (file_exists($fullPath)) {
                $file = new UploadedFile(
                    $fullPath,
                    basename($filePath),
                    $disk->mimeType($filePath) ?: null,
                    null,
                    true,
                );

                $result = $this->mediaService->upload([
                    'file' => $file,
                    'directory' => 'media',
                    'title_ar' => $data['title_ar'] ?? null,
                    'title_en' => $data['title_en'] ?? null,
                    'alt_text_ar' => $data['alt_text_ar'] ?? null,
                    'alt_text_en' => $data['alt_text_en'] ?? null,
                    'caption_ar' => $data['caption_ar'] ?? null,
                    'caption_en' => $data['caption_en'] ?? null,
                    'uploaded_by' => $user->id,
                    'faculty_scope_slug' => $data['faculty_scope_slug'] ?? null,
                    'focal_x' => $data['focal_x'] ?? 50,
                    'focal_y' => $data['focal_y'] ?? 50,
                    'display_fit' => $data['display_fit'] ?? 'cover',
                ]);

                // Clean up the temporary Filament upload
                $disk->delete($filePath);

                Notification::make()
                    ->title(__('admin_resources.media_asset.media_asset_uploaded_successfully'))
                    ->success()
                    ->send();

                return MediaAsset::findOrFail($result->mediaId);
            }
        }

        Notification::make()
            ->title(__('admin_resources.media_asset.media_upload_failed'))
            ->body(__('admin_resources.media_asset.the_uploaded_file_could_not_be_processed_by_the_media_servic'))
            ->danger()
            ->send();

        throw new \RuntimeException('Media upload failed before a valid asset could be created.');
    }
}
