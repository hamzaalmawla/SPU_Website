<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\Filament\Resources\ResearcherResource\Pages;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * The people named on the university's papers: the research office's
 * register and anyone added from a paper. Linking a researcher to their staff
 * profile puts their papers on it.
 */
class ResearcherResource extends Resource
{
    protected static ?string $model = Researcher::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.researcher.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.researcher.plural_model');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.researcher.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.research');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('create', ResearchPublication::class);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Researcher ? trim($record->full_name) : self::getModelLabel();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['faculty.translations', 'facultyMember.translations'])
            ->withCount('publications');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin.researcher.sections.details'))->schema([
                TextInput::make('full_name')->label(__('admin.researcher.fields.full_name'))->required()->maxLength(200),
                TextInput::make('email')->label(__('admin.researcher.fields.email'))->email()->maxLength(150),
                Select::make('faculty_id')
                    ->label(__('admin.researcher.fields.faculty'))
                    ->options(fn (): array => app(ResearcherAdminServiceInterface::class)->facultyOptions())
                    ->native(false),
                TextInput::make('affiliation')->label(__('admin.researcher.fields.affiliation'))->maxLength(255),
                Toggle::make('is_enabled')->label(__('admin.researcher.fields.is_enabled'))->helperText(__('admin.researcher.help.is_enabled'))->default(true)->columnSpanFull(),
            ])->columns(2),
            Section::make(__('admin.researcher.sections.staff'))
                ->description(__('admin.researcher.help.staff_profile'))
                ->schema([
                    Placeholder::make('suggestions')
                        ->hiddenLabel()
                        ->content(fn (?Researcher $record): string => self::suggestionText($record))
                        ->visible(fn (?Researcher $record): bool => self::suggestionText($record) !== ''),
                    Select::make('faculty_member_id')
                        ->label(__('admin.researcher.fields.staff_profile'))
                        ->searchable()
                        ->native(false)
                        ->options(fn (?Researcher $record): array => $record instanceof Researcher ? app(ResearcherAdminServiceInterface::class)->staffSuggestions((int) $record->getKey()) : [])
                        ->getSearchResultsUsing(fn (string $search): array => self::staffOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? (self::staffOptions(null, (int) $value)[(int) $value] ?? null) : null),
                ]),
            Section::make(__('admin.researcher.sections.links'))->schema([
                TextInput::make('orcid_url')->label(__('admin.researcher.fields.orcid_url'))->url()->maxLength(255),
                TextInput::make('scholar_url')->label(__('admin.researcher.fields.scholar_url'))->url()->maxLength(255),
                TextInput::make('scopus_url')->label(__('admin.researcher.fields.scopus_url'))->url()->maxLength(255),
                TagsInput::make('expertise')->label(__('admin.researcher.fields.expertise'))->helperText(__('admin.researcher.help.expertise'))->columnSpanFull(),
                Textarea::make('notes')->label(__('admin.researcher.fields.notes'))->rows(2)->columnSpanFull(),
            ])->columns(3)->collapsible(),
            Section::make(__('admin.researcher.sections.papers'))
                ->schema([
                    Placeholder::make('papers')
                        ->hiddenLabel()
                        ->content(fn (?Researcher $record): HtmlString => self::papersList($record)),
                ])
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('admin.researcher.table.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('faculty_name')
                    ->label(__('admin.researcher.table.faculty'))
                    ->getStateUsing(fn (Researcher $record): string => (string) ($record->faculty?->translations->firstWhere('locale', self::locale())?->name ?? ''))
                    ->placeholder('—'),
                TextColumn::make('publications_count')
                    ->label(__('admin.researcher.table.papers'))
                    ->sortable(),
                TextColumn::make('staff')
                    ->label(__('admin.researcher.table.staff'))
                    ->getStateUsing(fn (Researcher $record): string => self::staffState($record))
                    ->color(fn (Researcher $record): string => $record->faculty_member_id !== null ? 'success' : (self::firstSuggestion($record) !== null ? 'warning' : 'gray'))
                    ->wrap(),
                IconColumn::make('is_enabled')
                    ->label(__('admin.researcher.table.visible'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('faculty_id')
                    ->label(__('admin.researcher.filters.faculty'))
                    ->options(fn (): array => app(ResearcherAdminServiceInterface::class)->facultyOptions()),
                SelectFilter::make('link')
                    ->label(__('admin.researcher.filters.link'))
                    ->options([
                        'linked' => __('admin.researcher.filters.linked'),
                        'unlinked' => __('admin.researcher.filters.unlinked'),
                        'suggested' => __('admin.researcher.filters.suggested'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'linked' => $query->whereNotNull('faculty_member_id'),
                        'unlinked' => $query->whereNull('faculty_member_id'),
                        'suggested' => $query->whereKey(app(ResearcherAdminServiceInterface::class)->researchersWithSuggestions()),
                        default => $query,
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('link_staff')
                    ->label(__('admin.researcher.actions.link'))
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->visible(fn (Researcher $record): bool => $record->faculty_member_id === null)
                    ->modalHeading(fn (Researcher $record): string => __('admin.researcher.actions.link_heading', ['name' => trim($record->full_name)]))
                    ->form(fn (Researcher $record): array => [
                        Select::make('faculty_member_id')
                            ->label(__('admin.researcher.actions.link_field'))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->default(fn (): ?int => self::firstSuggestion($record))
                            ->options(fn (): array => app(ResearcherAdminServiceInterface::class)->staffSuggestions((int) $record->getKey()))
                            ->getSearchResultsUsing(fn (string $search): array => self::staffOptions($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? (self::staffOptions(null, (int) $value)[(int) $value] ?? null) : null),
                    ])
                    ->action(function (Researcher $record, array $data): void {
                        app(ResearcherAdminServiceInterface::class)->linkToStaff((int) $record->getKey(), (int) $data['faculty_member_id'], self::userId());
                        Notification::make()->title(__('admin.researcher.notifications.linked'))->success()->send();
                    }),
                Tables\Actions\EditAction::make()->label(__('admin.actions.edit')),
                Tables\Actions\Action::make('delete_researcher')
                    ->label(__('admin.researcher.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.researcher.help.delete'))
                    ->action(function (Researcher $record): void {
                        app(ResearcherAdminServiceInterface::class)->delete((int) $record->getKey(), self::userId());
                        Notification::make()->title(__('admin.researcher.notifications.deleted'))->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('publications_count')->orderBy('full_name'))
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResearchers::route('/'),
            'create' => Pages\CreateResearcher::route('/create'),
            'edit' => Pages\EditResearcher::route('/{record}/edit'),
        ];
    }

    private static function staffState(Researcher $record): string
    {
        if ($record->facultyMember !== null) {
            return (string) ($record->facultyMember->translations->firstWhere('locale', self::locale())?->full_name ?? $record->facultyMember->slug);
        }

        $suggestion = self::firstSuggestion($record);

        return $suggestion !== null
            ? __('admin.researcher.table.suggested', ['name' => app(ResearcherAdminServiceInterface::class)->staffSuggestions((int) $record->getKey())[$suggestion]])
            : __('admin.researcher.table.not_linked');
    }

    private static function firstSuggestion(Researcher $record): ?int
    {
        if ($record->faculty_member_id !== null) {
            return null;
        }

        $first = array_key_first(app(ResearcherAdminServiceInterface::class)->staffSuggestions((int) $record->getKey()));

        return $first !== null ? (int) $first : null;
    }

    private static function suggestionText(?Researcher $record): string
    {
        if (! $record instanceof Researcher || $record->faculty_member_id !== null) {
            return '';
        }

        $names = app(ResearcherAdminServiceInterface::class)->staffSuggestions((int) $record->getKey());

        return $names !== [] ? __('admin.researcher.help.suggestions', ['names' => implode('، ', $names)]) : '';
    }

    private static function papersList(?Researcher $record): HtmlString
    {
        $papers = $record instanceof Researcher ? app(ResearcherAdminServiceInterface::class)->publications((int) $record->getKey()) : [];

        if ($papers === []) {
            return new HtmlString(e(__('admin.researcher.help.no_papers')));
        }

        return new HtmlString('<ul class="list-disc space-y-1 ps-5">'.collect($papers)->map(fn (array $paper): string => '<li><a class="text-primary-600 hover:underline" href="'.e($paper['editUrl']).'">'.e($paper['title']).'</a>'.($paper['year'] !== '' ? ' <span class="text-gray-500">('.e($paper['year']).')</span>' : '').'</li>')->implode('').'</ul>');
    }

    /** @return array<int, string> */
    private static function staffOptions(?string $search, ?int $selectedId = null): array
    {
        return app(ResearchPublicationCmsServiceInterface::class)->facultyMemberOptions(self::userId(), $search, $selectedId);
    }

    private static function userId(): int
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return (int) $user->getKey();
    }

    private static function locale(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ar';
    }
}
