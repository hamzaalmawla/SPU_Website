<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Filament\Resources\FacultySubpageCardResource\Pages;
use App\Models\Faculty\FacultySubpageCard;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class FacultySubpageCardResource extends Resource
{
    protected static ?string $model = FacultySubpageCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('admin_resources.faculty_subpage_card.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.faculty_subpage_card.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.facilities');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_resources.faculty_subpage_card.navigation_label');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', FacultySubpageCard::class);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $scopedFaculties = self::scopedFacultySlugs();

        if ($scopedFaculties !== null) {
            $query->whereIn('faculty_slug', $scopedFaculties);
        }

        return $query;
    }

    /** @return list<string>|null */
    private static function scopedFacultySlugs(): ?array
    {
        return app(FacultySubpageCardServiceInterface::class)->scopedFacultySlugs((int) auth()->id());
    }

    /**
     * The faculty a user whose faculty limit holds exactly one faculty works in;
     * with several, the editor picks one from their own faculties.
     */
    private static function scopedFacultySlug(): ?string
    {
        $faculties = self::scopedFacultySlugs();

        return $faculties !== null && count($faculties) === 1 ? $faculties[0] : null;
    }

    /** @return array<string, string> */
    private static function facultyOptions(): array
    {
        return app(FacultySubpageCardServiceInterface::class)->facultyOptions((int) auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::cardFields());
    }

    /** @return array<int, Component> */
    private static function cardFields(): array
    {
        return [
            TextInput::make('title_override_ar')
                ->label(__('admin_resources.faculty_subpage_card.title_override_ar'))
                ->maxLength(255)
                ->placeholder(__('admin_resources.faculty_subpage_card.leave_blank_for_default')),
            TextInput::make('title_override_en')
                ->label(__('admin_resources.faculty_subpage_card.title_override_en'))
                ->maxLength(255)
                ->placeholder(__('admin_resources.faculty_subpage_card.leave_blank_for_default')),
            TextInput::make('sort_order')
                ->label(__('admin_resources.faculty_subpage_card.sort_order'))
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('is_visible')
                ->label(__('admin_resources.faculty_subpage_card.visible'))
                ->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('faculty_slug')
                    ->label(__('admin_resources.faculty_subpage_card.faculty'))
                    ->searchable(),
                TextColumn::make('subpage_slug')
                    ->label(__('admin_resources.faculty_subpage_card.subpage'))
                    ->searchable()
                    ->description(fn (FacultySubpageCard $record): string => $record->subpage_slug),
                TextColumn::make('status')
                    ->label(__('admin_resources.faculty_subpage_card.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('is_visible')
                    ->label(__('admin_resources.faculty_subpage_card.visible'))
                    ->boolean()
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->toggleVisibility((int) $record->getKey(), (int) auth()->id());
                    }),
                TextColumn::make('sort_order')
                    ->label(__('admin_resources.faculty_subpage_card.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('faculty_slug')
                    ->label(__('admin_resources.faculty_subpage_card.faculty'))
                    ->options(fn (): array => static::facultyOptions()),
            ])
            ->actions([
                Action::make('moveUp')
                    ->label('')
                    ->icon('heroicon-o-chevron-up')
                    ->color('gray')
                    ->tooltip(__('admin_resources.faculty_subpage_card.move_up'))
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->moveUp((int) $record->getKey(), (int) auth()->id());
                    }),
                Action::make('moveDown')
                    ->label('')
                    ->icon('heroicon-o-chevron-down')
                    ->color('gray')
                    ->tooltip(__('admin_resources.faculty_subpage_card.move_down'))
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->moveDown((int) $record->getKey(), (int) auth()->id());
                    }),
                Action::make('edit')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(__('admin_resources.faculty_subpage_card.edit_faculty_subpage_card'))
                    ->modalWidth('lg')
                    ->fillForm(fn (FacultySubpageCard $record): array => [
                        'title_override_ar' => $record->title_override_ar,
                        'title_override_en' => $record->title_override_en,
                        'sort_order' => $record->sort_order,
                        'is_visible' => $record->is_visible,
                    ])
                    ->form(static::cardFields())
                    ->action(function (FacultySubpageCard $record, array $data): void {
                        app(FacultySubpageCardServiceInterface::class)->updateCard(
                            (int) $record->getKey(),
                            $data,
                            (int) auth()->id(),
                        );
                    }),
                Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('admin_resources.faculty_subpage_card.delete_faculty_subpage_card'))
                    ->label(__('admin_resources.faculty_subpage_card.delete'))
                    ->action(fn (FacultySubpageCard $record): bool => app(FacultySubpageCardServiceInterface::class)
                        ->deleteCard((int) $record->getKey(), (int) auth()->id())),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label(__('admin_resources.faculty_subpage_card.publish'))
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => Gate::allows('faculty_subpage_cards.publish'))
                        ->action(function (Collection $records): void {
                            $service = app(FacultySubpageCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->publish((int) $record->getKey(), (int) auth()->id());
                            }

                            Notification::make()
                                ->title(__('admin_resources.faculty_subpage_card.faculty_subpage_cards_published'))
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('unpublish')
                        ->label(__('admin_resources.faculty_subpage_card.unpublish'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => Gate::allows('faculty_subpage_cards.publish'))
                        ->action(function (Collection $records): void {
                            $service = app(FacultySubpageCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->unpublish((int) $record->getKey(), (int) auth()->id());
                            }

                            Notification::make()
                                ->title(__('admin_resources.faculty_subpage_card.faculty_subpage_cards_unpublished'))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->headerActions([
                Action::make('addCard')
                    ->label(__('admin_resources.faculty_subpage_card.add_subpage_card'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('admin_resources.faculty_subpage_card.add_faculty_subpage_card'))
                    ->modalWidth('lg')
                    ->form([
                        Select::make('faculty_slug')
                            ->label(__('admin_resources.faculty_subpage_card.faculty'))
                            ->options(fn (): array => static::facultyOptions())
                            ->default(static::scopedFacultySlug())
                            ->hidden(fn (): bool => static::scopedFacultySlug() !== null)
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('subpage_slug', null)),
                        Select::make('subpage_slug')
                            ->label(__('admin_resources.faculty_subpage_card.subpage'))
                            ->options(fn (Get $get): array => app(FacultySubpageCardServiceInterface::class)
                                ->availableSubpageOptions((string) ($get('faculty_slug') ?? '')))
                            ->searchable()
                            ->required(),
                        TextInput::make('title_override_ar')
                            ->label(__('admin_resources.faculty_subpage_card.title_override_ar'))
                            ->maxLength(255)
                            ->placeholder(__('admin_resources.faculty_subpage_card.leave_blank_for_default')),
                        TextInput::make('title_override_en')
                            ->label(__('admin_resources.faculty_subpage_card.title_override_en'))
                            ->maxLength(255)
                            ->placeholder(__('admin_resources.faculty_subpage_card.leave_blank_for_default')),
                    ])
                    ->action(function (array $data): void {
                        $service = app(FacultySubpageCardServiceInterface::class);

                        $facultySlug = (string) ($data['faculty_slug'] ?? '') !== ''
                            ? (string) $data['faculty_slug']
                            : (string) (static::scopedFacultySlug() ?? '');

                        if ($facultySlug === '') {
                            Notification::make()
                                ->title(__('admin_resources.faculty_subpage_card.a_faculty_must_be_selected'))
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($service->cardExists($facultySlug, (string) $data['subpage_slug'])) {
                            Notification::make()
                                ->title(__('admin_resources.faculty_subpage_card.this_subpage_card_already_exists_for_this_faculty'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $service->createCard(
                            facultySlug: $facultySlug,
                            subpageSlug: $data['subpage_slug'],
                            userId: (int) auth()->id(),
                            titleOverrideAr: $data['title_override_ar'] ?? null,
                            titleOverrideEn: $data['title_override_en'] ?? null,
                        );

                        Notification::make()
                            ->title(__('admin_resources.faculty_subpage_card.faculty_subpage_card_added'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => Gate::allows('create', FacultySubpageCard::class)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacultySubpageCards::route('/'),
        ];
    }
}
