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
        $selectedLab = $page->subpageSlug === 'labs' ? collect($page->items)->firstWhere('slug', request('lab')) : null;
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
            'projects', 'alumni', 'valedictorians' => null,
            default => $subpage['summary'] ?? null,
        };
        $alumniYears = collect($page->items)->pluck('graduationYear')->filter()->unique()->values();
        $alumniDepartments = collect($page->items)->pluck('department')->filter()->unique()->values();
        $training = $page->subpageSlug === 'training' ? ($subpage['payload'] ?? []) : [];
        $localized = fn (array $item, string $key): string => (string) ($item[$key] ?? $item[$key.ucfirst($locale)] ?? $item[$key.'En'] ?? $item[$key.'Ar'] ?? '');
    @endphp

    @if ($page->subpageSlug === 'training')
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

    @if ($page->subpageSlug === 'overview')
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
                        <div class="mt-8 grid gap-3 sm:grid-cols-2">
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
                            <p class="mt-8 text-sm font-bold text-spu-blue">{{ $isAr ? ($dean['nameAr'] ?? '') : ($dean['nameEn'] ?? '') }}</p>
                            <div class="mt-6 space-y-5 text-[14px] leading-8 text-slate-600">
                                <p>{{ $isAr ? ($dean['messageAr'] ?? '') : ($dean['messageEn'] ?? '') }}</p>
                                <p>{{ $firstSection['body'] ?? '' }}</p>
                            </div>
                            <div class="mt-8 border-t border-slate-100 pt-5">
                                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-spu-blue">{{ $isAr ? ($dean['roleAr'] ?? 'عميد الكلية') : ($dean['roleEn'] ?? 'Faculty Dean') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="bg-section py-16 font-hacen lg:py-20">
            <div class="container">
                <div class="text-center">
                    <h2 class="text-[30px] font-bold leading-tight text-spu-blue md:text-[38px]">{{ $isAr ? 'مسارات الكلية' : 'Faculty Pathways' }}</h2>
                    <div class="mx-auto mt-4 h-[2px] w-72 max-w-full rounded-full" style="background-color: {{ $accent }}"></div>
                    <p class="mx-auto mt-7 max-w-[760px] text-[17px] leading-8 text-slate-600">{{ $isAr ? 'استكشف الصفحات الأكاديمية والخدمية المرتبطة بهذه الكلية.' : 'Explore the academic and service pages connected to this faculty.' }}</p>
                </div>
                <div class="mx-auto mt-12 grid max-w-[980px] gap-5 md:grid-cols-3">
                    @foreach ($page->navigation as $item)
                        <a href="{{ $item->url }}" class="group rounded-[8px] bg-white p-7 text-center shadow-[0_14px_36px_rgba(9,17,68,0.06)] transition-all hover:-translate-y-1">
                            <p class="text-[13px] font-bold uppercase tracking-[0.14em]" style="color: {{ $accent }}">{{ $isAr ? 'استكشف' : 'Explore' }}</p>
                            <h3 class="mt-4 text-xl font-bold text-spu-blue">{{ $item->label }}</h3>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif ($page->subpageSlug === 'departments')
        <section class="bg-white py-16 font-hacen lg:py-24">
            <div class="container">
                <div class="mx-auto grid max-w-[1080px] gap-4 sm:grid-cols-3">
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
                            <a href="/{{ $locale }}/admissions" class="inline-flex h-11 items-center justify-center rounded-[6px] px-5 text-sm font-bold text-white transition-all hover:-translate-y-0.5" style="background-color: {{ $accent }}">{{ $isAr ? 'القبول' : 'Admissions' }}</a>
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
                            <img src="{{ $selectedLab['image'] ?? '/images/dental-clin-lab.jpg' }}" alt="{{ $selectedLab['title'] ?? '' }}" class="h-full w-full object-cover">
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
                        <a href="/{{ $locale }}/facilities/{{ $page->facultySlug }}/labs" class="mt-12 inline-flex items-center gap-2 text-sm font-bold transition-colors hover:text-spu-red" style="color: {{ $accent }}">
                            <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-4 w-4 rtl:rotate-180" aria-hidden="true">
                            <span>{{ $isAr ? 'العودة إلى المخابر' : 'Back to Labs' }}</span>
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                        @forelse ($page->items as $item)
                            <a href="/{{ $locale }}/facilities/{{ $page->facultySlug }}/labs?lab={{ $item['slug'] ?? '' }}" class="group overflow-hidden rounded-[10px] bg-white shadow-[0_4px_20px_rgba(0,0,0,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(0,0,0,0.15)]">
                                <div class="relative h-[180px] overflow-hidden">
                                    <img src="{{ $item['image'] ?? '/images/dental-clin-lab.jpg' }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
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
                @endif
            </div>
        </section>
    @elseif ($page->subpageSlug === 'projects')
        <section class="bg-white py-16 font-hacen md:py-20">
            <div class="container">
                <div class="grid grid-cols-1 gap-7 md:grid-cols-2 xl:grid-cols-3">
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
                @if (count($page->items) > 0)
                    <div class="mt-10 flex items-center justify-center gap-2">
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-[4px] border border-slate-200 text-spu-blue transition hover:border-spu-blue" aria-label="{{ $isAr ? 'الصفحة السابقة' : 'Previous page' }}">
                            <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                        </button>
                        <button type="button" class="h-8 min-w-8 rounded-[4px] border border-spu-red bg-spu-red px-3 text-[12px] font-bold text-white">1</button>
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-[4px] border border-slate-200 text-spu-blue transition hover:border-spu-blue" aria-label="{{ $isAr ? 'الصفحة التالية' : 'Next page' }}">
                            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                        </button>
                    </div>
                @endif
            </div>
        </section>
    @elseif ($page->subpageSlug === 'alumni')
        <section class="bg-white py-12 font-hacen">
            <div class="container">
                <div class="mb-8 flex flex-wrap items-center gap-4">
                    <a href="/{{ $locale }}/facilities/{{ $page->facultySlug }}/alumni" class="inline-flex h-9 items-center justify-center rounded-[6px] bg-spu-red px-4 text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-spu-blue">{{ $isAr ? 'الكل' : 'All' }}</a>
                    <select class="h-9 min-w-[150px] rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue">
                        <option>{{ $isAr ? 'سنة التخرج' : 'Graduation Year' }}</option>
                        @foreach ($alumniYears as $year)
                            <option>{{ $year }}</option>
                        @endforeach
                    </select>
                    <select class="h-9 min-w-[150px] rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue">
                        <option>{{ $isAr ? 'القسم' : 'Department' }}</option>
                        @foreach ($alumniDepartments as $department)
                            <option>{{ $department }}</option>
                        @endforeach
                    </select>
                    <select class="h-9 min-w-[150px] rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue">
                        <option>{{ $isAr ? 'الكلية' : 'Faculty' }}</option>
                        <option>{{ $faculty['title'] }}</option>
                    </select>
                    <select class="h-9 min-w-[150px] rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue">
                        <option>{{ $isAr ? 'المرحلة الأكاديمية' : 'Academic Phase' }}</option>
                        <option>{{ $isAr ? 'خريج' : 'Graduate' }}</option>
                    </select>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse ($page->items as $item)
                        <article class="overflow-hidden border border-slate-200 bg-white shadow-[0_8px_26px_rgba(15,23,42,0.04)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_44px_rgba(15,23,42,0.1)]">
                            <div class="h-[230px] overflow-hidden bg-slate-100">
                                <img src="{{ $item['image'] ?? '/images/unkown.jpeg' }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover transition-transform duration-500 hover:scale-105">
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
            </div>
        </section>
    @elseif ($page->subpageSlug === 'valedictorians')
        <section class="honor-page bg-white py-14 font-hacen md:py-18">
            <div class="container">
                <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="honor-filter honor-filter--active">{{ $isAr ? 'كل الفصول' : 'All Semesters' }}</button>
                        <button type="button" class="honor-filter">{{ $isAr ? 'الفصل الأول' : 'First Semester' }}</button>
                        <button type="button" class="honor-filter">{{ $isAr ? 'الفصل الثاني' : 'Second Semester' }}</button>
                        <select class="honor-select">
                            <option>{{ $isAr ? 'القسم' : 'Department' }}</option>
                            @foreach (collect($page->items)->pluck('department')->filter()->unique() as $department)
                                <option>{{ $department }}</option>
                            @endforeach
                        </select>
                        <select class="honor-select">
                            <option>{{ $page->items[0]['academicYear'] ?? '2025-2026' }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-9 grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($page->items as $item)
                        <article class="honor-card">
                            <div class="honor-card__media">
                                <img src="{{ $item['image'] ?? '/images/unkown.jpeg' }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover">
                                <div class="honor-card__gpa">
                                    <span>{{ $isAr ? 'المعدل' : 'GPA' }}</span>
                                    <span dir="ltr">{{ $item['gpa'] ?? '' }}</span>
                                </div>
                            </div>
                            <div class="honor-card__body">
                                <h3 class="honor-card__name">{{ $item['title'] ?? '' }}</h3>
                                <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.08em] text-spu-blue/45">{{ $item['faculty'] ?? $faculty['title'] }}</p>
                                <div class="mt-6 flex items-center justify-between gap-5">
                                    <span class="honor-card__semester">{{ $item['semester'] ?? ($isAr ? 'الفصل الثاني' : 'Second Semester') }}</span>
                                    <span class="honor-card__rank">{{ $isAr ? 'قائمة الشرف' : 'Honor List' }}</span>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">{{ $isAr ? 'لا توجد سجلات منشورة حالياً.' : 'No published records are available yet.' }}</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
@endsection
