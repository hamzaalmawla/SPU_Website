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

    $honorItems = array_map(static function (array $item) use ($decodeHtmlEntities): array {
        $item['image'] = $item['image'] ?? ($item['imageUrl'] ?? '/images/slider-1.webp');
        return $decodeHtmlEntities($item);
    }, $section->payload->items);

    $sectionEyebrow = $decodeHtmlEntities($section->payload->eyebrow);
    $sectionTitle = $decodeHtmlEntities($section->payload->title);
@endphp

<section x-data="honorPanel()"
         data-items="{{ json_encode($honorItems, JSON_THROW_ON_ERROR) }}"
         data-item-label="{{ __('public.show_item') }}"
         class="py-16 bg-white font-hacen relative overflow-hidden reveal"
         role="region"
         aria-roledescription="{{ __('public.carousel') }}"
         aria-label="{{ $sectionTitle }}"
         @keydown="handleKey($event)"
         @mouseenter="stopAuto()"
         @mouseleave="startAuto()"
         @focusin="stopAuto()"
         @focusout="resumeAuto($event)">
    <div class="container">
        <div class="flex items-end justify-between mb-12">
            <div>
                @if ($sectionEyebrow)
                    <p class="text-spu-red font-bold tracking-widest text-xs uppercase mb-2">{{ $sectionEyebrow }}</p>
                @endif
                <h2 class="text-3xl lg:text-4xl font-bold text-spu-blue">{{ $sectionTitle }}</h2>
            </div>
            <div class="flex gap-3">
                <button @click="handleManual('prev')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="honor-panels" aria-label="{{ __('public.previous') }}"><img src="/images/icon-chevron-left-outline.svg" class="w-4 h-4 rtl:rotate-180" alt="" width="24" height="24" loading="lazy" decoding="async"></button>
                <button @click="handleManual('next')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="honor-panels" aria-label="{{ __('public.next') }}"><img src="/images/icon-chevron-right-outline.svg" class="w-4 h-4 rtl:rotate-180" alt="" width="24" height="24" loading="lazy" decoding="async"></button>
            </div>
        </div>

        <div id="honor-panels" class="relative h-[480px] md:h-[500px] w-full">
            <template x-for="(item, index) in items" :key="itemKey(item, index)">
                <div class="absolute transition-all duration-[1800ms] [transition-timing-function:cubic-bezier(0.25,1,0.5,1)] rounded-[28px] md:rounded-[40px] overflow-hidden group" :class="panelClass(index)" role="group" aria-roledescription="{{ __('public.slide') }}" :aria-label="itemLabel(index)" :aria-hidden="isHidden(index)">
                    <img :src="item.image" :alt="itemAlt(item)" class="content-media-image content-media-image--dark absolute inset-0 h-full w-full" width="560" height="500" loading="lazy" decoding="async">
                    <div x-show="isPrimary(index)" x-transition:enter="transition ease-out duration-[1200ms] delay-[600ms]" x-transition:enter-start="opacity-0 translate-y-12" x-transition:enter-end="opacity-100 translate-y-0" class="absolute inset-0 flex flex-col justify-between p-6 md:p-10 text-white">
                        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/45 to-transparent pointer-events-none"></div>

                        <div class="relative flex items-start">
                            <template x-if="item.typeTag"><span class="honor-panel-pill text-white/95 px-3.5 py-1.5 md:px-4 rounded-full bg-spu-red/95 text-[9px] md:text-[10px] font-bold uppercase tracking-wider whitespace-nowrap shadow-card-elevated" x-text="item.typeTag"></span></template>
                        </div>

                        <div class="relative">
                            <template x-if="item.meta"><p class="text-white/75 text-xs md:text-sm font-semibold tracking-wide mb-2 md:mb-3" x-text="item.meta"></p></template>
                            <h3 class="text-xl sm:text-2xl lg:text-4xl font-bold leading-snug text-balance line-clamp-3 mb-2.5 md:mb-4" x-text="item.title"></h3>
                            <template x-if="item.summary"><p class="text-white/80 text-sm md:text-base leading-relaxed mb-4 md:mb-6 line-clamp-2" x-text="item.summary"></p></template>
                            <template x-if="hasAction(item)">
                                <a :href="item.action.url" class="honor-panel-cta text-white/90 inline-flex items-center gap-2.5 md:gap-3 text-sm md:text-base font-bold group/link" x-bind:target="actionTarget(item)" x-bind:rel="actionRel(item)">
                                    <span class="border-b-2 border-spu-red pb-1" x-text="item.action.label"></span>
                                    <img src="/images/icon-arrow-right-outline.svg" class="w-4 h-4 brightness-0 invert transition-transform group-hover/link:translate-x-2 rtl:rotate-180" alt="" width="24" height="24" loading="lazy" decoding="async">
                                </a>
                            </template>
                        </div>
                    </div>
                    <button x-show="isSecondary(index)" type="button" class="absolute inset-0 hidden bg-black/30 hover:bg-black/10 transition-colors cursor-pointer focus-visible:outline focus-visible:outline-4 focus-visible:-outline-offset-4 focus-visible:outline-white lg:block" @click="handleManual('goto', index)" :aria-label="itemLabel(index)"></button>
                </div>
            </template>
        </div>

        <div class="flex justify-center gap-3 mt-10">
            <template x-for="(item, index) in items" :key="dotKey(item, index)">
                <button type="button" @click="handleManual('goto', index)" class="h-2 rounded-full transition-all duration-1000" :class="dotClass(index)" :aria-label="itemLabel(index)" :aria-current="isPrimary(index)"></button>
            </template>
        </div>
    </div>
</section>
