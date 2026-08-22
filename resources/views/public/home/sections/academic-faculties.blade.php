<section x-data="facultiesSlider()" class="mt-[60px] md:mt-[120px] bg-white font-hacen relative">
    <div class="container relative">
        <div class="flex flex-col md:flex-row items-center gap-[52px] relative">
            <div class="w-full relative md:w-[322px] min-h-[435px] text-center bg-[#1e2652] rounded-[24px] flex flex-col justify-center items-start text-white shrink-0 overflow-hidden group z-20 shadow-panel">
                <div class="absolute inset-0 opacity-[0.15] z-0 animate-slow-pan" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 30px 30px;"></div>
                <div class="relative z-10 w-full px-6 flex flex-col h-full justify-center text-right">
                    @php($facultyTitleParts = preg_split('/\s+/u', (string) $section->payload->title, 2))
                    <h2 class="mb-12 w-full text-center text-[clamp(2rem,10vw,2.8125rem)] font-bold leading-tight transition-all duration-500">
                        <span class="sr-only">{{ $section->payload->title }}</span>
                        <span>{{ $facultyTitleParts[0] ?? $section->payload->title }}</span>
                        @if (! empty($facultyTitleParts[1]))
                            <br>
                            <span class="text-white">{{ $facultyTitleParts[1] }}</span>
                        @endif
                    </h2>
                    @if ($section->payload->sectionAction)
                        <a href="{{ $section->payload->sectionAction->url }}" class="bg-white mx-auto absolute inset-x-0 bottom-10 w-[195px] h-[40px] text-spu-blue justify-center rounded-[10px] font-bold text-[16px] flex items-center gap-2 hover:bg-gray-100 transition-all shadow-card-elevated group/btn overflow-hidden" @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>
                            <span>{{ $section->payload->sectionAction->label }}</span>
                            <img src="/images/icon-arrow-right-outline.svg" class="w-2.5 h-2.5 mt-1 transition-transform group-hover:translate-x-1 rtl:rotate-180" alt="">
                        </a>
                    @endif
                </div>
            </div>

            <div class="flex-1 min-w-0 w-full relative">
                <div class="relative z-50 mb-4 flex justify-end gap-3 md:absolute md:-top-20 md:mb-0 md:ltr:right-0 md:rtl:left-0">
                    <button @click="slideFaculties('previous')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="academic-faculties-track" aria-label="{{ __('public.previous') }}">
                        <img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                    </button>
                    <button @click="slideFaculties('next')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="academic-faculties-track" aria-label="{{ __('public.next') }}">
                        <img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                    </button>
                </div>

                <div id="academic-faculties-track" x-ref="facultiesTrack" class="flex min-h-[390px] h-auto w-full snap-x snap-mandatory flex-nowrap gap-6 bg-transparent overflow-x-auto overflow-y-visible no-scrollbar scroll-smooth overscroll-x-contain ps-2 pe-6 pb-5 items-stretch z-10" role="region" aria-roledescription="{{ __('public.carousel') }}" aria-label="{{ $section->payload->title }}" tabindex="0" @keydown="handleSliderKey($event)">
                    @foreach ($section->payload->items as $item)
                        <article @mouseenter="setActiveFaculty({{ $loop->index }})" @mouseleave="clearActiveFaculty()" :class="facultyCardClass({{ $loop->index }})" class="faculty-card snap-start w-[292px] min-h-[380px] h-auto shrink-0 relative bg-white rounded-[24px] border border-gray-100 shadow-card-sm flex flex-col items-center justify-between text-center transition-all duration-300 group overflow-hidden" role="group" aria-roledescription="{{ __('public.slide') }}" aria-label="{{ __('public.slide_position', ['current' => $loop->iteration, 'total' => count($section->payload->items)]) }}">
                            @if (! empty($item['accent']))
                                <div class="absolute top-0 left-0 w-full h-0 group-hover:h-[6px] z-50 transition-all duration-300 ease-in-out" style="background-color: {{ $item['accent'] }};"></div>
                            @endif
                            @php($image = $item['imageUrl'] ?? ($item['image'] ?? null))
                            @if ($image)
                                <div class="relative w-[160px] h-[160px] mt-6 mb-4 flex items-center justify-center">
                                    <img src="{{ $image }}" alt="{{ $item['title'] ?? '' }}" loading="lazy" decoding="async" width="110" height="110" class="relative z-10 w-[110px] h-[110px] object-contain transition-transform duration-500">
                                </div>
                            @endif
                            <div class="flex flex-1 items-center px-4 mb-4">
                                <h3 class="text-[20px] font-bold leading-tight transition-colors duration-300 text-gray-800">{{ $item['title'] ?? '' }}</h3>
                            </div>
                            @if (! empty($item['metric']))
                                <div class="px-10 py-2.5 rounded-[8px] text-white font-bold text-[12px] mb-6 shadow-card-sm" @if (! empty($item['accent'])) style="background-color: {{ $item['accent'] }};" @endif>{{ $item['metric'] }}</div>
                            @endif
                            @if (! empty($item['action']['url']) && ! empty($item['action']['label']))
                                <a href="{{ $item['action']['url'] }}" class="mt-auto mb-6 flex items-center gap-2 text-[13px] font-extrabold transition-all duration-300 group-hover:gap-3" @if (! empty($item['accent'])) style="color: {{ $item['accent'] }};" @endif @if (! empty($item['action']['target'])) target="{{ $item['action']['target'] }}" rel="noreferrer" @endif>
                                    <span>{{ $item['action']['label'] }}</span>
                                    <img src="/images/icon-arrow-right-outline.svg" class="w-2.5 h-2.5 opacity-70 group-hover:opacity-100 transition-all rtl:rotate-180" alt="">
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
