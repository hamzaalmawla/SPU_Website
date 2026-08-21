@extends('layouts.public')

@section('content')
    @php
        $people = $directory->people;
        $rector = $people->firstWhere('category', 'rector');
        $vicePresidents = $people->where('category', 'vice_president')->values();
        $deans = $people->where('category', 'dean')->values();
        $leadershipCopy = collect($page->sections)->filter(static fn (mixed $section): bool => is_array($section) && isset($section['key']))->keyBy('key');
        $rectorQuote = (string) ($leadershipCopy->get('rector_quote')['body'] ?? '');
        $vicePresidentsTitle = (string) ($leadershipCopy->get('vice_presidents_title')['title'] ?? '');
        $deansTitle = (string) ($leadershipCopy->get('deans_title')['title'] ?? '');
    @endphp

    <div class="bg-[#faf9fb] font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

        <section class="bg-[#faf9fb] py-16 lg:py-24"
                 x-data="leadershipDirectory()"
                 data-initial-faculty="{{ $directory->activeFaculty }}"
                 data-dean-count="{{ $deans->count() }}"
                 data-of-label="{{ $locale === 'ar' ? 'من' : 'of' }}">
            <div class="container">
                <div class="filter-container">
                    <label for="leadership-faculty-filter" class="filter-label">{{ $locale === 'ar' ? 'عرض حسب الكلية' : 'View by Faculty' }}</label>
                    <select id="leadership-faculty-filter" class="filter-dropdown" x-model="faculty" @change="changeFaculty()">
                        <option value="">{{ $locale === 'ar' ? 'جميع أعضاء المجلس' : 'All Leadership' }}</option>
                        @foreach ($directory->facultyFilters as $filter)
                            <option value="{{ $filter['slug'] }}" @selected($directory->activeFaculty === $filter['slug'])>{{ $filter['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($rector)
                    <article class="staff-spotlight reveal reveal-up mx-auto mb-16 max-w-6xl" x-show="showInstitutional()">
                        <div class="staff-spotlight-media">@if ($rector->image)<img src="{{ $rector->image }}" alt="{{ $rector->name }}">@else<div class="flex h-full items-center justify-center bg-slate-100"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-20 w-20 opacity-30" aria-hidden="true"></div>@endif</div>
                        <div class="staff-spotlight-content">
                            <p class="mb-5 text-xs font-black uppercase tracking-[0.15em] text-spu-red">{{ $rector->role }}</p>
                            <h2 class="text-3xl font-black leading-tight text-spu-blue md:text-4xl">{{ $rector->name }}</h2>
                            <blockquote class="staff-quote mt-8 max-w-xl text-[0.95rem] font-medium leading-[1.8] text-gray-600">
                                 {{ $rector->quote ?: $rectorQuote }}
                            </blockquote>
                            <a href="{{ route('public.about.profile', ['locale' => $locale, 'slug' => $rector->slug]) }}" class="mt-10 inline-flex items-center gap-3 text-xs font-black uppercase tracking-[0.14em] text-spu-blue transition hover:text-spu-red">
                                <span>{{ $locale === 'ar' ? 'اقرأ الملف الكامل' : 'Read Full Profile' }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                            </a>
                        </div>
                    </article>
                @endif

                @if ($vicePresidents->isNotEmpty())
                    <div x-show="showInstitutional()">
                         <div class="section-title-wrapper"><h2 class="section-title">{{ $vicePresidentsTitle }}</h2></div>
                        <div class="vp-grid">
                            @foreach ($vicePresidents as $person)
                                <a href="{{ route('public.about.profile', ['locale' => $locale, 'slug' => $person->slug]) }}" class="vp-card reveal reveal-up block transition hover:-translate-y-1 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                                    <div class="vp-card-media">@if ($person->image)<img src="{{ $person->image }}" alt="{{ $person->name }}">@else<div class="flex h-full items-center justify-center bg-slate-100"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-16 w-16 opacity-30" aria-hidden="true"></div>@endif</div>
                                    <div class="vp-card-body">
                                        <h3 class="mb-2 text-lg font-black leading-tight text-spu-blue">{{ $person->name }}</h3>
                                        <p class="text-[0.68rem] font-black uppercase tracking-[0.1em] text-spu-red">{{ $person->role }}</p>
                                        @if ($person->bio)
                                            <p class="mt-6 text-sm leading-7 text-slate-600">{{ $person->bio }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($deans->isNotEmpty())
                    <div class="section-title-wrapper"><h2 class="section-title">{{ $deansTitle }}</h2></div>
                    <div class="deans-carousel-wrapper"
                         role="region"
                         aria-roledescription="carousel"
                         aria-label="{{ $locale === 'ar' ? 'عمداء الكليات' : 'Faculty Deans' }}"
                         tabindex="0"
                         @keydown.left.prevent="handleArrowLeft()"
                         @keydown.right.prevent="handleArrowRight()"
                         @touchstart.passive="startTouch($event)"
                         @touchend.passive="endTouch($event)">
                        <button type="button" class="carousel-nav-btn disabled:cursor-not-allowed disabled:opacity-30" @click="previousDean()" :disabled="previousDisabled()" :aria-disabled="previousDisabled()" aria-controls="leadership-deans" aria-label="{{ __('public.previous') }}">
                            <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-5 w-5 rtl:rotate-180" aria-hidden="true">
                        </button>
                        <div id="leadership-deans" class="deans-grid" aria-live="polite">
                            @foreach ($deans as $person)
                                <a href="{{ route('public.about.profile', ['locale' => $locale, 'slug' => $person->slug]) }}"
                                   class="dean-card reveal reveal-up block transition hover:-translate-y-1 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue"
                                   x-show="deanVisible({{ $loop->index }}, '{{ $person->facultySlug }}')"
                                   :inert="! deanVisible({{ $loop->index }}, '{{ $person->facultySlug }}')"
                                   role="group"
                                   aria-label="{{ ($loop->index + 1).' '.($locale === 'ar' ? 'من' : 'of').' '.$deans->count().': '.$person->name }}">
                                    <div class="dean-card-media">@if ($person->image)<img src="{{ $person->image }}" alt="{{ $person->name }}">@else<div class="flex h-full items-center justify-center bg-slate-100"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-16 w-16 opacity-30" aria-hidden="true"></div>@endif</div>
                                    <div class="dean-card-body">
                                        <h3 class="mb-2 text-lg font-black leading-tight text-spu-blue">{{ $person->name }}</h3>
                                        <p class="text-[0.68rem] font-black uppercase tracking-[0.1em] text-spu-red">{{ $person->role }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        <button type="button" class="carousel-nav-btn disabled:cursor-not-allowed disabled:opacity-30" @click="nextDean()" :disabled="nextDisabled()" :aria-disabled="nextDisabled()" aria-controls="leadership-deans" aria-label="{{ __('public.next') }}">
                            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-5 w-5 rtl:rotate-180" aria-hidden="true">
                        </button>
                    </div>
                    <p class="mt-5 text-center text-xs font-bold text-slate-500" aria-live="polite" x-text="statusText()"></p>
                @endif
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
