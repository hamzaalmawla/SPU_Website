@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php
        $profile = $page->data['profile'] ?? $page->item;
        $isAr = $locale === 'ar';
        $name = $profile['name'] ?? '';
        $role = $profile['role'] ?? '';
        $faculty = $profile['faculty']['name'] ?? '';
        $department = $faculty !== '' ? $faculty : ($profile['department'] ?? '');
        $description = $profile['description'] ?? '';
        $biography = $profile['biography'] ?? [];
        $education = $profile['education'] ?? [];
        $courses = $profile['courses'] ?? [];
        $expertise = $profile['expertise'] ?? [];
        $stats = $profile['researchStats'] ?? [];
        $publications = $profile['publications'] ?? [];
        $office = $profile['office'] ?? [];
        $officeAddress = $office['fullAddress'] ?? '';
        $image = $profile['image'] ?? '/images/uni-main-place.JPG';
        $email = $profile['email'] ?? '';
        $scholarUrl = $profile['scholarUrl'] ?? '';
        $orcidUrl = $profile['orcidUrl'] ?? '';
    @endphp

    <section class="font-hacen" dir="{{ $direction }}">
        <div class="relative h-[450px] overflow-hidden">
            <div class="absolute inset-0">
                <img src="/images/DSC_1596.JPG" alt="" class="h-full w-full object-cover object-center">
                <div class="absolute inset-0 bg-gradient-to-b from-[rgba(32,39,89,0.4)] to-[rgba(32,39,89,0.6)]"></div>
            </div>
        </div>

        <div class="container">
            <div class="relative -mt-[6.5rem] flex flex-col gap-6 border-b border-spu-blue/10 md:flex-row md:items-center md:gap-8">
                <div class="shrink-0">
                    <div class="h-[160px] w-[160px] overflow-hidden rounded-full border-[6px] border-white bg-[#f6f8fc] shadow-[0_8px_32px_rgba(32,39,89,0.2)] md:h-[260px] md:w-[260px]">
                        <img src="{{ $image }}" alt="{{ $name }}" class="h-full w-full object-cover">
                    </div>
                </div>

                <div class="flex-1">
                    <h1 class="text-[1.875rem] font-bold leading-tight text-white/90">{{ $name }}</h1>

                    <div class="my-1 flex flex-wrap items-center gap-2">
                        @if ($role !== '')
                            <span class="inline-flex items-center rounded-full bg-spu-red px-3 py-1 text-xs font-bold uppercase tracking-[0.05em] text-white">{{ $role }}</span>
                        @endif
                        @if ($department !== '')
                            <span class="text-spu-blue/30">•</span>
                            <span class="text-sm font-semibold text-spu-blue">{{ $department }}</span>
                        @endif
                    </div>

                    @if ($description !== '')
                        <p class="mb-3 text-base text-spu-blue/70">{{ $description }}</p>
                    @endif

                    @if ($officeAddress !== '')
                        <div class="mb-4">
                            <div class="flex items-center gap-2 text-sm text-spu-blue/60">
                                <img src="/images/icon-map-outline.svg" alt="" class="h-4 w-4 opacity-60" aria-hidden="true">
                                <span>{{ $officeAddress }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-3">
                        @if ($email !== '')
                            <a href="mailto:{{ $email }}" class="flex h-10 w-10 items-center justify-center rounded-[10px] bg-spu-blue/[0.06] text-spu-blue transition-all hover:-translate-y-0.5 hover:bg-spu-blue hover:text-white" title="Email">
                                <img src="/images/icon-envelope-outline.svg" alt="Email" class="h-4 w-4">
                            </a>
                        @endif

                        @if ($scholarUrl !== '')
                            <a href="{{ $scholarUrl }}" target="_blank" rel="noopener" class="flex h-10 items-center justify-center gap-2 rounded-[10px] bg-[#4285f4] px-4 text-white transition-all hover:-translate-y-0.5 hover:bg-[#3367d6]" title="Google Scholar">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 24a7 7 0 1 1 0-14 7 7 0 0 1 0 14Zm0-24L0 9.5l4.838 3.94A8 8 0 0 1 12 9a8 8 0 0 1 7.162 4.44L24 9.5 12 0Z"/></svg>
                                <span class="text-xs font-semibold">Google Scholar</span>
                            </a>
                        @endif

                        @if ($orcidUrl !== '')
                            <a href="{{ $orcidUrl }}" target="_blank" rel="noopener" class="flex h-10 items-center justify-center gap-2 rounded-[10px] bg-[#A6CE39] px-4 text-white transition-all hover:-translate-y-0.5 hover:bg-[#8ba82e]" title="ORCID">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.372 0 0 5.372 0 12s5.372 12 12 12 12-5.372 12-12S18.628 0 12 0zM7.369 4.378c.525 0 .947.431.947.947s-.422.947-.947.947a.95.95 0 0 1-.947-.947c0-.525.422-.947.947-.947zm-.722 3.038h1.444v10.041H6.647V7.416zm3.562 0h3.9c3.712 0 5.344 2.653 5.344 5.025 0 2.578-2.016 5.016-5.325 5.016h-3.919V7.416zm1.444 1.303v7.444h2.297c2.859 0 3.722-2.4 3.722-3.722 0-1.909-1.581-3.722-4.097-3.722h-1.922z"/></svg>
                                <span class="text-xs font-semibold">ORCID</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid gap-8 pb-16 pt-8 lg:grid-cols-[320px_1fr]">
                <aside class="flex flex-col gap-6">
                    <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                        <h3 class="mb-5 border-b border-spu-blue/[0.08] pb-3 text-base font-bold text-spu-blue">{{ $isAr ? 'معلومات التواصل' : 'Contact Information' }}</h3>

                        <div class="mb-5 flex flex-col gap-4">
                            @if ($email !== '')
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-spu-blue/[0.06]">
                                        <img src="/images/icon-envelope-outline.svg" alt="" class="h-4 w-4">
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs text-spu-blue/50">Email</span>
                                        <a href="mailto:{{ $email }}" class="break-all text-sm font-semibold text-spu-red hover:text-spu-blue hover:underline">{{ $email }}</a>
                                    </div>
                                </div>
                            @endif

                            @if ($officeAddress !== '' || $department !== '')
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-spu-blue/[0.06]">
                                        <img src="/images/icon-map-outline.svg" alt="" class="h-4 w-4">
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs text-spu-blue/50">{{ $isAr ? 'الموقع' : 'Location' }}</span>
                                        <span class="text-sm font-semibold text-spu-blue">{{ $officeAddress !== '' ? $officeAddress : $department }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($email !== '')
                            <a href="mailto:{{ $email }}" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-spu-red py-3.5 text-sm font-bold text-white transition-all hover:-translate-y-0.5 hover:bg-spu-blue hover:shadow-[0_4px_16px_rgba(111,22,22,0.3)]">
                                <img src="/images/icon-envelope-outline.svg" alt="" class="h-4 w-4 brightness-0 invert">
                                <span>{{ $isAr ? 'تواصل عبر البريد' : 'Contact via Email' }}</span>
                            </a>
                        @endif
                    </div>

                    @if (count($expertise) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                            <h3 class="mb-5 border-b border-spu-blue/[0.08] pb-3 text-base font-bold text-spu-blue">{{ $isAr ? 'مجالات الخبرة' : 'Expertise' }}</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($expertise as $item)
                                    <span class="inline-flex items-center rounded-full bg-spu-blue/5 px-3 py-1.5 text-xs font-semibold text-spu-blue">{{ $item }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($stats['publications']))
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                            <h3 class="mb-5 border-b border-spu-blue/[0.08] pb-3 text-base font-bold text-spu-blue">{{ $isAr ? 'إحصائيات البحث' : 'Research Statistics' }}</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="rounded-xl bg-spu-blue/[0.03] p-4 text-center">
                                    <span class="block text-2xl font-extrabold leading-none text-spu-blue">{{ $stats['publications'] }}</span>
                                    <span class="mt-1 block text-xs text-spu-blue/60">{{ $isAr ? 'منشورات' : 'Publications' }}</span>
                                </div>
                                @if (! empty($stats['citations']))
                                    <div class="rounded-xl bg-spu-blue/[0.03] p-4 text-center">
                                        <span class="block text-2xl font-extrabold leading-none text-spu-blue">{{ $stats['citations'] }}</span>
                                        <span class="mt-1 block text-xs text-spu-blue/60">{{ $isAr ? 'استشهادات' : 'Citations' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if (count($courses) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">{{ $isAr ? 'المساقات الدراسية' : 'Courses Taught' }}</h2>
                            <div class="grid grid-cols-1 gap-4">
                                @foreach ($courses as $course)
                                    @php
                                        $facultySlug = $profile['faculty']['slug'] ?? '';
                                        $courseHref = in_array($facultySlug, ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'business-administration', 'petroleum', 'building-construction-engineering'], true)
                                            ? '/'.$locale.'/facilities/'.$facultySlug.'/study-plan/course?department='.($course['departmentId'] ?? '').'&course='.($course['id'] ?? '')
                                            : '#';
                                    @endphp
                                    <a href="{{ $courseHref }}" class="group flex items-center gap-4 rounded-xl border border-spu-blue/[0.06] bg-spu-blue/[0.03] p-5 transition-all hover:-translate-y-0.5 hover:border-spu-blue/[0.15] hover:bg-white hover:shadow-[0_8px_24px_rgba(32,39,89,0.1)] {{ $courseHref === '#' ? 'pointer-events-none opacity-70' : '' }}">
                                        <div class="min-w-0 flex-1">
                                            <span class="block text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $course['code'] ?? '' }}</span>
                                            <h4 class="mt-0.5 truncate text-[0.9375rem] font-bold text-spu-blue">{{ $course['name'] ?? '' }}</h4>
                                        </div>
                                        <div class="text-spu-blue/30 transition-all group-hover:text-spu-red">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>

                <main class="flex flex-col gap-10">
                    @if (count($biography) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">{{ $isAr ? 'نبذة مهنية' : 'Professional Biography' }}</h2>
                            <div class="space-y-4 text-[0.9375rem] leading-[1.8] text-spu-blue/75">
                                @foreach ($biography as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (count($education) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">{{ $isAr ? 'الشهادات العلمية' : 'Education' }}</h2>
                            <div class="flex flex-col gap-5">
                                @foreach ($education as $edu)
                                    <div class="relative flex gap-4">
                                        <div class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-spu-red"></div>
                                        <div class="flex-1">
                                            <h4 class="mb-1 text-[0.9375rem] font-bold text-spu-blue">{{ $edu['degree'] ?? '' }}</h4>
                                            @if (! empty($edu['institution']))
                                                <p class="mb-1 text-sm text-spu-blue/60">{{ $edu['institution'] }}</p>
                                            @endif
                                            @if (! empty($edu['year']))
                                                <span class="inline-flex items-center rounded bg-spu-blue/[0.06] px-2 py-0.5 text-xs font-semibold text-spu-blue">{{ $edu['year'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (count($publications) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">
                                <span>{{ $isAr ? 'الأبحاث المنشورة' : 'Published Researches' }}</span>
                                @if ($scholarUrl !== '')
                                    <a href="{{ $scholarUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-semibold text-spu-red transition-colors hover:text-spu-blue">
                                        <span>{{ $isAr ? 'عرض الكل على Google Scholar' : 'View all on Google Scholar' }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                @endif
                            </h2>
                            <div class="flex flex-col gap-5">
                                @foreach ($publications as $publication)
                                    @php
                                        $publicationHref = rtrim($publication['links']['local'] ?? '#', '/');

                                        if ($publicationHref !== '#' && ! str_starts_with($publicationHref, '/'.$locale.'/') && ! str_starts_with($publicationHref, 'http')) {
                                            $publicationHref = '/'.$locale.$publicationHref;
                                        }
                                    @endphp
                                    <div class="group rounded-xl border border-spu-blue/[0.06] bg-spu-blue/[0.03] p-5 transition-all hover:-translate-y-0.5 hover:border-spu-blue/[0.15] hover:bg-white hover:shadow-[0_8px_24px_rgba(32,39,89,0.1)]">
                                        <a href="{{ $publicationHref }}" class="mb-3 block">
                                            <div class="mb-2 flex items-start justify-between gap-4">
                                                <h4 class="flex-1 text-[0.9375rem] font-semibold leading-[1.5] text-spu-blue transition-colors group-hover:text-spu-red">{{ $publication['title'] ?? '' }}</h4>
                                                @if (! empty($publication['year']))
                                                    <span class="shrink-0 rounded bg-spu-red px-2 py-1 text-xs font-bold text-white">{{ $publication['year'] }}</span>
                                                @endif
                                            </div>
                                            @if (! empty($publication['journal']))
                                                <p class="text-sm italic text-spu-blue/60">{{ $publication['journal'] }}</p>
                                            @endif
                                        </a>
                                        <div class="flex items-center gap-4">
                                            <a href="{{ $publicationHref }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-spu-blue transition-colors hover:text-spu-red">
                                                <span>{{ $isAr ? 'عرض التفاصيل' : 'View Details' }}</span>
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                            @if (! empty($publication['links']['scholar']) || $scholarUrl !== '')
                                                <a href="{{ $publication['links']['scholar'] ?? $scholarUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#4285f4] transition-colors hover:text-[#3367d6]">
                                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 24a7 7 0 1 1 0-14 7 7 0 0 1 0 14Zm0-24L0 9.5l4.838 3.94A8 8 0 0 1 12 9a8 8 0 0 1 7.162 4.44L24 9.5 12 0Z"/></svg>
                                                    <span>Google Scholar</span>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </main>
            </div>
        </div>
    </section>
@endsection
