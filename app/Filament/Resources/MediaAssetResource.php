<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MediaAssetResource\Pages;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * Filament resource for managing media assets (upload, search, edit metadata, view).
 *
 * All business logic is delegated to MediaServiceInterface.
 * This resource provides only the UI layer.
 */
class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'filename';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-media');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.media_library');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            return $query;
        }

        if ($user->role_slug !== 'faculty_editor' || ! is_string($user->faculty_scope_slug) || $user->faculty_scope_slug === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('faculty_scope_slug', $user->faculty_scope_slug);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('media_tabs')
                ->tabs([
                    self::uploadTab(),
                    self::arabicMetadataTab(),
                    self::englishMetadataTab(),
                ])
                ->persistTabInQueryString('tab')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(function (MediaAsset $record): ?string {
                        if (str_starts_with($record->mime_type, 'image/')) {
                            return self::publicMediaUrl($record->path, $record->disk);
                        }

                        return null;
                    })
                    ->formatStateUsing(fn (?string $state): HtmlString => self::thumbnailHtml($state))
                    ->html(),

                TextColumn::make('filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('title_en')
                    ->label('Title')
                    ->placeholder('—')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'image/') => 'success',
                        str_starts_with($state, 'video/') => 'info',
                        str_starts_with($state, 'application/pdf') => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('media_type')
                    ->label('Category')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'image' => 'success',
                        'pdf' => 'danger',
                        'document' => 'warning',
                        'video' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => self::formatFileSize($state))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mime_type')
                    ->label('File Type')
                    ->options([
                        'image/' => 'Images',
                        'application/pdf' => 'PDF',
                        'video/' => 'Videos',
                        'application/' => 'Documents',
                    ])
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->where('mime_type', 'like', $data['value'].'%');
                        }
                    }),
                SelectFilter::make('media_type')
                    ->label('Category')
                    ->options([
                        'image' => 'Images',
                        'pdf' => 'PDFs',
                        'document' => 'Documents',
                        'video' => 'Videos',
                        'icon' => 'Icons',
                        'other' => 'Other',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaAssets::route('/'),
            'create' => Pages\CreateMediaAsset::route('/create'),
            'view' => Pages\ViewMediaAsset::route('/{record}'),
            'edit' => Pages\EditMediaAsset::route('/{record}/edit'),
        ];
    }

    // ──────────────────────────────────────────────
    // Form Tab Builders
    // ──────────────────────────────────────────────

    private static function uploadTab(): Tab
    {
        return Tab::make('Upload')
            ->icon('heroicon-o-arrow-up-tray')
            ->schema([
                Section::make('File Upload')->schema([
                    FileUpload::make('file')
                        ->label('File')
                        ->required()
                        ->maxSize(20480)
                        ->disk((string) config('filesystems.media_disk', 'public'))
                        ->directory('media-tmp')
                        ->visibility('public')
                        ->acceptedFileTypes([
                            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'video/mp4', 'video/webm',
                        ])
                        ->hiddenOn(['edit', 'view']),
                ]),
            ]);
    }

    private static function arabicMetadataTab(): Tab
    {
        return Tab::make('العربية (AR)')
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'rtl'])
            ->schema([
                Section::make('Arabic Metadata')->schema([
                    TextInput::make('title_ar')
                        ->label('Title (AR)')
                        ->maxLength(255),

                    TextInput::make('alt_text_ar')
                        ->label('Alt Text (AR)')
                        ->maxLength(500),

                    TextInput::make('caption_ar')
                        ->label('Caption (AR)')
                        ->maxLength(1000),

                    TextInput::make('checksum')
                        ->label('Checksum')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn(['edit', 'view']),

                    TextInput::make('faculty_scope_slug')
                        ->label('Faculty Scope Slug')
                        ->helperText('Optional. Faculty editors only access media matching their scope.')
                        ->maxLength(255)
                        ->alphaDash()
                        ->visible(fn (): bool => in_array(auth()->user()?->role_slug, ['super_admin', 'editor'], true)),
                ]),
            ]);
    }

    private static function englishMetadataTab(): Tab
    {
        return Tab::make('English (EN)')
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'ltr'])
            ->schema([
                Section::make('English Metadata')->schema([
                    TextInput::make('title_en')
                        ->label('Title (EN)')
                        ->maxLength(255),

                    TextInput::make('alt_text_en')
                        ->label('Alt Text (EN)')
                        ->maxLength(500),

                    TextInput::make('caption_en')
                        ->label('Caption (EN)')
                        ->maxLength(1000),

                    TextInput::make('media_type')
                        ->label('Category')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn(['edit', 'view']),
                ]),
            ]);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

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

    private static function thumbnailHtml(?string $url): HtmlString
    {
        if ($url === null || $url === '') {
            return new HtmlString('<span style="display:inline-flex;width:40px;height:40px;border-radius:9999px;background:#f3f4f6;align-items:center;justify-content:center;color:#9ca3af;">-</span>');
        }

        return new HtmlString('<img src="'.e($url).'" alt="" loading="lazy" style="width:40px;height:40px;border-radius:9999px;object-fit:cover;" />');
    }
}
