<section x-data="researchSlider()" id="research-priorities" class="py-7.5 mt-[70px] bg-section font-hacen relative overflow-hidden reveal" style="content-visibility: auto; contain-intrinsic-size: auto 500px;">
    <div class="container">
        <div class="section-header relative">
            <h2 class="section-header__title text-[clamp(1.85rem,7vw,2.625rem)] font-bold text-spu-blue flex items-center gap-4 rtl:flex-row-reverse rtl:text-right ltr:text-left">{{ $section->payload->title }}</h2>
            <div class="section-header__controls flex gap-3 absolute top-0 z-50 rtl:left-0 ltr:right-0">
                @if ($section->payload->sectionAction)
                    <a href="{{ $section->payload->sectionAction->url }}" class="flex h-10 w-auto min-w-0 flex-1 items-center justify-center gap-3 rounded-[12px] bg-[#1e2652] text-center text-sm font-bold text-white transition-all hover:bg-opacity-90 md:w-full md:max-w-[195px] md:flex-none" @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>{{ $section->payload->sectionAction->label }}</a>
                @endif
                <button type="button" @click="slide('previous')" class="slider-nav-btn w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="research-priorities-track" aria-label="{{ __('public.previous') }}"><img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt=""></button>
                <button type="button" @click="slide('next')" class="slider-nav-btn w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="research-priorities-track" aria-label="{{ __('public.next') }}"><img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt=""></button>
            </div>
        </div>

        @if ($section->payload->researchItems !== [])
            <div id="research-priorities-track" x-ref="researchTrack" class="flex gap-8 pe-6 overflow-x-auto no-scrollbar scroll-smooth pb-10" style="will-change: scroll-position;" role="region" aria-roledescription="{{ __('public.carousel') }}" aria-label="{{ $section->payload->title }}" tabindex="0" @keydown="handleSliderKey($event)">
                @foreach ($section->payload->researchItems as $item)
                    <article class="reveal-item research-card relative flex h-[430px] w-[min(82vw,320px)] shrink-0 flex-col overflow-hidden rounded-[25px] bg-white shadow-[0_10px_30px_rgba(0,0,0,0.05)] group sm:w-[320px]" style="transform: translateZ(0);" role="group" aria-roledescription="{{ __('public.slide') }}" aria-label="{{ __('public.slide_position', ['current' => $loop->iteration, 'total' => count($section->payload->researchItems)]) }}">
                        @if ($item->url)
                            <a href="{{ $item->url }}" class="absolute inset-0 z-20 rounded-[25px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-spu-red" aria-label="{{ $locale === 'ar' ? 'عرض البحث: '.$item->title : 'View research: '.$item->title }}" data-research-card-link></a>
                        @endif
                        @if ($item->imageUrl)
                            <div class="relative h-[180px] shrink-0 overflow-hidden bg-gray-100">
                                <img src="{{ $item->imageUrl }}" alt="{{ $item->title }}" loading="lazy" decoding="async" width="320" height="180" class="h-full w-full object-cover" style="transform: translateZ(0);">
                                @if ($item->categoryLabel)
                                    <div class="absolute start-4 top-4 rounded-lg bg-spu-blue px-4 py-1.5 text-[11px] font-bold text-white">{{ $item->categoryLabel }}</div>
                                @endif
                            </div>
                        @endif
                        <div class="flex min-h-0 flex-1 flex-col items-start border-b-[3px] border-transparent p-5 transition-colors duration-200 group-hover:border-spu-red">
                            <div class="min-h-0 overflow-hidden">
                                <h3 class="line-clamp-4 text-[18px] font-bold leading-[1.35] text-spu-blue">{{ $item->title }}</h3>
                                @if ($item->summary)
                                    <p class="mt-3 line-clamp-2 text-[14px] leading-6 text-gray-500">{{ $item->summary }}</p>
                                @endif
                                @if ($item->authors !== [])
                                    <p class="mt-2 line-clamp-1 text-[12px] text-gray-400">{{ implode(' • ', $item->authors) }}</p>
                                @endif
                            </div>
                            @if ($item->url)
                                <div class="mt-auto pt-4">
                                    <span class="research-card__action">
                                        <span>{{ $locale === 'ar' ? 'عرض التفاصيل' : 'View Details' }}</span>
                                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-4 w-4 transition-transform duration-200 ease-in-out rtl:rotate-180" aria-hidden="true">
                                    </span>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
