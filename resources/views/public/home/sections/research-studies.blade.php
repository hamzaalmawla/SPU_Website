<section x-data="researchSlider()" id="research-priorities" class="py-7.5 mt-[70px] bg-section font-hacen relative overflow-hidden reveal" style="content-visibility: auto; contain-intrinsic-size: auto 500px;">
    <div class="container">
        <div class="section-header relative">
            <h2 class="section-header__title text-[42px] font-bold text-spu-blue flex items-center gap-4 rtl:flex-row-reverse rtl:text-right ltr:text-left">{{ $section->payload->title }}</h2>
            <div class="flex gap-3 absolute top-0 z-50 rtl:left-0 ltr:right-0">
                @if ($section->payload->sectionAction)
                    <a href="{{ $section->payload->sectionAction->url }}" class="bg-[#1e2652] text-white w-[195px] h-[40px] text-center justify-center rounded-[12px] text-sm font-bold flex items-center gap-3 hover:bg-opacity-90 transition-all" @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>{{ $section->payload->sectionAction->label }}</a>
                @endif
                <button type="button" @click="slide('left')" class="slider-nav-btn w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-label="{{ __('public.previous') }}"><img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt=""></button>
                <button type="button" @click="slide('right')" class="slider-nav-btn w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-label="{{ __('public.next') }}"><img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt=""></button>
            </div>
        </div>

        @if ($section->payload->researchItems !== [])
            <div x-ref="researchTrack" class="flex gap-8 overflow-x-auto no-scrollbar scroll-smooth pb-10" style="will-change: scroll-position;">
                @foreach ($section->payload->researchItems as $item)
                    <article class="reveal-item research-card w-[289px] h-[348.03px] shrink-0 relative bg-white rounded-[25px] shadow-[0_10px_30px_rgba(0,0,0,0.05)] overflow-hidden flex flex-col group" style="transform: translateZ(0);">
                        @if ($item->imageUrl)
                            <div class="relative h-[50%] overflow-hidden bg-gray-100">
                                <img src="{{ $item->imageUrl }}" alt="{{ $item->title }}" loading="lazy" decoding="async" width="289" height="174" class="w-full h-full object-cover" style="transform: translateZ(0);">
                                @if ($item->categoryLabel)
                                    <div class="absolute top-4 left-4 px-4 py-1.5 rounded-lg text-white text-[11px] font-bold bg-spu-blue">{{ $item->categoryLabel }}</div>
                                @endif
                            </div>
                        @endif
                        <div class="px-4 pt-2 h-[40%] flex flex-col justify-between items-start flex-1 border-b-[3px] border-transparent group-hover:border-spu-red transition-colors duration-200">
                            <div>
                                <h3 class="text-[18px] font-bold text-spu-blue mb-1 leading-tight">{{ $item->title }}</h3>
                                @if ($item->summary)
                                    <p class="text-gray-500 text-[14px] py-4 line-clamp-2">{{ $item->summary }}</p>
                                @endif
                                @if ($item->authors !== [])
                                    <p class="text-gray-400 text-[12px]">{{ implode(' • ', $item->authors) }}</p>
                                @endif
                            </div>
                            @if ($item->url)
                                <div class="mt-2 mb-2">
                                    <a href="{{ $item->url }}" class="research-card__action" @if (! empty($item->target)) target="{{ $item->target }}" rel="noreferrer" @endif>
                                        <span>{{ $locale === 'ar' ? 'عرض التفاصيل' : 'View Details' }}</span>
                                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="w-4 h-4 transition-transform duration-200 ease-in-out rtl:rotate-180" aria-hidden="true">
                                    </a>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
