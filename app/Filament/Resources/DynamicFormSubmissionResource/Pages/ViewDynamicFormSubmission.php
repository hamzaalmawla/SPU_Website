<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicFormSubmissionResource\Pages;

use App\Filament\Resources\DynamicFormSubmissionResource;
use App\Models\Form\DynamicFormSubmission;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ViewDynamicFormSubmission extends ViewRecord
{
    protected static string $resource = DynamicFormSubmissionResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        if (! $record instanceof DynamicFormSubmission) {
            return [];
        }

        $attachment = is_array($record->files_json) ? ($record->files_json['attachment'] ?? null) : null;

        if (! is_array($attachment) || ! is_string($attachment['path'] ?? null)) {
            return [];
        }

        return [
            Action::make('download_attachment')
                ->label('Download private attachment')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => Gate::allows('view', $record))
                ->action(fn () => Storage::disk((string) ($attachment['disk'] ?? 'local'))->download(
                    $attachment['path'],
                    is_string($attachment['original_name'] ?? null) ? $attachment['original_name'] : null,
                )),
        ];
    }
}
