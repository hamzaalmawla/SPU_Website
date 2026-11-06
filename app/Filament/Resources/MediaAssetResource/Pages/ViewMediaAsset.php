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
            Section::make(__('admin_resources.media_asset.preview'))
                ->schema([
                    TextEntry::make('preview')
                        ->label(__('admin_resources.media_asset.preview'))
                        ->getStateUsing(fn ($record): ?string => self::publicMediaUrl($record->path ?? null, $record->disk ?? null))
                        ->formatStateUsing(fn (?string $state): HtmlString => self::previewHtml($state))
                        ->html()
                        ->visible(fn ($record): bool => str_starts_with($record->mime_type ?? '', 'image/')),

                    TextEntry::make('mime_type')
                        ->label(__('admin_resources.media_asset.file_type'))
                        ->visible(fn ($record): bool => ! str_starts_with($record->mime_type ?? '', 'image/'))
                        ->badge()
                        ->size(TextEntry\TextEntrySize::Large),
                ]),

            Section::make(__('admin_resources.media_asset.file_information'))
                ->columns(2)
                ->schema([
                    TextEntry::make('filename')
                        ->label(__('admin_resources.media_asset.filename')),

                    TextEntry::make('original_name')
                        ->label(__('admin_resources.media_asset.original_name')),

                    TextEntry::make('mime_type')
                        ->label(__('admin_resources.media_asset.mime_type')),

                    TextEntry::make('media_type')
                        ->label(__('admin_resources.media_asset.category'))
                        ->badge(),

                    TextEntry::make('metadata_status')
                        ->label(__('admin_resources.media_asset.metadata_status'))
                        ->badge(),

                    TextEntry::make('extension')
                        ->label(__('admin_resources.media_asset.extension')),

                    TextEntry::make('size_bytes')
                        ->label(__('admin_resources.media_asset.size'))
                        ->formatStateUsing(fn (int $state): string => self::formatFileSize($state)),

                    TextEntry::make('checksum')
                        ->label(__('admin_resources.media_asset.checksum'))
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('public_url')
                        ->label(__('admin_resources.media_asset.url'))
                        ->getStateUsing(fn ($record): string => self::publicMediaUrl($record->path ?? null, $record->disk ?? null) ?? '')
                        ->copyable(),

                    TextEntry::make('width')
                        ->label(__('admin_resources.media_asset.width_px'))
                        ->placeholder(__('admin_resources.media_asset.n_a')),

                    TextEntry::make('height')
                        ->label(__('admin_resources.media_asset.height_px'))
                        ->placeholder(__('admin_resources.media_asset.n_a')),

                    TextEntry::make('display_fit')
                        ->label(__('admin_resources.media_asset.display_mode'))
                        ->badge()
                        ->visible(fn ($record): bool => str_starts_with($record->mime_type ?? '', 'image/')),

                    TextEntry::make('focal_x')
                        ->label(__('admin_resources.media_asset.horizontal_focus'))
                        ->suffix('%')
                        ->visible(fn ($record): bool => str_starts_with($record->mime_type ?? '', 'image/')),

                    TextEntry::make('focal_y')
                        ->label(__('admin_resources.media_asset.vertical_focus'))
                        ->suffix('%')
                        ->visible(fn ($record): bool => str_starts_with($record->mime_type ?? '', 'image/')),

                    TextEntry::make('created_at')
                        ->label(__('admin_resources.media_asset.uploaded_at'))
                        ->dateTime(),

                    TextEntry::make('uploadedBy.name')
                        ->label(__('admin_resources.media_asset.uploaded_by'))
                        ->placeholder('—'),
                ]),

            Section::make(__('admin_resources.media_asset.arabic_metadata'))
                ->columns(1)
                ->schema([
                    TextEntry::make('title_ar')
                        ->label(__('admin_resources.media_asset.title_ar'))
                        ->placeholder('—'),

                    TextEntry::make('alt_text_ar')
                        ->label(__('admin_resources.media_asset.alt_text_ar'))
                        ->placeholder('—'),

                    TextEntry::make('caption_ar')
                        ->label(__('admin_resources.media_asset.caption_ar'))
                        ->placeholder('—'),
                ]),

            Section::make(__('admin_resources.media_asset.english_metadata'))
                ->columns(1)
                ->schema([
                    TextEntry::make('title_en')
                        ->label(__('admin_resources.media_asset.title_en'))
                        ->placeholder('—'),

                    TextEntry::make('alt_text_en')
                        ->label(__('admin_resources.media_asset.alt_text_en'))
                        ->placeholder('—'),

                    TextEntry::make('caption_en')
                        ->label(__('admin_resources.media_asset.caption_en'))
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

    private static function publicMediaUrl(?string $path, ?string $disk): ?string
    {
        $url = MediaUrlResolver::resolve($path, $disk);

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
