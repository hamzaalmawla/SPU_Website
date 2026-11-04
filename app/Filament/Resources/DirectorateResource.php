<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Filament\Resources\Concerns\AuthorizesThroughPermissions;
use App\Filament\Resources\DirectorateResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Content\Directorate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class DirectorateResource extends Resource
{
    use AuthorizesThroughPermissions;

    protected static ?string $model = Directorate::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 4;

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Directorate) {
            return self::getModelLabel();
        }

        $translation = $record->translations->first();

        return filled($translation?->title) ? trim((string) $translation->title) : $record->slug;
    }

    protected static function permissionComponent(): string
    {
        return 'directorates';
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.directorate.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.directorate.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.about');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.directorates');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin_resources.directorate.directorate'))->schema([
                TextInput::make('slug')->required()->maxLength(255)->alphaDash(),
                MediaPicker::icon('icon', 'Icon'),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('location')->maxLength(255),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            // One profile per person: the manager's name, photo, contact and
            // education come from their faculty profile.
            Section::make(__('admin_resources.directorate.manager_section'))
                ->description(__('admin_resources.directorate.manager_section_help'))
                ->schema([
                    Select::make('manager_member_id')
                        ->label(__('admin_resources.directorate.manager'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => app(ProfileAdminServiceInterface::class)->profileOptions($search, (int) auth()->id()))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value)
                            ? (app(ProfileAdminServiceInterface::class)->profileOptions('', (int) auth()->id(), [(int) $value])[(int) $value] ?? null)
                            : null)
                        ->nullable()
                        ->live()
                        ->native(false),
                    Placeholder::make('manager_profile_link')
                        ->hiddenLabel()
                        ->content(fn (Get $get): HtmlString => self::managerProfileLink($get('manager_member_id'))),
                ])->columns(2),
            Repeater::make('translations')->schema([
                Select::make('locale')->required()->options(['ar' => __('admin_resources.directorate.arabic'), 'en' => __('admin_resources.directorate.english')])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('summary')->rows(3),
                Textarea::make('description')->rows(5),
                TagsInput::make('services_json')
                    ->label(__('admin_resources.directorate.services'))
                    ->helperText(__('admin_resources.directorate.services_help'))
                    ->columnSpanFull(),
                Repeater::make('task_groups_json')
                    ->label(__('admin_resources.directorate.task_groups'))
                    ->helperText(__('admin_resources.directorate.task_groups_help'))
                    ->schema([
                        TextInput::make('title')->label(__('admin_resources.directorate.task_group_title'))->required()->maxLength(255),
                        Textarea::make('summary')->label(__('admin_resources.directorate.task_group_summary'))->rows(2),
                        Repeater::make('items')
                            ->label(__('admin_resources.directorate.tasks'))
                            ->schema([
                                TextInput::make('title')->label(__('admin_resources.directorate.task_title'))->required()->maxLength(255),
                                Textarea::make('body')->label(__('admin_resources.directorate.task_body'))->rows(2),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? (string) $state['title'] : null)
                            ->addActionLabel(__('admin_resources.directorate.add_task'))
                            ->defaultItems(0),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? (string) $state['title'] : null)
                    ->addActionLabel(__('admin_resources.directorate.add_task_group'))
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
        ]);
    }

    private static function managerProfileLink(mixed $memberId): HtmlString
    {
        $link = static fn (string $url, string $label): string => '<a href="'.e($url).'" target="_blank" rel="noopener" class="text-primary-600 underline">'.e($label).'</a>';

        if (is_numeric($memberId)) {
            return new HtmlString(e(__('admin_resources.directorate.manager_from_profile')).' '
                .$link(FacultyMemberResource::getUrl('edit', ['record' => (int) $memberId]), __('admin_resources.person.edit_profile')));
        }

        return new HtmlString(e(__('admin_resources.directorate.no_manager_help')).' '
            .$link(FacultyMemberResource::getUrl('create'), __('admin_resources.person.create_profile')));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.title')
                ->label(__('admin_resources.directorate.titles'))
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'translations',
                    fn (Builder $q): Builder => $q->where('title', 'like', "%{$search}%")
                ))
                ->listWithLineBreaks()
                ->limit(40),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('publication_status')->badge()->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDirectorates::route('/'), 'create' => Pages\CreateDirectorate::route('/create'), 'edit' => Pages\EditDirectorate::route('/{record}/edit')];
    }
}
