<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicFormSubmissionResource\Pages;
use App\Models\Form\DynamicFormSubmission;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class DynamicFormSubmissionResource extends Resource
{
    protected static ?string $model = DynamicFormSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'form_id';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', DynamicFormSubmission::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.contact');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.dynamic_form_submissions');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete', $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Submission')->schema([
                TextInput::make('form_id')->label('Form')->disabled(),
                TextInput::make('locale')->disabled(),
                TextInput::make('applicant_name')->disabled(),
                TextInput::make('applicant_email')->disabled(),
                TextInput::make('status')->disabled(),
                TextInput::make('created_at')->label('Submitted')->disabled(),
            ])->columns(2),

            Section::make('Payload')->schema([
                Textarea::make('payload_json')
                    ->label('Submitted Fields')
                    ->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                    ->rows(12)
                    ->disabled()
                    ->columnSpanFull(),
            ]),

            Section::make('Files')->schema([
                Textarea::make('files_json')
                    ->label('Stored Files')
                    ->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                    ->rows(8)
                    ->disabled()
                    ->columnSpanFull(),
            ]),

            Section::make('Request')->schema([
                TextInput::make('ip_address')->label('IP Address')->disabled(),
                Textarea::make('user_agent')->label('User Agent')->disabled()->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('form_id')->label('Form')->searchable()->sortable(),
                TextColumn::make('applicant_name')->searchable()->sortable()->placeholder('-'),
                TextColumn::make('applicant_email')->searchable()->sortable()->placeholder('-'),
                TextColumn::make('payload_json._context.job_title')->label('Selected Job')->wrap()->placeholder('-'),
                TextColumn::make('payload_json.targetFaculty')->label('Admissions Faculty')->wrap()->placeholder('-'),
                TextColumn::make('locale')->badge()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'new' ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('form_id')
                    ->label('Form')
                    ->options([
                        'conference-registration' => 'Conference Registration',
                        'symposium-registration' => 'Symposium Registration',
                        'activity-registration' => 'Activity Registration',
                        'job-application' => 'Job Application',
                        'admissions-application' => 'Admissions Application',
                        'suggestions-complaints' => 'Suggestions & Complaints',
                    ]),
                SelectFilter::make('locale')->options(['ar' => 'Arabic', 'en' => 'English']),
                SelectFilter::make('status')->options(['new' => 'New']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDynamicFormSubmissions::route('/'),
            'view' => Pages\ViewDynamicFormSubmission::route('/{record}'),
        ];
    }
}
