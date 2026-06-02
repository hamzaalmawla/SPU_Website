@php
    $honorItems = array_map(static function (array $item): array {
        $item['image'] = $item['image'] ?? ($item['imageUrl'] ?? '/images/slider-1.webp');
        return $item;
    }, $section->payload->items);
@endphp
<script>window.spuHonorItems = @json($honorItems);</script>

<section x-data="honorPanel()" class="py-16 bg-white font-hacen relative overflow-hidden reveal">
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
            <template x-for="(item, index) in items" :key="item.id || index">
                <div class="absolute transition-all duration-[1800ms] [transition-timing-function:cubic-bezier(0.25,1,0.5,1)] rounded-[40px] overflow-hidden group" :class="{'w-full lg:w-[65%] h-full z-30 left-0 top-0 opacity-100 shadow-2xl scale-100': getPos(index) === 0, 'w-0 lg:w-[32%] h-[48%] z-20 left-0 lg:left-[68%] top-0 opacity-0 lg:opacity-100 scale-95 brightness-90': getPos(index) === 1, 'w-0 lg:w-[32%] h-[48%] z-10 left-0 lg:left-[68%] top-[52%] opacity-0 lg:opacity-100 scale-90 brightness-75': getPos(index) === 2}">
                    <img :src="item.image" :alt="item.title || ''" class="absolute inset-0 w-full h-full object-cover transition-transform duration-[3000ms] group-hover:scale-110">
                    <div x-show="getPos(index) === 0" x-transition:enter="transition ease-out duration-[1200ms] delay-[600ms]" x-transition:enter-start="opacity-0 translate-y-12" x-transition:enter-end="opacity-100 translate-y-0" class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/40 to-transparent flex flex-col justify-end p-10 text-white">
                        <div class="flex items-center gap-3 mb-4">
                            <template x-if="item.typeTag"><span class="honor-panel-pill px-4 py-1 rounded-full bg-spu-red text-[10px] font-bold uppercase" x-text="item.typeTag"></span></template>
                            <template x-if="item.meta"><span class="text-white/70 text-sm" x-text="item.meta"></span></template>
                        </div>
                        <h3 class="text-2xl lg:text-4xl font-bold mb-4" x-text="item.title"></h3>
                        <template x-if="item.summary"><p class="text-white/80 leading-relaxed mb-6 line-clamp-2" x-text="item.summary"></p></template>
                        <template x-if="item.action && item.action.url">
                            <a :href="item.action.url" class="honor-panel-cta inline-flex items-center gap-3 font-bold group/link" x-bind:target="item.action.target || null" x-bind:rel="item.action.target ? 'noreferrer' : null">
                                <span class="border-b-2 border-spu-red pb-1" x-text="item.action.label"></span>
                                <img src="/images/icon-arrow-right-outline.svg" class="w-4 h-4 brightness-0 invert transition-transform group-hover/link:translate-x-2 rtl:rotate-180" alt="">
                            </a>
                        </template>
                    </div>
                    <div x-show="getPos(index) !== 0" class="absolute inset-0 bg-black/30 hover:bg-black/10 transition-colors cursor-pointer" @click="handleManual('goto', index)"></div>
                </div>
            </template>
        </div>

        <div class="flex justify-center gap-3 mt-10">
            <template x-for="(item, index) in items" :key="'dot-' + (item.id || index)">
                <button @click="handleManual('goto', index)" class="h-2 rounded-full transition-all duration-1000" :class="activeIndex === index ? 'w-10 bg-spu-red' : 'w-2 bg-slate-200'" :aria-label="'Item ' + (index + 1)"></button>
            </template>
        </div>
    </div>
</section>
