<?php

declare(strict_types=1);

namespace App\Filament\Resources;

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
        return Gate::allows('manage-media');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return 'Legacy Media Archive';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('library_scope', 'legacy');
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
                    ->label('Filename')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('original_name')
                    ->label('Original Name')
                    ->searchable()
                    ->toggleable()
                    ->limit(40),

                TextColumn::make('title_en')
                    ->label('Title')
                    ->searchable()
                    ->placeholder('-')
                    ->limit(30),

                TextColumn::make('path')
                    ->label('Path')
                    ->searchable()
                    ->copyable()
                    ->limit(55),

                TextColumn::make('media_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'image' => 'success',
                        'pdf' => 'danger',
                        'document' => 'warning',
                        'video' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('metadata_status')
                    ->label('Metadata')
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
                    ->label('Type')
                    ->options([
                        'image' => 'Images',
                        'pdf' => 'PDFs',
                        'document' => 'Documents',
                        'video' => 'Videos',
                        'icon' => 'Icons',
                        'other' => 'Other',
                    ]),
                Filter::make('directory')
                    ->form([
                        TextInput::make('directory')
                            ->label('Directory')
                            ->placeholder('news/images'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $directory = $data['directory'] ?? null;

                        return is_string($directory) && trim($directory) !== ''
                            ? $query->where('directory', trim($directory, '/'))
                            : $query;
                    }),
                Filter::make('missing_metadata')
                    ->label('Missing Metadata')
                    ->query(fn (Builder $query): Builder => $query->where('metadata_status', 'missing')),
                Filter::make('missing_image_alt')
                    ->label('Images Missing Alt Text')
                    ->query(fn (Builder $query): Builder => $query->where('media_type', 'image')->whereNull('alt_text_ar')->whereNull('alt_text_en')),
            ])
            ->actions([
                Tables\Actions\Action::make('promote')
                    ->label('Promote')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => Gate::allows('create', MediaAsset::class))
                    ->form([
                        TextInput::make('title_ar')->label('Title (AR)')->maxLength(255),
                        TextInput::make('title_en')->label('Title (EN)')->maxLength(255),
                        TextInput::make('alt_text_ar')->label('Alt Text (AR)')->maxLength(500),
                        TextInput::make('alt_text_en')->label('Alt Text (EN)')->maxLength(500),
                    ])
                    ->action(function (MediaAsset $record, array $data): void {
                        app(MediaServiceInterface::class)->promoteLegacyAsset($record->id, $data, (int) auth()->id());

                        Notification::make()
                            ->title('Legacy asset promoted to Main Media Library')
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
