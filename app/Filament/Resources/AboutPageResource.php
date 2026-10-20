<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AboutPageResource\Pages;
use App\Filament\Resources\Concerns\AuthorizesThroughPermissions;
use App\Filament\Support\JsonEditor;
use App\Filament\Support\MediaPicker;
use App\Models\Page\AboutPage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AboutPageResource extends Resource
{
    use AuthorizesThroughPermissions;

    protected static ?string $model = AboutPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 3;

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof AboutPage) {
            return self::getModelLabel();
        }

        $translation = $record->translations->first();

        return filled($translation?->title) ? trim((string) $translation->title) : $record->slug;
    }

    protected static function permissionComponent(): string
    {
        return 'about_pages';
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.about_page.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.about_page.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.about');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.about_pages');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin_resources.about_page.page'))->schema([
                TextInput::make('slug')->required()->maxLength(255)->alphaDash(),
                TextInput::make('template')->required()->maxLength(255),
                MediaPicker::image('hero_image', __('admin_resources.about_page.hero_image')),
                JsonEditor::make('payload_json')->label(__('admin_resources.about_page.landing_payload_json'))->helperText(__('admin_resources.about_page.payload_help'))->columnSpanFull(),
                Select::make('status')->required()->options(['draft' => __('admin_resources.about_page.draft'), 'published' => __('admin_resources.about_page.published'), 'scheduled' => __('admin_resources.about_page.scheduled')])->default('published'),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            Repeater::make('translations')->relationship()->schema([
                Select::make('locale')->required()->options(['ar' => __('admin_resources.about_page.arabic'), 'en' => __('admin_resources.about_page.english')]),
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('headline')->maxLength(255),
                Textarea::make('summary')->rows(3),
                JsonEditor::make('sections_json')->label(__('admin_resources.about_page.content_sections'))->helperText(__('admin_resources.about_page.payload_help'))->columnSpanFull(),
            ])->columns(2)->minItems(2)->maxItems(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.title')
                ->label(__('admin_resources.about_page.title'))
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'translations',
                    fn (Builder $q): Builder => $q->where('title', 'like', "%{$search}%")
                ))
                ->listWithLineBreaks()
                ->limit(40),
            TextColumn::make('template')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAboutPages::route('/'),
            'create' => Pages\CreateAboutPage::route('/create'),
            'edit' => Pages\EditAboutPage::route('/{record}/edit'),
        ];
    }
}
