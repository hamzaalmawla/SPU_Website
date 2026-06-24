<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NewsArticleResource\Pages;
use App\Models\News\NewsArticle;
use App\Models\User\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'News';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', NewsArticle::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return self::scopeQueryToCurrentUser(parent::getEloquentQuery())
            ->with(['translations', 'category.translations', 'coverMedia']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Article')->schema([
                TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                Select::make('news_category_id')->label('Category')->relationship('category', 'slug')->searchable()->preload(),
                Select::make('cover_media_id')->label('Cover Media')->relationship('coverMedia', 'original_name')->searchable()->preload(),
                Select::make('status')
                    ->required()
                    ->options(['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'archived' => 'Archived'])
                    ->default('draft'),
                DateTimePicker::make('published_at'),
                DateTimePicker::make('scheduled_at'),
                Toggle::make('is_enabled')->default(true),
                Toggle::make('is_featured')->default(false),
                TextInput::make('sort_order')->numeric()->default(0),
                TextInput::make('faculty_scope_slug')
                    ->maxLength(255)
                    ->alphaDash()
                    ->visible(fn (): bool => in_array(auth()->user()?->role_slug, ['super_admin', 'editor'], true)),
            ])->columns(2),
            Repeater::make('translations')->relationship()->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English']),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('excerpt')->rows(3)->columnSpanFull(),
                RichEditor::make('body')->columnSpanFull(),
            ])->columns(2)->minItems(2)->maxItems(2)->columnSpanFull(),
            Repeater::make('seoMeta')->relationship()->label('SEO Metadata')->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English']),
                TextInput::make('meta_title')->maxLength(255),
                Textarea::make('meta_description')->rows(2),
                TextInput::make('og_title')->maxLength(255),
                Textarea::make('og_description')->rows(2),
                Select::make('og_image_media_id')->label('OG Image')->relationship('ogImageMedia', 'original_name')->searchable()->preload(),
                TextInput::make('og_image_url')->maxLength(255),
                TextInput::make('robots')->default('index,follow')->maxLength(255),
            ])->columns(2)->maxItems(2)->columnSpanFull()->collapsed(),
            Repeater::make('attachments')->relationship()->schema([
                Select::make('media_asset_id')->label('Media')->relationship('mediaAsset', 'original_name')->searchable()->preload(),
                Select::make('kind')->required()->options(['image' => 'Image', 'file' => 'File', 'video' => 'Video'])->default('file'),
                TextInput::make('label_ar')->maxLength(255),
                TextInput::make('label_en')->maxLength(255),
                TextInput::make('legacy_path')->maxLength(255),
                TextInput::make('sort_order')->numeric()->default(0),
            ])->columns(2)->columnSpanFull()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('translations.title')->label('Titles')->listWithLineBreaks()->limit(50),
                TextColumn::make('slug')->searchable()->limit(42),
                TextColumn::make('category.slug')->label('Category')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'published' => 'success',
                    'scheduled' => 'warning',
                    'archived' => 'gray',
                    default => 'gray',
                }),
                IconColumn::make('is_enabled')->boolean(),
                IconColumn::make('is_featured')->boolean(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'archived' => 'Archived']),
                SelectFilter::make('news_category_id')->label('Category')->relationship('category', 'slug')->searchable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([])
            ->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsArticles::route('/'),
            'create' => Pages\CreateNewsArticle::route('/create'),
            'edit' => Pages\EditNewsArticle::route('/{record}/edit'),
        ];
    }

    private static function scopeQueryToCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->role_slug !== 'faculty_editor') {
            return $query;
        }

        $scope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';

        return $scope === '' ? $query->whereRaw('1 = 0') : $query->where('faculty_scope_slug', $scope);
    }
}
