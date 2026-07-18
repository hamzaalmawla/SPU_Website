@extends('layouts.public')

@section('content')
    @php
        $staffUrl = static function (int $pageNumber, string $faculty = '') use ($locale): string {
            return route('public.about.directorates.staff', array_filter([
                'locale' => $locale,
                'faculty' => $faculty !== '' ? $faculty : null,
                'page' => $pageNumber > 1 ? $pageNumber : null,
            ], static fn (mixed $value): bool => $value !== null));
        };
        $pageStart = max(1, $directory->currentPage - 2);
        $pageEnd = min($directory->totalPages, $directory->currentPage + 2);
    @endphp

    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

        <section id="staff-directory" class="bg-white font-hacen" aria-labelledby="staff-directory-heading">
            <div class="container mx-auto px-6">
                <h2 id="staff-directory-heading" class="sr-only">{{ $locale === 'ar' ? 'أعضاء الهيئة الأكاديمية' : 'Academic staff members' }}</h2>
                <form method="GET" action="{{ route('public.about.directorates.staff', ['locale' => $locale]) }}" class="staff-filter-bar">
                    <label for="staff-faculty-filter" class="staff-filter-label">{{ $locale === 'ar' ? 'عرض حسب الكلية' : 'View by Faculty' }}</label>
                    <div class="flex w-full max-w-xl flex-col items-stretch gap-3 sm:flex-row sm:items-end">
                        <select id="staff-faculty-filter" name="faculty" class="staff-filter-select flex-1">
                            <option value="">{{ $locale === 'ar' ? 'جميع الكليات' : 'All Faculties' }}</option>
                            @foreach ($directory->facultyFilters as $filter)
                                <option value="{{ $filter['slug'] }}" @selected($directory->activeFaculty === $filter['slug'])>{{ $filter['label'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md bg-spu-blue px-6 py-3 text-sm font-black text-white transition hover:bg-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                            {{ $locale === 'ar' ? 'تطبيق التصفية' : 'Apply Filter' }}
                        </button>
                    </div>
                </form>

                <p class="mb-8 text-center text-sm font-bold text-slate-600" role="status">
                    {{ $locale === 'ar' ? 'عدد النتائج: '.$directory->totalItems : $directory->totalItems.' results' }}
                </p>

                @if ($directory->items->isNotEmpty())
                    <div class="staff-grid reveal reveal-up reveal-delay-1">
                        @foreach ($directory->items as $person)
                            <a href="{{ route('public.about.profile', ['locale' => $locale, 'source' => $person->sourceType, 'slug' => $person->slug]) }}" class="staff-card reveal reveal-up block transition hover:-translate-y-1 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                            <div class="staff-card-media">@if ($person->image)<img src="{{ $person->image }}" alt="{{ $person->name }}" loading="lazy">@else<div class="flex h-full items-center justify-center bg-slate-100"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-16 w-16 opacity-30" aria-hidden="true"></div>@endif</div>
                            <div class="staff-card-body">
                                <h2 class="staff-card-name">{{ $person->name }}</h2>
                                <p class="staff-card-role">{{ $person->role }}</p>
                                @if ($person->facultyName)
                                    <p class="mt-3 text-xs font-bold text-slate-500">{{ $person->facultyName }}</p>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                @else
                    <div class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center">
                        <p class="font-bold text-slate-700">{{ $locale === 'ar' ? 'لا يوجد أعضاء هيئة أكاديمية مطابقون لهذه التصفية.' : 'No academic staff members match this filter.' }}</p>
                        <a href="{{ $staffUrl(1) }}" class="mt-5 inline-flex font-black text-spu-blue underline decoration-spu-red/40 underline-offset-4 hover:text-spu-red">{{ $locale === 'ar' ? 'عرض جميع الكليات' : 'View all faculties' }}</a>
                    </div>
                @endif

                @if ($directory->totalPages > 1)
                    <nav class="staff-pagination" aria-label="{{ $locale === 'ar' ? 'ترقيم دليل الهيئة الأكاديمية' : 'Staff pagination' }}">
                        @if ($directory->currentPage > 1)
                            <a href="{{ $staffUrl($directory->currentPage - 1, $directory->activeFaculty) }}#staff-directory" class="pag-btn pag-arrow" rel="prev" aria-label="{{ $locale === 'ar' ? 'الصفحة السابقة' : 'Previous page' }}">
                                <svg class="rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </a>
                        @else
                            <span class="pag-btn pag-arrow cursor-not-allowed opacity-40" aria-disabled="true">
                                <svg class="rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            </span>
                        @endif

                        @foreach (range($pageStart, $pageEnd) as $pageNumber)
                            @if ($pageNumber === $directory->currentPage)
                                <span class="pag-btn active" aria-current="page" aria-label="{{ ($locale === 'ar' ? 'الصفحة ' : 'Page ').$pageNumber }}">{{ $pageNumber }}</span>
                            @else
                                <a href="{{ $staffUrl($pageNumber, $directory->activeFaculty) }}#staff-directory" class="pag-btn" aria-label="{{ ($locale === 'ar' ? 'الصفحة ' : 'Page ').$pageNumber }}">{{ $pageNumber }}</a>
                            @endif
                        @endforeach

                        @if ($directory->currentPage < $directory->totalPages)
                            <a href="{{ $staffUrl($directory->currentPage + 1, $directory->activeFaculty) }}#staff-directory" class="pag-btn pag-arrow" rel="next" aria-label="{{ $locale === 'ar' ? 'الصفحة التالية' : 'Next page' }}">
                                <svg class="rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </a>
                        @else
                            <span class="pag-btn pag-arrow cursor-not-allowed opacity-40" aria-disabled="true">
                                <svg class="rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </span>
                        @endif
                    </nav>
                @else
                    <div class="pb-20"></div>
                @endif
            </div>
        </section>
        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
