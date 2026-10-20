<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Career\StudentListImportServiceInterface;
use App\DTOs\Career\StudentListImportChoicesDTO;
use App\DTOs\Career\StudentListImportPreviewDTO;
use App\DTOs\Career\StudentListSpecializationDTO;
use App\Enums\AcademicSemester;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * "Import from decision" on a faculty's Alumni and Honor List admin pages:
 * upload the council decision (.docx), review what it will do, confirm.
 * History lists past imports with Undo for the newest one.
 *
 * Relies on the properties and helpers of ManagesFacultyHomepage. All reading,
 * matching and writing happens in StudentListImportServiceInterface.
 */
trait ImportsStudentLists
{
    /** Path of the uploaded decision on the private disk, between upload and confirm. */
    public ?string $studentListUpload = null;

    public ?string $studentListUploadName = null;

    private StudentListImportServiceInterface $studentListImports;

    private ?StudentListImportPreviewDTO $studentListPreviewCache = null;

    /** alumni | honor_students for the open target, null on other targets. */
    public function studentListKind(): ?string
    {
        return match ($this->subpageSlugFromTarget($this->currentTargetKeyForSchema())) {
            'alumni' => StudentListImportServiceInterface::KIND_ALUMNI,
            'valedictorians' => StudentListImportServiceInterface::KIND_HONOR_STUDENTS,
            default => null,
        };
    }

    public function canImportStudentList(): bool
    {
        $user = auth()->user();
        $kind = $this->studentListKind();

        return $user instanceof User
            && $kind !== null
            && $this->studentListImports->canImport($user, $this->facultyScope, $kind);
    }

    public function undoStudentListImport(int $importId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        try {
            $this->studentListImports->undo($importId, $user);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title(__($exception->getMessage()))->danger()->send();

            return;
        }

        Notification::make()->title(__('student_lists.admin.undo_done'))->success()->send();
        $this->loadTarget($this->currentTargetKeyForSchema());
    }

    /** @return list<Action> */
    protected function studentListImportActions(): array
    {
        return [
            Action::make('importStudentList')
                ->label(__('student_lists.admin.import_action'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => $this->canImportStudentList())
                ->modalHeading(fn (): string => $this->studentListKind() === StudentListImportServiceInterface::KIND_ALUMNI
                    ? __('student_lists.admin.import_alumni_heading')
                    : __('student_lists.admin.import_honor_heading'))
                ->modalDescription(__('student_lists.admin.import_description'))
                ->modalSubmitActionLabel(__('student_lists.admin.read_file'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('student_lists.admin.file'))
                        ->helperText(__('student_lists.admin.file_help'))
                        ->disk('local')
                        ->directory(StudentListImportServiceInterface::UPLOAD_DIRECTORY)
                        ->visibility('private')
                        // Some editors save .docx files that are detected as a plain zip; the
                        // service checks the contents either way.
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'])
                        ->maxSize(5120)
                        ->storeFileNamesIn('file_name')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->discardStudentListUpload();
                    $path = is_string($data['file'] ?? null) ? $data['file'] : '';
                    $name = is_string($data['file_name'] ?? null) ? $data['file_name'] : basename($path);

                    try {
                        $this->studentListPreviewCache = $this->studentListImports->preview($this->facultyScope, (string) $this->studentListKind(), $path, $name);
                    } catch (InvalidArgumentException $exception) {
                        $this->studentListImports->discardUpload($path);
                        Notification::make()->title(__($exception->getMessage()))->danger()->send();

                        return;
                    }

                    $this->studentListUpload = $path;
                    $this->studentListUploadName = $name;
                    $this->replaceMountedAction('confirmStudentListImport');
                }),

            Action::make('confirmStudentListImport')
                ->label(__('student_lists.admin.confirm_action'))
                ->visible(fn (): bool => $this->studentListUpload !== null && $this->canImportStudentList())
                ->extraAttributes(['class' => 'hidden'])
                ->modalHeading(__('student_lists.admin.confirm_heading'))
                ->modalWidth(MaxWidth::SevenExtraLarge)
                ->modalSubmitActionLabel(__('student_lists.admin.confirm_action'))
                ->fillForm(function (): array {
                    $preview = $this->studentListPreview();

                    return [
                        'academic_year' => $preview?->academicYear,
                        'semester' => $preview?->semester,
                        'departments' => collect($preview->specializations ?? [])
                            ->map(fn (StudentListSpecializationDTO $specialization): ?int => $specialization->departmentId)
                            ->all(),
                    ];
                })
                ->form(fn (): array => $this->studentListConfirmSchema())
                ->action(function (array $data): void {
                    $this->applyStudentListImport($data);
                }),

            Action::make('studentListHistory')
                ->label(__('student_lists.admin.history_action'))
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->visible(fn (): bool => $this->canImportStudentList())
                ->modalHeading(__('student_lists.admin.history_heading'))
                ->modalWidth(MaxWidth::FiveExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament-actions::modal.actions.close.label'))
                ->modalContent(fn (): View => view('filament.student-lists.import-history', [
                    'imports' => $this->studentListImports->history($this->facultyScope, (string) $this->studentListKind()),
                ])),
        ];
    }

    /** @return array<int, Component> */
    private function studentListConfirmSchema(): array
    {
        $preview = $this->studentListPreview();

        if ($preview === null) {
            return [];
        }

        $departmentSelects = collect($preview->specializations)
            ->map(fn (StudentListSpecializationDTO $specialization, int $index): Select => Select::make('departments.'.$index)
                ->label(__('student_lists.admin.department_for', ['text' => $specialization->text, 'count' => $specialization->count]))
                ->options($preview->departmentOptions)
                ->placeholder(__('student_lists.admin.no_department'))
                ->hint($specialization->needsChoice ? __('student_lists.admin.needs_choice') : null)
                ->hintColor('warning')
                ->hintIcon($specialization->needsChoice ? 'heroicon-o-exclamation-triangle' : null))
            ->values()
            ->all();

        return [
            Placeholder::make('preview')
                ->hiddenLabel()
                ->content(new HtmlString(view('filament.student-lists.import-preview', ['preview' => $preview])->render())),
            Section::make()
                ->schema([
                    TextInput::make('academic_year')
                        ->label(__('student_lists.admin.academic_year'))
                        ->helperText(__('student_lists.admin.academic_year_help'))
                        ->required()
                        ->regex('/^\d{4}\/\d{4}$/'),
                    Select::make('semester')
                        ->label(__('student_lists.admin.semester'))
                        ->options(collect(AcademicSemester::cases())->mapWithKeys(fn (AcademicSemester $semester): array => [$semester->value => $semester->label(app()->getLocale())])->all())
                        ->required(),
                ])
                ->columns(2),
            Section::make(__('student_lists.admin.departments'))
                ->description(__('student_lists.admin.departments_help'))
                ->schema($departmentSelects)
                ->columns(2)
                ->visible($departmentSelects !== []),
        ];
    }

    /** @param array<string, mixed> $data */
    private function applyStudentListImport(array $data): void
    {
        $user = auth()->user();
        $preview = $this->studentListPreview();

        if (! $user instanceof User || $preview === null || $this->studentListUpload === null) {
            Notification::make()->title(__('student_lists.errors.upload_missing'))->danger()->send();

            return;
        }

        $chosen = is_array($data['departments'] ?? null) ? $data['departments'] : [];
        $departments = [];

        foreach ($preview->specializations as $index => $specialization) {
            $value = $chosen[$index] ?? null;
            $departments[$specialization->text] = is_numeric($value) ? (int) $value : null;
        }

        try {
            $result = $this->studentListImports->import(
                $this->facultyScope,
                $preview->kind,
                $this->studentListUpload,
                (string) $this->studentListUploadName,
                new StudentListImportChoicesDTO(
                    academicYear: trim((string) ($data['academic_year'] ?? '')),
                    semester: (string) ($data['semester'] ?? ''),
                    departments: $departments,
                ),
                $user,
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title(__($exception->getMessage()))->danger()->send();

            return;
        }

        $this->studentListUpload = null;
        $this->studentListUploadName = null;
        $this->studentListPreviewCache = null;

        Notification::make()
            ->title(__('student_lists.admin.imported', ['created' => $result->created, 'updated' => $result->updated, 'skipped' => $result->skipped]))
            ->success()
            ->send();

        $this->loadTarget($this->currentTargetKeyForSchema());
    }

    /** The preview of the pending upload, read once per request. */
    private function studentListPreview(): ?StudentListImportPreviewDTO
    {
        if ($this->studentListPreviewCache === null && $this->studentListUpload !== null && $this->studentListKind() !== null) {
            try {
                $this->studentListPreviewCache = $this->studentListImports->preview(
                    $this->facultyScope,
                    (string) $this->studentListKind(),
                    $this->studentListUpload,
                    (string) $this->studentListUploadName,
                );
            } catch (InvalidArgumentException) {
                $this->studentListUpload = null;
            }
        }

        return $this->studentListPreviewCache;
    }

    private function discardStudentListUpload(): void
    {
        if ($this->studentListUpload !== null) {
            $this->studentListImports->discardUpload($this->studentListUpload);
        }

        $this->studentListUpload = null;
        $this->studentListUploadName = null;
        $this->studentListPreviewCache = null;
    }
}
