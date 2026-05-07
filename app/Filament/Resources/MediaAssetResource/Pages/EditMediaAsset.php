<?php

declare(strict_types=1);

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Contracts\MediaServiceInterface;
use App\Filament\Resources\MediaAssetResource;
use App\Models\MediaAsset;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;

    private MediaServiceInterface $mediaService;

    public function boot(MediaServiceInterface $mediaService): void
    {
        $this->mediaService = $mediaService;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MediaAsset $record */
        $metadata = [
            'title_ar' => $data['title_ar'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'alt_text_ar' => $data['alt_text_ar'] ?? null,
            'alt_text_en' => $data['alt_text_en'] ?? null,
            'caption_ar' => $data['caption_ar'] ?? null,
            'caption_en' => $data['caption_en'] ?? null,
        ];

        if (array_key_exists('faculty_scope_slug', $data)) {
            $metadata['faculty_scope_slug'] = $data['faculty_scope_slug'];
        }

        $this->mediaService->updateMetadata($record->id, $metadata, (int) auth()->id());

        Notification::make()
            ->title('Media metadata updated')
            ->success()
            ->send();

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->action(function (): void {
                    /** @var MediaAsset $record */
                    $record = $this->record;
                    $this->mediaService->delete($record->id, (int) auth()->id());

                    Notification::make()
                        ->title('Media asset deleted')
                        ->success()
                        ->send();

                    $this->redirect(MediaAssetResource::getUrl('index'));
                }),
        ];
    }
}
