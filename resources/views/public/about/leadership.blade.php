@extends('layouts.public')

@section('content')
    <div class="bg-[#faf9fb] font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => '', 'image' => $page->heroImage])

        <section class="bg-[#faf9fb] py-16 lg:py-24">
            <div class="container">
                <div class="filter-container">
                    <label for="leadership-faculty-filter" class="filter-label">{{ $locale === 'ar' ? 'عرض حسب الكلية' : 'View by Faculty' }}</label>
                    <select id="leadership-faculty-filter" class="filter-dropdown">
                        <option>{{ $locale === 'ar' ? 'كل القيادات' : 'All Leadership' }}</option>
                    </select>
                </div>

                @php($rector = $people->firstWhere('category', 'rector'))
                @if ($rector)
                    <article class="staff-spotlight reveal reveal-up mx-auto mb-16 max-w-6xl">
                        <div class="staff-spotlight-media"><img src="{{ $rector->image ?? '/images/medicine-dean.jpg' }}" alt="{{ $rector->name }}"></div>
                        <div class="staff-spotlight-content">
                            <p class="mb-5 text-xs font-black uppercase tracking-[0.15em] text-spu-red">{{ $rector->role }}</p>
                            <h2 class="text-3xl font-black leading-tight text-spu-blue md:text-4xl">{{ $rector->name }}</h2>
                            <blockquote class="staff-quote mt-8 max-w-xl text-[0.95rem] font-medium leading-[1.8] text-gray-600">
                                {{ $rector->quote ?: ($locale === 'ar' ? 'تتمثل رؤيتنا في بناء بيئة أكاديمية لا تكتفي بالسعي إلى التميز في البحث والتعليم، بل تساهم في التنمية المستدامة للمجتمع وتمكين طلابنا من قيادة المستقبل.' : 'Our vision is to foster an academic environment that not only pursues excellence in research and education but also actively contributes to the sustainable development of our society. We are committed to empowering our students to become the leaders and innovators of tomorrow.') }}
                            </blockquote>
                            <a href="{{ $rector->profileUrl ?? '#' }}" class="mt-10 inline-flex items-center gap-3 text-xs font-black uppercase tracking-[0.14em] text-spu-blue transition hover:text-spu-red">
                                <span>{{ $locale === 'ar' ? 'اقرأ الملف الكامل' : 'Read Full Profile' }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                            </a>
                        </div>
                    </article>
                @endif

                @php($vicePresidents = $people->where('category', 'vice_president'))
                @if ($vicePresidents->isNotEmpty())
                    <div class="section-title-wrapper"><h2 class="section-title">{{ $locale === 'ar' ? 'نواب رئيس الجامعة' : 'Vice Presidents' }}</h2></div>
                    <div class="vp-grid">
                        @foreach ($vicePresidents as $person)
                            <article class="vp-card reveal reveal-up">
                                <div class="vp-card-media"><img src="{{ $person->image ?? '/images/medicine-dean.jpg' }}" alt="{{ $person->name }}"></div>
                                <div class="vp-card-body">
                                    <h3 class="mb-2 text-lg font-black leading-tight text-spu-blue">{{ $person->name }}</h3>
                                    <p class="text-[0.68rem] font-black uppercase tracking-[0.1em] text-spu-red">{{ $person->role }}</p>
                                    @if ($person->bio)
                                        <p class="mt-6 text-sm leading-7 text-slate-600">{{ $person->bio }}</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                @php($deans = $people->where('category', 'dean'))
                @if ($deans->isNotEmpty())
                    <div class="section-title-wrapper"><h2 class="section-title">{{ $locale === 'ar' ? 'عمداء الكليات' : 'Faculty Deans' }}</h2></div>
                    <div class="deans-carousel-wrapper">
                        <button type="button" class="carousel-nav-btn" aria-label="{{ __('public.previous') }}">
                            <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-5 w-5 rtl:rotate-180" aria-hidden="true">
                        </button>
                        <div class="deans-grid">
                            @foreach ($deans->take(3) as $person)
                                <article class="dean-card reveal reveal-up">
                                    <div class="dean-card-media"><img src="{{ $person->image ?? '/images/medicine-dean.jpg' }}" alt="{{ $person->name }}"></div>
                                    <div class="dean-card-body">
                                        <h3 class="mb-2 text-lg font-black leading-tight text-spu-blue">{{ $person->name }}</h3>
                                        <p class="text-[0.68rem] font-black uppercase tracking-[0.1em] text-spu-red">{{ $person->role }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <button type="button" class="carousel-nav-btn" aria-label="{{ __('public.next') }}">
                            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-5 w-5 rtl:rotate-180" aria-hidden="true">
                        </button>
                    </div>
                @endif
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
