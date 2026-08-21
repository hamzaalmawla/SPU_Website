<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\FormSubmissionInbox;
use App\Enums\FormSubmissionStatus;
use App\Filament\Resources\DynamicFormSubmissionResource\Pages;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
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

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', DynamicFormSubmission::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role_slug === 'hr'
            ? __('admin.navigation.groups.job_board')
            : __('admin.navigation.groups.contact');
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role_slug === 'hr'
            ? __('admin.navigation.items.job_applications')
            : __('admin.navigation.items.dynamic_form_submissions');
    }

    public static function getModelLabel(): string
    {
        return __('form_submissions.resource.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('form_submissions.resource.plural_model');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->whereNull('read_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof DynamicFormSubmission) {
            return self::getModelLabel();
        }

        $applicant = filled($record->applicant_name)
            ? (string) $record->applicant_name
            : (filled($record->applicant_email) ? (string) $record->applicant_email : __('form_submissions.values.unknown_applicant'));

        return __('form_submissions.resource.record_title', [
            'applicant' => $applicant,
            'form' => self::formLabel((string) $record->form_id),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $formIds = collect(FormSubmissionInbox::cases())
            ->flatMap(fn (FormSubmissionInbox $inbox): array => $inbox->formIds())
            ->all();

        $query = parent::getEloquentQuery()->whereIn('form_id', $formIds);
        $user = auth()->user();

        if ($user instanceof User && $user->role_slug === 'hr') {
            $query->where('form_id', 'job-application');
        }

        return $query;
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
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label(__('form_submissions.detail.reference'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('applicant_name')
                    ->label(__('form_submissions.columns.applicant'))
                    ->description(fn (DynamicFormSubmission $record): ?string => $record->applicant_email)
                    ->searchable(['applicant_name', 'applicant_email'])
                    ->sortable()
                    ->placeholder(__('form_submissions.values.unknown_applicant')),
                TextColumn::make('context_title')
                    ->label(__('form_submissions.columns.context_title'))
                    ->state(fn (DynamicFormSubmission $record): ?string => self::contextTitle($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $extract = $query->getModel()->getConnection()->getDriverName() === 'sqlite'
                            ? static fn (string $path): string => "json_extract(payload_json, '{$path}')"
                            : static fn (string $path): string => "JSON_UNQUOTE(JSON_EXTRACT(payload_json, '{$path}'))";
                        $term = "%{$search}%";

                        return $query->where(function (Builder $q) use ($extract, $term): void {
                            $q->whereRaw($extract('$._context.event_title').' LIKE ?', [$term])
                                ->orWhereRaw($extract('$._context.job_title').' LIKE ?', [$term])
                                ->orWhereRaw($extract('$.subject').' LIKE ?', [$term]);
                        });
                    })
                    ->wrap()
                    ->placeholder(__('form_submissions.values.no_context_title')),
                TextColumn::make('request_target')
                    ->label(__('form_submissions.columns.request_target'))
                    ->state(fn (DynamicFormSubmission $record): ?string => self::requestTarget($record))
                    ->wrap()
                    ->placeholder(__('form_submissions.values.not_applicable')),
                TextColumn::make('form_id')
                    ->label(__('form_submissions.columns.form_type'))
                    ->formatStateUsing(fn (string $state): string => self::formLabel($state))
                    ->sortable(),
                TextColumn::make('locale')
                    ->label(__('form_submissions.columns.locale'))
                    ->formatStateUsing(fn (string $state): string => __('form_submissions.locales.'.$state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('form_submissions.columns.status'))
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->sortable(),
                TextColumn::make('read_at')
                    ->label(__('form_submissions.columns.read'))
                    ->state(fn (DynamicFormSubmission $record): string => $record->read_at ? __('form_submissions.values.read') : __('form_submissions.values.unread'))
                    ->badge()
                    ->color(fn (DynamicFormSubmission $record): string => $record->read_at ? 'gray' : 'danger'),
                TextColumn::make('created_at')
                    ->label(__('form_submissions.columns.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('form_id')
                    ->label(__('form_submissions.filters.form_type'))
                    ->options([
                        'conference-registration' => __('form_submissions.forms.conference-registration'),
                        'symposium-registration' => __('form_submissions.forms.symposium-registration'),
                        'activity-registration' => __('form_submissions.forms.activity-registration'),
                        'job-application' => __('form_submissions.forms.job-application'),
                        'admissions-application' => __('form_submissions.forms.admissions-application'),
                        'suggestions-complaints' => __('form_submissions.forms.suggestions-complaints'),
                    ]),
                SelectFilter::make('locale')
                    ->label(__('form_submissions.filters.locale'))
                    ->options([
                        'ar' => __('form_submissions.locales.ar'),
                        'en' => __('form_submissions.locales.en'),
                    ]),
                SelectFilter::make('status')
                    ->label(__('form_submissions.filters.status'))
                    ->options(collect(FormSubmissionStatus::cases())
                        ->mapWithKeys(fn (FormSubmissionStatus $status): array => [
                            $status->value => __('form_submissions.statuses.'.$status->value),
                        ])
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('form_submissions.actions.review')),
            ])
            ->bulkActions([])
            ->emptyStateHeading(__('form_submissions.empty.heading'))
            ->emptyStateDescription(__('form_submissions.empty.description'))
            ->emptyStateIcon('heroicon-o-inbox')
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

    private static function formLabel(string $formId): string
    {
        $key = 'form_submissions.forms.'.$formId;
        $label = __($key);

        return $label === $key ? __('form_submissions.inboxes.unknown') : $label;
    }

    private static function statusLabel(string $status): string
    {
        $key = 'form_submissions.statuses.'.$status;
        $label = __($key);

        return $label === $key ? __('form_submissions.values.unknown_status') : $label;
    }

    private static function statusColor(string $status): string
    {
        return match (FormSubmissionStatus::tryFrom($status)) {
            FormSubmissionStatus::NEW => 'info',
            FormSubmissionStatus::IN_REVIEW => 'warning',
            FormSubmissionStatus::ACCEPTED, FormSubmissionStatus::RESOLVED => 'success',
            FormSubmissionStatus::REJECTED => 'danger',
            FormSubmissionStatus::CLOSED => 'gray',
            default => 'gray',
        };
    }

    private static function contextTitle(DynamicFormSubmission $record): ?string
    {
        $payload = is_array($record->payload_json) ? $record->payload_json : [];
        $context = is_array($payload['_context'] ?? null) ? $payload['_context'] : [];

        foreach ([$context['event_title'] ?? null, $context['job_title'] ?? null, $payload['subject'] ?? null] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function requestTarget(DynamicFormSubmission $record): ?string
    {
        $payload = is_array($record->payload_json) ? $record->payload_json : [];

        foreach (['requestType', 'applicantType', 'targetFaculty', 'role'] as $field) {
            $value = $payload[$field] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $key = 'form_submissions.options.'.$value;
            $label = __($key);

            return $label === $key ? $value : $label;
        }

        return null;
    }
}
