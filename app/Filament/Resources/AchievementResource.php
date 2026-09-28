<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AchievementResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Achievement\Achievement;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AchievementResource extends Resource
{
    protected static ?string $model = Achievement::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('admin.achievement.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.achievement.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.achievement.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Achievement::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['translations', 'categories.translations', 'imageMedia']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin.achievement.content'))->schema([
                MediaPicker::assetImage('image_media_id', __('admin.achievement.image')),
                Select::make('categories')->label(__('admin.achievement.categories'))->relationship('categories', 'slug', modifyQueryUsing: fn (Builder $query): Builder => $query->active())->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->translations->firstWhere('locale', app()->getLocale())?->name ?? $record->translations->firstWhere('locale', 'ar')?->name ?? $record->slug))->multiple()->preload()->searchable()->required(),
                Repeater::make('translations')->relationship()->label(__('admin.achievement.translations'))->schema([
                    Hidden::make('locale')->required(),
                    TextInput::make('title')->label(__('admin.achievement.title'))->required()->maxLength(255)->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                    Textarea::make('summary')->label(__('admin.achievement.summary'))->rows(3)->maxLength(1000)->columnSpanFull()->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                    TextInput::make('meta')->label(__('admin.achievement.meta'))->maxLength(255),
                    TextInput::make('action_label')->label(__('admin.achievement.action_label'))->maxLength(100),
                    TextInput::make('action_url')->label(__('admin.achievement.action_url'))->maxLength(2048)->columnSpanFull(),
                ])->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->deletable(false)->reorderable(false)->columns(2)->columnSpanFull(),
            ])->columns(2),
            Section::make(__('admin.achievement.publishing'))->schema([
                Select::make('status')->label(__('admin.achievement.status'))->options(['draft' => __('admin.achievement.statuses.draft'), 'published' => __('admin.achievement.statuses.published')])->default('draft')->required()->live(),
                DateTimePicker::make('published_at')->label(__('admin.achievement.published_at'))->seconds(false)->required(fn (Get $get): bool => $get('status') === 'published' && (bool) $get('is_public')),
                Toggle::make('is_public')->label(__('admin.achievement.public'))->default(false)->live(),
                Toggle::make('pin_to_homepage')->label(__('admin.achievement.pin'))->default(false),
                TextInput::make('sort_order')->label(__('admin.achievement.sort_order'))->numeric()->default(0),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label(__('admin.achievement.title'))->getStateUsing(fn (Achievement $record): string => (string) ($record->translations->firstWhere('locale', app()->getLocale())?->title ?? $record->translations->firstWhere('locale', 'ar')?->title ?? '#'.$record->getKey()))->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('translations', fn (Builder $q): Builder => $q->where('title', 'like', "%{$search}%"))),
            TextColumn::make('status')->label(__('admin.achievement.status'))->badge(),
            IconColumn::make('is_public')->label(__('admin.achievement.public'))->boolean(),
            IconColumn::make('pin_to_homepage')->label(__('admin.achievement.pin'))->boolean(),
            TextColumn::make('published_at')->label(__('admin.achievement.published_at'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => __('admin.achievement.statuses.draft'), 'published' => __('admin.achievement.statuses.published')]),
            TernaryFilter::make('is_public')->label(__('admin.achievement.public')),
            TernaryFilter::make('pin_to_homepage')->label(__('admin.achievement.pin')),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAchievements::route('/'), 'create' => Pages\CreateAchievement::route('/create'), 'edit' => Pages\EditAchievement::route('/{record}/edit')];
    }
}
