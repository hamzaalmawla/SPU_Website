@extends('layouts.public')

@push('styles')
    <style>
        .project-detail-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5rem;
        }

        @media (min-width: 1024px) {
            .project-detail-layout {
                grid-template-columns: minmax(0, 1fr) 340px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $faculty = $page->faculty;
        $project = $page->project;
        $accent = $project['facultyColor'] ?? ($faculty['accentColor'] ?: '#202759');
        $isAr = $locale === 'ar';
        $homeLabel = $isAr ? 'الرئيسية' : 'Home';
        $projectsLabel = $isAr ? 'المشاريع' : 'Projects';
        $facultyLabel = $isAr ? 'الكلية' : 'Faculty';
        $yearLabel = $isAr ? 'العام الدراسي' : 'Academic Year';
        $statusLabel = $isAr ? 'الحالة' : 'Status';
        $completedLabel = $isAr ? 'مكتمل' : 'Completed';
        $statusValue = (string) ($project['status'] ?? $completedLabel);
        $technologiesLabel = $isAr ? 'التقنيات' : 'Technologies';
        $teamLabel = $isAr ? 'الفريق' : 'Team';
        $supervisorLabel = $isAr ? 'المشرف' : 'Supervisor';
        $galleryLabel = $isAr ? 'معرض المشروع' : 'Project Gallery';
        $relatedLabel = $isAr ? 'مشاريع ذات صلة' : 'Related Projects';
        $previousLabel = $isAr ? 'السابق' : 'Previous';
        $nextLabel = $isAr ? 'التالي' : 'Next';
        $viewAllLabel = $isAr ? 'عرض جميع المشاريع' : 'View All Projects';
        $createdByLabel = $isAr ? 'أعده' : 'Created By';
        $detailsLabel = $isAr ? 'عرض التفاصيل' : 'View Details';
        $facultyProjectsUrl = '/'.$locale.'/facilities/'.$page->facultySlug.'/projects';
        $initials = function (string $value): string {
            $words = preg_split('/\s+/u', trim($value)) ?: [];

            return collect($words)
                ->filter()
                ->map(fn (string $word): string => mb_substr($word, 0, 1))
                ->take(2)
                ->implode('');
        };
    @endphp

    <section class="bg-white py-16 font-hacen md:py-20">
        <div class="container">
            <nav class="mb-8 flex items-center gap-2 text-[12px] font-bold text-slate-500" aria-label="Breadcrumb">
                <a href="/{{ $locale }}" class="transition hover:text-spu-blue">{{ $homeLabel }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                <a href="{{ $facultyProjectsUrl }}" class="transition hover:text-spu-blue">{{ $project['facultyTitle'] ?? ($faculty['title'] ?? '') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                <span class="text-spu-blue">{{ $project['title'] ?? '' }}</span>
            </nav>

            <div class="project-detail-layout">
                <div>
                    <div class="relative aspect-[2.1] overflow-hidden rounded-[6px] bg-slate-100">
                        <img src="{{ $project['image'] ?? '/images/Gemini_Generated_Image_c89yjwc89yjwc89y.webp' }}" alt="{{ $project['title'] ?? '' }}" class="h-full w-full object-cover">
                        <span class="absolute left-3 top-3 rounded-[3px] px-2 py-1 text-[10px] font-bold leading-none text-white rtl:left-auto rtl:right-3" style="background-color: {{ $accent }}">{{ $project['tag'] ?? ($isAr ? 'مشروع' : 'Project') }}</span>
                    </div>

                    <div class="mt-8">
                        <h1 class="text-[26px] font-bold leading-[34px] text-spu-blue md:text-[32px] md:leading-[42px]">{{ $project['title'] ?? '' }}</h1>
                        <p class="mt-4 text-[14px] font-medium leading-[26px] text-slate-600">{{ $project['summary'] ?? '' }}</p>
                    </div>

                    <div class="mt-8 space-y-4">
                        @foreach (($project['longDescription'] ?? []) as $paragraph)
                            @if ($paragraph)
                                <p class="text-[14px] leading-[26px] text-slate-600">{{ $paragraph }}</p>
                            @endif
                        @endforeach
                    </div>

                    @if (! empty($project['gallery']))
                        <div class="mt-12">
                            <h2 class="text-[18px] font-bold text-spu-blue">{{ $galleryLabel }}</h2>
                            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                @foreach ($project['gallery'] as $image)
                                    <div class="aspect-[4/3] overflow-hidden rounded-[4px] bg-slate-100">
                                        <img src="{{ $image }}" alt="" class="h-full w-full object-cover transition duration-500 hover:scale-[1.03]">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($page->relatedProjects))
                        <div class="mt-14 border-t border-slate-100 pt-10">
                            <h2 class="text-[18px] font-bold text-spu-blue">{{ $relatedLabel }}</h2>
                            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                                @foreach ($page->relatedProjects as $related)
                                    <article class="overflow-hidden rounded-[6px] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md">
                                        <div class="relative aspect-[1.72] overflow-hidden bg-slate-100">
                                            <img src="{{ $related['image'] ?? '/images/Gemini_Generated_Image_c89yjwc89yjwc89y.webp' }}" alt="{{ $related['title'] ?? '' }}" class="h-full w-full object-cover transition duration-500 hover:scale-[1.03]">
                                        </div>
                                        <div class="p-4">
                                            <h3 class="line-clamp-2 text-[13px] font-bold leading-[20px] text-spu-blue">{{ $related['title'] ?? '' }}</h3>
                                            <a href="{{ $related['detailRoute'] ?? '#' }}" class="mt-3 inline-flex items-center gap-1.5 text-[11px] font-bold text-spu-red transition hover:text-spu-blue">
                                                <span>{{ $detailsLabel }}</span>
                                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                                            </a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-10 flex items-center justify-between border-t border-slate-100 pt-6">
                        <a href="{{ $page->previousProject['detailRoute'] ?? '#' }}" class="group inline-flex items-center gap-2 text-[12px] font-bold text-slate-500 transition hover:text-spu-blue">
                            <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-3 w-3 transition group-hover:-translate-x-0.5 rtl:rotate-180" aria-hidden="true">
                            <span>{{ $previousLabel }}</span>
                        </a>
                        <a href="{{ $facultyProjectsUrl }}" class="text-[12px] font-bold text-spu-red transition hover:text-spu-blue">{{ $viewAllLabel }}</a>
                        <a href="{{ $page->nextProject['detailRoute'] ?? '#' }}" class="group inline-flex items-center gap-2 text-[12px] font-bold text-slate-500 transition hover:text-spu-blue">
                            <span>{{ $nextLabel }}</span>
                            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3 w-3 transition group-hover:translate-x-0.5 rtl:rotate-180" aria-hidden="true">
                        </a>
                    </div>
                </div>

                <aside class="space-y-8">
                    <div class="rounded-[6px] border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-[13px] font-bold uppercase tracking-[0.04em] text-slate-400">{{ $facultyLabel }}</h3>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="h-3 w-3 rounded-full" style="background-color: {{ $accent }}"></div>
                            <span class="text-[14px] font-bold text-spu-blue">{{ $project['facultyTitle'] ?? ($faculty['title'] ?? '') }}</span>
                        </div>
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between text-[12px]">
                                <span class="text-slate-400">{{ $yearLabel }}</span>
                                <span class="font-bold text-spu-blue">{{ $project['year'] ?? '' }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-[12px]">
                                <span class="text-slate-400">{{ $statusLabel }}</span>
                                <span class="rounded-[3px] bg-green-50 px-2 py-0.5 font-bold text-green-600">{{ $statusValue }}</span>
                            </div>
                        </div>
                    </div>

                    @if (! empty($project['supervisor']))
                        <div class="rounded-[6px] border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-[13px] font-bold uppercase tracking-[0.04em] text-slate-400">{{ $supervisorLabel }}</h3>
                            <a href="/{{ $locale }}" class="mt-3 flex items-center gap-3 transition hover:opacity-80">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-spu-blue text-[12px] font-bold text-white">{{ $initials((string) $project['supervisor']) }}</div>
                                <div>
                                    <p class="text-[13px] font-bold text-spu-blue">{{ $project['supervisor'] }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $supervisorLabel }}</p>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if (! empty($project['createdBy']))
                        <div class="rounded-[6px] border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-[13px] font-bold uppercase tracking-[0.04em] text-slate-400">{{ $createdByLabel }}</h3>
                            <a href="/{{ $locale }}/facilities/{{ $page->facultySlug }}/alumni" class="mt-3 flex items-center gap-3 transition hover:opacity-80">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full text-[12px] font-bold text-white" style="background-color: {{ $accent }}">{{ $initials((string) $project['createdBy']) }}</div>
                                <div>
                                    <p class="text-[13px] font-bold text-spu-blue">{{ $project['createdBy'] }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $createdByLabel }}</p>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if (! empty($project['teamMembers']))
                        <div class="rounded-[6px] border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-[13px] font-bold uppercase tracking-[0.04em] text-slate-400">{{ $teamLabel }}</h3>
                            <ul class="mt-4 space-y-3">
                                @foreach ($project['teamMembers'] as $member)
                                    <li>
                                        <a href="/{{ $locale }}/facilities/{{ $page->facultySlug }}/alumni" class="flex items-center gap-3 transition hover:opacity-80">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-500">{{ $initials((string) ($member['name'] ?? '')) }}</div>
                                            <div>
                                                <p class="text-[12px] font-bold text-spu-blue">{{ $member['name'] ?? '' }}</p>
                                                <p class="text-[10px] text-slate-400">{{ $member['role'] ?? '' }}</p>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($project['technologies']))
                        <div class="rounded-[6px] border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-[13px] font-bold uppercase tracking-[0.04em] text-slate-400">{{ $technologiesLabel }}</h3>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($project['technologies'] as $technology)
                                    <span class="rounded-[3px] bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ $technology }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </section>
@endsection
