@php
    $decodeHtmlEntities = static function (mixed $value) use (&$decodeHtmlEntities): mixed {
        if (is_string($value)) {
            return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (is_array($value)) {
            return array_map($decodeHtmlEntities, $value);
        }

        return $value;
    };

    $events = $decodeHtmlEntities($section->payload->events);
    $sectionTitle = $decodeHtmlEntities($section->payload->title);
    $calendarHighlights = $decodeHtmlEntities($section->payload->content['calendarHighlights'] ?? []);
    $eventCtaLabel = $decodeHtmlEntities($section->payload->content['eventCtaLabel'] ?? ($locale === 'ar' ? 'اكتشف التفاصيل' : 'Explore Details'));
@endphp

<section x-data="calendarApp()"
         data-events="{{ json_encode($events, JSON_THROW_ON_ERROR) }}"
         data-event-label="{{ __('public.show_event') }}"
         class="overflow-hidden bg-white py-16 font-hacen lg:py-20"
         @mouseenter="stopCarousel()"
         @mouseleave="startCarousel()"
         @focusin="stopCarousel()"
         @focusout="resumeCarousel($event)">
    <div class="container">
        <h2 class="mb-8 text-[34px] font-bold tracking-tight text-[#1e2652] sm:mb-10 sm:text-[42px] lg:text-[52px]">{{ $sectionTitle }}</h2>

        <div class="grid grid-cols-1 gap-10 xl:grid-cols-[minmax(0,560px)_minmax(0,1fr)] xl:items-stretch">
            <div class="overflow-hidden rounded-[20px] bg-white shadow-panel-elevated h-full flex flex-col relative" role="region" aria-roledescription="{{ __('public.carousel') }}" aria-label="{{ $sectionTitle }}" tabindex="0" @keydown="handleCarouselKey($event)">
                <template x-if="selectedEvent">
                    <article :key="activeEventIndex" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" class="flex flex-1 flex-col">
                        <div class="relative h-[220px] overflow-hidden md:h-[250px]">
                            <img :src="selectedEventImage()" :alt="selectedEventAlt()" class="content-media-image h-full w-full">
                            <div class="absolute inset-x-6 top-6 w-fit rounded-[10px] bg-[#27316d] px-7 py-2 text-[15px] font-bold text-white rtl:right-6 rtl:left-auto ltr:left-6 ltr:right-auto" x-text="selectedEvent.type"></div>
                        </div>
                        <div class="flex flex-1 flex-col bg-[#edf2fa] px-8 py-6 md:px-8 md:py-5">
                            <p class="mb-5 text-[15px] font-bold text-[#c63030]" translate="no" x-text="selectedEvent.dateText"></p>
                            <h3 class="mb-3 text-xl font-bold text-[#1e2652]" x-text="selectedEvent.title"></h3>
                            <p class="mb-6 text-[17px] leading-[1.65] text-[#55627c]" x-text="selectedEvent.description"></p>
                            <a :href="selectedEventLink()" class="inline-flex w-fit items-center gap-2 text-[18px] font-bold text-[#1e2652] transition-all ease-in-out delay-75 hover:text-spu-red">
                                 <span>{{ $eventCtaLabel }}</span>
                                <img src="/images/icon-chevron-right-outline.svg" class="w-2.5 h-2.5 rtl:rotate-180" alt="">
                            </a>
                            <div class="mt-auto pt-5">
                                <div class="border-t border-[#9c2a2a]/20"></div>
                                <div class="flex items-center justify-center gap-2 pt-7">
                                    <template x-for="(eventItem, index) in selectedDateEvents" :key="eventKey(eventItem, index)">
                                        <button type="button" @click="selectEvent(index)" class="h-[8px] rounded-full transition-all duration-300" :class="eventDotClass(index)" :aria-label="eventDotLabel(index)" :aria-current="isEventActive(index)"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </article>
                </template>

                <template x-if="!selectedEvent">
                    <div class="flex min-h-[440px] flex-col items-center justify-center bg-[#edf2fa] px-10 text-center">
                        <p class="mb-4 text-[15px] font-bold text-[#c63030]" x-text="selectedDateLabel"></p>
                        <p class="max-w-[360px] text-[18px] leading-[1.7] text-[#55627c]" x-text="noEventsLabel"></p>
                    </div>
                </template>
            </div>

            <div class="rounded-[28px] bg-white px-6 py-7 shadow-panel-elevated sm:px-8 sm:py-8 lg:px-7 xl:min-h-[440px] xl:px-8 h-full">
                <div class="mb-7 flex items-start justify-between gap-4">
                    <div class="flex items-end gap-5 text-[#1e2652]">
                        <span class="text-[21px] font-bold" x-text="monthLabel"></span>
                        <span class="text-[40px] font-black leading-none sm:text-[48px]" translate="no" x-text="selectedYear"></span>
                    </div>
                    <div class="flex items-center gap-2 text-[#111111]">
                        <button type="button" @click="prevMonth()" class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-gray-100"><img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="{{ __('public.previous') }}"></button>
                        <button type="button" @click="nextMonth()" class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-gray-100"><img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="{{ __('public.next') }}"></button>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-y-3 sm:gap-y-4">
                    <template x-for="day in calendarDays" :key="day.date">
                        <button type="button" @click="selectDate(day.date)" class="relative mx-auto flex aspect-square w-full max-w-[50px] items-center justify-center rounded-[14px] transition-colors after:absolute after:-inset-1 after:content-[''] sm:h-[56px] sm:w-[56px] sm:max-w-[56px]" :class="dayButtonClass(day)" :aria-label="dayLabel(day)" :aria-pressed="isDateSelected(day)">
                            <span class="text-[18px] font-semibold transition-colors sm:text-[20px]" translate="no" :class="dayNumberClass(day)" x-text="day.dayNumber"></span>
                            <span x-show="showEventMarker(day)" class="absolute bottom-[6px] left-1/2 h-[4px] w-[4px] -translate-x-1/2 rounded-full bg-[#27316d]"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-10">
            @foreach ($calendarHighlights as $highlight)
                <div class="inline-block rounded-2xl border border-slate-100 px-5 py-3 me-4 mb-4">
                    @if (! empty($highlight['label']))<p class="font-medium text-spu-blue">{{ $highlight['label'] }}</p>@endif
                    @if (! empty($highlight['date']))<p class="mt-1 text-sm text-slate-400">{{ $highlight['date'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
