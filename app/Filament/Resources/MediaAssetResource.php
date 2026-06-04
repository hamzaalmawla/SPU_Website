<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MediaAssetResource\Pages;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

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

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'filename';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-media');
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
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(function (MediaAsset $record): ?string {
                        if (str_starts_with($record->mime_type, 'image/')) {
                            return $record->path;
                        }

                        return null;
                    })
                    ->disk(fn (MediaAsset $record): string => $record->disk ?? 'local')
                    ->size(40)
                    ->circular(),

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
                        ->disk((string) config('filesystems.default', 'local'))
                        ->directory('media-tmp')
                        ->visibility('private')
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
}
