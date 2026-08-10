<section id="home-news" class="py-7.5 mt-[70px] bg-white font-hacen overflow-hidden reveal">
    <div class="container">
        <div class="flex items-center justify-between relative mb-10">
            <h2 class="text-[clamp(1.85rem,7vw,2.625rem)] font-bold text-[#1e2652] tracking-tight">{{ $section->payload->title }}</h2>
            @if ($section->payload->sectionAction)
                <div class="section-header__controls flex items-center absolute top-4.5 gap-6 rtl:left-0 ltr:right-0">
                    <a href="{{ $section->payload->sectionAction->url }}" class="bg-[#1e2652] text-white w-[195px] h-[40px] text-center justify-center rounded-[12px] text-sm font-bold flex items-center gap-3 hover:bg-opacity-90 transition-all" @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>{{ $section->payload->sectionAction->label }}</a>
                </div>
            @endif
        </div>

        @if ($section->payload->articles !== [])
            <div class="cms-grid-news gap-8 pb-10">
                @foreach ($section->payload->articles as $article)
                    <article class="reveal-item w-full">
                    <a href="{{ $article->url }}" class="h-full bg-white rounded-[25px] shadow-[0_15px_35px_rgba(0,0,0,0.06)] overflow-hidden flex flex-col group transition-all duration-500 ease-out hover:-translate-y-2 hover:shadow-[0_25px_60px_rgba(0,0,0,0.12)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red">
                        @if ($article->imageUrl)
                            <div class="relative h-[210px] overflow-hidden">
                                <img src="{{ $article->imageUrl }}" alt="{{ $article->title }}" loading="lazy" decoding="async" width="400" height="210" class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-110">
                                @if ($article->categoryLabel)
                                    <div class="absolute top-4 start-4 px-4 py-1 rounded-md text-white text-[11px] font-bold z-10 bg-spu-blue">{{ $article->categoryLabel }}</div>
                                @endif
                                <div class="absolute bottom-0 left-0 w-full h-[3px] bg-spu-red opacity-80 z-10 transition-transform duration-500 group-hover:scale-x-110"></div>
                            </div>
                        @endif
                        <div class="p-6 text-center flex flex-col flex-1">
                            <h3 class="text-[22px] font-bold text-[#1B1B1F] mb-1 leading-tight">{{ $article->title }}</h3>
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
