@php
    /** @var \Illuminate\Support\Collection<int, \App\DTOs\Career\StudentListImportSummaryDTO> $imports */
    $undoableId = $imports->first(fn ($import) => $import->undoneAt === null)?->id;
@endphp

@if ($imports->isEmpty())
    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('student_lists.admin.history_empty') }}</p>
@else
    <div class="spu-import-table-wrap">
        <table class="spu-import-table">
            <thead>
                <tr>
                    <th>{{ __('student_lists.admin.columns.imported_at') }}</th>
                    <th>{{ __('student_lists.admin.columns.file') }}</th>
                    <th>{{ __('student_lists.admin.semester') }}</th>
                    <th>{{ __('student_lists.admin.columns.result') }}</th>
                    <th>{{ __('student_lists.admin.columns.imported_by') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($imports as $import)
                    <tr>
                        <td dir="ltr">{{ $import->importedAt }}</td>
                        <td>
                            {{ $import->fileName }}
                            @if ($import->decisionNumber)
                                <span class="spu-import-table__note" dir="ltr">{{ $import->decisionNumber }}</span>
                            @endif
                        </td>
                        <td>{{ \App\Enums\AcademicSemester::tryFrom($import->semester)?->label(app()->getLocale()) }} <span dir="ltr">{{ $import->academicYear }}</span></td>
                        <td>{{ __('student_lists.admin.counts', ['create' => $import->created, 'update' => $import->updated, 'skip' => $import->skipped]) }}</td>
                        <td>{{ $import->importedBy ?? '—' }}</td>
                        <td>
                            @if ($import->undoneAt)
                                <span class="spu-import-table__note">{{ __('student_lists.admin.undone', ['date' => $import->undoneAt]) }}</span>
                            @elseif ($import->id === $undoableId)
                                <x-filament::button
                                    color="danger"
                                    size="xs"
                                    wire:click="undoStudentListImport({{ $import->id }})"
                                    wire:confirm="{{ __('student_lists.admin.undo_confirm') }}"
                                >{{ __('student_lists.admin.undo') }}</x-filament::button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
