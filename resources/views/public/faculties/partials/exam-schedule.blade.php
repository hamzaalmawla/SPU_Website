{{-- One final exam timetable as an interactive board (a column per exam day, like the study plan), with year filters, search, a table view and an exam dialog. Expects $rows, $scheduleKey, $accent, $isAr, $examColumns. --}}
@php
    $board = \App\Support\ExamScheduleBoard::build($rows, $scheduleKey, $isAr ? 'ar' : 'en');
    $yearsByKey = collect($board['years'])->keyBy('key')->all();
    $et = $isAr
        ? [
            'hint' => 'اختر سنة لعرض امتحاناتها، أو مرّر على امتحان لرؤية مسار سنته عبر الأيام، واضغط عليه لعرض تفاصيله.',
            'board' => 'لوحة',
            'table' => 'جدول',
            'view' => 'طريقة العرض',
            'all' => 'كل السنوات',
            'years' => 'تصفية حسب السنة',
            'search' => 'ابحث عن مقرر',
            'searchPlaceholder' => 'ابحث عن مقرر أو قاعة…',
            'shown' => 'امتحان معروض',
            'empty' => 'لا توجد امتحانات تطابق اختيارك.',
            'other' => 'مواعيد أخرى',
            'exams' => 'امتحان',
            'today' => 'اليوم',
            'next' => 'الامتحان القادم',
            'done' => 'انتهى',
            'close' => 'إغلاق',
            'yearPath' => 'امتحانات هذه السنة',
            'inDays' => 'بعد :n يوم',
            'tomorrow' => 'غداً',
            'reset' => 'إعادة الضبط',
        ]
        : [
            'hint' => 'Pick a year to see its exams, hover an exam to trace its year across the days, and select it for the details.',
            'board' => 'Board',
            'table' => 'Table',
            'view' => 'View',
            'all' => 'All years',
            'years' => 'Filter by year',
            'search' => 'Search a course',
            'searchPlaceholder' => 'Search a course or hall…',
            'shown' => 'exams shown',
            'empty' => 'No exams match your selection.',
            'other' => 'Other dates',
            'exams' => 'exams',
            'today' => 'Today',
            'next' => 'Next exam',
            'done' => 'Done',
            'close' => 'Close',
            'yearPath' => "This year's exams",
            'inDays' => 'In :n days',
            'tomorrow' => 'Tomorrow',
            'reset' => 'Reset',
        ];
    $dayLabel = static fn (array $day): string => trim($day['day'].($day['day'] !== '' && $day['date'] !== '' ? ' · ' : '').$day['date']);
    $columns = array_filter($examColumns, static fn (string $label, string $key): bool => collect($rows)->contains(static fn (mixed $row): bool => is_array($row) && trim((string) ($row[$key] ?? '')) !== ''), ARRAY_FILTER_USE_BOTH);
@endphp

<div
    class="es-schedule mt-6"
    style="--es-accent: {{ $accent }}"
    data-exam-schedule
    data-label-today="{{ $et['today'] }}"
    data-label-next="{{ $et['next'] }}"
    data-label-done="{{ $et['done'] }}"
    data-label-tomorrow="{{ $et['tomorrow'] }}"
    data-label-in-days="{{ $et['inDays'] }}"
>
    <p class="text-sm font-semibold leading-6 text-slate-500">{{ $et['hint'] }}</p>

    <div class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        @if (count($board['years']) > 1)
            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ $et['years'] }}">
                <button type="button" class="es-chip" aria-pressed="true" data-exam-year="">
                    {{ $et['all'] }} <span class="es-chip-count" dir="ltr">{{ $board['total'] }}</span>
                </button>
                @foreach ($board['years'] as $year)
                    <button type="button" class="es-chip" aria-pressed="false" data-exam-year="{{ $year['key'] }}" style="--es-year: {{ $year['color'] }}">
                        <span class="es-dot" aria-hidden="true"></span>{{ $year['label'] }} <span class="es-chip-count" dir="ltr">{{ $year['count'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 lg:ms-auto">
            <label class="relative block">
                <span class="sr-only">{{ $et['search'] }}</span>
                <input type="search" class="es-search" placeholder="{{ $et['searchPlaceholder'] }}" autocomplete="off" data-exam-search>
            </label>
            <div class="es-toggle" role="group" aria-label="{{ $et['view'] }}">
                <button type="button" aria-pressed="true" data-exam-view="board">{{ $et['board'] }}</button>
                <button type="button" aria-pressed="false" data-exam-view="table">{{ $et['table'] }}</button>
            </div>
        </div>
    </div>

    <p class="mt-4 text-xs font-bold text-spu-blue/60" role="status" aria-live="polite">
        <span dir="ltr" data-exam-count>{{ $board['total'] }}</span> {{ $et['shown'] }}
    </p>

    {{-- Days read left to right, as on the study plan, so a year's path runs forward. --}}
    <div class="es-scroller mt-4" dir="ltr" data-exam-panel="board">
        <div class="es-board" data-exam-board>
            <svg class="es-edges" aria-hidden="true" focusable="false" data-exam-edges></svg>
            @foreach ($board['days'] as $day)
                <section class="es-day" dir="{{ $isAr ? 'rtl' : 'ltr' }}" data-exam-day data-iso="{{ $day['iso'] }}" aria-labelledby="{{ $day['key'] }}-label">
                    <header class="es-day-head">
                        <h3 id="{{ $day['key'] }}-label" class="text-sm font-bold">
                            @if ($day['day'] !== '')
                                <span class="block">{{ $day['day'] }}</span>
                            @endif
                            <span class="{{ $day['day'] !== '' ? 'mt-0.5 block text-xs font-semibold text-white/80' : 'block' }}" dir="auto">{{ $day['date'] !== '' ? $day['date'] : ($day['day'] === '' ? $et['other'] : '') }}</span>
                        </h3>
                        <span class="es-day-badge" data-exam-day-badge hidden></span>
                    </header>
                    <div class="flex flex-col gap-3">
                        @foreach ($day['exams'] as $exam)
                            @php $year = $yearsByKey[$exam['yearKey']] ?? null; @endphp
                            <button
                                type="button"
                                id="{{ $exam['id'] }}"
                                class="es-exam"
                                style="--es-year: {{ $year['color'] ?? $accent }}"
                                aria-haspopup="dialog"
                                data-exam="{{ $exam['id'] }}"
                                data-year="{{ $exam['yearKey'] }}"
                                data-previous="{{ $exam['previous'] }}"
                                data-search="{{ \App\Support\SearchTextNormalizer::normalize($exam['course'].' '.$exam['room'].' '.$exam['year']) }}"
                                data-course="{{ $exam['course'] }}"
                                data-time="{{ $exam['time'] }}"
                                data-year-label="{{ $exam['year'] }}"
                                data-room="{{ $exam['room'] }}"
                                data-day="{{ $exam['day'] }}"
                                data-date="{{ $exam['date'] }}"
                                data-iso="{{ $exam['iso'] }}"
                            >
                                <span class="flex items-center justify-between gap-3 text-[11px] font-bold">
                                    @if ($exam['time'] !== '')
                                        <span class="es-time" dir="ltr">{{ $exam['time'] }}</span>
                                    @else
                                        <span></span>
                                    @endif
                                    @if ($exam['year'] !== '')
                                        <span class="es-year-badge">{{ $exam['year'] }}</span>
                                    @endif
                                </span>
                                <span class="mt-2 block text-sm font-bold leading-6 text-spu-blue" dir="auto">{{ $exam['course'] }}</span>
                                @if ($exam['room'] !== '')
                                    <span class="mt-1 block text-xs font-semibold text-slate-500" dir="auto">{{ $examColumns['room'] }}: {{ $exam['room'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-[6px] border border-slate-100" data-exam-panel="table" hidden>
        <table class="w-full min-w-[560px] text-start text-sm">
            <thead class="bg-section text-xs font-bold uppercase tracking-[0.08em] text-spu-blue/70">
                <tr>
                    @foreach ($columns as $label)
                        <th scope="col" class="px-4 py-3 text-start">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @foreach ($board['days'] as $day)
                    @foreach ($day['exams'] as $exam)
                        <tr data-exam-row="{{ $exam['id'] }}">
                            @foreach (array_keys($columns) as $key)
                                <td class="px-4 py-3 {{ $key === 'course' ? 'font-bold text-spu-blue' : 'font-semibold' }}" dir="auto">{{ $exam[$key] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 rounded-[8px] border border-dashed border-slate-200 px-6 py-10 text-center" data-exam-empty hidden>
        <p class="font-bold text-slate-600">{{ $et['empty'] }}</p>
        <button type="button" class="es-chip mt-4" data-exam-reset>{{ $et['reset'] }}</button>
    </div>

    <dialog class="es-dialog font-hacen" dir="{{ $isAr ? 'rtl' : 'ltr' }}" aria-labelledby="{{ $scheduleKey }}-dialog-title" data-exam-dialog>
        <div class="max-h-[calc(100vh-48px)] overflow-y-auto p-6 md:p-8">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-xs font-bold">
                        <span class="es-year-badge" data-dialog-year></span>
                        <span class="es-status" data-dialog-status hidden></span>
                    </p>
                    <h3 id="{{ $scheduleKey }}-dialog-title" class="mt-2 text-xl font-bold leading-8 text-spu-blue" dir="auto" data-dialog-course></h3>
                </div>
                <button type="button" class="es-close" data-exam-close aria-label="{{ $et['close'] }}">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['day', 'date', 'time', 'room'] as $field)
                    <div class="es-fact" data-dialog-fact="{{ $field }}">
                        <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $examColumns[$field] }}</dt>
                        <dd class="mt-1 text-sm font-bold text-spu-blue" dir="auto" data-dialog-value></dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-6" data-dialog-path-wrap>
                <h4 class="text-sm font-bold text-spu-blue">{{ $et['yearPath'] }}</h4>
                <ol class="es-path mt-3" data-dialog-path></ol>
            </div>
        </div>
    </dialog>
</div>
