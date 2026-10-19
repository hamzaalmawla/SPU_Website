<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Enums\FormSubmissionInbox;
use App\Filament\Resources\FormDefinitionResource\Pages;
use App\Models\Form\FormDefinition;
use App\Models\User\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Forms editors build themselves: name, inbox and fields (optionally in
 * steps). Pages that take a form (events, admissions, jobs, suggestions)
 * pick one of these by name.
 */
class FormDefinitionResource extends Resource
{
    protected static ?string $model = FormDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', FormDefinition::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.contact');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.forms.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.forms.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.forms.plural_model');
    }

    public static function form(Form $form): Form
    {
        $isFileType = fn (Get $get): bool => $get('type') === 'file';
        $hasPlaceholder = fn (Get $get): bool => in_array($get('type'), ['text', 'email', 'tel', 'number', 'textarea'], true);

        return $form->schema([
            Section::make(__('admin.forms.sections.form'))->schema([
                TextInput::make('nameAr')->label(__('admin.forms.fields.name_ar'))->required()->maxLength(255),
                TextInput::make('nameEn')->label(__('admin.forms.fields.name_en'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('key')
                    ->label(__('admin.forms.fields.key'))
                    ->helperText(__('admin.forms.help.key'))
                    ->required()
                    ->maxLength(80)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->extraInputAttributes(['dir' => 'ltr']),
                Select::make('inbox')
                    ->label(__('admin.forms.fields.inbox'))
                    ->helperText(__('admin.forms.help.inbox'))
                    ->options(collect(FormSubmissionInbox::cases())->mapWithKeys(fn (FormSubmissionInbox $inbox): array => [$inbox->value => __('form_submissions.inboxes.'.$inbox->value)])->all())
                    ->required(),
                TextInput::make('submitLabelAr')->label(__('admin.forms.fields.submit_label_ar'))->maxLength(80),
                TextInput::make('submitLabelEn')->label(__('admin.forms.fields.submit_label_en'))->maxLength(80)->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('isEnabled')->label(__('admin.forms.fields.enabled'))->helperText(__('admin.forms.help.enabled'))->default(true),
                Toggle::make('multiStep')->label(__('admin.forms.fields.multi_step'))->helperText(__('admin.forms.help.multi_step'))->live(),
            ])->columns(2),

            Repeater::make('steps')
                ->label(fn (Get $get): string => $get('multiStep') ? __('admin.forms.fields.steps') : __('admin.forms.fields.fields'))
                ->schema([
                    TextInput::make('titleAr')->label(__('admin.forms.fields.step_title_ar'))->required()->maxLength(120)->visible(fn (Get $get): bool => (bool) $get('../../multiStep')),
                    TextInput::make('titleEn')->label(__('admin.forms.fields.step_title_en'))->required()->maxLength(120)->visible(fn (Get $get): bool => (bool) $get('../../multiStep'))->extraInputAttributes(['dir' => 'ltr']),
                    Repeater::make('fields')
                        ->label(__('admin.forms.fields.fields'))
                        ->schema([
                            TextInput::make('labelAr')->label(__('admin.forms.fields.label_ar'))->required()->maxLength(255),
                            TextInput::make('labelEn')->label(__('admin.forms.fields.label_en'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                            TextInput::make('name')
                                ->label(__('admin.forms.fields.field_name'))
                                ->helperText(__('admin.forms.help.field_name'))
                                ->required()
                                ->maxLength(60)
                                ->regex('/^[a-zA-Z][a-zA-Z0-9_]*$/')
                                ->extraInputAttributes(['dir' => 'ltr']),
                            Select::make('type')
                                ->label(__('admin.forms.fields.type'))
                                ->options(collect(FormDefinitionServiceInterface::FIELD_TYPES)->mapWithKeys(fn (string $type): array => [$type => __('admin.forms.types.'.$type)])->all())
                                ->default('text')
                                ->required()
                                ->live(),
                            Toggle::make('required')->label(__('admin.forms.fields.required'))->inline(false),
                            TextInput::make('placeholderAr')->label(__('admin.forms.fields.placeholder_ar'))->maxLength(255)->visible($hasPlaceholder),
                            TextInput::make('placeholderEn')->label(__('admin.forms.fields.placeholder_en'))->maxLength(255)->visible($hasPlaceholder)->extraInputAttributes(['dir' => 'ltr']),
                            TextInput::make('maxLength')->label(__('admin.forms.fields.max_length'))->numeric()->minValue(1)->maxValue(10000)->visible(fn (Get $get): bool => in_array($get('type'), ['text', 'textarea'], true)),
                            Repeater::make('options')
                                ->label(__('admin.forms.fields.options'))
                                ->schema([
                                    TextInput::make('labelAr')->label(__('admin.forms.fields.label_ar'))->required()->maxLength(255),
                                    TextInput::make('labelEn')->label(__('admin.forms.fields.label_en'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                                    TextInput::make('value')->label(__('admin.forms.fields.option_value'))->helperText(__('admin.forms.help.option_value'))->required()->maxLength(80)->extraInputAttributes(['dir' => 'ltr']),
                                ])
                                ->columns(3)
                                ->minItems(1)
                                ->defaultItems(1)
                                ->reorderable()
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => $get('type') === 'select'),
                            CheckboxList::make('fileTypes')
                                ->label(__('admin.forms.fields.file_types'))
                                ->options(collect(FormDefinitionServiceInterface::FILE_TYPES)->mapWithKeys(fn (string $type): array => [$type => strtoupper($type)])->all())
                                ->default(['pdf'])
                                ->columns(4)
                                ->required()
                                ->visible($isFileType),
                            TextInput::make('maxMegabytes')->label(__('admin.forms.fields.max_megabytes'))->numeric()->minValue(1)->maxValue(10)->default(5)->required()->visible($isFileType),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->reorderable()
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => trim(($state['labelAr'] ?? '').' · '.($state['labelEn'] ?? ''), ' ·') ?: null)
                        ->addActionLabel(__('admin.forms.actions.add_field')),
                ])
                ->minItems(1)
                ->maxItems(fn (Get $get): ?int => $get('multiStep') ? null : 1)
                ->defaultItems(1)
                ->reorderable(fn (Get $get): bool => (bool) $get('multiStep'))
                ->addable(fn (Get $get): bool => (bool) $get('multiStep'))
                ->deletable(fn (Get $get): bool => (bool) $get('multiStep'))
                ->itemLabel(fn (array $state): ?string => trim(($state['titleAr'] ?? '').' · '.($state['titleEn'] ?? ''), ' ·') ?: null)
                ->addActionLabel(__('admin.forms.actions.add_step'))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $locale = fn (): string => app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $table
            ->columns([
                TextColumn::make('name_ar')
                    ->label(__('admin.forms.fields.name'))
                    ->formatStateUsing(fn (FormDefinition $record): string => $locale() === 'ar' ? (string) $record->name_ar : (string) $record->name_en)
                    ->searchable(['name_ar', 'name_en', 'key']),
                TextColumn::make('key')->label(__('admin.forms.fields.key'))->fontFamily('mono'),
                TextColumn::make('inbox')->label(__('admin.forms.fields.inbox'))->badge()->formatStateUsing(fn (string $state): string => __('form_submissions.inboxes.'.$state)),
                TextColumn::make('fields_count')
                    ->label(__('admin.forms.fields.fields_count'))
                    ->state(fn (FormDefinition $record): int => count(app(FormDefinitionServiceInterface::class)->find((string) $record->key)?->fields ?? [])),
                TextColumn::make('submissions_count')
                    ->label(__('admin.forms.fields.submissions_count'))
                    ->state(fn (FormDefinition $record): int => app(FormDefinitionServiceInterface::class)->submissionCount((string) $record->key)),
                IconColumn::make('is_enabled')->label(__('admin.forms.fields.enabled'))->boolean(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn (FormDefinition $record): string => $record->is_enabled ? __('admin.forms.actions.disable') : __('admin.forms.actions.enable'))
                    ->icon(fn (FormDefinition $record): string => $record->is_enabled ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (FormDefinition $record): string => $record->is_enabled ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (FormDefinition $record): void {
                        app(FormDefinitionServiceInterface::class)->setEnabled((int) $record->getKey(), ! $record->is_enabled, self::userId());
                    }),
                Action::make('delete')
                    ->label(__('admin.forms.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.forms.help.delete'))
                    ->action(function (FormDefinition $record): void {
                        try {
                            app(FormDefinitionServiceInterface::class)->delete((int) $record->getKey(), self::userId());
                            Notification::make()->title(__('admin.forms.notifications.deleted'))->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(__('admin.forms.notifications.cannot_delete'))
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormDefinitions::route('/'),
            'create' => Pages\CreateFormDefinition::route('/create'),
            'edit' => Pages\EditFormDefinition::route('/{record}/edit'),
        ];
    }

    /**
     * The stored form as the builder's fields.
     *
     * @return array<string, mixed>
     */
    public static function formState(FormDefinition $record): array
    {
        $form = app(FormDefinitionServiceInterface::class)->find((string) $record->key);

        if ($form === null) {
            return [];
        }

        return [
            'key' => $form->key,
            'nameAr' => $form->nameAr,
            'nameEn' => $form->nameEn,
            'inbox' => $form->inbox->value,
            'isEnabled' => $form->isEnabled,
            'multiStep' => $form->multiStep,
            'submitLabelAr' => $form->submitLabelAr,
            'submitLabelEn' => $form->submitLabelEn,
            'steps' => array_map(fn (array $step): array => [
                'titleAr' => $step['titleAr'],
                'titleEn' => $step['titleEn'],
                'fields' => array_map(fn (array $field): array => $field + ($field['type'] === 'file' ? [
                    'fileTypes' => array_values(array_filter(array_map(fn (string $type): string => ltrim(trim($type), '.'), explode(',', (string) ($field['accept'] ?? ''))))),
                    'maxMegabytes' => round(((int) ($field['maxKilobytes'] ?? 5120)) / 1024, 1),
                ] : []), $step['fields']),
            ], $form->steps),
        ];
    }

    /**
     * Save through the service, which checks the whole form; its findings are
     * shown as one message and the save stops. Returns the saved form's id.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveForm(?int $id, array $data, callable $halt): int
    {
        try {
            return app(FormDefinitionServiceInterface::class)->save($id, $data, self::userId())->id;
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('admin.forms.notifications.not_saved'))
                ->body(collect($exception->errors())->flatten()->unique()->implode("\n"))
                ->danger()
                ->persistent()
                ->send();
            $halt();

            // halt() stops the page; this line is never reached.
            throw $exception;
        }
    }

    private static function userId(): int
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return (int) $user->getKey();
    }
}
