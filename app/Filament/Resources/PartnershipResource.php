<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PartnershipResource\Pages;
use App\Models\Content\Partnership;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\Gate;

class PartnershipResource extends Resource
{
    protected static ?string $model = Partnership::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'About';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Partnership')->schema([
                TextInput::make('slug')->required()->maxLength(255)->alphaDash(),
                TextInput::make('logo')->maxLength(255),
                TextInput::make('website_url')->url()->maxLength(255),
                DatePicker::make('signed_at'),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            Repeater::make('translations')->relationship()->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English']),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('category')->maxLength(255),
                TextInput::make('status')->maxLength(255),
                TextInput::make('established_label')->maxLength(255),
                TextInput::make('scope')->maxLength(255),
                Textarea::make('description')->rows(4)->columnSpanFull(),
            ])->columns(2)->minItems(2)->maxItems(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.name')->label('Names')->listWithLineBreaks()->limit(40),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPartnerships::route('/'), 'create' => Pages\CreatePartnership::route('/create'), 'edit' => Pages\EditPartnership::route('/{record}/edit')];
    }
}
