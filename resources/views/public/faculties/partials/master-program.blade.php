{{-- A faculty's master's program: facts, the semester board with course dialogs, outcomes, coverage, assessment, project rules and admission. --}}
@php
    $program = is_array($page->detail['program'] ?? null) ? $page->detail['program'] : [];
    $terms = $program['terms'] ?? [];
    $totals = $program['totals'] ?? [];
    $coverage = $program['coverage'] ?? ['plos' => [], 'rows' => [], 'legend' => ''];
    $admission = $program['admission'] ?? [];
    $t = $isAr
        ? [
            'jump' => 'محتويات الصفحة',
            'plan' => 'الخطة الدراسية',
            'planHint' => 'اختر مقرراً لعرض وصفه ومخرجات تعلمه ومتطلباته السابقة.',
            'credits' => 'س.م',
            'creditsLong' => 'ساعة معتمدة',
            'theory' => 'نظري',
            'practical' => 'عملي',
            'courses' => 'مقرر',
            'total' => 'المجموع',
            'prerequisites' => 'المتطلبات السابقة',
            'none' => 'لا يوجد',
            'unlocks' => 'يفتح المقررات',
            'outcomes' => 'مخرجات تعلم المقرر',
            'grading' => 'توزيع العلامات',
            'close' => 'إغلاق',
            'plos' => 'مخرجات تعلم البرنامج',
            'coverage' => 'مصفوفة تغطية المنهج',
            'course' => 'المقرر',
            'assessment' => 'استراتيجيات التقييم',
            'type' => 'نوع التقييم',
            'weight' => 'النسبة',
            'description' => 'الوصف',
            'project' => 'أحكام مشروع تخرج الماجستير',
            'admission' => 'شروط القبول',
            'majors' => 'الاختصاصات المقبولة',
            'criteria' => 'معايير المفاضلة',
            'requirements' => 'متطلبات الحصول على الدرجة العلمية',
            'teaching' => 'طرائق التدريس واستراتيجيات التقويم',
            'hours' => 'الساعات',
            'elective' => 'اختياري',
            'conditions' => 'شروط القبول',
            'theoryShort' => 'ن',
            'practicalShort' => 'ع',
        ]
        : [
            'jump' => 'On this page',
            'plan' => 'Study plan',
            'planHint' => 'Select a course to see its description, learning outcomes and prerequisites.',
            'credits' => 'CR',
            'creditsLong' => 'credit hours',
            'theory' => 'Theory',
            'practical' => 'Practical',
            'courses' => 'courses',
            'total' => 'Total',
            'prerequisites' => 'Prerequisites',
            'none' => 'None',
            'unlocks' => 'Opens',
            'outcomes' => 'Course learning outcomes',
            'grading' => 'Grading',
            'close' => 'Close',
            'plos' => 'Program learning outcomes',
            'coverage' => 'Curriculum coverage matrix',
            'course' => 'Course',
            'assessment' => 'Assessment strategies',
            'type' => 'Assessment type',
            'weight' => 'Weight',
            'description' => 'Description',
            'project' => "Master's graduation project rules",
            'admission' => 'Admission requirements',
            'majors' => 'Eligible majors',
            'criteria' => 'Ranking criteria',
            'requirements' => 'Degree requirements',
            'teaching' => 'Teaching and assessment',
            'hours' => 'Hours',
            'elective' => 'Elective',
            'conditions' => 'Admission conditions',
            'theoryShort' => 'T',
            'practicalShort' => 'P',
        ];
    $sections = array_filter([
        'plan' => $terms !== [],
        'outcomes' => ($program['outcomes'] ?? []) !== [],
        'coverage' => ($coverage['rows'] ?? []) !== [],
        'assessment' => ($program['assessment'] ?? []) !== [],
        'project' => ($program['projectRules'] ?? []) !== [] || ($program['projectIntro'] ?? '') !== '',
        'admission' => ($admission['majors'] ?? []) !== [] || ($admission['conditions'] ?? []) !== [] || ($admission['criteria'] ?? []) !== [] || trim((string) ($admission['intro'] ?? '')) !== '',
    ]);
    $projectTitle = ($program['projectTitle'] ?? '') !== '' ? $program['projectTitle'] : $t['project'];
    $sectionLabels = ['plan' => $t['plan'], 'outcomes' => $t['plos'], 'coverage' => $t['coverage'], 'assessment' => $t['assessment'], 'project' => $projectTitle, 'admission' => $t['admission']];
    $courseId = static fn (string $id): string => 'mp-course-'.Str::slug($id);
    // A course with no stated credits (some curricula give only weekly hours) shows its hours.
    $creditsBadge = static fn (array $course): string => $course['credits'] > 0
        ? $course['credits'].' '.$t['credits']
        : $course['theory'].' '.$t['theoryShort'].' + '.$course['practical'].' '.$t['practicalShort'];
    // Literal classes, so Tailwind keeps them: the board has as many columns as terms, up to four.
    $boardColumns = match (min(count($terms), 4)) {
        1 => '',
        2 => 'md:grid-cols-2',
        3 => 'md:grid-cols-2 xl:grid-cols-3',
        default => 'md:grid-cols-2 xl:grid-cols-4',
    };
@endphp

<div class="bg-white font-hacen" data-master-program style="--mp-accent: {{ $accent }}">
    <section class="py-14 md:py-16">
        <div class="container">
            @if (($program['facts'] ?? []) !== [])
                <dl class="mx-auto grid max-w-[1080px] grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach ($program['facts'] as $fact)
                        <div class="rounded-[8px] border border-slate-100 bg-white p-5 text-center shadow-[0_12px_40px_rgba(9,17,68,0.06)]">
                            <dt class="text-xs font-bold text-spu-blue/60">{{ $fact['label'] }}</dt>
                            <dd class="mt-2 text-lg font-bold leading-7" style="color: {{ $accent }}">{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if (($program['overview'] ?? []) !== [] || trim((string) ($subpage['body'] ?? '')) !== '')
                <div class="mx-auto mt-10 max-w-[860px] space-y-4 text-center text-base font-semibold leading-8 text-slate-600">
                    @if (trim((string) ($subpage['body'] ?? '')) !== '')
                        <x-public.rich-text :text="$subpage['body']" />
                    @endif
                    @foreach ($program['overview'] ?? [] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            @endif

            @if (count($sections) > 1)
                <nav class="mx-auto mt-10 flex max-w-[1080px] flex-wrap justify-center gap-2" aria-label="{{ $t['jump'] }}">
                    @foreach (array_keys($sections) as $key)
                        <a href="#mp-{{ $key }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-bold text-spu-blue transition-colors hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">{{ $sectionLabels[$key] }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>

    @if ($terms !== [])
        <section id="mp-plan" class="scroll-mt-28 bg-slate-50 py-14 md:py-16">
            <div class="container">
                <div class="mx-auto max-w-[1180px]">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-spu-blue md:text-[28px]">{{ $t['plan'] }}</h2>
                            <p class="mt-2 text-sm font-semibold text-slate-500">{{ $t['planHint'] }}</p>
                        </div>
                        @if (($totals['credits'] ?? 0) > 0)
                            <p class="text-sm font-bold text-spu-blue">
                                {{ $t['total'] }}: <span dir="ltr">{{ $totals['credits'] }}</span> {{ $t['creditsLong'] }} · <span dir="ltr">{{ $totals['courses'] }}</span> {{ $t['courses'] }}
                            </p>
                        @endif
                    </div>

                    {{-- Semesters read left to right, as on the study plan, so prerequisite lines run forward. --}}
                    <div class="mp-board mt-8 grid gap-5 {{ $boardColumns }}" dir="ltr" data-master-board>
                        <svg class="mp-edges hidden xl:block" aria-hidden="true" focusable="false" data-master-edges></svg>
                        @foreach ($terms as $term)
                            <div class="flex flex-col gap-3" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
                                <header class="rounded-[8px] px-4 py-3 text-white" style="background-color: {{ $accent }}">
                                    <h3 class="text-sm font-bold">{{ $term['label'] }}</h3>
                                    @if (($term['theme'] ?? '') !== '')
                                        <p class="mt-0.5 text-xs font-semibold text-white/80">{{ $term['theme'] }}</p>
                                    @endif
                                </header>
                                @if (($term['note'] ?? '') !== '')
                                    <p class="px-1 text-xs font-semibold leading-5 text-slate-600">{{ $term['note'] }}</p>
                                @endif
                                <ul class="flex flex-col gap-3">
                                    @foreach ($term['courses'] as $course)
                                        <li>
                                            <button
                                                type="button"
                                                id="{{ $courseId($course['id']) }}"
                                                class="mp-course flex w-full flex-col gap-1 rounded-[8px] border {{ $course['elective'] ? 'border-dashed border-slate-300' : 'border-slate-200' }} bg-white p-4 text-start focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue"
                                                aria-haspopup="dialog"
                                                data-master-course="{{ $course['id'] }}"
                                                data-prerequisites="{{ implode(' ', array_column($course['prerequisites'], 'id')) }}"
                                            >
                                                <span class="flex items-center justify-between gap-3 text-[11px] font-bold">
                                                    <span class="flex items-center gap-2">
                                                        @if ($course['code'] !== '')
                                                            <span dir="ltr" style="color: {{ $accent }}">{{ $course['code'] }}</span>
                                                        @endif
                                                        @if ($course['elective'])
                                                            <span class="rounded-full border border-slate-300 px-2 py-0.5 text-slate-600">{{ $t['elective'] }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-spu-blue" dir="ltr">{{ $creditsBadge($course) }}</span>
                                                </span>
                                                <span class="text-sm font-bold leading-6 text-spu-blue">{{ $course['title'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="mt-auto px-1 text-xs font-bold text-slate-500">
                                    {{ $t['total'] }}: <span dir="ltr">{{ $term['credits'] }}</span> {{ $t['creditsLong'] }}
                                    @unless ($term['hasElectives'] || $term['theory'] + $term['practical'] === 0)
                                        · {{ $t['theory'] }} <span dir="ltr">{{ $term['theory'] }}</span>
                                        · {{ $t['practical'] }} <span dir="ltr">{{ $term['practical'] }}</span>
                                    @endunless
                                </p>
                            </div>
                        @endforeach
                    </div>

                    @if (($program['coding'] ?? '') !== '')
                        <p class="mt-6 text-xs font-semibold leading-6 text-slate-500">{{ $program['coding'] }}</p>
                    @endif
                </div>
            </div>

            @foreach ($terms as $term)
                @foreach ($term['courses'] as $course)
                    <dialog class="mp-dialog font-hacen" dir="{{ $isAr ? 'rtl' : 'ltr' }}" aria-labelledby="{{ $courseId($course['id']) }}-title" data-master-dialog="{{ $course['id'] }}">
                        <div class="max-h-[calc(100vh-48px)] overflow-y-auto p-6 md:p-8">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    @if ($course['code'] !== '' || $course['elective'])
                                        <p class="flex items-center gap-2 text-xs font-bold">
                                            @if ($course['code'] !== '')
                                                <span style="color: {{ $accent }}" dir="ltr">{{ $course['code'] }}</span>
                                            @endif
                                            @if ($course['elective'])
                                                <span class="rounded-full border border-slate-300 px-2 py-0.5 text-slate-600">{{ $t['elective'] }}</span>
                                            @endif
                                        </p>
                                    @endif
                                    <h3 id="{{ $courseId($course['id']) }}-title" class="mt-1 text-xl font-bold leading-8 text-spu-blue">{{ $course['title'] }}</h3>
                                    @if (($course['titleAlt'] ?? '') !== '')
                                        <p class="mt-1 text-sm font-semibold text-slate-500" dir="{{ $isAr ? 'ltr' : 'rtl' }}" lang="{{ $isAr ? 'en' : 'ar' }}">{{ $course['titleAlt'] }}</p>
                                    @endif
                                </div>
                                <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-lg font-bold text-spu-blue transition-colors hover:bg-slate-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue" aria-label="{{ $t['close'] }}" data-master-close>
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>

                            <dl class="mt-5 grid {{ $course['credits'] > 0 ? 'grid-cols-3' : 'grid-cols-2' }} gap-3 text-center">
                                @if ($course['credits'] > 0)
                                    <div class="rounded-[8px] bg-slate-50 p-3"><dt class="text-[11px] font-bold text-slate-500">{{ $t['creditsLong'] }}</dt><dd class="mt-1 text-lg font-bold text-spu-blue" dir="ltr">{{ $course['credits'] }}</dd></div>
                                @endif
                                <div class="rounded-[8px] bg-slate-50 p-3"><dt class="text-[11px] font-bold text-slate-500">{{ $t['theory'] }}</dt><dd class="mt-1 text-lg font-bold text-spu-blue" dir="ltr">{{ $course['theory'] }}</dd></div>
                                <div class="rounded-[8px] bg-slate-50 p-3"><dt class="text-[11px] font-bold text-slate-500">{{ $t['practical'] }}</dt><dd class="mt-1 text-lg font-bold text-spu-blue" dir="ltr">{{ $course['practical'] }}</dd></div>
                            </dl>

                            @if (($course['description'] ?? '') !== '')
                                <p class="mt-5 text-sm leading-7 text-slate-600">{{ $course['description'] }}</p>
                            @endif

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <h4 class="text-sm font-bold text-spu-blue">{{ $t['prerequisites'] }}</h4>
                                    @if ($course['prerequisites'] !== [])
                                        <ul class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($course['prerequisites'] as $prerequisite)
                                                <li><button type="button" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-bold text-spu-blue hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-spu-blue" data-master-open="{{ $prerequisite['id'] }}" title="{{ $prerequisite['title'] }}">@if ($prerequisite['code'] !== '')<span dir="ltr">{{ $prerequisite['code'] }}</span>@else{{ $prerequisite['title'] }}@endif</button></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if (($course['prerequisiteNote'] ?? '') !== '')
                                        <p class="mt-2 text-xs font-semibold text-slate-600">{{ $course['prerequisiteNote'] }}</p>
                                    @elseif ($course['prerequisites'] === [])
                                        <p class="mt-2 text-xs font-semibold text-slate-500">{{ $t['none'] }}</p>
                                    @endif
                                </div>
                                @if ($course['unlocks'] !== [])
                                    <div>
                                        <h4 class="text-sm font-bold text-spu-blue">{{ $t['unlocks'] }}</h4>
                                        <ul class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($course['unlocks'] as $next)
                                                <li><button type="button" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-bold text-spu-blue hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-spu-blue" data-master-open="{{ $next['id'] }}" title="{{ $next['title'] }}">@if ($next['code'] !== '')<span dir="ltr">{{ $next['code'] }}</span>@else{{ $next['title'] }}@endif</button></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            @if ($course['outcomes'] !== [])
                                <h4 class="mt-6 text-sm font-bold text-spu-blue">{{ $t['outcomes'] }}</h4>
                                <ol class="mt-2 list-decimal space-y-1.5 ps-5 text-sm leading-7 text-slate-600">
                                    @foreach ($course['outcomes'] as $outcome)
                                        <li>{{ $outcome }}</li>
                                    @endforeach
                                </ol>
                            @endif

                            @if (($course['grading'] ?? '') !== '')
                                <h4 class="mt-6 text-sm font-bold text-spu-blue">{{ $t['grading'] }}</h4>
                                <p class="mt-2 text-sm leading-7 text-slate-600">{{ $course['grading'] }}</p>
                            @endif
                        </div>
                    </dialog>
                @endforeach
            @endforeach
        </section>
    @endif

    @if (($program['outcomes'] ?? []) !== [])
        <section id="mp-outcomes" class="scroll-mt-28 py-14 md:py-16">
            <div class="container">
                <div class="mx-auto max-w-[1080px]">
                    <h2 class="text-2xl font-bold text-spu-blue md:text-[28px]">{{ $t['plos'] }}</h2>
                    @foreach ($program['outcomeGroups'] ?? [] as $group)
                        @if ($group['group'] !== '')
                            <h3 class="mt-8 text-lg font-bold text-spu-blue">{{ $group['group'] }}</h3>
                        @endif
                        <ol class="{{ $group['group'] !== '' ? 'mt-4' : 'mt-8' }} grid gap-4 md:grid-cols-2">
                            @foreach ($group['outcomes'] as $outcome)
                                <li class="flex gap-4 rounded-[8px] border border-slate-100 bg-white p-5 shadow-[0_12px_40px_rgba(9,17,68,0.06)]">
                                    @if ($outcome['code'] !== '')
                                        <span class="shrink-0 text-sm font-bold" style="color: {{ $accent }}" dir="ltr">{{ $outcome['code'] }}</span>
                                    @else
                                        <span class="mt-2.5 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $accent }}" aria-hidden="true"></span>
                                    @endif
                                    <span class="text-sm leading-7 text-slate-600">{{ $outcome['text'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (($coverage['rows'] ?? []) !== [])
        <section id="mp-coverage" class="scroll-mt-28 bg-slate-50 py-14 md:py-16">
            <div class="container">
                <div class="mx-auto max-w-[1180px]">
                    <h2 class="text-2xl font-bold text-spu-blue md:text-[28px]">{{ $t['coverage'] }}</h2>
                    @if (($coverage['legend'] ?? '') !== '')
                        <p class="mt-2 text-sm font-semibold text-slate-500">{{ $coverage['legend'] }}</p>
                    @endif
                    <div class="mt-8 overflow-x-auto rounded-[8px] border border-slate-200 bg-white" tabindex="0" role="region" aria-label="{{ $t['coverage'] }}">
                        <table class="w-full min-w-[860px] border-collapse text-sm">
                            <thead class="bg-slate-100 text-xs font-bold text-spu-blue">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start">{{ $t['course'] }}</th>
                                    @foreach ($coverage['plos'] as $plo)
                                        <th scope="col" class="px-2 py-3 text-center" dir="ltr">{{ $plo }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($coverage['rows'] as $row)
                                    <tr class="border-t border-slate-100">
                                        <th scope="row" class="px-4 py-2.5 text-start font-semibold text-slate-600">@if ($row['code'] !== '')<span class="font-bold text-spu-blue" dir="ltr">{{ $row['code'] }}</span> @endif{{ $row['title'] }}@if ($row['elective'] ?? false) <span class="text-xs text-slate-500">({{ $t['elective'] }})</span>@endif</th>
                                        @foreach ($coverage['plos'] as $plo)
                                            <td class="px-2 py-2.5 text-center font-bold" style="{{ isset($row['levels'][$plo]) ? 'color: '.$accent : '' }}">{{ $row['levels'][$plo] ?? '' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (($program['assessment'] ?? []) !== [])
        <section id="mp-assessment" class="scroll-mt-28 py-14 md:py-16">
            <div class="container">
                <div class="mx-auto max-w-[1080px]">
                    <h2 class="text-2xl font-bold text-spu-blue md:text-[28px]">{{ $t['assessment'] }}</h2>
                    <div class="mt-8 grid gap-4 md:grid-cols-2">
                        @foreach ($program['assessment'] as $assessment)
                            <article class="rounded-[8px] border border-slate-100 bg-white p-6 shadow-[0_12px_40px_rgba(9,17,68,0.06)]">
                                <div class="flex items-start justify-between gap-4">
                                    <h3 class="text-base font-bold text-spu-blue">{{ $assessment['type'] }}</h3>
                                    @if ($assessment['weight'] !== '')
                                        <span class="shrink-0 text-lg font-bold" style="color: {{ $accent }}">{{ $assessment['weight'] }}</span>
                                    @endif
                                </div>
                                @if ($assessment['description'] !== '')
                                    <p class="mt-3 text-sm leading-7 text-slate-600">{{ $assessment['description'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if (($program['projectRules'] ?? []) !== [] || ($program['projectIntro'] ?? '') !== '')
        <section id="mp-project" class="scroll-mt-28 bg-slate-50 py-14 md:py-16">
            <div class="container">
                <div class="mx-auto max-w-[1080px]">
                    <h2 class="text-2xl font-bold text-spu-blue md:text-[28px]">{{ $projectTitle }}</h2>
                    @if (($program['projectIntro'] ?? '') !== '')
                        <p class="mt-4 text-sm leading-7 text-slate-600">{{ $program['projectIntro'] }}</p>
                    @endif
                    @if (($program['projectRules'] ?? []) !== [])
                    <ol class="mt-6 space-y-3">
                        @foreach ($program['projectRules'] as $rule)
                            <li class="flex gap-4 rounded-[8px] bg-white p-5 shadow-[0_12px_40px_rgba(9,17,68,0.05)]">
                                <span class="shrink-0 text-lg font-bold leading-7" style="color: {{ $accent }}" dir="ltr">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-sm leading-7 text-slate-600">{{ $rule }}</span>
                            </li>
                        @endforeach
                    </ol>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if (isset($sections['admission']) || ($program['degreeRequirements'] ?? []) !== [] || ($program['teachingNote'] ?? '') !== '')
        <section id="mp-admission" class="scroll-mt-28 py-14 md:py-16">
            <div class="container">
                <div class="mx-auto max-w-[1080px]">
                    @if (isset($sections['admission']))
                        <h2 class="text-2xl font-bold text-spu-blue md:text-[28px]">{{ $t['admission'] }}</h2>
                        @if (($admission['intro'] ?? '') !== '')
                            <p class="mt-4 text-sm leading-7 text-slate-600">{{ $admission['intro'] }}</p>
                        @endif
                        @if (($admission['conditions'] ?? []) !== [])
                            <h3 class="mt-8 text-lg font-bold text-spu-blue">{{ $t['conditions'] }}</h3>
                            <ol class="mt-4 list-decimal space-y-2 ps-5 text-sm leading-7 text-slate-600">
                                @foreach ($admission['conditions'] as $condition)
                                    <li>{{ $condition }}</li>
                                @endforeach
                            </ol>
                        @endif
                        @if (($admission['majors'] ?? []) !== [])
                            <h3 class="mt-8 text-lg font-bold text-spu-blue">{{ $t['majors'] }}</h3>
                            <ul class="mt-4 grid gap-3 md:grid-cols-2">
                                @foreach ($admission['majors'] as $major)
                                    <li class="flex gap-3 rounded-[8px] border border-slate-100 p-4 text-sm leading-6 text-slate-600">
                                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $accent }}" aria-hidden="true"></span>
                                        <span>{{ $major }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if (($admission['criteria'] ?? []) !== [])
                            <h3 class="mt-8 text-lg font-bold text-spu-blue">{{ $t['criteria'] }}</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                @foreach ($admission['criteria'] as $criterion)
                                    <div class="rounded-[8px] border border-slate-100 bg-white p-5 shadow-[0_12px_40px_rgba(9,17,68,0.06)]">
                                        <div class="flex items-baseline justify-between gap-4">
                                            <span class="text-base font-bold text-spu-blue">{{ $criterion['label'] }}</span>
                                            <span class="text-2xl font-bold" style="color: {{ $accent }}" dir="ltr">{{ $criterion['weight'] }}</span>
                                        </div>
                                        @if ($criterion['description'] !== '')
                                            <p class="mt-2 text-sm leading-7 text-slate-600">{{ $criterion['description'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if (($admission['note'] ?? '') !== '')
                            <p class="mt-4 text-xs font-semibold leading-6 text-slate-500">{{ $admission['note'] }}</p>
                        @endif
                    @endif

                    @if (($program['degreeRequirements'] ?? []) !== [])
                        <h3 class="mt-10 text-lg font-bold text-spu-blue">{{ $t['requirements'] }}</h3>
                        <ol class="mt-4 list-decimal space-y-2 ps-5 text-sm leading-7 text-slate-600">
                            @foreach ($program['degreeRequirements'] as $requirement)
                                <li>{{ $requirement }}</li>
                            @endforeach
                        </ol>
                    @endif

                    @if (($program['teachingNote'] ?? '') !== '')
                        <h3 class="mt-10 text-lg font-bold text-spu-blue">{{ $t['teaching'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $program['teachingNote'] }}</p>
                    @endif
                </div>
            </div>
        </section>
    @endif
</div>
