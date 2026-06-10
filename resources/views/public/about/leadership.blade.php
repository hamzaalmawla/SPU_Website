@extends('layouts.public')

@section('content')
    <div class="bg-[#faf9fb] font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

        <section class="bg-[#faf9fb] py-16 lg:py-24">
            <div class="container">
                @php($rector = $people->firstWhere('category', 'rector'))
                @if ($rector)
                    <article class="staff-spotlight reveal reveal-up mx-auto mb-16 max-w-6xl">
                        <div class="staff-spotlight-media"><img src="{{ $rector->image ?? '/images/medicine-dean.jpg' }}" alt="{{ $rector->name }}"></div>
                        <div class="staff-spotlight-content">
                            <p class="mb-5 text-xs font-black uppercase tracking-[0.15em] text-spu-red">{{ $rector->role }}</p>
                            <h2 class="text-3xl font-black leading-tight text-spu-blue md:text-4xl">{{ $rector->name }}</h2>
                            @if ($rector->quote)<blockquote class="staff-quote mt-8 max-w-xl text-[0.95rem] font-medium leading-[1.8] text-gray-600">{{ $rector->quote }}</blockquote>@endif
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
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                @php($deans = $people->where('category', 'dean'))
                @if ($deans->isNotEmpty())
                    <div class="section-title-wrapper"><h2 class="section-title">{{ $locale === 'ar' ? 'عمداء الكليات' : 'Faculty Deans' }}</h2></div>
                    <div class="deans-grid">
                        @foreach ($deans as $person)
                        <article class="dean-card reveal reveal-up">
                            <div class="dean-card-media"><img src="{{ $person->image ?? '/images/medicine-dean.jpg' }}" alt="{{ $person->name }}"></div>
                            <div class="dean-card-body">
                                <h3 class="mb-2 text-lg font-black leading-tight text-spu-blue">{{ $person->name }}</h3>
                                <p class="text-[0.68rem] font-black uppercase tracking-[0.1em] text-spu-red">{{ $person->role }}</p>
                            </div>
                        </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
