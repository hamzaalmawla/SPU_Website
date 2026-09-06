@php
    $studyPayload = $subpage['payload'] ?? [];
    $plan = $studyPayload['plan'] ?? [];
    $labels = $studyPayload['courseLabels'] ?? [];
    $lessonTypes = $studyPayload['lessonTypes'] ?? [];
    $legend = $studyPayload['legend'] ?? [];
    $activeDepartment = $page->detail['activeDepartment'];
    $course = $page->detail['course'];
    $filteredLessons = collect($page->detail['lessons']);
    $selectedType = $page->detail['selectedType'];
    $availableTypes = collect($page->detail['availableTypes']);
    $prerequisites = collect($page->detail['prerequisites']);
    $openedCourses = collect($page->detail['openedCourses']);
    $label = fn (string $key): string => (string) ($labels[$key] ?? '');
    $text = fn (array $item, string $key): string => (string) ($item[$key] ?? $item[$key.'En'] ?? $item[$key.'Ar'] ?? '');
    $typeLabel = function (?array $course) use ($legend, $text, $label): string {
        if (! is_array($course)) {
            return '';
        }
        if (! ($course['required'] ?? true)) {
            return $label('elective');
        }
        $legendItem = collect($legend)->firstWhere('id', $course['type'] ?? 'faculty');

        return is_array($legendItem) ? $text($legendItem, 'label') : $label('required');
    };
    $courseHref = fn (array $item): string => '/'.$locale.'/faculties/'.$page->facultySlug.'/study-plan/course?department='.urlencode((string) ($activeDepartment['id'] ?? '')).'&course='.urlencode((string) ($item['id'] ?? ''));
@endphp

<section class="relative flex min-h-[330px] items-end justify-center overflow-hidden pt-28 text-center font-hacen">
    <div class="absolute inset-0">
        <img src="{{ $plan['heroImage'] ?? ($faculty['heroImage'] ?? '/images/uni-main-place.JPG') }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/72 to-spu-blue/18"></div>
    </div>
    <div class="container relative z-10 pb-14 text-white">
        <nav class="mb-4 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-white/75" aria-label="Breadcrumb">
            <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $label('home') ?: $homeLabel }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <a href="/{{ $locale }}/faculties" class="transition-colors hover:text-white">{{ $label('faculties') ?: ($isAr ? 'الكليات' : 'Facilities') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <a href="/{{ $locale }}/faculties/{{ $page->facultySlug }}/study-plan?department={{ urlencode((string) ($activeDepartment['id'] ?? '')) }}" class="transition-colors hover:text-white">{{ $label('studyPlan') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <span>{{ is_array($course) ? ($course['code'] ?? '') : $label('coursePage') }}</span>
        </nav>
        @if (is_array($course))
            <div class="mx-auto max-w-4xl">
                <p class="text-[12px] font-bold uppercase tracking-[0.16em] text-white/75">{{ ($plan['faculty'] ?? $faculty['title']).' / '.($activeDepartment['name'] ?? '') }}</p>
                <h1 class="mt-3 text-[34px] font-bold leading-tight md:text-[46px]">{{ $course['title'] ?? '' }}</h1>
            </div>
        @else
            <h1 class="text-[34px] font-bold leading-tight md:text-[46px]">{{ $label('notFound') }}</h1>
        @endif
    </div>
</section>

<section class="bg-white py-14 font-hacen lg:py-20">
    <div class="container">
        @if (is_array($course))
            <div class="grid gap-8 lg:grid-cols-[0.78fr_1.22fr] lg:items-start">
                <aside class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_18px_44px_rgba(9,17,68,0.07)] lg:sticky lg:top-28">
                    <a href="/{{ $locale }}/faculties/{{ $page->facultySlug }}/study-plan?department={{ urlencode((string) ($activeDepartment['id'] ?? '')) }}" class="inline-flex items-center gap-2 text-[12px] font-bold text-spu-red transition hover:text-spu-blue"><img src="/images/icon-arrow-left-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true"><span>{{ $label('backToPlan') }}</span></a>
                    <div class="mt-6 rounded-[8px] bg-section p-5"><p class="text-[12px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ $course['code'] ?? '' }}</p><h2 class="mt-2 text-[24px] font-bold leading-tight text-spu-blue">{{ $course['title'] ?? '' }}</h2></div>
                    <dl class="mt-5 grid grid-cols-2 gap-3 text-[11px] font-bold uppercase">
                        <div class="rounded-[6px] border border-slate-100 bg-white p-3"><dt class="text-slate-400">{{ $label('credits') }}</dt><dd class="mt-1 text-lg text-spu-blue">{{ $course['credits'] ?? 0 }}</dd></div>
                        <div class="rounded-[6px] border border-slate-100 bg-white p-3"><dt class="text-slate-400">{{ $label('courseType') }}</dt><dd class="mt-1 text-[13px] text-spu-blue">{{ $typeLabel($course) }}</dd></div>
                        <div class="col-span-2 rounded-[6px] border border-slate-100 bg-white p-3"><dt class="text-slate-400">{{ $label('requiredStatus') }}</dt><dd class="mt-1 text-[13px] text-spu-blue">{{ ($course['required'] ?? true) ? $label('required') : $label('elective') }}</dd></div>
                        @if (is_array($course['instructor'] ?? null))
                            @if (is_string($course['instructorUrl'] ?? null))
                                <a href="{{ $course['instructorUrl'] }}" class="col-span-2 rounded-[1px] border border-slate-100 bg-white p-3 transition hover:border-spu-blue hover:bg-spu-blue/5"><dt class="text-slate-400">{{ $isAr ? 'المدرس' : 'Instructor' }}</dt><dd class="mt-1 flex items-center gap-2 text-[13px] font-bold text-spu-blue"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-4 w-4" aria-hidden="true"><span>{{ $isAr ? ($course['instructor']['nameAr'] ?? '') : ($course['instructor']['nameEn'] ?? '') }}</span></dd></a>
                            @else
                                <div class="col-span-2 rounded-[1px] border border-slate-100 bg-white p-3"><dt class="text-slate-400">{{ $isAr ? 'المدرس' : 'Instructor' }}</dt><dd class="mt-1 flex items-center gap-2 text-[13px] font-bold text-spu-blue"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-4 w-4" aria-hidden="true"><span>{{ $isAr ? ($course['instructor']['nameAr'] ?? '') : ($course['instructor']['nameEn'] ?? '') }}</span></dd></div>
                            @endif
                        @endif
                    </dl>
                </aside>

                <div class="space-y-8">
                    <section class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-sm md:p-7">
                        <h2 class="text-[24px] font-bold text-spu-blue">{{ $label('prerequisites') }}</h2>
                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <div><h3 class="text-[13px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $label('prerequisites') }}</h3><div class="mt-3 grid gap-2">@forelse ($prerequisites as $item)<a href="{{ $courseHref($item) }}" class="rounded-[6px] border border-slate-200 bg-section p-4 transition hover:border-spu-blue hover:bg-white"><span class="block text-[11px] font-bold uppercase text-slate-400">{{ $item['code'] ?? '' }}</span><span class="mt-1 block text-[14px] font-bold text-spu-blue">{{ $item['title'] ?? '' }}</span></a>@empty<p class="text-[13px] font-semibold text-slate-500">{{ $label('emptyLinks') }}</p>@endforelse</div></div>
                            <div><h3 class="text-[13px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $label('opensAfter') }}</h3><div class="mt-3 grid gap-2">@forelse ($openedCourses as $item)<a href="{{ $courseHref($item) }}" class="rounded-[6px] border border-slate-200 bg-section p-4 transition hover:border-spu-blue hover:bg-white"><span class="block text-[11px] font-bold uppercase text-slate-400">{{ $item['code'] ?? '' }}</span><span class="mt-1 block text-[14px] font-bold text-spu-blue">{{ $item['title'] ?? '' }}</span></a>@empty<p class="text-[13px] font-semibold text-slate-500">{{ $label('emptyLinks') }}</p>@endforelse</div></div>
                        </div>
                    </section>

                    <section class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-sm md:p-7">
                        <div class="mb-6 flex items-center justify-between gap-4"><h2 class="text-[24px] font-bold text-spu-blue">{{ $label('lessons') }}</h2><div class="flex flex-wrap gap-2"><a href="{{ $courseHref($course) }}" class="rounded-[5px] px-3 py-1/2 text-[11px] flex items-center justify-center font-bold uppercase tracking-wide {{ $selectedType === 'all' ? 'bg-spu-blue text-white' : 'bg-slate-100 text-slate-500' }}">{{ $label('all') }}</a>@foreach ($availableTypes as $type)<a href="{{ $courseHref($course) }}&type={{ urlencode((string) $type) }}" class="rounded-[5px] flex items-center justify-between px-3 py-1/2 text-[11px] font-bold uppercase tracking-wide {{ $selectedType === $type ? 'bg-spu-blue text-white' : 'bg-slate-100 text-slate-500' }}">{{ $lessonTypes[$type]['label'] ?? $type }}</a>@endforeach</div></div>
                        <div class="space-y-4">@foreach ($filteredLessons as $lesson)<div class="group rounded-[8px] border border-slate-200 bg-section p-5 transition hover:border-spu-blue/30 hover:bg-white"><div class="flex items-start justify-between gap-4"><div class="flex-1"><div class="flex items-center gap-2"><span class="rounded-[5px] bg-spu-blue/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-spu-blue">{{ $lessonTypes[$lesson['type'] ?? 'lecture']['label'] ?? ($lesson['type'] ?? '') }}</span><span class="text-[11px] font-semibold text-slate-400">#{{ $lesson['order'] ?? '' }}</span></div><h3 class="mt-2 text-[16px] font-bold text-spu-blue">{{ $lesson['title'] ?? '' }}</h3><p class="mt-1 text-[13px] font-medium leading-6 text-slate-500">{{ $lesson['description'] ?? '' }}</p></div>@if (! empty($lesson['pdfUrl']))<div class="flex flex-col gap-2"><a href="{{ $lesson['pdfUrl'] }}" target="_blank" class="inline-flex items-center gap-1 rounded-[6px] bg-spu-blue px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-spu-blue/90">{{ $label('viewPdf') }}</a><a href="{{ $lesson['pdfUrl'] }}" download class="inline-flex items-center gap-1 rounded-[6px] bg-slate-200 px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-300">{{ $label('download') }}</a></div>@endif</div>@if (empty($lesson['pdfUrl']))<p class="mt-3 text-[12px] font-semibold italic text-slate-400">{{ $label('noPdf') }}</p>@endif</div>@endforeach</div>
                    </section>
                </div>
            </div>
        @else
            <div class="py-20 text-center"><img src="/images/icon-file-outline.svg" alt="" class="mx-auto h-16 w-16" aria-hidden="true"><p class="mt-4 text-lg font-semibold text-slate-500">{{ $label('notFound') }}</p><a href="/{{ $locale }}/faculties/{{ $page->facultySlug }}/study-plan" class="mt-6 inline-flex items-center gap-2 rounded-[6px] bg-spu-blue px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-spu-blue/90">{{ $label('backToPlan') }}</a></div>
        @endif
    </div>
</section>
