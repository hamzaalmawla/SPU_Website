<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Filament\Resources\FacultySubpageCardResource\Pages;
use App\Models\Faculty\FacultySubpageCard;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
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
                        $service->toggleVisibility((int) $record->getKey());
                    }),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('faculty_slug')
                    ->label('Faculty')
                    ->options(fn (): array => FacultySubpageCard::query()
                        ->distinct()
                        ->pluck('faculty_slug', 'faculty_slug')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('moveUp')
                    ->label('')
                    ->icon('heroicon-o-chevron-up')
                    ->color('gray')
                    ->tooltip('Move Up')
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->moveUp((int) $record->getKey());
                    }),
                Tables\Actions\Action::make('moveDown')
                    ->label('')
                    ->icon('heroicon-o-chevron-down')
                    ->color('gray')
                    ->tooltip('Move Down')
                    ->action(function (FacultySubpageCard $record): void {
                        $service = app(FacultySubpageCardServiceInterface::class);
                        $service->moveDown((int) $record->getKey());
                    }),
                Tables\Actions\EditAction::make()
                    ->modalHeading('Edit Faculty Subpage Card')
                    ->modalWidth('lg'),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Delete Faculty Subpage Card')
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $service = app(FacultySubpageCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->publish((int) $record->getKey());
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
                        ->action(function (Collection $records): void {
                            $service = app(FacultySubpageCardServiceInterface::class);
                            foreach ($records as $record) {
                                $service->unpublish((int) $record->getKey());
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
                            ->options(fn (): array => \App\Models\Faculty\Faculty::query()
                                ->where('is_enabled', true)
                                ->pluck('public_slug', 'public_slug')
                                ->all())
                            ->searchable()
                            ->required(),
                        Select::make('subpage_slug')
                            ->label('Subpage')
                            ->options([
                                'overview' => 'Overview',
                                'departments' => 'Departments',
                                'study-plan' => 'Study Plan',
                                'labs' => 'Laboratories',
                                'projects' => 'Projects',
                                'alumni' => 'Alumni',
                                'valedictorians' => 'Honor List',
                                'training' => 'Training',
                                'research' => 'Research',
                            ])
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

                        $existing = FacultySubpageCard::query()
                            ->where('faculty_slug', $data['faculty_slug'])
                            ->where('subpage_slug', $data['subpage_slug'])
                            ->exists();

                        if ($existing) {
                            Notification::make()
                                ->title('This subpage card already exists for this faculty')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service->createCard(
                            facultySlug: $data['faculty_slug'],
                            subpageSlug: $data['subpage_slug'],
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
