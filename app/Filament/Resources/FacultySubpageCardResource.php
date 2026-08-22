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

    protected static ?string $navigationGroup = 'Facilities';

    protected static ?string $navigationLabel = 'Subpage Navigation';

    protected static ?string $modelLabel = 'Faculty Subpage Card';

    protected static ?string $pluralModelLabel = 'Faculty Subpage Cards';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-faculties');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::scopedFacultySlug() !== null) {
            $query->where('faculty_slug', static::scopedFacultySlug());
        }

        return $query;
    }

    private static function scopedFacultySlug(): ?string
    {
        return app(FacultySubpageCardServiceInterface::class)->scopedFacultySlug((int) auth()->id());
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
                ->label('Title Override (AR)')
                ->maxLength(255)
                ->placeholder('Leave blank for default'),
            TextInput::make('title_override_en')
                ->label('Title Override (EN)')
                ->maxLength(255)
                ->placeholder('Leave blank for default'),
            TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('is_visible')
                ->label('Visible')
                ->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('faculty_slug')
                    ->label('Faculty')
                    ->searchable(),
                TextColumn::make('subpage_slug')
                    ->label('Subpage')
                    ->searchable()
                    ->description(fn (FacultySubpageCard $record): string => $record->subpage_slug),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean()
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->toggleVisibility((int) $record->getKey(), (int) auth()->id());
                    }),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('faculty_slug')
                    ->label('Faculty')
                    ->options(fn (): array => static::facultyOptions()),
            ])
            ->actions([
                Action::make('moveUp')
                    ->label('')
                    ->icon('heroicon-o-chevron-up')
                    ->color('gray')
                    ->tooltip('Move Up')
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->moveUp((int) $record->getKey(), (int) auth()->id());
                    }),
                Action::make('moveDown')
                    ->label('')
                    ->icon('heroicon-o-chevron-down')
                    ->color('gray')
                    ->tooltip('Move Down')
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->moveDown((int) $record->getKey(), (int) auth()->id());
                    }),
                Action::make('edit')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edit Faculty Subpage Card')
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
                    ->modalHeading('Delete Faculty Subpage Card')
                    ->label('Delete')
                    ->action(fn (FacultySubpageCard $record): bool => app(FacultySubpageCardServiceInterface::class)
                        ->deleteCard((int) $record->getKey(), (int) auth()->id())),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => Gate::allows('publish-content'))
                        ->action(function (Collection $records): void {
                            $service = app(FacultySubpageCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->publish((int) $record->getKey(), (int) auth()->id());
                            }

                            Notification::make()
                                ->title('Faculty subpage cards published')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => Gate::allows('publish-content'))
                        ->action(function (Collection $records): void {
                            $service = app(FacultySubpageCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->unpublish((int) $record->getKey(), (int) auth()->id());
                            }

                            Notification::make()
                                ->title('Faculty subpage cards unpublished')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->headerActions([
                Action::make('addCard')
                    ->label('Add Subpage Card')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add Faculty Subpage Card')
                    ->modalWidth('lg')
                    ->form([
                        Select::make('faculty_slug')
                            ->label('Faculty')
                            ->options(fn (): array => static::facultyOptions())
                            ->default(static::scopedFacultySlug())
                            ->hidden(fn (): bool => static::scopedFacultySlug() !== null)
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('subpage_slug', null)),
                        Select::make('subpage_slug')
                            ->label('Subpage')
                            ->options(fn (Get $get): array => app(FacultySubpageCardServiceInterface::class)
                                ->availableSubpageOptions((string) ($get('faculty_slug') ?? '')))
                            ->searchable()
                            ->required(),
                        TextInput::make('title_override_ar')
                            ->label('Title Override (AR)')
                            ->maxLength(255)
                            ->placeholder('Leave blank for default'),
                        TextInput::make('title_override_en')
                            ->label('Title Override (EN)')
                            ->maxLength(255)
                            ->placeholder('Leave blank for default'),
                    ])
                    ->action(function (array $data): void {
                        $service = app(FacultySubpageCardServiceInterface::class);

                        $facultySlug = (string) ($data['faculty_slug'] ?? '') !== ''
                            ? (string) $data['faculty_slug']
                            : (string) (static::scopedFacultySlug() ?? '');

                        if ($facultySlug === '') {
                            Notification::make()
                                ->title('A faculty must be selected')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($service->cardExists($facultySlug, (string) $data['subpage_slug'])) {
                            Notification::make()
                                ->title('This subpage card already exists for this faculty')
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
                            ->title('Faculty subpage card added')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => Gate::allows('manage-faculties')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacultySubpageCards::route('/'),
        ];
    }
}
