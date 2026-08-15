<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Filament\Resources\AboutNavigationCardResource\Pages;
use App\Models\Page\AboutNavigationCard;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class AboutNavigationCardResource extends Resource
{
    protected static ?string $model = AboutNavigationCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.about');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.about_navigation_cards');
    }

    public static function getModelLabel(): string
    {
        return __('admin.about_navigation_card.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.about_navigation_card.plural_model');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title_override_ar')
                ->label(__('admin.about_navigation_card.fields.title_override_ar'))
                ->maxLength(255)
                ->placeholder(__('admin.about_navigation_card.help.leave_blank_for_default')),
            TextInput::make('title_override_en')
                ->label(__('admin.about_navigation_card.fields.title_override_en'))
                ->maxLength(255)
                ->placeholder(__('admin.about_navigation_card.help.leave_blank_for_default')),
            Select::make('status')
                ->label(__('admin.about_navigation_card.fields.status'))
                ->options([
                    'draft' => __('admin.about_navigation_card.statuses.draft'),
                    'published' => __('admin.about_navigation_card.statuses.published'),
                    'scheduled' => __('admin.about_navigation_card.statuses.scheduled'),
                ])
                ->disabled()
                ->dehydrated(false)
                ->helperText(__('admin.about_navigation_card.help.status_read_only')),
            TextInput::make('sort_order')
                ->label(__('admin.about_navigation_card.fields.sort_order'))
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('is_visible')
                ->label(__('admin.about_navigation_card.fields.is_visible'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('resolved_title')
                    ->label(__('admin.about_navigation_card.table.title'))
                    ->getStateUsing(function (AboutNavigationCard $record): string {
                        $locale = app()->getLocale();
                        $service = app(AboutNavigationCardServiceInterface::class);
                        $dto = $service->getAllCards()->firstWhere('id', (int) $record->getKey());

                        if (! $dto instanceof \App\DTOs\About\AboutNavigationCardDTO) {
                            return $record->target_key;
                        }

                        return $locale === 'ar' ? $dto->resolvedTitleAr : $dto->resolvedTitleEn;
                    })
                    ->description(fn (AboutNavigationCard $record): string => $record->target_key)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $q): Builder => $q
                            ->where('target_key', 'like', "%{$search}%")
                            ->orWhere('title_override_ar', 'like', "%{$search}%")
                            ->orWhere('title_override_en', 'like', "%{$search}%")
                    )),
                TextColumn::make('status')
                    ->label(__('admin.about_navigation_card.table.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('is_visible')
                    ->label(__('admin.about_navigation_card.table.is_visible'))
                    ->boolean()
                    ->action(function (AboutNavigationCard $record): void {
                        $service = app(AboutNavigationCardServiceInterface::class);
                        $service->toggleVisibility((int) $record->getKey());
                    }),
                TextColumn::make('sort_order')
                    ->label(__('admin.about_navigation_card.table.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.about_navigation_card.fields.status'))
                    ->options([
                        'draft' => __('admin.about_navigation_card.statuses.draft'),
                        'published' => __('admin.about_navigation_card.statuses.published'),
                        'scheduled' => __('admin.about_navigation_card.statuses.scheduled'),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('moveUp')
                    ->label('')
                    ->icon('heroicon-o-chevron-up')
                    ->color('gray')
                    ->tooltip(__('admin.about_navigation_card.actions.move_up'))
                    ->action(function (AboutNavigationCard $record): void {
                        $service = app(AboutNavigationCardServiceInterface::class);
                        $service->moveUp((int) $record->getKey());
                    }),
                Tables\Actions\Action::make('moveDown')
                    ->label('')
                    ->icon('heroicon-o-chevron-down')
                    ->color('gray')
                    ->tooltip(__('admin.about_navigation_card.actions.move_down'))
                    ->action(function (AboutNavigationCard $record): void {
                        $service = app(AboutNavigationCardServiceInterface::class);
                        $service->moveDown((int) $record->getKey());
                    }),
                Tables\Actions\EditAction::make()
                    ->modalHeading(__('admin.about_navigation_card.actions.edit'))
                    ->modalWidth('lg'),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(__('admin.about_navigation_card.actions.delete'))
                    ->label(__('admin.about_navigation_card.actions.remove')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label(__('admin.about_navigation_card.actions.publish'))
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $service = app(AboutNavigationCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->publish((int) $record->getKey());
                            }

                            Notification::make()
                                ->title(__('admin.about_navigation_card.notifications.published'))
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('schedule')
                        ->label(__('admin.about_navigation_card.actions.schedule'))
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->form([
                            DateTimePicker::make('publish_at')
                                ->label(__('admin.about_navigation_card.fields.publish_at'))
                                ->required()
                                ->minDate(now())
                                ->native(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $service = app(AboutNavigationCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->schedule((int) $record->getKey(), (string) $data['publish_at']);
                            }

                            Notification::make()
                                ->title(__('admin.about_navigation_card.notifications.scheduled'))
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('unpublish')
                        ->label(__('admin.about_navigation_card.actions.unpublish'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $service = app(AboutNavigationCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->unpublish((int) $record->getKey());
                            }

                            Notification::make()
                                ->title(__('admin.about_navigation_card.notifications.unpublished'))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->headerActions([
                Action::make('addCard')
                    ->label(__('admin.about_navigation_card.actions.add_card'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('admin.about_navigation_card.actions.add_card'))
                    ->modalWidth('lg')
                    ->form([
                        Select::make('target_key')
                            ->label(__('admin.about_navigation_card.fields.target_key'))
                            ->options(function (): array {
                                $existingKeys = AboutNavigationCard::query()->pluck('target_key')->all();
                                $allTargets = app(\App\Contracts\Cms\CmsTargetRegistryInterface::class)->forArea('about');

                                return $allTargets
                                    ->filter(fn ($target) => $target->key !== 'about.landing' && $target->publicPath !== null && ! in_array($target->key, $existingKeys, true))
                                    ->mapWithKeys(fn ($target): array => [$target->key => __($target->labelKey) . ' (' . $target->key . ')'])
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $service = app(AboutNavigationCardServiceInterface::class);
                        $service->createCard(targetKey: $data['target_key']);

                        Notification::make()
                            ->title(__('admin.about_navigation_card.notifications.added'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => Gate::allows('manage-pages')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAboutNavigationCards::route('/'),
        ];
    }
}
