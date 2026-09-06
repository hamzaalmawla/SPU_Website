@php
    $studyPayload = $subpage['payload'] ?? [];
    $plan = $studyPayload['plan'] ?? [];
    $labels = $studyPayload['labels'] ?? [];
    $legend = $studyPayload['legend'] ?? [];
    $departments = collect($plan['departments'] ?? [])->filter(fn ($item) => is_array($item))->values();
    $activeDepartment = is_array($page->detail['activeDepartment'] ?? null) ? $page->detail['activeDepartment'] : null;
    $terms = collect($activeDepartment['terms'] ?? [])->filter(fn ($item) => is_array($item))->values();
    $clientPayload = $studyPayload;
    $clientPayload['plan'] = [
        ...$plan,
        'departments' => $departments->map(function (array $department) use ($activeDepartment): array {
            if (($department['id'] ?? null) === ($activeDepartment['id'] ?? null)) {
                return $department;
            }

            return [
                'id' => $department['id'] ?? '',
                'name' => $department['name'] ?? '',
                'nameAr' => $department['nameAr'] ?? '',
                'nameEn' => $department['nameEn'] ?? '',
                'totalCredits' => $department['totalCredits'] ?? null,
            ];
        })->values()->all(),
    ];
    $facultyPlanName = (string) ($plan['faculty'] ?? $faculty['title']);
    $accent = (string) ($plan['accent'] ?? ($faculty['accentColor'] ?? '#202759'));
    $label = fn (string $key): string => (string) ($labels[$key] ?? $labels[$key.'En'] ?? '');
    $text = fn (?array $item, string $key): string => is_array($item) ? (string) ($item[$key] ?? $item[$key.'En'] ?? $item[$key.'Ar'] ?? '') : '';
    $typeKey = static function (array $course): string {
        if (! ($course['required'] ?? true)) {
            return 'elective';
        }

        return in_array($course['type'] ?? 'faculty', ['university', 'faculty', 'specialization'], true) ? (string) $course['type'] : 'faculty';
    };
    $typeClasses = [
        'university' => 'sp-card-accent-uni border-spu-blue/20 focus:ring-spu-blue',
        'faculty' => 'sp-card-accent-fac border-slate-200 focus:ring-slate-400',
        'specialization' => 'sp-card-accent-spec border-indigo-200 focus:ring-indigo-400',
        'elective' => 'sp-card-accent-elec border-slate-200 focus:ring-amber-400',
    ];
    $dotClasses = [
        'university' => 'bg-spu-blue',
        'faculty' => 'bg-slate-400',
        'specialization' => 'bg-spu-blue/70',
        'elective' => 'bg-slate-300',
    ];
    $poolLabel = fn (string $poolId): string => match ($poolId) {
        'university' => $label('universityElective'),
        'faculty' => $label('facultyElective'),
        default => $label('departmentElective'),
    };

    $layout = [
        'totalTerms' => 10,
        'columnWidth' => 150,
        'columnGap' => 64,
        'cardHeight' => 100,
        'cardGap' => 18,
        'headerHeight' => 36,
        'headerGap' => 24,
        'bottomPadding' => 28,
        'sidePadding' => 28,
    ];
    $boardWidth = ($layout['totalTerms'] * $layout['columnWidth']) + (($layout['totalTerms'] - 1) * $layout['columnGap']) + (2 * $layout['sidePadding']);
    $layoutCoursesByTerm = [];
    $boardHeight = $layout['headerHeight'] + $layout['bottomPadding'];

    foreach ($terms as $termIndex => $term) {
        $top = $layout['headerHeight'] + $layout['headerGap'];
        $termLayouts = [];
        foreach (($term['courses'] ?? []) as $course) {
            if (! is_array($course)) {
                continue;
            }

            $termLayouts[] = ['course' => $course, 'top' => $top];
            $top += $layout['cardHeight'] + $layout['cardGap'];
        }

        $termHeight = max($layout['headerHeight'] + $layout['bottomPadding'], $top - $layout['cardGap'] + $layout['bottomPadding']);
        $boardHeight = max($boardHeight, $termHeight);
        $layoutCoursesByTerm[$termIndex] = ['term' => $term, 'courses' => $termLayouts, 'height' => $termHeight];
    }
@endphp

<div class="font-hacen {{ $isAr ? 'rtl' : '' }}" dir="{{ $direction }}" data-study-plan data-locale="{{ $locale }}" data-faculty-slug="{{ $page->facultySlug }}" style="--accent-color: {{ $accent }}; --accent-color-15: {{ $accent }}26;">
    <script type="application/json" data-study-plan-payload>{!! json_encode($clientPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

    <section class="relative flex min-h-[330px] items-center justify-center overflow-hidden pt-28">
        <div class="absolute inset-0">
            <img src="{{ $plan['heroImage'] ?? ($faculty['heroImage'] ?? '/images/uni-main-place.JPG') }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/72 to-spu-blue/18"></div>
        </div>

        <div class="container relative z-10 pb-14 text-center text-white">
            <nav class="mb-4 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-white/75" aria-label="Breadcrumb">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $label('home') ?: $homeLabel }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/faculties" class="transition-colors hover:text-white">{{ $label('faculties') ?: ($isAr ? 'الكليات' : 'Facilities') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $facultyPlanName }}</span>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $label('title') ?: ($isAr ? 'الخطة الدراسية' : 'Study Plan') }}</span>
            </nav>
            <h1 class="mt-3 text-[34px] font-bold leading-tight md:text-[52px]">{{ $label('title') ?: ($isAr ? 'الخطة الدراسية' : 'Study Plan') }}</h1>
        </div>
    </section>

    <section class="bg-section py-16 lg:py-24">
        <div class="container">
            @if ($activeDepartment === null)
                <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                    <p class="text-[15px] font-bold text-spu-blue/40">{{ $label('empty') }}</p>
                </div>
            @else
                <div class="space-y-10">
                    @if ($departments->count() > 1)
                        <div class="flex flex-wrap gap-2.5 rounded-3xl border border-slate-100 bg-white p-1.5 shadow-sm">
                            @foreach ($departments as $department)
                                @php($isActiveDepartment = ($department['id'] ?? '') === ($activeDepartment['id'] ?? ''))
                                <button type="button" data-department-tab="{{ $department['id'] ?? '' }}" aria-pressed="{{ $isActiveDepartment ? 'true' : 'false' }}" class="rounded-full px-6 py-3 text-[13px] font-bold transition-all duration-300 {{ $isActiveDepartment ? 'bg-spu-blue text-white shadow-lg shadow-spu-blue/25' : 'bg-white text-spu-blue/80 hover:bg-slate-50' }}">{{ $text($department, 'name') }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <p class="text-[14px] font-semibold text-slate-500">{{ $label('courseTypes') ?: ($isAr ? 'أنواع المقررات' : 'Course Types') }}</p>
                            @foreach ($legend as $item)
                                @php($legendType = $item['id'] ?? 'faculty')
                                <div class="flex cursor-pointer items-center gap-2.5 rounded-full border border-slate-100 bg-white px-5 py-2.5 shadow-sm transition-all hover:border-slate-200 hover:shadow-md">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $dotClasses[$legendType] ?? 'bg-slate-400' }}"></span>
                                    <span class="text-[12px] font-extrabold uppercase tracking-widest text-slate-700">{{ $text($item, 'label') }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" data-study-plan-download class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-[12px] font-bold text-spu-blue shadow-sm transition-all hover:border-spu-blue/30 hover:shadow-md">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span>{{ $isAr ? 'تحميل الساعات' : 'Download Hours' }}</span>
                            </button>
                            @if (is_string($plan['pdfUrl'] ?? null) && $plan['pdfUrl'] !== '')
                                <a href="{{ $plan['pdfUrl'] }}" download class="inline-flex items-center gap-2 rounded-lg bg-spu-blue px-4 py-2.5 text-[12px] font-bold text-white shadow-md shadow-spu-blue/20 transition-all hover:bg-spu-blue/90 hover:shadow-lg">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14" /></svg>
                                    <span>{{ $label('downloadPlan') ?: ($isAr ? 'تحميل الخطة' : 'Download Plan') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div data-study-plan-viewport dir="ltr" tabindex="0" role="region" aria-label="{{ $isAr ? 'مخطط المتطلبات السابقة للمقررات. استخدم مفاتيح الأسهم للتحريك ومفتاحي الجمع والطرح للتكبير.' : 'Course prerequisite graph. Use the arrow keys to pan and plus or minus to zoom.' }}" class="relative w-full overflow-hidden border border-slate-200/50 bg-white shadow-[0_8px_40px_rgba(32,39,89,0.04)] sp-custom-scrollbar" style="height: 80vh;">
                        <div data-study-plan-controls class="absolute right-4 top-4 z-50 flex flex-col gap-2">
                            <button type="button" data-study-plan-zoom-in class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-black text-spu-blue shadow-md transition-colors hover:bg-slate-50" aria-label="{{ $isAr ? 'تكبير المخطط' : 'Zoom in' }}">+</button>
                            <button type="button" data-study-plan-zoom-out class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-black text-spu-blue shadow-md transition-colors hover:bg-slate-50" aria-label="{{ $isAr ? 'تصغير المخطط' : 'Zoom out' }}">−</button>
                            <button type="button" data-study-plan-fit class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-black text-spu-blue shadow-md transition-colors hover:bg-slate-50" aria-label="{{ $isAr ? 'ملاءمة المخطط للشاشة' : 'Fit graph to screen' }}">⟲</button>
                        </div>

                        <div data-study-plan-world class="absolute left-0 top-0 origin-top-left cursor-grab select-none">
                            <div data-study-plan-board class="relative" style="width: {{ $boardWidth }}px; height: {{ $boardHeight }}px;">
                                <svg data-study-plan-svg class="pointer-events-none absolute inset-0 z-0 h-full w-full overflow-visible" aria-hidden="true">
                                    <defs>
                                        <marker id="sp-arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto"><path d="M 0 0 L 6 3 L 0 6 z" fill="#cbd5e1"></path></marker>
                                        <marker id="sp-arrow-path" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto"><path d="M 0 0 L 7 3.5 L 0 7 z" fill="{{ $accent }}"></path></marker>
                                    </defs>
                                </svg>

                                <div class="relative z-10 grid" style="margin-left: {{ $layout['sidePadding'] }}px; grid-template-columns: repeat({{ $layout['totalTerms'] }}, {{ $layout['columnWidth'] }}px); gap: {{ $layout['columnGap'] }}px;">
                                    @foreach ($layoutCoursesByTerm as $termLayout)
                                        @php($term = $termLayout['term'])
                                        <div data-term-column class="relative" style="height: {{ $termLayout['height'] }}px;">
                                            <div class="z-30 mb-8 flex h-12 items-center justify-center rounded-2xl border border-slate-100 bg-white text-[13px] font-black uppercase tracking-[0.2em] text-spu-blue shadow-lg">{{ $text($term, 'label') }}</div>

                                            @foreach ($termLayout['courses'] as $layoutCourse)
                                                @php($course = $layoutCourse['course'])
                                                @php($courseType = $typeKey($course))
                                                <button type="button" data-course-card data-course-id="{{ $course['id'] ?? '' }}" aria-haspopup="dialog" aria-expanded="false" class="sp-course-card group absolute w-full rounded-2xl bg-white p-3.5 text-start border shadow-sm transition-all duration-300 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $typeClasses[$courseType] ?? $typeClasses['faculty'] }}" style="top: {{ $layoutCourse['top'] }}px; height: {{ $layout['cardHeight'] }}px;" dir="{{ $direction }}">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-[11px] font-black uppercase tracking-[0.15em] text-slate-400 group-hover:text-spu-red">{{ $course['code'] ?? '' }}</span>
                                                        <span class="rounded bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-400">{{ $course['credits'] ?? 0 }}cr</span>
                                                    </div>
                                                    <span class="mt-2.5 block text-[12px] font-extrabold leading-[1.5] text-slate-900 group-hover:text-spu-blue">{{ $text($course, 'title') }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6">
                        <div class="rounded-2xl border border-slate-200/60 bg-white p-8 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                            <div class="flex items-center gap-3"><div class="h-8 w-1 rounded-full bg-spu-red"></div><h3 class="text-[20px] font-black text-spu-blue">{{ $label('electiveRequirements') }}</h3></div>
                            <p class="mt-3 text-[13px] font-medium leading-6 text-slate-500">{{ $label('electiveNote') }}</p>
                            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                                @foreach (($activeDepartment['electivePools'] ?? []) as $pool)
                                    <div class="group relative rounded-xl border border-slate-200 bg-white p-5 transition-all duration-200 hover:border-spu-blue/30 hover:shadow-[0_4px_16px_rgba(32,39,89,0.08)]">
                                        <div class="flex items-center gap-2.5"><span class="h-2.5 w-2.5 rounded-full {{ ($pool['id'] ?? '') === 'university' ? 'bg-spu-blue' : (($pool['id'] ?? '') === 'faculty' ? 'bg-spu-blue/60' : 'bg-slate-400') }}"></span><span class="text-[12px] font-bold uppercase tracking-wider text-slate-600">{{ $poolLabel((string) ($pool['id'] ?? '')) }}</span></div>
                                        <div class="mt-4 flex items-baseline gap-1.5"><span class="text-[28px] font-black leading-none text-spu-red">{{ $pool['requiredHours'] ?? 0 }}</span><span class="text-[12px] font-bold text-slate-400">{{ $label('hoursRequired') }}</span></div>
                                        <p class="mt-2 text-[12px] leading-5 text-slate-500">{{ $text($pool, 'description') }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200/60 bg-white p-8 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                            <div class="flex items-center gap-3"><div class="h-8 w-1 rounded-full bg-spu-red"></div><h3 class="text-[20px] font-black text-spu-blue">{{ $label('promotionRequirements') }}</h3></div>
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                @foreach (($activeDepartment['promotionRequirements'] ?? []) as $req)
                                    <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-all duration-200 hover:border-spu-blue/30 hover:shadow-[0_4px_12px_rgba(32,39,89,0.06)]">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-spu-blue text-[13px] font-black text-white shadow-md shadow-spu-blue/20"><span>{{ $req['fromYear'] ?? '' }}</span><span class="mx-0.5 opacity-60">{{ $isAr ? '←' : '→' }}</span><span>{{ $req['toYear'] ?? '' }}</span></div>
                                        <div><div class="text-[20px] font-black leading-none text-spu-red">{{ $req['requiredCredits'] ?? '' }}</div><div class="mt-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $label('creditsRequired') }}</div></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <div data-course-modal role="dialog" aria-modal="true" aria-labelledby="study-plan-modal-title" aria-describedby="study-plan-modal-description" aria-hidden="true" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
        <div class="relative w-[90%] max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" dir="{{ $direction }}">
            <button type="button" data-modal-close data-modal-initial-focus class="absolute right-3 top-3 text-slate-400 transition-colors hover:text-slate-600 rtl:left-3 rtl:right-auto" aria-label="{{ $label('close') ?: ($isAr ? 'إغلاق' : 'Close') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span data-modal-code class="text-[11px] font-black uppercase tracking-wider text-spu-red"></span>
                <span data-modal-credits class="rounded bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-400"></span>
                <span data-modal-type class="text-[10px] font-bold uppercase tracking-wider text-spu-blue/60"></span>
            </div>
            <h3 id="study-plan-modal-title" data-modal-title class="text-[18px] font-black leading-tight text-spu-blue"></h3>
            <p id="study-plan-modal-description" data-modal-description class="mt-2 text-[13px] font-medium leading-6 text-slate-500"></p>
            <div class="mt-5 space-y-4">
                <div data-modal-prerequisites-wrap class="hidden"><p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $label('prerequisites') }}</p><div data-modal-prerequisites class="flex flex-wrap gap-1.5"></div></div>
                <div data-modal-openers-wrap class="hidden"><p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $label('opensAfter') }}</p><div data-modal-openers class="flex flex-wrap gap-1.5"></div></div>
            </div>
            <div class="mt-6 flex gap-3">
                <a data-modal-details href="{{ route('public.faculties.study-plan', ['locale' => $locale, 'faculty' => $page->facultySlug]) }}" class="inline-flex items-center gap-2 rounded-lg bg-spu-blue px-4 py-2.5 text-[12px] font-bold text-white transition-colors hover:bg-spu-blue/90"><span>{{ $label('viewDetails') }}</span><svg class="h-3.5 w-3.5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></a>
                <button type="button" data-modal-close class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2.5 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50">{{ $label('close') }}</button>
            </div>
        </div>
    </div>
</div>
