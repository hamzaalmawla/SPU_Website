@extends('layouts.public')

@section('content')
    @php
        $faculty = $page->faculty;
        $subpage = $page->page;
        $accent = $faculty['accentColor'] ?: '#202759';
        $isAr = $locale === 'ar';
        $heroImage = $subpage['heroImage'] ?? ($faculty['heroImage'] ?? '/images/uni-main-place.JPG');
        $homeLabel = $isAr ? 'الرئيسية' : 'Home';
        $facultyUrl = '/'.$locale.'/facilities/'.$page->facultySlug;
        $breadcrumbTitle = $subpage['title'] ?? '';
        $selectedLab = $page->subpageSlug === 'labs' ? ($page->detail['item'] ?? null) : null;
        $relatedLabs = $page->subpageSlug === 'labs' && is_array($page->detail['related'] ?? null) ? $page->detail['related'] : [];
        $isLabDetail = is_array($selectedLab);
        $heroImage = match ($page->subpageSlug) {
            'labs' => $isLabDetail ? ($selectedLab['image'] ?? '/images/dental-clin-lab.jpg') : ($faculty['heroImage'] ?? '/images/slider-3.webp'),
            'projects', 'alumni' => '/images/pharmacy-place.jpg',
            'valedictorians' => '/images/uni-main-place.JPG',
            default => $heroImage,
        };
        $heroTitle = $isLabDetail ? ($selectedLab['title'] ?? '') : ($subpage['title'] ?? '');
        $heroSummary = match ($page->subpageSlug) {
            'labs' => $isLabDetail ? null : count($page->items).($isAr ? ' مخابر بحثية وتدريبية' : ' research and training labs'),
            'members' => count($page->items).($isAr ? ' عضو هيئة أكاديمية' : ' faculty members'),
            'projects', 'alumni', 'valedictorians' => null,
            default => $subpage['summary'] ?? null,
        };
        $filters = $page->filters ?? [];
        $filterOptions = $page->filterOptions ?? [];
        $pagination = $page->pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => count($page->items), 'from' => count($page->items) > 0 ? 1 : 0, 'to' => count($page->items)];
        $studentListUrl = '/'.$locale.'/facilities/'.$page->facultySlug.'/'.$page->subpageSlug;
        $validatedQuery = collect($filters)
            ->except('page')
            ->filter(fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
        $queryUrl = fn (array $query): string => $studentListUrl.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        $pageUrl = fn (int $pageNumber): string => $queryUrl([...$validatedQuery, ...($pageNumber > 1 ? ['page' => $pageNumber] : [])]);
        $labDetailUrl = fn (string $slug): string => $queryUrl(array_filter([
            'lab' => $slug,
            'page' => (int) ($pagination['current_page'] ?? 1) > 1 ? (int) $pagination['current_page'] : null,
        ], fn (mixed $value): bool => $value !== null));
        $labBackUrl = $queryUrl((int) ($pagination['current_page'] ?? 1) > 1 ? ['page' => (int) $pagination['current_page']] : []);
        $training = $page->subpageSlug === 'training' ? ($subpage['payload'] ?? []) : [];
        $localized = fn (array $item, string $key): string => (string) ($item[$key] ?? $item[$key.ucfirst($locale)] ?? $item[$key.'En'] ?? $item[$key.'Ar'] ?? '');
    @endphp

    @if ($page->subpageSlug === 'research')
    @elseif ($page->subpageSlug === 'study-plan')
        @include('public.faculties.study-plan')
    @elseif ($page->subpageSlug === 'study-plan-course')
        @include('public.faculties.course-lessons')
    @elseif ($page->subpageSlug === 'training')
        @include('public.faculties.training')
    @else
        <section class="relative flex {{ $page->subpageSlug === 'alumni' ? 'min-h-[300px]' : ($page->subpageSlug === 'projects' ? 'min-h-[315px]' : 'min-h-[330px]') }} items-end overflow-hidden pt-28 font-hacen">
            <div class="absolute inset-0">
                <img src="{{ $heroImage }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/65 to-spu-blue/15"></div>
            </div>

            <div class="container relative z-10 pb-14 text-center text-white">
                <nav class="mb-3 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-white/72" aria-label="Breadcrumb">
                    <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $homeLabel }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                    <a href="{{ $facultyUrl }}" class="transition-colors hover:text-white">{{ $faculty['title'] }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                    <span class="text-white">{{ $breadcrumbTitle }}</span>
                </nav>
                <h1 class="text-[34px] font-bold leading-tight md:text-[42px]">{{ $heroTitle }}</h1>
                @if ($heroSummary)
                    <p class="mx-auto mt-4 max-w-[760px] text-sm font-semibold leading-7 text-white/82">{{ $heroSummary }}</p>
                @endif
            </div>
        </section>
    @endif

    @if ($page->subpageSlug === 'research')
        @include('public.faculties.research')
    @elseif ($page->subpageSlug === 'overview')
        @php
            $sections = $subpage['sections'] ?? [];
            $stats = $subpage['payload']['stats'] ?? [];
            $dean = $subpage['payload']['dean'] ?? [];
            $firstSection = $sections[0] ?? null;
            $secondSection = $sections[1] ?? null;
        @endphp

        <section class="bg-white py-16 font-hacen lg:py-24">
            <div class="container space-y-20 lg:space-y-24">
                <div class="grid items-center gap-10 lg:grid-cols-[0.86fr_1.14fr] lg:gap-16">
                    <div>
                        <h2 class="text-[30px] font-bold leading-tight text-spu-blue md:text-[38px]">{{ $isAr ? 'لمحة عن الكلية' : 'Faculty Overview' }}</h2>
                        <div class="mt-4 h-[3px] w-14 rounded-full" style="background-color: {{ $accent }}"></div>
                        <p class="mt-6 text-[15px] leading-8 text-slate-600">{{ $subpage['body'] ?? $faculty['description'] }}</p>
                        @if ($firstSection)
                            <p class="mt-5 text-[15px] leading-8 text-slate-600">{{ $firstSection['body'] ?? '' }}</p>
                        @endif
                    </div>
                    <div class="overflow-hidden rounded-[8px] shadow-[0_18px_45px_rgba(9,17,68,0.16)]">
                        <img src="{{ $heroImage }}" alt="" class="aspect-[16/7] w-full object-cover lg:aspect-[16/8]">
                    </div>
                </div>

                @if ($firstSection || $secondSection)
                    <div class="-mx-5 bg-section px-5 py-12 sm:-mx-8 sm:px-8 lg:-mx-12 lg:px-12">
                        <div class="grid items-center gap-10 lg:grid-cols-[1.08fr_0.92fr] lg:gap-16">
                            <div class="order-2 overflow-hidden rounded-[8px] shadow-[0_18px_45px_rgba(9,17,68,0.12)] lg:order-1">
                                <img src="{{ $faculty['heroImage'] ?? $heroImage }}" alt="" class="aspect-[16/8] w-full object-cover">
                            </div>
                            <div class="order-1 lg:order-2">
                                <h2 class="text-[30px] font-bold leading-tight text-spu-blue md:text-[38px]">{{ $isAr ? 'الرؤية والرسالة' : 'Vision and Mission' }}</h2>
                                <div class="mt-4 h-[3px] w-14 rounded-full" style="background-color: {{ $accent }}"></div>
                                @foreach ([$firstSection, $secondSection] as $section)
                                    @if ($section)
                                        <p class="mt-6 text-[15px] leading-8 text-slate-600">{{ $section['body'] ?? '' }}</p>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid items-center gap-10 lg:grid-cols-[0.86fr_1.14fr] lg:gap-16">
                    <div>
                        <h2 class="text-[30px] font-bold leading-tight text-spu-blue md:text-[38px]">{{ $isAr ? 'الأهداف الأكاديمية' : 'Academic Objectives' }}</h2>
                        <div class="mt-4 h-[3px] w-14 rounded-full" style="background-color: {{ $accent }}"></div>
                        <p class="mt-6 text-[15px] leading-8 text-slate-600">{{ $secondSection['body'] ?? ($subpage['body'] ?? $faculty['description']) }}</p>
                        <div class="cms-grid-stats cms-grid-stats-cols-4 mt-8 gap-3">
                            @foreach (array_slice($stats, 0, 4) as $stat)
                                <div class="rounded-[8px] border border-slate-100 bg-white p-5 shadow-[0_10px_28px_rgba(9,17,68,0.05)]">
                                    <p class="text-[28px] font-bold leading-none" style="color: {{ $accent }}" dir="ltr">{{ $stat['value'] ?? '' }}</p>
                                    <p class="mt-2 text-[11px] font-bold uppercase tracking-[0.14em] text-spu-blue/50">{{ $stat['label'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-[8px] shadow-[0_18px_45px_rgba(9,17,68,0.16)]">
                        <img src="{{ $heroImage }}" alt="" class="aspect-[16/8] w-full object-cover">
                    </div>
                </div>
            </div>
        </section>

        @if (! empty($dean))
            <section class="bg-white py-16 font-hacen lg:py-24">
                <div class="container">
                    <div class="mx-auto grid max-w-[1080px] gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:gap-16">
                        <div class="min-h-[360px] overflow-hidden rounded-[8px] border border-slate-100 bg-slate-50 shadow-[0_16px_42px_rgba(9,17,68,0.05)]">
                            <img src="{{ $dean['image'] ?? $heroImage }}" alt="" class="h-full min-h-[360px] w-full object-cover object-top">
                        </div>
                        <div class="border-l-[3px] pl-9 rtl:border-l-0 rtl:border-r-[3px] rtl:pl-0 rtl:pr-9" style="border-color: {{ $accent }}">
                            <h2 class="text-[30px] font-bold leading-tight text-spu-blue md:text-[40px]">{{ $isAr ? 'رسالة العميد' : 'Dean Message' }}</h2>
                            @php($deanName = $dean['name'] ?? ($isAr ? ($dean['nameAr'] ?? '') : ($dean['nameEn'] ?? '')))
                            @if ($page->deanProfile)
                                <a href="{{ $page->deanProfile->path }}" class="mt-8 inline-flex text-sm font-bold text-spu-blue transition-colors hover:text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">{{ $deanName }}</a>
                            @else
                                <p class="mt-8 text-sm font-bold text-spu-blue">{{ $deanName }}</p>
                            @endif
                            <div class="mt-6 space-y-5 text-[14px] leading-8 text-slate-600">
                                <p>{{ $dean['message'] ?? ($isAr ? ($dean['messageAr'] ?? '') : ($dean['messageEn'] ?? '')) }}</p>
                                <p>{{ $firstSection['body'] ?? '' }}</p>
                            </div>
                            <div class="mt-8 border-t border-slate-100 pt-5">
                                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-spu-blue">{{ $dean['role'] ?? ($isAr ? ($dean['roleAr'] ?? 'عميد الكلية') : ($dean['roleEn'] ?? 'Faculty Dean')) }}</p>
                                @if ($page->deanProfile)
                                    <a href="{{ $page->deanProfile->path }}" class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-spu-blue transition-colors hover:text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                                        <span>{{ $isAr ? 'عرض الملف الشخصي' : 'View Profile' }}</span>
                                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @include('public.faculties.partials.latest-research', [
            'latestResearch' => $page->latestResearch,
            'locale' => $locale,
            'accent' => $accent,
            'sectionId' => 'overview-latest-research',
        ])

        @include('public.faculties.partials.navigation-section', [
            'navSectionId' => 'faculty-pathways',
            'navHeadingAr' => 'مسارات',
            'navHighlightAr' => 'الكلية',
            'navHeadingEn' => 'Faculty',
            'navHighlightEn' => 'Pathways',
            'navCards' => $page->navigation,
            'locale' => $locale,
        ])
    @elseif ($page->subpageSlug === 'departments')
        <section class="bg-white py-16 font-hacen lg:py-24">
            <div class="container">
                <div class="cms-grid-stats cms-grid-stats-cols-3 mx-auto max-w-[1080px] gap-4">
                    <div class="rounded-[8px] bg-section p-6 text-center">
                        <p class="text-3xl font-bold" style="color: {{ $accent }}" dir="ltr">{{ count($page->items) }}</p>
                        <p class="mt-2 text-xs font-bold uppercase tracking-[0.16em] text-spu-blue/45">{{ $isAr ? 'أقسام' : 'Departments' }}</p>
                    </div>
                    <div class="rounded-[8px] bg-section p-6 text-center">
                        <p class="text-3xl font-bold" style="color: {{ $accent }}">{{ $faculty['yearsLabel'] }}</p>
                        <p class="mt-2 text-xs font-bold uppercase tracking-[0.16em] text-spu-blue/45">{{ $isAr ? 'مدة الدراسة' : 'Study Duration' }}</p>
                    </div>
                    <div class="rounded-[8px] bg-section p-6 text-center">
                        <p class="text-3xl font-bold" style="color: {{ $accent }}">{{ $isAr ? 'بكالوريوس' : 'Bachelor' }}</p>
                        <p class="mt-2 text-xs font-bold uppercase tracking-[0.16em] text-spu-blue/45">{{ $isAr ? 'الدرجة' : 'Degree' }}</p>
                    </div>
                </div>

                <div class="mx-auto mt-12 max-w-[1080px] divide-y divide-slate-100 overflow-hidden rounded-[8px] border border-slate-100 bg-white shadow-[0_12px_40px_rgba(9,17,68,0.06)]">
                    @forelse ($page->items as $item)
                        <article id="{{ $item['slug'] ?? 'department-'.$loop->iteration }}" class="grid gap-5 p-6 md:grid-cols-[80px_1fr_auto] md:items-center md:p-8">
                            <div class="text-[32px] font-bold leading-none text-spu-red/80">{{ $item['code'] ?? str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-xl font-bold text-spu-blue">{{ $item['title'] ?? '' }}</h2>
                                    <span class="rounded-full bg-section px-3 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-spu-blue/55">{{ $item['degrees'] ?? ($isAr ? 'اختصاص أكاديمي' : 'Academic Track') }}</span>
                                </div>
                                <p class="mt-3 max-w-[720px] text-sm leading-7 text-slate-600">{{ $item['summary'] ?? '' }}</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @forelse (($item['tags'] ?? []) as $tag)
                                        <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500">{{ $tag }}</span>
                                    @empty
                                        <span class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500">{{ $faculty['title'] }}</span>
                                    @endforelse
                                </div>
                            </div>
                            <a href="{{ $item['studyPlanUrl'] ?? ('/'.$locale.'/facilities/'.$page->facultySlug.'/study-plan') }}" class="inline-flex h-11 items-center justify-center rounded-[6px] px-5 text-sm font-bold text-white transition-all hover:-translate-y-0.5" style="background-color: {{ $accent }}">{{ $isAr ? 'الخطة الدراسية' : 'Study Plan' }}</a>
                        </article>
                    @empty
                        <p class="p-8 text-center text-slate-500">{{ $isAr ? 'لا توجد أقسام منشورة حالياً.' : 'No published departments are available yet.' }}</p>
                    @endforelse
                </div>
            </div>
        </section>
    @elseif ($page->subpageSlug === 'labs')
        <section class="bg-white py-16 font-hacen lg:py-24">
            <div class="container">
                @if ($selectedLab)
                    <div class="mx-auto max-w-4xl">
                        <div class="relative h-[300px] overflow-hidden rounded-[10px]">
                            @if (! empty($selectedLab['image']))
                                <img src="{{ $selectedLab['image'] }}" alt="{{ $selectedLab['title'] ?? '' }}" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="mt-8 space-y-4">
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="rounded-full bg-section px-4 py-2 text-sm font-bold text-spu-blue">{{ $isAr ? 'القسم' : 'Department' }}</span>
                                <span class="text-lg font-semibold text-slate-700">{{ $selectedLab['department'] ?? $faculty['title'] }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="rounded-full bg-section px-4 py-2 text-sm font-bold text-spu-blue">{{ $isAr ? 'المشرف' : 'Instructor' }}</span>
                                <span class="text-lg font-semibold text-slate-700">{{ $selectedLab['instructor'] ?? '' }}</span>
                            </div>
                        </div>
                        <div class="mt-8">
                            <h2 class="mb-4 text-xl font-bold text-spu-blue">{{ $isAr ? 'الوصف' : 'Description' }}</h2>
                            <p class="text-lg leading-relaxed text-slate-600">{{ $selectedLab['summary'] ?? '' }}</p>
                        </div>
                        @if ($relatedLabs !== [])
                            <div class="mt-12 border-t border-slate-100 pt-8">
                                <h2 class="text-xl font-bold text-spu-blue">{{ $isAr ? 'مخابر ذات صلة' : 'Related Labs' }}</h2>
                                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                                    @foreach ($relatedLabs as $relatedLab)
                                        <a href="{{ $labDetailUrl((string) ($relatedLab['slug'] ?? '')) }}" class="overflow-hidden rounded-[8px] border border-slate-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                            @if (! empty($relatedLab['image']))
                                                <img src="{{ $relatedLab['image'] }}" alt="{{ $relatedLab['title'] ?? '' }}" class="h-28 w-full object-cover">
                                            @endif
                                            <span class="block p-4 text-sm font-bold text-spu-blue">{{ $relatedLab['title'] ?? '' }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <a href="{{ $labBackUrl }}" class="mt-12 inline-flex items-center gap-2 text-sm font-bold transition-colors hover:text-spu-red" style="color: {{ $accent }}">
                            <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-4 w-4 rtl:rotate-180" aria-hidden="true">
                            <span>{{ $isAr ? 'العودة إلى المخابر' : 'Back to Labs' }}</span>
                        </a>
                    </div>
                @else
                    <div class="cms-grid-cards gap-6 lg:gap-8">
                        @forelse ($page->items as $item)
                            <a href="{{ $labDetailUrl((string) ($item['slug'] ?? '')) }}" class="group overflow-hidden rounded-[10px] bg-white shadow-[0_4px_20px_rgba(0,0,0,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(0,0,0,0.15)]">
                                <div class="relative h-[180px] overflow-hidden">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                                    @endif
                                    <div class="absolute right-3 top-3 rtl:left-3 rtl:right-auto">
                                        <span class="flex items-center gap-1 rounded border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold text-spu-blue shadow-sm">{{ $item['department'] ?? $faculty['title'] }}</span>
                                    </div>
                                </div>
                                <div class="p-5">
                                    <h3 class="text-lg font-bold leading-tight text-spu-blue">{{ $item['title'] ?? '' }}</h3>
                                    <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">{{ $isAr ? 'القسم' : 'Department' }}</p>
                                    <p class="text-sm text-slate-600">{{ $item['department'] ?? '' }}</p>
                                    <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">{{ $isAr ? 'المشرف' : 'Instructor' }}</p>
                                    <p class="text-sm text-slate-400">{{ $item['instructor'] ?? '' }}</p>
                                </div>
                            </a>
                        @empty
                            <p class="text-slate-500">{{ $isAr ? 'لا توجد مخابر منشورة حالياً.' : 'No published labs are available yet.' }}</p>
                        @endforelse
                    </div>
                    <x-public.pagination :current-page="$pagination['current_page'] ?? 1" :total-pages="$pagination['total_pages'] ?? 1" :page-url="$pageUrl" :locale="$locale" class="mt-10" />
                @endif
            </div>
        </section>
    @elseif ($page->subpageSlug === 'projects')
        <section class="bg-white py-16 font-hacen md:py-20">
            <div class="container">
                <div class="cms-grid-wide gap-7">
                    @forelse ($page->items as $item)
                        <article id="{{ $item['slug'] ?? 'project-'.$loop->iteration }}" class="overflow-hidden rounded-[6px] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md">
                            <div class="relative aspect-[1.72] overflow-hidden bg-slate-100">
                                <img src="{{ $item['image'] ?? '/images/Gemini_Generated_Image_c89yjwc89yjwc89y.webp' }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover transition duration-500 hover:scale-[1.03]">
                                <span class="absolute left-3 top-3 rounded-[3px] bg-spu-red px-2 py-1 text-[10px] font-bold leading-none text-white rtl:left-auto rtl:right-3">{{ $item['tag'] ?? ($isAr ? 'مشروع' : 'Project') }}</span>
                            </div>
                            <div class="p-6">
                                <h2 class="min-h-[42px] text-[16px] font-bold leading-[21px] text-spu-blue">{{ $item['title'] ?? '' }}</h2>
                                <p class="mt-3 min-h-[44px] text-[13px] font-medium leading-[22px] text-slate-600">{{ $item['summary'] ?? '' }}</p>
                                <div class="mt-6 border-t border-slate-100 pt-4">
                                    <dl class="space-y-3 text-[10px] font-bold uppercase tracking-[0.04em]">
                                        <div>
                                            <dt class="text-slate-400">{{ $isAr ? 'الفريق' : 'Team' }}</dt>
                                            <dd class="mt-1 text-spu-blue">{{ $item['team'] ?? '' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-400">{{ $isAr ? 'المشرف' : 'Supervisor' }}</dt>
                                            <dd class="mt-1 text-spu-blue">{{ $item['supervisor'] ?? '' }}</dd>
                                        </div>
                                    </dl>
                                    <a href="{{ $item['detailRoute'] ?? ('#'.($item['slug'] ?? '')) }}" class="mt-5 inline-flex items-center gap-2 text-[12px] font-bold text-spu-red transition hover:text-spu-blue">
                                        <span>{{ $isAr ? 'عرض التفاصيل' : 'View Details' }}</span>
                                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                                    </a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="text-slate-500">{{ $isAr ? 'لا توجد مشاريع منشورة حالياً.' : 'No published projects are available yet.' }}</p>
                    @endforelse
                </div>
                <x-public.pagination :current-page="$pagination['current_page'] ?? 1" :total-pages="$pagination['total_pages'] ?? 1" :page-url="$pageUrl" :locale="$locale" class="mt-10" />
            </div>
        </section>
    @elseif ($page->subpageSlug === 'alumni')
        <section class="bg-white py-12 font-hacen">
            <div class="container">
                <form method="GET" action="{{ $studentListUrl }}" class="mb-8 flex flex-wrap items-center gap-4">
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $isAr ? 'ابحث عن اسم الطالب' : 'Search student name' }}" class="h-9 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[220px]">
                    <select name="year" onchange="this.form.submit()" class="h-9 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[150px]">
                        <option value="">{{ $isAr ? 'سنة التخرج' : 'Graduation Year' }}</option>
                        @foreach (($filterOptions['years'] ?? []) as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select name="department" onchange="this.form.submit()" class="h-9 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[150px]">
                        <option value="">{{ $isAr ? 'القسم' : 'Department' }}</option>
                        @foreach (($filterOptions['departments'] ?? []) as $department)
                            <option value="{{ $department }}" @selected(($filters['department'] ?? '') === (string) $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                    <select name="faculty" onchange="this.form.submit()" class="h-9 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[150px]">
                        <option value="">{{ $isAr ? 'الكلية' : 'Faculty' }}</option>
                        @foreach (($filterOptions['faculties'] ?? []) as $facultyOption)
                            <option value="{{ $facultyOption }}" @selected(($filters['faculty'] ?? '') === (string) $facultyOption)>{{ $facultyOption }}</option>
                        @endforeach
                    </select>
                    <select name="academic_phase" onchange="this.form.submit()" class="h-9 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[150px]">
                        <option value="">{{ $isAr ? 'المرحلة الأكاديمية' : 'Academic Phase' }}</option>
                        @foreach (($filterOptions['academicPhases'] ?? []) as $phase)
                            <option value="{{ $phase }}" @selected(($filters['academic_phase'] ?? '') === (string) $phase)>{{ $phase }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-[6px] bg-spu-red px-4 text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-spu-blue">{{ $isAr ? 'بحث' : 'Search' }}</button>
                    <a href="{{ $studentListUrl }}" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-slate-200 px-4 text-[11px] font-bold uppercase tracking-[0.08em] text-spu-blue transition-colors hover:border-spu-blue">{{ $isAr ? 'الكل' : 'All' }}</a>
                </form>

                <p class="mb-5 text-[12px] font-semibold text-slate-500">
                    {{ $isAr ? 'عرض' : 'Showing' }} {{ $pagination['from'] ?? 0 }}-{{ $pagination['to'] ?? 0 }} {{ $isAr ? 'من' : 'of' }} {{ $pagination['total_items'] ?? count($page->items) }}
                </p>

                <div class="grid gap-5" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 24rem), 24rem));">
                    @forelse ($page->items as $item)
                        <article class="overflow-hidden border border-slate-200 bg-white shadow-[0_8px_26px_rgba(15,23,42,0.04)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_44px_rgba(15,23,42,0.1)]">
                            <div class="h-[230px] overflow-hidden bg-slate-100">
                                @if (! empty($item['image']))
                                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover transition-transform duration-500 hover:scale-105">
                                @endif
                            </div>
                            <div class="p-4 text-center">
                                <h2 class="text-[14px] font-bold text-spu-blue">{{ $item['title'] ?? '' }}</h2>
                                <p class="mt-2 text-[11px] font-bold text-spu-red">{{ $isAr ? 'سنة التخرج: ' : 'Graduation Year: ' }}{{ $item['graduationYear'] ?? '' }}</p>
                                <p class="mt-1 text-[11px] font-bold text-spu-red">{{ $isAr ? 'الفصل: ' : 'Semester: ' }}{{ $item['semester'] ?? '' }}</p>
                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $isAr ? 'القسم: ' : 'Department: ' }}{{ $item['department'] ?? $faculty['title'] }}</p>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">{{ $isAr ? 'لا توجد سجلات منشورة حالياً.' : 'No published records are available yet.' }}</p>
                    @endforelse
                </div>

                <x-public.pagination :current-page="$pagination['current_page'] ?? 1" :total-pages="$pagination['total_pages'] ?? 1" :page-url="$pageUrl" :locale="$locale" class="mt-10" />
            </div>
        </section>
    @elseif ($page->subpageSlug === 'valedictorians')
        <section class="honor-page bg-white py-14 font-hacen md:py-18">
            <div class="container">
                <form method="GET" action="{{ $studentListUrl }}" class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $isAr ? 'ابحث عن اسم الطالب' : 'Search student name' }}" class="honor-select w-full min-w-0 sm:w-auto sm:min-w-[220px]">
                        <select name="semester" onchange="this.form.submit()" class="honor-select">
                            <option value="">{{ $isAr ? 'كل الفصول' : 'All Semesters' }}</option>
                            @foreach (($filterOptions['semesters'] ?? []) as $semester)
                                <option value="{{ $semester['key'] }}" @selected(($filters['semester'] ?? '') === (string) $semester['key'])>{{ $semester['label'] }}</option>
                            @endforeach
                        </select>
                        <select name="department" onchange="this.form.submit()" class="honor-select">
                            <option value="">{{ $isAr ? 'القسم' : 'Department' }}</option>
                            @foreach (($filterOptions['departments'] ?? []) as $department)
                                <option value="{{ $department }}" @selected(($filters['department'] ?? '') === (string) $department)>{{ $department }}</option>
                            @endforeach
                        </select>
                        <select name="year" onchange="this.form.submit()" class="honor-select">
                            <option value="">{{ $isAr ? 'السنة' : 'Year' }}</option>
                            @foreach (($filterOptions['years'] ?? []) as $year)
                                <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="honor-filter honor-filter--active">{{ $isAr ? 'بحث' : 'Search' }}</button>
                        <a href="{{ $studentListUrl }}" class="honor-filter">{{ $isAr ? 'الكل' : 'All' }}</a>
                    </div>
                </form>
                <p class="mt-5 text-[12px] font-semibold text-slate-500">
                    {{ $isAr ? 'عرض' : 'Showing' }} {{ $pagination['from'] ?? 0 }}-{{ $pagination['to'] ?? 0 }} {{ $isAr ? 'من' : 'of' }} {{ $pagination['total_items'] ?? count($page->items) }}
                </p>
                <div class="mt-9 grid gap-x-8 gap-y-12" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 24rem), 24rem));">
                    @forelse ($page->items as $item)
                        @php($isMemorial = (bool) ($item['isMemorial'] ?? false))
                        <article class="honor-card" @if ($isMemorial) style="position: relative; border-color: rgba(236, 214, 160, 0.95); background: linear-gradient(180deg, #fffdf7 0%, #ffffff 54%); box-shadow: 0 14px 34px rgba(111, 22, 22, 0.10);" @endif>
                            @if ($isMemorial)
                                <span aria-hidden="true" style="position: absolute; top: 10px; right: 10px; z-index: 3; display: inline-flex; gap: 2px; opacity: 0.95; filter: drop-shadow(0 4px 8px rgba(32, 39, 89, 0.14));">
                                    <svg width="50" height="26" viewBox="0 0 50 26" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                        <circle cx="13" cy="11" r="5" fill="#fffaf0" stroke="#ecd6a0" stroke-width="1"/>
                                        <circle cx="8" cy="15" r="5" fill="#fffaf0" stroke="#ecd6a0" stroke-width="1"/>
                                        <circle cx="18" cy="15" r="5" fill="#fffaf0" stroke="#ecd6a0" stroke-width="1"/>
                                        <circle cx="10" cy="21" r="5" fill="#fffaf0" stroke="#ecd6a0" stroke-width="1"/>
                                        <circle cx="16" cy="21" r="5" fill="#fffaf0" stroke="#ecd6a0" stroke-width="1"/>
                                        <circle cx="13" cy="17" r="2" fill="#d8a928"/>
                                        <path d="M25 17C31 8 39 7 46 11" stroke="#8cae84" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M35 10C36 15 32 17 28 17C29 13 31 11 35 10Z" fill="#8cae84"/>
                                    </svg>
                                </span>
                            @endif
                            <div class="honor-card__media">
                                @if (! empty($item['image']))
                                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover">
                                @endif
                                <div class="honor-card__gpa">
                                    <span>{{ $isAr ? 'المعدل' : 'GPA' }}</span>
                                    <span dir="ltr">{{ $item['gpa'] ?? '' }}</span>
                                </div>
                            </div>
                            <div class="honor-card__body" @if ($isMemorial) style="position: relative; overflow: hidden;" @endif>
                                @if ($isMemorial)
                                    <span aria-hidden="true" style="position: absolute; right: -12px; bottom: -8px; z-index: 1; width: 78%; max-width: 270px; opacity: 0.30; pointer-events: none;">
                                        <svg viewBox="0 0 260 110" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                                            <path d="M8 95C46 78 72 64 101 41C119 27 139 13 169 9" stroke="#24304f" stroke-width="1.35" stroke-linecap="round"/>
                                            <path d="M52 74C43 58 48 47 62 38C67 54 63 67 52 74Z" fill="#fffdf7" stroke="#24304f" stroke-width="1.05"/>
                                            <path d="M71 64C84 53 98 53 111 63C96 72 83 72 71 64Z" fill="#fffdf7" stroke="#24304f" stroke-width="1.05"/>
                                            <path d="M105 39C97 24 103 13 117 5C121 21 116 32 105 39Z" fill="#fffdf7" stroke="#24304f" stroke-width="1.05"/>
                                            <path d="M135 25C150 17 164 19 176 32C159 38 146 35 135 25Z" fill="#fffdf7" stroke="#24304f" stroke-width="1.05"/>
                                            <path d="M178 10C190 4 202 7 211 18C197 22 187 20 178 10Z" fill="#fffdf7" stroke="#24304f" stroke-width="1.05"/>
                                            <path d="M37 82C42 76 48 73 56 73" stroke="#24304f" stroke-width="1" stroke-linecap="round"/>
                                            <path d="M86 55C91 45 98 39 108 37" stroke="#24304f" stroke-width="1" stroke-linecap="round"/>
                                            <path d="M124 31C135 31 143 35 150 43" stroke="#24304f" stroke-width="1" stroke-linecap="round"/>
                                            <path d="M165 12C174 18 179 27 181 39" stroke="#24304f" stroke-width="1" stroke-linecap="round"/>
                                            <g transform="translate(42 51)">
                                                <path d="M18 16C4 12 0 3 4 -8C15 -2 20 6 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C12 2 16 -7 27 -12C31 1 27 10 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C24 2 33 -1 43 5C36 16 27 20 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C31 20 35 29 30 39C18 33 14 25 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C7 25 -2 24 -9 15C2 8 11 8 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <circle cx="18" cy="16" r="2.2" fill="#d8a928"/>
                                            </g>
                                            <g transform="translate(115 15) scale(0.82)">
                                                <path d="M18 16C5 12 1 4 5 -7C15 -1 20 7 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C12 4 15 -6 26 -11C31 1 27 10 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C25 4 34 1 43 8C35 18 26 20 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C29 21 33 29 27 38C17 32 14 24 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <path d="M18 16C7 24 -2 22 -8 13C3 8 11 9 18 16Z" fill="#ffffff" stroke="#24304f" stroke-width="1"/>
                                                <circle cx="18" cy="16" r="2.2" fill="#d8a928"/>
                                            </g>
                                            <path d="M217 17C229 10 240 11 250 21" stroke="#24304f" stroke-width="1" stroke-linecap="round"/>
                                            <path d="M224 17C228 27 223 34 213 38C212 27 216 21 224 17Z" fill="#fffdf7" stroke="#24304f" stroke-width="1"/>
                                        </svg>
                                    </span>
                                @endif
                                <div @if ($isMemorial) style="position: relative; z-index: 2;" @endif>
                                    <h3 class="honor-card__name">{{ $item['title'] ?? '' }}</h3>
                                    <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.08em] text-spu-blue/45">{{ $item['faculty'] ?? $faculty['title'] }}</p>
                                    <div class="mt-6 flex items-center justify-end gap-5">
                                        @if (! empty($item['semester']))
                                            <span class="honor-card__semester">{{ $item['semester'] }}</span>
                                        @endif
                                        <span class="honor-card__rank">{{ $isAr ? 'قائمة الشرف' : 'Honor List' }}</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">{{ $isAr ? 'لا توجد سجلات منشورة حالياً.' : 'No published records are available yet.' }}</p>
                    @endforelse
                </div>

                <x-public.pagination :current-page="$pagination['current_page'] ?? 1" :total-pages="$pagination['total_pages'] ?? 1" :page-url="$pageUrl" :locale="$locale" class="mt-10" />
                @if (is_string($subpage['payload']['quote'] ?? null) && trim($subpage['payload']['quote']) !== '')
                    <blockquote class="mx-auto mt-14 max-w-3xl border-y border-slate-100 px-6 py-8 text-center text-xl font-bold leading-9 text-spu-blue">
                        {{ $subpage['payload']['quote'] }}
                    </blockquote>
                @endif
            </div>
        </section>
    @elseif ($page->subpageSlug === 'members')
        <section class="bg-white py-14 font-hacen md:py-18">
            <div class="container">
                <p class="mb-8 text-center text-sm font-bold text-slate-600" role="status">
                    {{ $isAr ? 'عدد الأعضاء: '.count($page->items) : count($page->items).' members' }}
                </p>

                @if ($page->items !== [])
                    <div class="staff-grid">
                        @foreach ($page->items as $member)
                            <a href="{{ $member['profileUrl'] ?? '#' }}" class="staff-card block transition hover:-translate-y-1 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                                <div class="staff-card-media">
                                    @if (! empty($member['image']))
                                        <img src="{{ $member['image'] }}" alt="{{ $member['name'] ?? '' }}" loading="lazy">
                                    @else
                                        <div class="flex h-full items-center justify-center bg-slate-100">
                                            <img src="/images/icon-user-graduate-outline.svg" alt="" class="h-16 w-16 opacity-30" aria-hidden="true">
                                        </div>
                                    @endif
                                </div>
                                <div class="staff-card-body">
                                    <h2 class="staff-card-name">{{ $member['name'] ?? '' }}</h2>
                                    @if (! empty($member['position']))
                                        <p class="staff-card-role">{{ $member['position'] }}</p>
                                    @endif
                                    @if (! empty($member['department']))
                                        <p class="mt-3 text-xs font-bold text-slate-500">{{ $member['department'] }}</p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center">
                        <p class="font-bold text-slate-700">{{ $isAr ? 'لا يوجد أعضاء هيئة أكاديمية منشورون حالياً.' : 'No published faculty members are available yet.' }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endif
@endsection
