<?php

declare(strict_types=1);

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Contracts\MediaServiceInterface;
use App\Filament\Resources\MediaAssetResource;
use App\Models\MediaAsset;
use App\Models\User;
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
            $disk = Storage::disk('public');
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
                ]);

                // Clean up the temporary Filament upload
                $disk->delete($filePath);

                Notification::make()
                    ->title('Media asset uploaded successfully')
                    ->success()
                    ->send();

                return MediaAsset::findOrFail($result->mediaId);
            }
        }

        Notification::make()
            ->title('Media upload failed')
            ->body('The uploaded file could not be processed by the media service.')
            ->danger()
            ->send();

        throw new \RuntimeException('Media upload failed before a valid asset could be created.');
    }
}
