@php
    $honorItems = array_map(static function (array $item): array {
        $item['image'] = $item['image'] ?? ($item['imageUrl'] ?? '/images/slider-1.webp');
        return $item;
    }, $section->payload->items);
@endphp

<section x-data="honorPanel()" data-items="{{ json_encode($honorItems, JSON_THROW_ON_ERROR) }}" class="py-16 bg-white font-hacen relative overflow-hidden reveal">
    <div class="container">
        <div class="flex items-end justify-between mb-12">
            <div>
                @if ($section->payload->eyebrow)
                    <p class="text-spu-red font-bold tracking-widest text-xs uppercase mb-2">{{ $section->payload->eyebrow }}</p>
                @endif
                <h2 class="text-3xl lg:text-4xl font-bold text-spu-blue">{{ $section->payload->title }}</h2>
            </div>
            <div class="flex gap-3">
                <button @click="handleManual('prev')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"><img src="/images/icon-chevron-left-outline.svg" class="w-4 h-4 rtl:rotate-180" alt="{{ __('public.previous') }}"></button>
                <button @click="handleManual('next')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"><img src="/images/icon-chevron-right-outline.svg" class="w-4 h-4 rtl:rotate-180" alt="{{ __('public.next') }}"></button>
            </div>
        </div>

        <div class="relative h-[500px] w-full">
            <template x-for="(item, index) in items" :key="itemKey(item, index)">
                <div class="absolute transition-all duration-[1800ms] [transition-timing-function:cubic-bezier(0.25,1,0.5,1)] rounded-[40px] overflow-hidden group" :class="panelClass(index)">
                    <img :src="item.image" :alt="itemAlt(item)" class="absolute inset-0 w-full h-full object-cover transition-transform duration-[3000ms] group-hover:scale-110">
                    <div x-show="isPrimary(index)" x-transition:enter="transition ease-out duration-[1200ms] delay-[600ms]" x-transition:enter-start="opacity-0 translate-y-12" x-transition:enter-end="opacity-100 translate-y-0" class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/40 to-transparent flex flex-col justify-end p-10 text-white">
                        <div class="flex items-center gap-3 mb-4">
                            <template x-if="item.typeTag"><span class="honor-panel-pill px-4 py-1 rounded-full bg-spu-red text-[10px] font-bold uppercase" x-text="item.typeTag"></span></template>
                            <template x-if="item.meta"><span class="text-white/70 text-sm" x-text="item.meta"></span></template>
                        </div>
                        <h3 class="text-2xl lg:text-4xl font-bold mb-4" x-text="item.title"></h3>
                        <template x-if="item.summary"><p class="text-white/80 leading-relaxed mb-6 line-clamp-2" x-text="item.summary"></p></template>
                        <template x-if="hasAction(item)">
                            <a :href="item.action.url" class="honor-panel-cta inline-flex items-center gap-3 font-bold group/link" x-bind:target="actionTarget(item)" x-bind:rel="actionRel(item)">
                                <span class="border-b-2 border-spu-red pb-1" x-text="item.action.label"></span>
                                <img src="/images/icon-arrow-right-outline.svg" class="w-4 h-4 brightness-0 invert transition-transform group-hover/link:translate-x-2 rtl:rotate-180" alt="">
                            </a>
                        </template>
                    </div>
                    <div x-show="isSecondary(index)" class="absolute inset-0 bg-black/30 hover:bg-black/10 transition-colors cursor-pointer" @click="handleManual('goto', index)"></div>
                </div>
            </template>
        </div>

        <div class="flex justify-center gap-3 mt-10">
            <template x-for="(item, index) in items" :key="dotKey(item, index)">
                <button @click="handleManual('goto', index)" class="h-2 rounded-full transition-all duration-1000" :class="dotClass(index)" :aria-label="itemLabel(index)"></button>
            </template>
        </div>
    </div>
</section>
