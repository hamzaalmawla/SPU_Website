<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Career\GraduationGradeServiceInterface;
use App\DTOs\Career\GraduationGradeDTO;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The graduate grade on a faculty's Alumni page: the Grade dropdown on each
 * graduate, and the super-admin "Grades" menu (edit the list, delete a grade
 * and move its graduates). The list is shared by every faculty, so these
 * show on every faculty's Alumni page.
 *
 * Relies on the helpers of ManagesFacultyHomepage. All reading and writing
 * happens in GraduationGradeServiceInterface.
 */
trait ManagesGraduationGrades
{
    public function canManageGraduationGrades(string $action = 'view'): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $this->subpageSlugFromTarget($this->currentTargetKeyForSchema()) === 'alumni'
            && $this->graduationGrades()->canManage($user, $action);
    }

    /** The Grade dropdown on one graduate of the Alumni page editor. */
    protected function graduationGradeField(): Select
    {
        return Select::make('gradeKey')
            ->label(__('student_lists.grades_admin.field'))
            ->helperText(__('student_lists.grades_admin.field_help'))
            ->options(fn (): array => $this->graduationGrades()->options())
            ->placeholder(__('student_lists.grades_admin.no_grade'))
            ->native(false)
            ->createOptionForm($this->graduationGradeFormSchema())
            ->createOptionUsing(fn (array $data): string => $this->saveGraduationGrade(null, $data))
            ->createOptionModalHeading(__('student_lists.grades_admin.add_heading'))
            ->createOptionAction(fn (FormAction $action): FormAction => $action->visible(fn (): bool => $this->canManageGraduationGrades('create')))
            ->editOptionForm($this->graduationGradeFormSchema())
            ->fillEditOptionActionFormUsing(fn (Select $component): array => $this->graduationGradeFormData($component->getState()))
            ->updateOptionUsing(function (array $data, Select $component): void {
                $this->saveGraduationGrade(is_string($component->getState()) ? $component->getState() : null, $data);
            })
            ->editOptionModalHeading(__('student_lists.grades_admin.edit_one_heading'))
            ->editOptionAction(fn (FormAction $action): FormAction => $action->visible(fn (): bool => $this->canManageGraduationGrades('update')));
    }

    /** @return list<ActionGroup> */
    protected function graduationGradeActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('editGraduationGrades')
                    ->label(__('student_lists.grades_admin.edit_action'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->canManageGraduationGrades('update'))
                    ->modalHeading(__('student_lists.grades_admin.edit_heading'))
                    ->modalDescription(__('student_lists.grades_admin.edit_description'))
                    ->fillForm(fn (): array => [
                        'grades' => $this->graduationGrades()->all()
                            ->map(static fn (GraduationGradeDTO $grade): array => ['key' => $grade->key, 'nameAr' => $grade->nameAr, 'nameEn' => $grade->nameEn])
                            ->all(),
                    ])
                    ->form([
                        Repeater::make('grades')
                            ->label(__('student_lists.grades_admin.list'))
                            ->schema([
                                Hidden::make('key'),
                                ...$this->graduationGradeNameFields(),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->deletable(false)
                            ->addable(fn (): bool => $this->canManageGraduationGrades('create'))
                            ->addActionLabel(__('student_lists.grades_admin.add'))
                            ->defaultItems(0),
                    ])
                    ->action(function (array $data): void {
                        $this->saveGraduationGradeList(is_array($data['grades'] ?? null) ? array_values($data['grades']) : []);
                    }),

                Action::make('deleteGraduationGrade')
                    ->label(__('student_lists.grades_admin.delete_action'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => $this->canManageGraduationGrades('delete'))
                    ->modalHeading(__('student_lists.grades_admin.delete_heading'))
                    ->modalDescription(__('student_lists.grades_admin.delete_description'))
                    ->modalSubmitActionLabel(__('student_lists.grades_admin.delete_action'))
                    ->form([
                        Select::make('grade')
                            ->label(__('student_lists.grades_admin.delete_grade'))
                            ->options(fn (): array => $this->graduationGrades()->options())
                            ->native(false)
                            ->required()
                            ->live(),
                        Placeholder::make('usage')
                            ->label(__('student_lists.grades_admin.usage'))
                            ->content(function (Get $get): string {
                                $key = is_string($get('grade')) ? $get('grade') : '';
                                $count = $key !== '' ? $this->graduationGrades()->usageCount($key) : 0;

                                return $count > 0
                                    ? __('student_lists.grades_admin.usage_count', ['count' => $count])
                                    : __('student_lists.grades_admin.usage_none');
                            })
                            ->visible(fn (Get $get): bool => is_string($get('grade')) && $get('grade') !== ''),
                        Select::make('moveTo')
                            ->label(__('student_lists.grades_admin.move_to'))
                            ->helperText(__('student_lists.grades_admin.move_to_help'))
                            ->options(fn (Get $get): array => array_diff_key($this->graduationGrades()->options(), [(string) $get('grade') => true]))
                            ->placeholder(__('student_lists.grades_admin.no_grade'))
                            ->native(false)
                            ->visible(fn (Get $get): bool => is_string($get('grade')) && $get('grade') !== ''),
                    ])
                    ->action(function (array $data): void {
                        $this->deleteGraduationGrade((string) ($data['grade'] ?? ''), is_string($data['moveTo'] ?? null) ? $data['moveTo'] : null);
                    }),
            ])
                ->label(__('student_lists.grades_admin.manage_action'))
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->button()
                ->visible(fn (): bool => $this->canManageGraduationGrades('update') || $this->canManageGraduationGrades('delete')),
        ];
    }

    /** @return array<int, Component> */
    private function graduationGradeFormSchema(): array
    {
        return [Hidden::make('key'), ...$this->graduationGradeNameFields()];
    }

    /** @return list<TextInput> */
    private function graduationGradeNameFields(): array
    {
        return [
            TextInput::make('nameAr')->label(__('student_lists.grades_admin.name_ar'))->required()->maxLength(120)->extraInputAttributes(['dir' => 'rtl']),
            TextInput::make('nameEn')->label(__('student_lists.grades_admin.name_en'))->required()->maxLength(120)->extraInputAttributes(['dir' => 'ltr']),
        ];
    }

    /** @return array<string, string> */
    private function graduationGradeFormData(mixed $key): array
    {
        $grade = is_string($key) && $key !== '' ? $this->graduationGrades()->find($key) : null;

        return $grade === null ? [] : ['key' => $grade->key, 'nameAr' => $grade->nameAr, 'nameEn' => $grade->nameEn];
    }

    /**
     * Adds a grade to the shared list, or renames one, and returns its key.
     * A refused name keeps the dialog open with the reason.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveGraduationGrade(?string $key, array $data): string
    {
        return $this->withGradeErrors(function () use ($key, $data): string {
            $nameAr = (string) ($data['nameAr'] ?? '');
            $nameEn = (string) ($data['nameEn'] ?? '');

            return $key === null || $key === ''
                ? $this->graduationGrades()->create($nameAr, $nameEn, $this->authenticatedUserId())->key
                : $this->graduationGrades()->update($key, $nameAr, $nameEn, $this->authenticatedUserId())->key;
        });
    }

    /** @param list<mixed> $rows the list as edited: renamed, added and reordered */
    private function saveGraduationGradeList(array $rows): void
    {
        $this->withGradeErrors(function () use ($rows): void {
            $grades = $this->graduationGrades();
            $order = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $key = is_string($row['key'] ?? null) ? $row['key'] : '';
                $nameAr = trim((string) ($row['nameAr'] ?? ''));
                $nameEn = trim((string) ($row['nameEn'] ?? ''));
                $current = $key !== '' ? $grades->find($key) : null;

                if ($current === null) {
                    $order[] = $grades->create($nameAr, $nameEn, $this->authenticatedUserId())->key;

                    continue;
                }

                if ($current->nameAr !== $nameAr || $current->nameEn !== $nameEn) {
                    $grades->update($key, $nameAr, $nameEn, $this->authenticatedUserId());
                }

                $order[] = $key;
            }

            $grades->reorder($order, $this->authenticatedUserId());
        });

        Notification::make()->title(__('student_lists.grades_admin.saved'))->success()->send();
    }

    private function deleteGraduationGrade(string $key, ?string $moveTo): void
    {
        $moveTo = $moveTo !== null && $moveTo !== '' ? $moveTo : null;
        $moveToName = $moveTo !== null ? $this->graduationGrades()->label($moveTo, app()->getLocale() === 'ar' ? 'ar' : 'en') : null;
        $count = $this->withGradeErrors(fn (): int => $this->graduationGrades()->delete($key, $moveTo, $this->authenticatedUserId()));

        Notification::make()
            ->title($moveTo !== null
                ? __('student_lists.grades_admin.deleted_moved', ['count' => $count, 'grade' => (string) $moveToName])
                : __('student_lists.grades_admin.deleted_cleared', ['count' => $count]))
            ->success()
            ->send();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withGradeErrors(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title((string) (collect($exception->errors())->flatten()->first() ?? __('student_lists.grades_admin.errors.unknown')))
                ->send();
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }

        throw new Halt;
    }

    private function graduationGrades(): GraduationGradeServiceInterface
    {
        return app(GraduationGradeServiceInterface::class);
    }
}
