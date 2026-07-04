<?php

declare(strict_types=1);

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Filament\Resources\MediaAssetResource;
use App\Support\MediaUrlResolver;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewMediaAsset extends ViewRecord
{
    protected static string $resource = MediaAssetResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Preview')
                ->schema([
                    TextEntry::make('preview')
                        ->label('Preview')
                        ->getStateUsing(fn ($record): ?string => self::publicMediaUrl($record->path ?? null, $record->disk ?? null))
                        ->formatStateUsing(fn (?string $state): HtmlString => self::previewHtml($state))
                        ->html()
                        ->visible(fn ($record): bool => str_starts_with($record->mime_type ?? '', 'image/')),

                    TextEntry::make('mime_type')
                        ->label('File Type')
                        ->visible(fn ($record): bool => ! str_starts_with($record->mime_type ?? '', 'image/'))
                        ->badge()
                        ->size(TextEntry\TextEntrySize::Large),
                ]),

            Section::make('File Information')
                ->columns(2)
                ->schema([
                    TextEntry::make('filename')
                        ->label('Filename'),

                    TextEntry::make('original_name')
                        ->label('Original Name'),

                    TextEntry::make('mime_type')
                        ->label('MIME Type'),

                    TextEntry::make('media_type')
                        ->label('Category')
                        ->badge(),

                    TextEntry::make('extension')
                        ->label('Extension'),

                    TextEntry::make('size_bytes')
                        ->label('Size')
                        ->formatStateUsing(fn (int $state): string => self::formatFileSize($state)),

                    TextEntry::make('checksum')
                        ->label('Checksum')
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('public_url')
                        ->label('URL')
                        ->getStateUsing(fn ($record): string => self::publicMediaUrl($record->path ?? null, $record->disk ?? null) ?? '')
                        ->copyable(),

                    TextEntry::make('width')
                        ->label('Width (px)')
                        ->placeholder('N/A'),

                    TextEntry::make('height')
                        ->label('Height (px)')
                        ->placeholder('N/A'),

                    TextEntry::make('created_at')
                        ->label('Uploaded At')
                        ->dateTime(),

                    TextEntry::make('uploadedBy.name')
                        ->label('Uploaded By')
                        ->placeholder('—'),
                ]),

            Section::make('Arabic Metadata')
                ->columns(1)
                ->schema([
                    TextEntry::make('title_ar')
                        ->label('Title (AR)')
                        ->placeholder('—'),

                    TextEntry::make('alt_text_ar')
                        ->label('Alt Text (AR)')
                        ->placeholder('—'),

                    TextEntry::make('caption_ar')
                        ->label('Caption (AR)')
                        ->placeholder('—'),
                ]),

            Section::make('English Metadata')
                ->columns(1)
                ->schema([
                    TextEntry::make('title_en')
                        ->label('Title (EN)')
                        ->placeholder('—'),

                    TextEntry::make('alt_text_en')
                        ->label('Alt Text (EN)')
                        ->placeholder('—'),

                    TextEntry::make('caption_en')
                        ->label('Caption (EN)')
                        ->placeholder('—'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    private static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private static function configuredDisk(?string $disk): string
    {
        $disks = config('filesystems.disks', []);

        if (is_string($disk) && array_key_exists($disk, is_array($disks) ? $disks : [])) {
            return $disk;
        }

        return (string) config('filesystems.media_disk', 'public');
    }

    private static function publicMediaUrl(?string $path, ?string $disk): ?string
    {
        $url = MediaUrlResolver::resolve($path, self::configuredDisk($disk));

        if (is_string($url) && str_starts_with($url, '/')) {
            return url($url);
        }

        return $url;
    }

    private static function previewHtml(?string $url): HtmlString
    {
        if ($url === null || $url === '') {
            return new HtmlString('<span>No preview available.</span>');
        }

        return new HtmlString('<img src="'.e($url).'" alt="" loading="lazy" style="max-width:100%;max-height:300px;border-radius:12px;object-fit:contain;" />');
    }
}
