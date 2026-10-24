<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Filament\Resources\LegacyMediaAssetResource\Pages;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

final class LegacyMediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'filename';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', MediaAsset::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.legacy_media_asset.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.legacy_media_asset.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_resources.legacy_media_asset.plural_model');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('library_scope', 'legacy');
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->hasFacultyLimit()) {
            return $query;
        }

        return $query->whereIn('faculty_scope_slug', app(AdminAccessServiceInterface::class)->facultyScopeValues($user));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(fn (MediaAsset $record): ?string => str_starts_with($record->mime_type, 'image/')
                        ? self::publicMediaUrl($record->path, $record->disk)
                        : null)
                    ->formatStateUsing(fn (?string $state): HtmlString => self::thumbnailHtml($state))
                    ->html(),

                TextColumn::make('filename')
                    ->label(__('admin_resources.legacy_media_asset.filename'))
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('original_name')
                    ->label(__('admin_resources.legacy_media_asset.original_name'))
                    ->searchable()
                    ->toggleable()
                    ->limit(40),

                TextColumn::make('title_en')
                    ->label(__('admin_resources.legacy_media_asset.title'))
                    ->searchable()
                    ->placeholder('-')
                    ->limit(30),

                TextColumn::make('path')
                    ->label(__('admin_resources.legacy_media_asset.path'))
                    ->searchable()
                    ->copyable()
                    ->limit(55),

                TextColumn::make('media_type')
                    ->label(__('admin_resources.legacy_media_asset.type'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'image' => 'success',
                        'pdf' => 'danger',
                        'document' => 'warning',
                        'video' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('metadata_status')
                    ->label(__('admin_resources.legacy_media_asset.metadata'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'reviewed' => 'success',
                        'auto_generated' => 'warning',
                        'missing' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('media_type')
                    ->label(__('admin_resources.legacy_media_asset.type'))
                    ->options([
                        'image' => __('admin_resources.legacy_media_asset.images'),
                        'pdf' => __('admin_resources.legacy_media_asset.pdfs'),
                        'document' => __('admin_resources.legacy_media_asset.documents'),
                        'video' => __('admin_resources.legacy_media_asset.videos'),
                        'icon' => __('admin_resources.legacy_media_asset.icons'),
                        'other' => __('admin_resources.legacy_media_asset.other'),
                    ]),
                Filter::make('directory')
                    ->form([
                        TextInput::make('directory')
                            ->label(__('admin_resources.legacy_media_asset.directory'))
                            ->placeholder('news/images'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $directory = $data['directory'] ?? null;

                        return is_string($directory) && trim($directory) !== ''
                            ? $query->where('directory', trim($directory, '/'))
                            : $query;
                    }),
                Filter::make('missing_metadata')
                    ->label(__('admin_resources.legacy_media_asset.missing_metadata'))
                    ->query(fn (Builder $query): Builder => $query->where('metadata_status', 'missing')),
                Filter::make('missing_image_alt')
                    ->label(__('admin_resources.legacy_media_asset.images_missing_alt_text'))
                    ->query(fn (Builder $query): Builder => $query->where('media_type', 'image')->whereNull('alt_text_ar')->whereNull('alt_text_en')),
            ])
            ->actions([
                Tables\Actions\Action::make('promote')
                    ->label(__('admin_resources.legacy_media_asset.promote'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => Gate::allows('create', MediaAsset::class))
                    ->form([
                        TextInput::make('title_ar')->label(__('admin_resources.legacy_media_asset.title_ar'))->maxLength(255),
                        TextInput::make('title_en')->label(__('admin_resources.legacy_media_asset.title_en'))->maxLength(255),
                        TextInput::make('alt_text_ar')->label(__('admin_resources.legacy_media_asset.alt_text_ar'))->maxLength(500),
                        TextInput::make('alt_text_en')->label(__('admin_resources.legacy_media_asset.alt_text_en'))->maxLength(500),
                    ])
                    ->action(function (MediaAsset $record, array $data): void {
                        app(MediaServiceInterface::class)->promoteLegacyAsset($record->id, $data, (int) auth()->id());

                        Notification::make()
                            ->title(__('admin_resources.legacy_media_asset.legacy_asset_promoted_to_main_media_library'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegacyMediaAssets::route('/'),
        ];
    }

    private static function publicMediaUrl(?string $path, ?string $disk): ?string
    {
        $url = MediaUrlResolver::resolve($path, $disk);

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
