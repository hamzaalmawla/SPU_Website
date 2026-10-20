<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Filament\Components\FocalPointPicker;
use App\Filament\Resources\MediaAssetResource\Pages;
use App\Filament\Support\GrantedFacultySelect;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

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

    public static function getModelLabel(): string
    {
        return __('admin_resources.media_asset.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.media_asset.plural_model');
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

        $query->where('library_scope', 'main');

        if (in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            return $query;
        }

        if (! $user->isLimitedEditor()) {
            return $query->whereRaw('1 = 0');
        }

        $scopes = app(AdminAccessServiceInterface::class)->facultyScopeValues($user);

        return $query->where(function (Builder $builder) use ($scopes, $user): void {
            $builder->where('uploaded_by', $user->getKey());

            if ($scopes !== []) {
                $builder->orWhereIn('faculty_scope_slug', $scopes);
            }
        });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('media_tabs')
                ->tabs([
                    self::uploadTab(),
                    self::arabicMetadataTab(),
                    self::englishMetadataTab(),
                    self::displayTab(),
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
                    ->label(__('admin_resources.media_asset.filename'))
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('title_en')
                    ->label(__('admin_resources.media_asset.title'))
                    ->placeholder('—')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('mime_type')
                    ->label(__('admin_resources.media_asset.type'))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'image/') => 'success',
                        str_starts_with($state, 'video/') => 'info',
                        str_starts_with($state, 'application/pdf') => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('media_type')
                    ->label(__('admin_resources.media_asset.category'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'image' => 'success',
                        'pdf' => 'danger',
                        'document' => 'warning',
                        'video' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('metadata_status')
                    ->label(__('admin_resources.media_asset.metadata'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'reviewed' => 'success',
                        'auto_generated' => 'warning',
                        'missing' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('size_bytes')
                    ->label(__('admin_resources.media_asset.size'))
                    ->formatStateUsing(fn (int $state): string => self::formatFileSize($state))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin_resources.media_asset.uploaded'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mime_type')
                    ->label(__('admin_resources.media_asset.file_type'))
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
                    ->label(__('admin_resources.media_asset.category'))
                    ->options([
                        'image' => __('admin_resources.media_asset.images'),
                        'pdf' => __('admin_resources.media_asset.pdfs'),
                        'document' => __('admin_resources.media_asset.documents'),
                        'video' => __('admin_resources.media_asset.videos'),
                        'icon' => __('admin_resources.media_asset.icons'),
                        'other' => __('admin_resources.media_asset.other'),
                    ]),
                SelectFilter::make('metadata_status')
                    ->label(__('admin_resources.media_asset.metadata_status'))
                    ->options(self::metadataStatusOptions()),
                Filter::make('missing_title')
                    ->label(__('admin_resources.media_asset.missing_title'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('title_ar')->whereNull('title_en')),
                Filter::make('missing_image_alt')
                    ->label(__('admin_resources.media_asset.images_missing_alt_text'))
                    ->query(fn (Builder $query): Builder => $query->where('media_type', 'image')->whereNull('alt_text_ar')->whereNull('alt_text_en')),
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
            'batch-upload' => Pages\BatchUploadMediaAssets::route('/batch-upload'),
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
        return Tab::make('Upload')->label(__('admin_resources.media_asset.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->schema([
                Section::make(__('admin_resources.media_asset.file_upload'))->schema([
                    FileUpload::make('file')
                        ->label(__('admin_resources.media_asset.file'))
                        ->required()
                        // Re-renders the framing tab with the new image.
                        ->live()
                        ->maxSize(10240)
                        ->disk((string) config('filesystems.media_disk', 'public'))
                        ->directory('media-tmp')
                        ->visibility('public')
                        ->orientImagesFromExif()
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
                Section::make(__('admin_resources.media_asset.arabic_metadata'))->schema([
                    TextInput::make('title_ar')
                        ->label(__('admin_resources.media_asset.title_ar'))
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => ! self::filledString($get('title_en'))),

                    TextInput::make('alt_text_ar')
                        ->label(__('admin_resources.media_asset.alt_text_ar'))
                        ->maxLength(500),

                    TextInput::make('caption_ar')
                        ->label(__('admin_resources.media_asset.caption_ar'))
                        ->maxLength(1000),

                    TextInput::make('checksum')
                        ->label(__('admin_resources.media_asset.checksum'))
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn(['edit', 'view']),

                    TextInput::make('faculty_scope_slug')
                        ->label(__('admin_resources.media_asset.faculty_scope_slug'))
                        ->helperText(__('admin_resources.media_asset.optional_faculty_editors_only_access_media_matching_their_sc'))
                        ->maxLength(255)
                        ->alphaDash()
                        ->visible(fn (): bool => in_array(auth()->user()?->role_slug, ['super_admin', 'editor'], true)),

                    GrantedFacultySelect::make('faculty_scope_slug', __('admin_resources.media_asset.faculty_scope_slug')),
                ]),
            ]);
    }

    private static function englishMetadataTab(): Tab
    {
        return Tab::make('English (EN)')->label(__('admin_resources.media_asset.english_en'))
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'ltr'])
            ->schema([
                Section::make(__('admin_resources.media_asset.english_metadata'))->schema([
                    TextInput::make('title_en')
                        ->label(__('admin_resources.media_asset.title_en'))
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => ! self::filledString($get('title_ar'))),

                    TextInput::make('alt_text_en')
                        ->label(__('admin_resources.media_asset.alt_text_en'))
                        ->maxLength(500),

                    TextInput::make('caption_en')
                        ->label(__('admin_resources.media_asset.caption_en'))
                        ->maxLength(1000),

                    TextInput::make('media_type')
                        ->label(__('admin_resources.media_asset.category'))
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn(['edit', 'view']),

                    Select::make('metadata_status')
                        ->label(__('admin_resources.media_asset.metadata_status'))
                        ->options(self::metadataStatusOptions())
                        ->default('missing')
                        ->required()
                        ->visibleOn(['edit', 'view']),
                ]),
            ]);
    }

    private static function displayTab(): Tab
    {
        return Tab::make('Crop & Display')->label(__('admin_resources.media_asset.crop_display'))
            ->icon('heroicon-o-viewfinder-circle')
            ->visible(fn (?MediaAsset $record): bool => $record === null || str_starts_with((string) $record->mime_type, 'image/'))
            ->schema([
                Section::make(__('admin_resources.media_asset.image_framing'))
                    ->description(__('admin_resources.media_asset.use_the_visual_framing_control_to_choose_the_visible_crop_wi'))
                    ->schema([
                        FileUpload::make('replacement_file')
                            ->label(__('admin_resources.media_asset.replace_existing_image'))
                            ->helperText(__('admin_resources.media_asset.optional_upload_a_replacement_then_use_the_visual_framing_co'))
                            ->image()
                            ->orientImagesFromExif()
                            ->maxSize(10240)
                            ->disk((string) config('filesystems.media_disk', 'public'))
                            ->directory('media-tmp')
                            ->visibility('public')
                            ->live()
                            ->visibleOn('edit'),
                        Hidden::make('display_fit')
                            ->default('cover')
                            ->required(),
                        FocalPointPicker::make('focal_x')
                            ->label(__('admin.media_picker.focal_label'))
                            ->imageUrl(function (Get $get, ?MediaAsset $record): ?string {
                                // Before the form is saved a fresh upload is a Livewire
                                // temporary file, not a path; frame that file, not the old one.
                                $pending = self::pendingUploadUrl($get('replacement_file')) ?? self::pendingUploadUrl($get('file'));

                                if ($pending !== null) {
                                    return $pending;
                                }

                                $temporaryPath = self::uploadedPath($get('replacement_file')) ?? self::uploadedPath($get('file'));

                                return $temporaryPath !== null
                                    ? MediaUrlResolver::resolve($temporaryPath, (string) config('filesystems.media_disk', 'public'))
                                    : ($record instanceof MediaAsset
                                        ? MediaUrlResolver::resolveImage($record->webp_path, $record->path, $record->disk)
                                        : null);
                            })
                            ->rules(['numeric', 'min:0', 'max:100'])
                            ->default(50)
                            ->required(),
                        Hidden::make('focal_y')
                            ->rules(['numeric', 'min:0', 'max:100'])
                            ->default(50)
                            ->required(),
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

    private static function publicMediaUrl(?string $path, ?string $disk): ?string
    {
        $url = MediaUrlResolver::resolve($path, $disk);

        if (is_string($url) && str_starts_with($url, '/')) {
            return url($url);
        }

        return $url;
    }

    private static function uploadedPath(mixed $state): ?string
    {
        if (is_string($state) && $state !== '') {
            return $state;
        }

        if (is_array($state)) {
            return collect($state)->first(fn (mixed $path): bool => is_string($path) && $path !== '');
        }

        return null;
    }

    private static function pendingUploadUrl(mixed $state): ?string
    {
        $upload = $state instanceof TemporaryUploadedFile
            ? $state
            : (is_array($state) ? collect($state)->first(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile) : null);

        if (! $upload instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $upload->temporaryUrl();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, string> */
    private static function metadataStatusOptions(): array
    {
        return [
            'missing' => __('admin_resources.media_asset.missing'),
            'auto_generated' => __('admin_resources.media_asset.auto_generated'),
            'reviewed' => __('admin_resources.media_asset.reviewed'),
        ];
    }

    private static function thumbnailHtml(?string $url): HtmlString
    {
        if ($url === null || $url === '') {
            return new HtmlString('<span style="display:inline-flex;width:40px;height:40px;border-radius:9999px;background:#f3f4f6;align-items:center;justify-content:center;color:#9ca3af;">-</span>');
        }

        return new HtmlString('<img src="'.e($url).'" alt="" loading="lazy" style="width:40px;height:40px;border-radius:9999px;object-fit:cover;" />');
    }

    private static function filledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
