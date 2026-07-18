@php
    $aboutNavigationCards = collect($aboutNavigationCards ?? []);
    $aboutNavigationSlides = $aboutNavigationCards->chunk(4)->values();
    $aboutNavigationShouldSlide = $aboutNavigationCards->count() > 4;
@endphp

@if ($aboutNavigationCards->isNotEmpty())
    <section id="about-navigation"
             class="relative rounded-md bg-section py-20 font-hacen lg:py-28"
             role="region"
             aria-label="{{ $locale === 'ar' ? 'روابط صفحات عن الجامعة' : 'Related About pages' }}"
             @if ($aboutNavigationShouldSlide)
                 x-data="aboutNavigation()"
                 data-slide-count="{{ $aboutNavigationSlides->count() }}"
                 tabindex="0"
                  @keydown.left.prevent="onArrowLeft()"
                  @keydown.right.prevent="onArrowRight()"
                  @pointerdown.passive="startSwipe($event)"
                  @pointerup.passive="endSwipe($event)"
             @endif>
        <div class="container relative z-10">
            <div class="flex flex-col items-start gap-14 lg:flex-row lg:items-center lg:gap-20">
                <div class="w-full shrink-0 lg:w-[38%]">
                    <h2 class="text-[32px] font-bold leading-tight text-spu-blue md:text-[48px]">
                        @if ($locale === 'ar')
                            استكشف المزيد عن <span class="text-spu-red">الجامعة</span>
                        @else
                            Learn More About <span class="text-spu-red">SPU</span>
                        @endif
                    </h2>
                </div>

                <div class="w-full min-w-0 flex-1">
                    @if (! $aboutNavigationShouldSlide)
                        <div class="cms-grid-cards gap-4">
                            @foreach ($aboutNavigationCards as $subPage)
                                @include('public.about.partials.navigation-card', ['subPage' => $subPage, 'locale' => $locale])
                            @endforeach
                        </div>
                    @else
                        <div>
                            <div class="overflow-hidden">
                                <div id="about-navigation-track" class="flex transition-transform duration-500 ease-out motion-reduce:transition-none" :style="slideStyle()">
                                    @foreach ($aboutNavigationSlides as $slide)
                                        <div class="cms-grid-cards w-full shrink-0 gap-4"
                                             :aria-hidden="activeSlide !== {{ $loop->index }}"
                                              :inert="activeSlide !== {{ $loop->index }}"
                                              style="touch-action: pan-y;">
                                            @foreach ($slide as $subPage)
                                                @include('public.about.partials.navigation-card', ['subPage' => $subPage, 'locale' => $locale])
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-6 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    @foreach ($aboutNavigationSlides as $slideIndex => $slide)
                                        <button type="button"
                                                 class="h-1.5 rounded-full transition-all duration-300"
                                                 :class="dotClass({{ $slideIndex }})"
                                                 @click="goToSlide({{ $slideIndex }})"
                                                 :aria-current="activeSlide === {{ $slideIndex }} ? 'true' : null"
                                                 aria-controls="about-navigation-track"
                                                 aria-label="{{ ($locale === 'ar' ? 'انتقل إلى مجموعة البطاقات ' : 'Go to navigation card group ') . ($slideIndex + 1) }}"></button>
                                    @endforeach
                                </div>

                                <div class="flex items-center gap-3">
                                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue" @click="previousSlide()" aria-controls="about-navigation-track" aria-label="{{ $locale === 'ar' ? 'السابق' : 'Previous' }}">
                                        <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                                    </button>
                                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue" @click="nextSlide()" aria-controls="about-navigation-track" aria-label="{{ $locale === 'ar' ? 'التالي' : 'Next' }}">
                                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                                    </button>
                                </div>
                            </div>
                            <p class="sr-only" aria-live="polite" x-text="'{{ $locale === 'ar' ? 'مجموعة' : 'Group' }} ' + (activeSlide + 1) + ' {{ $locale === 'ar' ? 'من' : 'of' }} ' + (maxSlide + 1)"></p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
