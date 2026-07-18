@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $sourceType = $profile->sourceType;
    @endphp

    <section class="font-hacen" dir="{{ $direction }}">
        <div class="relative h-[450px] overflow-hidden">
            <div class="absolute inset-0">
                <img src="/images/about/hero-img.jpg" alt="" class="h-full w-full object-cover object-center" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-b from-[rgba(32,39,89,0.4)] to-[rgba(32,39,89,0.6)]"></div>
            </div>
        </div>

        <div class="container">
            <div class="relative -mt-[6.5rem] flex flex-col gap-6 border-b border-spu-blue/10 md:flex-row md:items-center md:gap-8">
                <div class="shrink-0">
                    <div class="h-[160px] w-[160px] overflow-hidden rounded-full border-[6px] border-white bg-[#f6f8fc] shadow-[0_8px_32px_rgba(32,39,89,0.2)] md:h-[260px] md:w-[260px]">
                        @if ($profile->image)
                            <img src="{{ $profile->image }}" alt="{{ $profile->name }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-slate-100"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-20 w-20 opacity-30" aria-hidden="true"></div>
                        @endif
                    </div>
                </div>

                <div class="flex-1">
                    <h1 class="text-[1.875rem] mb-4 font-bold leading-tight text-white/90">{{ $profile->name }}</h1>

                    <div class="my-1 flex flex-wrap items-center gap-2">
                        @if ($profile->position !== null)
                            <span class="inline-flex items-center rounded-full bg-spu-red px-3 py-1 text-xs font-bold uppercase tracking-[0.05em] text-white">{{ $profile->position }}</span>
                        @endif
                        @if ($profile->title !== null && $profile->title !== $profile->position)
                            <span class="text-spu-blue/30">•</span>
                            <span class="text-sm font-semibold text-spu-blue">{{ $profile->title }}</span>
                        @endif
                        @if ($profile->facultyName !== null)
                            <span class="text-spu-blue/30">•</span>
                            <span class="text-sm font-semibold text-spu-blue">{{ $profile->facultyName }}</span>
                        @endif
                        @if ($profile->departmentName !== null)
                            <span class="text-spu-blue/30">•</span>
                            <span class="text-sm font-semibold text-spu-blue">{{ $profile->departmentName }}</span>
                        @endif
                    </div>

                    @if ($profile->bio !== null && $profile->bio !== '')
                        <p class="mb-3 text-base text-spu-blue/70">{{ Str::limit($profile->bio, 200) }}</p>
                    @endif

                    @if ($profile->officeLocation !== null)
                        <div class="mb-4">
                            <div class="flex items-center gap-2 text-sm text-spu-blue/60">
                                <img src="/images/icon-map-outline.svg" alt="" class="h-4 w-4 opacity-60" aria-hidden="true">
                                <span>{{ $profile->officeLocation }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-3">
                        @if ($profile->email !== null)
                            <a href="mailto:{{ $profile->email }}" class="flex h-10 w-10 items-center justify-center rounded-[10px] bg-spu-blue/[0.06] text-spu-blue transition-all hover:-translate-y-0.5 hover:bg-spu-blue hover:text-white" aria-label="{{ $isAr ? 'البريد الإلكتروني' : 'Email' }}">
                                <img src="/images/icon-envelope-outline.svg" alt="" class="h-4 w-4" aria-hidden="true">
                            </a>
                        @endif

                        @if (is_array($profile->socialLinks))
                            @if (!empty($profile->socialLinks['linkedin']))
                                <a href="{{ $profile->socialLinks['linkedin'] }}" target="_blank" rel="noopener" class="flex h-10 items-center justify-center gap-2 rounded-[10px] bg-[#0077B5] px-4 text-white transition-all hover:-translate-y-0.5 hover:bg-[#006699]" title="LinkedIn">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    <span class="text-xs font-semibold">LinkedIn</span>
                                </a>
                            @endif
                            @if (!empty($profile->socialLinks['scholar']))
                                <a href="{{ $profile->socialLinks['scholar'] }}" target="_blank" rel="noopener" class="flex h-10 items-center justify-center gap-2 rounded-[10px] bg-[#4285f4] px-4 text-white transition-all hover:-translate-y-0.5 hover:bg-[#3367d6]" title="Google Scholar">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 24a7 7 0 1 1 0-14 7 7 0 0 1 0 14Zm0-24L0 9.5l4.838 3.94A8 8 0 0 1 12 9a8 8 0 0 1 7.162 4.44L24 9.5 12 0Z"/></svg>
                                    <span class="text-xs font-semibold">Google Scholar</span>
                                </a>
                            @endif
                            @if (!empty($profile->socialLinks['orcid']))
                                <a href="{{ $profile->socialLinks['orcid'] }}" target="_blank" rel="noopener" class="flex h-10 items-center justify-center gap-2 rounded-[10px] bg-[#A6CE39] px-4 text-white transition-all hover:-translate-y-0.5 hover:bg-[#8ba82e]" title="ORCID">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.372 0 0 5.372 0 12s5.372 12 12 12 12-5.372 12-12S18.628 0 12 0zM7.369 4.378c.525 0 .947.431.947.947s-.422.947-.947.947a.95.95 0 0 1-.947-.947c0-.525.422-.947.947-.947zm-.722 3.038h1.444v10.041H6.647V7.416zm3.562 0h3.9c3.712 0 5.344 2.653 5.344 5.025 0 2.578-2.016 5.016-5.325 5.016h-3.919V7.416zm1.444 1.303v7.444h2.297c2.859 0 3.722-2.4 3.722-3.722 0-1.909-1.581-3.722-4.097-3.722h-1.922z"/></svg>
                                    <span class="text-xs font-semibold">ORCID</span>
                                </a>
                            @endif
                            @if (!empty($profile->socialLinks['researchgate']))
                                <a href="{{ $profile->socialLinks['researchgate'] }}" target="_blank" rel="noopener" class="flex h-10 items-center justify-center gap-2 rounded-[10px] bg-[#00CCBB] px-4 text-white transition-all hover:-translate-y-0.5 hover:bg-[#009999]" title="ResearchGate">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19.586 0c-1.31 0-2.938.95-4.27 2.392C14.056.866 12.44 0 11.13 0c-1.31 0-2.938.95-4.27 2.392C5.526.866 3.91 0 2.6 0H0v24h2.6c1.31 0 2.938-.95 4.27-2.392C8.204 23.134 9.82 24 11.13 24c1.31 0 2.938-.95 4.27-2.392C16.764 23.134 18.38 24 19.69 24H24V0h-4.414zM11.13 19.2c-1.31 0-2.938-.95-4.27-2.392C5.526 15.334 3.91 14.4 2.6 14.4H1.2v-4.8h1.4c1.31 0 2.938-.95 4.27-2.392C8.204 5.734 9.82 4.8 11.13 4.8c1.31 0 2.938.95 4.27 2.392C16.764 9.666 18.38 10.6 19.69 10.6h1.4v4.8h-1.4c-1.31 0-2.938.95-4.27 2.392C14.056 18.266 12.44 19.2 11.13 19.2z"/></svg>
                                    <span class="text-xs font-semibold">ResearchGate</span>
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid gap-8 pb-16 pt-8 lg:grid-cols-[320px_1fr]">
                <aside class="flex flex-col gap-6">
                    <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                        <h3 class="mb-5 border-b border-spu-blue/[0.08] pb-3 text-base font-bold text-spu-blue">{{ $isAr ? 'معلومات التواصل' : 'Contact Information' }}</h3>

                        <div class="mb-5 flex flex-col gap-4">
                            @if ($profile->email !== null)
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-spu-blue/[0.06]">
                                        <img src="/images/icon-envelope-outline.svg" alt="" class="h-4 w-4">
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs text-spu-blue/50">{{ $isAr ? 'البريد الإلكتروني' : 'Email' }}</span>
                                        <a href="mailto:{{ $profile->email }}" class="break-all text-sm font-semibold text-spu-red hover:text-spu-blue hover:underline">{{ $profile->email }}</a>
                                    </div>
                                </div>
                            @endif

                            @if ($profile->phone !== null)
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-spu-blue/[0.06]">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs text-spu-blue/50">{{ $isAr ? 'الهاتف' : 'Phone' }}</span>
                                        <a href="tel:{{ $profile->phone }}" class="text-sm font-semibold text-spu-red hover:text-spu-blue hover:underline">{{ $profile->phone }}</a>
                                    </div>
                                </div>
                            @endif

                            @if ($profile->officeLocation !== null || $profile->facultyName !== null)
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-spu-blue/[0.06]">
                                        <img src="/images/icon-map-outline.svg" alt="" class="h-4 w-4">
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs text-spu-blue/50">{{ $isAr ? 'الموقع' : 'Location' }}</span>
                                        <span class="text-sm font-semibold text-spu-blue">{{ $profile->officeLocation ?? $profile->facultyName }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($profile->email !== null)
                            <a href="mailto:{{ $profile->email }}" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-spu-red py-3.5 text-sm font-bold text-white transition-all hover:-translate-y-0.5 hover:bg-spu-blue hover:shadow-[0_4px_16px_rgba(111,22,22,0.3)]">
                                <img src="/images/icon-envelope-outline.svg" alt="" class="h-4 w-4 brightness-0 invert">
                                <span>{{ $isAr ? 'تواصل عبر البريد' : 'Contact via Email' }}</span>
                            </a>
                        @endif
                    </div>

                    @if (is_array($profile->specializations) && count($profile->specializations) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                            <h3 class="mb-5 border-b border-spu-blue/[0.08] pb-3 text-base font-bold text-spu-blue">{{ $isAr ? 'التخصصات' : 'Specializations' }}</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($profile->specializations as $item)
                                    <span class="inline-flex items-center rounded-full bg-spu-blue/5 px-3 py-1.5 text-xs font-semibold text-spu-blue">{{ $item }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($sourceType === 'faculty_member' && count($profile->publications) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                            <h3 class="mb-5 border-b border-spu-blue/[0.08] pb-3 text-base font-bold text-spu-blue">{{ $isAr ? 'إحصائيات البحث' : 'Research Statistics' }}</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <div class="rounded-xl bg-spu-blue/[0.03] p-4 text-center">
                                    <span class="block text-2xl font-extrabold leading-none text-spu-blue">{{ count($profile->publications) }}</span>
                                    <span class="mt-1 block text-xs text-spu-blue/60">{{ $isAr ? 'منشورات' : 'Publications' }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($profile->cvUrl !== null)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)]">
                            <h3 class="mb-5 border-b border-spu-blue/[0.08] pb-3 text-base font-bold text-spu-blue">{{ $isAr ? 'السيرة الذاتية' : 'Curriculum Vitae' }}</h3>
                            <a href="{{ $profile->cvUrl }}" target="_blank" rel="noopener" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-spu-blue py-3.5 text-sm font-bold text-white transition-all hover:-translate-y-0.5 hover:bg-spu-red hover:shadow-[0_4px_16px_rgba(32,39,89,0.3)]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>{{ $isAr ? 'تحميل السيرة الذاتية' : 'Download CV' }}</span>
                            </a>
                        </div>
                    @endif
                </aside>

                <div class="flex flex-col gap-10">
                    @if ($profile->bio !== null && $profile->bio !== '')
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">{{ $isAr ? 'نبذة مهنية' : 'Professional Biography' }}</h2>
                            <div class="space-y-4 text-[0.9375rem] leading-[1.8] text-spu-blue/75">
                                <p>{{ $profile->bio }}</p>
                            </div>
                        </div>
                    @endif

                    @if ($profile->quote !== null && $profile->quote !== '')
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">{{ $isAr ? 'مقولته' : 'Quote' }}</h2>
                            <blockquote class="border-s-4 border-spu-red ps-6 text-[0.9375rem] italic leading-[1.8] text-spu-blue/75">
                                {{ $profile->quote }}
                            </blockquote>
                        </div>
                    @endif

                    @if (count($profile->educations) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">{{ $isAr ? 'الشهادات العلمية' : 'Education' }}</h2>
                            <div class="flex flex-col gap-5">
                                @foreach ($profile->educations as $edu)
                                    <div class="relative flex gap-4">
                                        <div class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-spu-red"></div>
                                        <div class="flex-1">
                                            <h4 class="mb-1 text-[0.9375rem] font-bold text-spu-blue">{{ $edu->degree }}</h4>
                                            @if ($edu->institution !== null)
                                                <p class="mb-1 text-sm text-spu-blue/60">{{ $edu->institution }}</p>
                                            @endif
                                            @if ($edu->fieldOfStudy !== null)
                                                <p class="mb-1 text-sm text-spu-blue/50">{{ $edu->fieldOfStudy }}</p>
                                            @endif
                                            @if ($edu->yearStart !== null || $edu->yearEnd !== null)
                                                <span class="inline-flex items-center rounded bg-spu-blue/[0.06] px-2 py-0.5 text-xs font-semibold text-spu-blue">
                                                    {{ $edu->yearStart ?? '' }}{{ $edu->yearStart !== null && $edu->yearEnd !== null ? ' - ' : '' }}{{ $edu->yearEnd ?? '' }}
                                                </span>
                                            @endif
                                            @if ($edu->description !== null)
                                                <p class="mt-2 text-sm text-spu-blue/60">{{ $edu->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (count($profile->councilMemberships) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">{{ $isAr ? 'العضويات' : 'Council Memberships' }}</h2>
                            <div class="flex flex-col gap-5">
                                @foreach ($profile->councilMemberships as $membership)
                                    <div class="relative flex gap-4">
                                        <div class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-spu-blue"></div>
                                        <div class="flex-1">
                                            <h4 class="mb-1 text-[0.9375rem] font-bold text-spu-blue">{{ $membership['councilName'] }}</h4>
                                            @if (!empty($membership['position']))
                                                <p class="mb-1 text-sm text-spu-red font-semibold">{{ $membership['position'] }}</p>
                                            @endif
                                            @if (!empty($membership['bio']))
                                                <p class="text-sm text-spu-blue/60">{{ $membership['bio'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (count($profile->publications) > 0)
                        <div class="rounded-2xl border border-spu-blue/[0.08] bg-white p-6 shadow-[0_4px_24px_rgba(32,39,89,0.06)] md:p-10">
                            <h2 class="mb-5 flex flex-wrap items-center justify-between gap-2 border-b border-spu-blue/[0.08] pb-3 text-lg font-bold text-spu-blue">
                                <span>{{ $isAr ? 'الأبحاث المنشورة' : 'Published Researches' }}</span>
                            </h2>
                            <div class="flex flex-col gap-5">
                                @foreach ($profile->publications as $publication)
                                    @php($publicationHref = $publication['externalUrl'] ?? null)
                                    <div class="group rounded-xl border border-spu-blue/[0.06] bg-spu-blue/[0.03] p-5 transition-all hover:-translate-y-0.5 hover:border-spu-blue/[0.15] hover:bg-white hover:shadow-[0_8px_24px_rgba(32,39,89,0.1)]">
                                        @if ($publicationHref)<a href="{{ $publicationHref }}" target="_blank" rel="noopener noreferrer" class="mb-3 block">@else<div class="mb-3">@endif
                                            <div class="mb-2 flex items-start justify-between gap-4">
                                                <h4 class="flex-1 text-[0.9375rem] font-semibold leading-[1.5] text-spu-blue transition-colors group-hover:text-spu-red">{{ $publication['title'] }}</h4>
                                                @if (!empty($publication['year']))
                                                    <span class="shrink-0 rounded bg-spu-red px-2 py-1 text-xs font-bold text-white">{{ $publication['year'] }}</span>
                                                @endif
                                            </div>
                                            @if (!empty($publication['publisher']))
                                                <p class="text-sm italic text-spu-blue/60">{{ $publication['publisher'] }}</p>
                                            @endif
                                        @if ($publicationHref)</a>@else</div>@endif
                                        @if (!empty($publication['excerpt']))
                                            <p class="text-sm text-spu-blue/50">{{ \Illuminate\Support\Str::limit($publication['excerpt'], 150) }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
