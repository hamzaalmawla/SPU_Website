@php
    /** @var \App\DTOs\Career\StudentListImportPreviewDTO $preview */
    $isAlumni = $preview->kind === \App\Contracts\Career\StudentListImportServiceInterface::KIND_ALUMNI;
    $actionColor = fn (string $action): string => match ($action) {
        'create' => 'success',
        'update' => 'info',
        default => 'danger',
    };
@endphp

<div class="flex flex-col gap-4">
    <div class="text-sm text-gray-600 dark:text-gray-300">
        <p>{{ __('student_lists.admin.summary', ['faculty' => $preview->facultyName, 'file' => $preview->fileName, 'number' => $preview->decisionNumber ?? '—']) }}</p>
        <p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ __('student_lists.admin.counts', ['create' => $preview->createCount, 'update' => $preview->updateCount, 'skip' => $preview->skipCount]) }}</p>
    </div>

    @foreach ($preview->warnings as $warning)
        <p class="rounded-lg bg-warning-50 p-3 text-sm font-semibold text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">{{ __('student_lists.warnings.'.$warning) }}</p>
    @endforeach

    <div class="spu-import-table-wrap">
        <table class="spu-import-table">
            <thead>
                <tr>
                    <th>{{ __('student_lists.admin.columns.line') }}</th>
                    <th>{{ __('student_lists.admin.columns.id') }}</th>
                    <th>{{ __('student_lists.admin.columns.name') }}</th>
                    @unless ($isAlumni)
                        <th>{{ __('student_lists.admin.columns.year') }}</th>
                        <th>{{ __('student_lists.admin.columns.rank') }}</th>
                    @endunless
                    <th>{{ __('student_lists.admin.columns.department') }}</th>
                    <th>{{ __('student_lists.admin.columns.gpa') }}</th>
                    @if ($isAlumni)
                        <th>{{ __('student_lists.admin.columns.grade') }}</th>
                    @endif
                    <th>{{ __('student_lists.admin.columns.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($preview->rows as $row)
                    <tr>
                        <td>{{ $row->line }}</td>
                        <td dir="ltr">{{ $row->studentIdentifier ?? '—' }}</td>
                        <td>
                            {{ $row->name !== '' ? $row->name : '—' }}
                            @if ($row->possibleDuplicate)
                                <span class="spu-import-table__note">{{ __('student_lists.admin.possible_duplicate', ['name' => $row->possibleDuplicate]) }}</span>
                            @endif
                        </td>
                        @unless ($isAlumni)
                            <td>{{ $row->studyYear !== null ? __('student_lists.study_years.'.$row->studyYear) : '—' }}</td>
                            <td>{{ $row->rank ?? '—' }}</td>
                        @endunless
                        <td>{{ $row->departmentName ?? ($row->specialization ?? '—') }}</td>
                        <td dir="ltr">
                            @if ($isAlumni)
                                {{ $row->gpaPercent !== null ? rtrim(rtrim(number_format($row->gpaPercent, 2), '0'), '.') : '—' }}
                                @if ($row->gpaPoints !== null)
                                    <span class="spu-import-table__note">({{ number_format($row->gpaPoints, 2) }})</span>
                                @endif
                            @else
                                {{ $row->gpa !== null ? rtrim(rtrim(number_format($row->gpa, 2), '0'), '.') : '—' }}
                            @endif
                        </td>
                        @if ($isAlumni)
                            <td>{{ $row->grade ? __('student_lists.grades.'.$row->grade) : '—' }}</td>
                        @endif
                        <td>
                            <x-filament::badge :color="$actionColor($row->action)">{{ __('student_lists.admin.actions.'.$row->action) }}</x-filament::badge>
                            @foreach ($row->issues as $issue)
                                <span class="spu-import-table__note spu-import-table__note--danger">{{ __('student_lists.issues.'.$issue) }}</span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
