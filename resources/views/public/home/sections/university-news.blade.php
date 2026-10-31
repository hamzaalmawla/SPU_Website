{{-- Below xl the news cards scroll like the research slider; from xl they sit in a four-column grid. --}}
<section x-data="researchSlider('.news-slide')" id="home-news" class="py-7.5 mt-[70px] bg-white font-hacen overflow-hidden reveal">
    <div class="container">
        <div class="section-header research-section-header relative">
            <h2 class="section-header__title research-section-header__title text-[clamp(1.85rem,7vw,2.625rem)] font-bold text-[#1e2652] tracking-tight rtl:text-right ltr:text-left">{{ $section->payload->title }}</h2>
            <div class="section-header__controls research-section-header__controls flex gap-3">
                @if ($section->payload->sectionAction)
                    <a href="{{ $section->payload->sectionAction->url }}" class="flex h-10 w-auto min-w-[120px] max-w-[195px] shrink-0 items-center justify-center gap-3 rounded-[12px] bg-[#1e2652] px-5 text-center text-sm font-bold text-white transition-all hover:bg-opacity-90 xl:w-[195px]" @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>{{ $section->payload->sectionAction->label }}</a>
                @endif
                @if (count($section->payload->articles) > 1)
                    <button type="button" @click="slide('previous')" class="slider-nav-btn shrink-0 w-12 h-12 rounded-full border border-slate-200 hover:bg-slate-50 transition-all xl:hidden" aria-controls="home-news-track" aria-label="{{ __('public.previous') }}"><img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="" width="24" height="24" loading="lazy" decoding="async"></button>
                    <button type="button" @click="slide('next')" class="slider-nav-btn shrink-0 w-12 h-12 rounded-full border border-slate-200 hover:bg-slate-50 transition-all xl:hidden" aria-controls="home-news-track" aria-label="{{ __('public.next') }}"><img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="" width="24" height="24" loading="lazy" decoding="async"></button>
                @endif
            </div>
        </div>

        @if ($section->payload->articles !== [])
            <div id="home-news-track" x-ref="researchTrack" class="flex gap-8 pe-6 pt-2 overflow-x-auto no-scrollbar scroll-smooth pb-10 xl:grid xl:grid-cols-4 xl:overflow-visible xl:pe-0" role="region" aria-label="{{ $section->payload->title }}" tabindex="0" @keydown="handleSliderKey($event)">
            @foreach ($section->payload->articles as $article)
                <article class="reveal-item news-slide w-[min(82vw,320px)] shrink-0 sm:w-[320px] xl:w-auto xl:shrink">
                    <a href="{{ $article->url }}" class="h-full bg-white rounded-[25px] shadow-card-elevated overflow-hidden flex flex-col group transition-all duration-500 ease-out hover:-translate-y-2 hover:shadow-card-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red">
                        @if ($article->imageUrl)
                            <div class="relative h-[210px] overflow-hidden">
                                @php($newsSrcset = \App\Support\MediaUrlResolver::legacySrcset($article->imageUrl))
                                <img src="{{ $article->imageUrl }}" @if ($newsSrcset) srcset="{{ $newsSrcset }}" sizes="(max-width: 768px) 100vw, 400px" @endif alt="{{ $article->title }}" loading="lazy" decoding="async" width="400" height="210" class="content-media-image h-full w-full">
                                @if ($article->categoryLabel)
                                    <div class="absolute top-4 start-4 px-4 py-1 rounded-md text-white text-[11px] font-bold z-10 bg-spu-blue">{{ $article->categoryLabel }}</div>
                                @endif
                                <div class="absolute bottom-0 left-0 w-full h-[3px] bg-spu-red opacity-80 z-10 transition-transform duration-500 group-hover:scale-x-110"></div>
                            </div>
                        @endif
                        <div class="p-6 text-center flex flex-col flex-1">
                            <h3 class="card-title text-[22px] font-bold text-[#1B1B1F] mb-1 leading-tight" dir="auto">{{ $article->title }}</h3>
                            @if ($article->publishedAt)
                                <p class="text-spu-red text-[15px] mb-3" translate="no">{{ $article->publishedAt }}</p>
                            @endif
                            @if ($article->excerpt)
                                <p class="text-gray-700 text-[14px] leading-[1.6] line-clamp-3">{{ $article->excerpt }}</p>
                            @endif
                        </div>
                    </a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
