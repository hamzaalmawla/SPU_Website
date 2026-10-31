@php
    $navCards = collect($navCards ?? [])->map(function ($card) use ($locale) {
        if (is_array($card)) {
            $title = $card['title'] ?? $card['label'] ?? '';
            $link = $card['link'] ?? $card['url'] ?? null;
        } else {
            $title = $card->label ?? '';
            $link = $card->url ?? null;
        }

        // The shared about navigation-card prepends "/{locale}" to the link,
        // so we strip any leading locale segment here to avoid a double-prefixed URL.
        if (is_string($link) && $link !== '#') {
            $link = preg_replace('#^/' . preg_quote($locale, '#') . '(?=/|$)#', '', $link);
        }

        return ['title' => $title, 'link' => $link];
    })->values();

    $navSlides = $navCards->chunk(4)->values();
    $navShouldSlide = $navCards->count() > 4;
    $navSectionId = $navSectionId ?? 'faculty-navigation';
    $navHeadingAr = $navHeadingAr ?? 'مسارات';
    $navHeadingEn = $navHeadingEn ?? 'Faculty';
    $navHighlightAr = $navHighlightAr ?? 'الكلية';
    $navHighlightEn = $navHighlightEn ?? 'Pathways';
@endphp

@if ($navCards->isNotEmpty())
    <section id="{{ $navSectionId }}" class="relative rounded-md bg-section py-20 font-hacen lg:py-28" @if ($navShouldSlide) x-data="aboutNavigation()" data-slide-count="{{ $navSlides->count() }}" @endif>
        <div class="container relative z-10">
            <div class="flex flex-col items-start gap-14 lg:flex-row lg:items-center lg:gap-20">
                <div class="w-full shrink-0 lg:w-[38%]">
                    <h2 class="text-[32px] font-bold leading-tight text-spu-blue md:text-[48px]">
                        @if ($locale === 'ar')
                            {{ $navHeadingAr }} <span class="text-spu-red">{{ $navHighlightAr }}</span>
                        @else
                            {{ $navHeadingEn }} <span class="text-spu-red">{{ $navHighlightEn }}</span>
                        @endif
                    </h2>
                </div>

                <div class="w-full min-w-0 flex-1">
                    @if (! $navShouldSlide)
                        <div class="cms-grid-cards gap-4">
                            @foreach ($navCards as $subPage)
                                @include('public.about.partials.navigation-card', ['subPage' => $subPage, 'locale' => $locale])
                            @endforeach
                        </div>
                    @else
                        <div>
                            <div class="overflow-hidden">
                                <div class="flex transition-transform duration-500 ease-out" :style="slideStyle()">
                                    @foreach ($navSlides as $slide)
                                        <div class="cms-grid-cards w-full shrink-0 gap-4">
                                            @foreach ($slide as $subPage)
                                                @include('public.about.partials.navigation-card', ['subPage' => $subPage, 'locale' => $locale])
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-6 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    @foreach ($navSlides as $slideIndex => $slide)
                                        <button type="button"
                                                class="h-1.5 rounded-full transition-all duration-300"
                                                :class="dotClass({{ $slideIndex }})"
                                                @click="goToSlide({{ $slideIndex }})"
                                                aria-label="{{ ($locale === 'ar' ? 'انتقل إلى مجموعة البطاقات ' : 'Go to navigation card group ') . ($slideIndex + 1) }}"></button>
                                    @endforeach
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white transition-colors hover:bg-slate-50" @click="previousSlide()" aria-label="{{ $locale === 'ar' ? 'السابق' : 'Previous' }}">
                                        <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                                    </button>
                                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white transition-colors hover:bg-slate-50" @click="nextSlide()" aria-label="{{ $locale === 'ar' ? 'التالي' : 'Next' }}">
                                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif