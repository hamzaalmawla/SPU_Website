@php
    $societyTitle = $section->payload->content['society_title'] ?? null;
    $societyActionLabel = $section->payload->content['society_cta_label'] ?? null;
    $societyActionUrl = $section->payload->content['society_cta_url'] ?? null;
    $societyArticles = $section->payload->content['societyEventArticles'] ?? [];
@endphp

<section id="home-society-events" class="mt-[70px] overflow-hidden bg-white py-7.5 font-hacen reveal">
    <div class="container">
        <div class="relative mb-10 flex flex-wrap items-center justify-between gap-y-4">
            <h2 class="text-[clamp(1.85rem,7vw,2.625rem)] font-bold tracking-tight text-[#1e2652]">{{ $societyTitle }}</h2>
            @if ($societyActionLabel && $societyActionUrl)
                <div class="section-header__controls absolute top-4.5 flex gap-6 rtl:left-0 ltr:right-0">
                    <a href="{{ $societyActionUrl }}" class="flex h-[40px] w-[195px] items-center justify-center gap-3 rounded-[12px] bg-[#1e2652] text-center text-sm font-bold text-white transition-all hover:bg-opacity-90">{{ $societyActionLabel }}</a>
                </div>
            @endif
        </div>

        @if ($societyArticles !== [])
            <div class="grid grid-cols-1 gap-8 pb-10 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($societyArticles as $article)
                    <article class="reveal-item min-w-0">
                        <a href="{{ $article['url'] }}" class="group flex h-full flex-col overflow-hidden rounded-[25px] bg-white shadow-card-elevated transition-all duration-500 ease-out hover:-translate-y-2 hover:shadow-card-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red">
                            @if ($article['imageUrl'])
                                <div class="relative h-[210px] overflow-hidden">
                                    @php($eventSrcset = \App\Support\MediaUrlResolver::legacySrcset($article['imageUrl']))
                                    <img src="{{ $article['imageUrl'] }}" @if ($eventSrcset) srcset="{{ $eventSrcset }}" sizes="(max-width: 640px) 100vw, (max-width: 1280px) 50vw, 25vw" @endif alt="{{ $article['title'] }}" loading="lazy" decoding="async" width="400" height="210" class="content-media-image h-full w-full">
                                    @if ($article['categoryLabel'])
                                        <div class="absolute start-4 top-4 z-10 rounded-md bg-spu-blue px-4 py-1 text-[11px] font-bold text-white">{{ $article['categoryLabel'] }}</div>
                                    @endif
                                    <div class="absolute bottom-0 left-0 z-10 h-[3px] w-full bg-spu-red opacity-80 transition-transform duration-500 group-hover:scale-x-110"></div>
                                </div>
                            @endif
                            <div class="flex flex-1 flex-col p-6 text-center">
                                <h3 class="mb-1 text-[22px] font-bold leading-tight text-[#1B1B1F]">{{ $article['title'] }}</h3>
                                @if ($article['publishedAt'])
                                    <p class="mb-3 text-[15px] text-spu-red" translate="no">{{ $article['publishedAt'] }}</p>
                                @endif
                                @if ($article['excerpt'])
                                    <p class="line-clamp-3 text-[14px] leading-[1.6] text-gray-700">{{ $article['excerpt'] }}</p>
                                @endif
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
