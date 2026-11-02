{{--
    A center's required or elective courses. Each course has the anchor
    #course-<id>; a faculty study plan links straight to it, and the script
    below opens that course's details and scrolls to it.
--}}
@php
    $listUrl = $page->path;
    $activeFaculty = $page->filters['faculty'] ?? '';
    $formatNumber = fn (int|float $value): string => rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
@endphp

<section class="bg-section py-14 font-hacen md:py-20" dir="{{ $direction }}" data-center-courses>
    <div class="container">
        @if ($page->filterOptions !== [])
            <nav class="mb-8" aria-label="{{ __('centers.filter_by_faculty') }}">
                <p class="mb-3 text-center text-sm font-bold text-slate-600">{{ __('centers.filter_by_faculty') }}</p>
                <ul class="flex flex-wrap justify-center gap-2">
                    <li>
                        <a href="{{ $listUrl }}" @if ($activeFaculty === '') aria-current="true" @endif class="inline-flex rounded-full px-4 py-2 text-[13px] font-bold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue {{ $activeFaculty === '' ? 'bg-spu-blue text-white' : 'bg-white text-spu-blue ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ __('centers.all_faculties') }}</a>
                    </li>
                    @foreach ($page->filterOptions as $facultySlug => $facultyName)
                        <li>
                            <a href="{{ $listUrl }}?faculty={{ urlencode($facultySlug) }}" @if ($activeFaculty === $facultySlug) aria-current="true" @endif class="inline-flex rounded-full px-4 py-2 text-[13px] font-bold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue {{ $activeFaculty === $facultySlug ? 'bg-spu-blue text-white' : 'bg-white text-spu-blue ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $facultyName }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <p class="mb-8 text-center text-sm font-bold text-slate-600" role="status">{{ __('centers.courses_count', ['count' => count($page->courses)]) }}</p>

        @if ($page->courses === [])
            <div class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white px-6 py-12 text-center">
                <p class="font-bold text-slate-700">{{ $activeFaculty !== '' ? __('centers.no_courses_for_faculty') : __('centers.no_courses') }}</p>
            </div>
        @else
            <ol class="mx-auto grid max-w-5xl gap-5">
                @foreach ($page->courses as $course)
                    <li>
                        <article id="{{ $course['anchor'] }}" class="scroll-mt-32 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-shadow target:border-spu-blue target:shadow-[0_0_0_3px_rgba(32,39,89,0.25)] md:p-8">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-bold {{ $course['required'] ? 'bg-spu-blue text-white' : 'bg-amber-100 text-amber-900' }}">{{ $course['required'] ? __('centers.required') : __('centers.elective') }}</span>
                                        @if ($course['code'] !== '')
                                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-700" dir="ltr">{{ $course['code'] }}</span>
                                        @endif
                                    </div>
                                    <h2 class="card-title mt-3 text-[1.3rem] font-bold leading-snug text-spu-blue" dir="auto">{{ $course['title'] }}</h2>
                                </div>
                                <dl class="flex flex-wrap gap-3 text-center">
                                    <div class="min-w-[86px] rounded-xl bg-slate-50 px-3 py-2">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('centers.credits') }}</dt>
                                        <dd class="text-lg font-black text-spu-blue" dir="ltr">{{ $formatNumber($course['credits']) }}</dd>
                                    </div>
                                    <div class="min-w-[86px] rounded-xl bg-slate-50 px-3 py-2">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('centers.theory') }}</dt>
                                        <dd class="text-lg font-black text-spu-blue" dir="ltr">{{ $formatNumber($course['hours']['theory']) }}</dd>
                                    </div>
                                    <div class="min-w-[86px] rounded-xl bg-slate-50 px-3 py-2">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('centers.practical') }}</dt>
                                        <dd class="text-lg font-black text-spu-blue" dir="ltr">{{ $formatNumber($course['hours']['practical']) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            @if ($course['faculties'] !== [])
                                <div class="mt-5">
                                    <h3 class="text-[12px] font-bold uppercase tracking-wider text-slate-500">{{ __('centers.faculties') }}</h3>
                                    <ul class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($course['faculties'] as $link)
                                            <li>
                                                <a href="{{ $link['url'] }}" title="{{ __('centers.open_study_plan', ['faculty' => $link['name']]) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] font-bold text-slate-700 transition-colors hover:border-spu-blue hover:text-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                                                    <span>{{ $link['name'] }}</span>
                                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600" dir="ltr">{{ $link['code'] }}</span>
                                                    @if ($link['credits'] !== null)
                                                        <span class="text-[11px] text-slate-500">{{ __('centers.credits_short', ['count' => $formatNumber($link['credits'])]) }}</span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @php($hasDetails = $course['description'] !== '' || $course['prerequisites'] !== [] || $course['teachers'] !== [] || $course['lessons'] !== [] || $course['specificationUrl'] !== '')
                            @if ($hasDetails)
                                <details class="group mt-5 border-t border-slate-100 pt-4" data-course-details>
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded text-sm font-bold text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                                        {{ __('centers.details') }}
                                        <svg class="h-3 w-3 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                    </summary>
                                    <div class="mt-4 grid gap-6">
                                        @if ($course['description'] !== '')
                                            <x-public.rich-text class="text-[15px] leading-7 text-[#46464f]" :text="$course['description']" />
                                        @endif

                                        @if ($course['prerequisites'] !== [])
                                            <div>
                                                <h3 class="text-[12px] font-bold uppercase tracking-wider text-slate-500">{{ __('centers.prerequisites') }}</h3>
                                                <ul class="mt-2 flex flex-wrap gap-2">
                                                    @foreach ($course['prerequisites'] as $prerequisite)
                                                        <li><a href="{{ $prerequisite['url'] }}" class="inline-flex rounded-lg bg-slate-50 px-3 py-1.5 text-[13px] font-bold text-spu-blue underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">{{ $prerequisite['title'] }}</a></li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if ($course['teachers'] !== [])
                                            <div>
                                                <h3 class="text-[12px] font-bold uppercase tracking-wider text-slate-500">{{ __('centers.teachers') }}</h3>
                                                <ul class="mt-2 flex flex-wrap gap-3">
                                                    @foreach ($course['teachers'] as $teacher)
                                                        <li>
                                                            <a href="{{ $teacher['url'] }}" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 transition-colors hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                                                                @if (! empty($teacher['image']))
                                                                    <img src="{{ $teacher['image'] }}" alt="" class="h-10 w-10 rounded-full object-cover" loading="lazy" aria-hidden="true">
                                                                @else
                                                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100" aria-hidden="true"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-5 w-5 opacity-40"></span>
                                                                @endif
                                                                <span class="flex flex-col">
                                                                    <span class="text-[13px] font-bold text-spu-blue" dir="auto">{{ $teacher['name'] }}</span>
                                                                    <span class="text-[11px] font-semibold text-slate-500">{{ __('centers.teacher_parts.'.$teacher['part']) }}</span>
                                                                </span>
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if ($course['lessons'] !== [] || $course['specificationUrl'] !== '')
                                            <div>
                                                <h3 class="text-[12px] font-bold uppercase tracking-wider text-slate-500">{{ __('centers.lessons') }}</h3>
                                                <ul class="mt-2 grid gap-2">
                                                    @if ($course['specificationUrl'] !== '')
                                                        <li><a href="{{ $course['specificationUrl'] }}" target="_blank" rel="noopener" class="text-[14px] font-bold text-spu-red underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">{{ __('centers.specification') }}</a></li>
                                                    @endif
                                                    @foreach ($course['lessons'] as $lesson)
                                                        <li class="flex items-center gap-3 text-[14px] text-slate-700">
                                                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">{{ __('centers.lesson_types.'.$lesson['type']) }}</span>
                                                            @if ($lesson['url'] !== '')
                                                                <a href="{{ $lesson['url'] }}" target="_blank" rel="noopener" class="font-bold text-spu-blue underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue" dir="auto">{{ $lesson['title'] }}</a>
                                                            @else
                                                                <span dir="auto">{{ $lesson['title'] }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>

<script>
    (function () {
        // A link from a study plan names one course (#course-<id>): open its details.
        function openLinkedCourse() {
            var id = decodeURIComponent((window.location.hash || '').slice(1));
            if (id.indexOf('course-') !== 0) return;
            var course = document.getElementById(id);
            if (!course) return;
            var details = course.querySelector('[data-course-details]');
            if (details) details.open = true;
            course.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        }
        window.addEventListener('hashchange', openLinkedCourse);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', openLinkedCourse);
        } else {
            openLinkedCourse();
        }
    })();
</script>
