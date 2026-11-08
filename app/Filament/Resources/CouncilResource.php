<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Council\CouncilCatalogServiceInterface;
use App\Filament\Resources\Concerns\AuthorizesThroughPermissions;
use App\Filament\Resources\CouncilResource\Pages;
use App\Filament\Support\CouncilEditor;
use App\Filament\Support\StatusBadge;
use App\Models\Content\UniversityCouncil;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * About › Councils: every council is a page built from the same template
 * (introduction, members, functions). Adding one here puts it on the Councils
 * page once it is published.
 */
class CouncilResource extends Resource
{
    use AuthorizesThroughPermissions;

    protected static ?string $model = UniversityCouncil::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'councils';

    protected static function permissionComponent(): string
    {
        return 'councils';
    }

    public static function getModelLabel(): string
    {
        return __('admin_councils.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_councils.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.site_sections');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.councils');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof UniversityCouncil
            ? app(CouncilCatalogServiceInterface::class)->adminTitle((int) $record->getKey(), app()->getLocale())
            : self::getModelLabel();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin_councils.sections.settings'))
                ->description(fn (string $operation): string => $operation === 'create'
                    ? __('admin_councils.help.create')
                    : __('admin_councils.sections.settings_help'))
                ->schema([
                    TextInput::make('slug')
                        ->label(__('admin_councils.fields.slug'))
                        ->helperText(__('admin_councils.help.slug'))
                        ->required()
                        ->maxLength(120)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique(UniversityCouncil::class, 'slug', ignoreRecord: true)
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                    TextInput::make('sort_order')
                        ->label(__('admin_councils.fields.sort_order'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Toggle::make('is_enabled')
                        ->label(__('admin_councils.fields.is_enabled'))
                        ->helperText(__('admin_councils.help.is_enabled'))
                        ->default(true),
                    TextInput::make('title_ar')
                        ->label(__('admin_councils.fields.title_ar'))
                        ->required()
                        ->maxLength(180)
                        ->visibleOn('create'),
                    TextInput::make('title_en')
                        ->label(__('admin_councils.fields.title_en'))
                        ->required()
                        ->maxLength(180)
                        ->visibleOn('create'),
                ])
                ->columns(2)
                ->collapsible(),
            CouncilEditor::contentTabs()->hiddenOn('create'),
            CouncilEditor::membersSection()->hiddenOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $catalog = app(CouncilCatalogServiceInterface::class);

        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin_councils.table.title'))
                    ->state(fn (UniversityCouncil $record): string => $catalog->adminTitle((int) $record->getKey(), app()->getLocale())),
                TextColumn::make('slug')->label(__('admin_councils.table.slug'))->searchable(),
                TextColumn::make('sort_order')->label(__('admin_councils.table.sort_order'))->sortable(),
                IconColumn::make('is_enabled')->label(__('admin_councils.table.is_enabled'))->boolean(),
                StatusBadge::publication(
                    TextColumn::make('publication')
                        ->label(__('admin_councils.table.status'))
                        ->state(fn (UniversityCouncil $record): string => $catalog->isPublished((int) $record->getKey()) ? 'published' : 'draft'),
                ),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouncils::route('/'),
            'create' => Pages\CreateCouncil::route('/create'),
            'edit' => Pages\EditCouncil::route('/{record}/edit'),
        ];
    }
}
