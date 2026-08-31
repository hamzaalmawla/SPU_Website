@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php
        $data = $page->data;
        $activeFilters = $data['activeFilters'] ?? ['q' => '', 'faculty' => '', 'expertise' => '', 'page' => 1];
        $isExpertFinder = $page->type === 'expert-finder';
        $basePath = '/'.$locale.'/research/'.($isExpertFinder ? 'expert-finder' : 'researchers');
    @endphp
    @if (! $page->isAvailable)
        @include('public.research.partials.empty-state', ['locale' => $locale, 'direction' => $direction])
    @else
    @include('public.research.partials.page-hero', ['hero' => $data['hero'] ?? [], 'locale' => $locale, 'direction' => $direction])

    <section class="bg-white py-12 font-hacen md:py-16" dir="{{ $direction }}">
        <div class="container mx-auto px-6">
            <h1 class="sr-only">{{ $data['hero']['title'] ?? '' }}</h1>
            <div class="mx-auto mb-10 max-w-[1200px]">
                <form method="GET" action="{{ $basePath }}" class="flex flex-col gap-4 rounded-xl bg-spu-blue/[0.04] p-4 lg:flex-row lg:items-center">
                    <label class="relative flex-1">
                        <span class="sr-only">{{ $locale === 'ar' ? 'البحث عن باحث' : 'Search researchers' }}</span>
                        <img src="/images/icon-search-outline.svg" alt="" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-spu-blue/40 rtl:left-auto rtl:right-4 rtl:rotate-180" aria-hidden="true">
                        <input name="q" value="{{ $activeFilters['q'] ?? '' }}" type="search" placeholder="{{ $data['searchPlaceholder'] ?? '' }}" class="h-12 w-full rounded-lg border border-spu-blue/10 bg-white py-3 pl-12 pr-4 text-sm text-spu-blue placeholder:text-spu-blue/40 focus:border-spu-blue focus:outline-none focus:ring-2 focus:ring-spu-blue/20 rtl:pl-4 rtl:pr-12 rtl:text-right">
                    </label>
                    <div class="flex flex-col gap-3 sm:flex-row md:w-auto">
                        <label>
                            <span class="sr-only">{{ $data['filters']['allFaculties'] ?? '' }}</span>
                            <select name="faculty" onchange="this.form.submit()" class="h-12 w-full sm:w-auto sm:min-w-[160px] cursor-pointer rounded-lg border border-spu-blue/10 bg-white px-4 text-sm text-spu-blue focus:border-spu-blue focus:outline-none focus:ring-2 focus:ring-spu-blue/20">
                                <option value="">{{ $data['filters']['allFaculties'] ?? '' }}</option>
                                @foreach (($data['faculties'] ?? []) as $faculty)
                                    @php($facultyValue = (string) ($faculty['id'] ?? $faculty['value'] ?? ''))
                                    <option value="{{ $facultyValue }}" @selected(($activeFilters['faculty'] ?? '') === $facultyValue)>{{ $faculty['name'] ?? $faculty['label'] ?? '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        @unless ($isExpertFinder)
                            <label>
                                <span class="sr-only">{{ $data['filters']['allExpertise'] ?? '' }}</span>
                                <select name="expertise" onchange="this.form.submit()" class="h-12 w-full sm:w-auto sm:min-w-[160px] cursor-pointer rounded-lg border border-spu-blue/10 bg-white px-4 text-sm text-spu-blue focus:border-spu-blue focus:outline-none focus:ring-2 focus:ring-spu-blue/20">
                                    <option value="">{{ $data['filters']['allExpertise'] ?? '' }}</option>
                                    @foreach (($data['expertiseAreas'] ?? []) as $area)
                                        @php($expertiseValue = (string) ($area['id'] ?? $area['value'] ?? ''))
                                        <option value="{{ $expertiseValue }}" @selected(($activeFilters['expertise'] ?? '') === $expertiseValue)>{{ $area['name'] ?? $area['label'] ?? '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endunless
                    </div>
                </form>
                <div class="mt-4 flex items-center justify-between gap-4 text-sm text-spu-blue/60">
                    <p aria-live="polite">{{ $data['resultCount'] ?? count($data['items'] ?? []) }} {{ $data['resultsLabel'] ?? ($locale === 'ar' ? 'نتيجة' : 'results') }}</p>
                    @if (collect($activeFilters)->except('page')->filter(fn ($value) => (string) $value !== '')->isNotEmpty())
                        <a href="{{ $basePath }}" class="font-bold text-spu-red hover:text-spu-blue">{{ $locale === 'ar' ? 'مسح عوامل التصفية' : 'Clear filters' }}</a>
                    @endif
                </div>
            </div>

            <div class="mx-auto grid max-w-[1200px] gap-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @forelse (($data['items'] ?? []) as $researcher)
                    <a href="{{ $researcher['profileUrl'] ?? '/'.$locale.'/about/profile/'.($researcher['slug'] ?? '') }}" class="group flex flex-col items-center text-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                        <div class="relative mb-4 h-28 w-28 overflow-hidden rounded-full border-2 border-spu-blue/10 transition-all group-hover:border-spu-red">
                            <img src="{{ $researcher['image'] ?? '/images/unkown.jpeg' }}" alt="{{ $researcher['name'] ?? '' }}" class="h-full w-full object-cover object-top">
                        </div>
                        <h2 class="mb-1 text-base font-bold text-spu-blue transition-colors group-hover:text-spu-red">{{ $researcher['name'] ?? '' }}</h2>
                        <p class="mb-2 text-sm text-spu-blue/70">{{ $researcher['faculty'] ?? '' }}</p>
                        <p class="text-xs font-semibold text-spu-blue/60">{{ $researcher['publications'] ?? 0 }} {{ $data['publicationsLabel'] ?? ($locale === 'ar' ? 'منشور' : 'Publications') }}</p>
                    </a>
                @empty
                    <div class="rounded-xl border border-slate-200 bg-section p-8 text-center sm:col-span-2 md:col-span-3 lg:col-span-4 xl:col-span-5">
                        <h2 class="text-xl font-bold text-spu-blue">{{ $locale === 'ar' ? 'لا توجد نتائج' : 'No results found' }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ $locale === 'ar' ? 'جرّب تغيير كلمات البحث أو عوامل التصفية.' : 'Try changing your search terms or filters.' }}</p>
                        <a href="{{ $basePath }}" class="mt-5 inline-flex rounded-[6px] bg-spu-red px-5 py-2.5 text-xs font-bold text-white">{{ $locale === 'ar' ? 'مسح عوامل التصفية' : 'Clear filters' }}</a>
                    </div>
                @endforelse
            </div>

            @include('public.research.partials.listing-pagination', ['data' => $data, 'basePath' => $basePath, 'locale' => $locale])
        </div>
    </section>
    @endif
@endsection
