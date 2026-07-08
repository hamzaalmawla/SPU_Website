<a href="/{{ $locale }}/research/publications/{{ $publication['slug'] ?? '' }}"
   class="group block overflow-hidden rounded-[10px] border border-[#d5d9e2] bg-white shadow-[0_4px_10px_rgba(0,0,0,0.16)] transition-all hover:-translate-y-1 hover:shadow-[0_8px_24px_rgba(32,39,89,0.12)]">
    <img src="{{ $publication['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $publication['title'] ?? '' }}" class="h-[169px] w-full object-cover transition-transform duration-500 group-hover:scale-105">
    <div class="px-[18px] pb-[20px] pt-[20px]">
        <div class="flex flex-wrap gap-[7px]">
            @foreach ([$publication['type'] ?? null, $publication['faculty'] ?? null, $publication['author'] ?? null, $publication['year'] ?? null] as $tag)
                @if ($tag)
                    <span class="rounded-[5px] border border-[#c8ceda] px-[12px] py-[5px] text-[9px] font-bold text-spu-blue">{{ $tag }}</span>
                @endif
            @endforeach
            @if ($publication['isOpenAccess'] ?? false)
                <span class="rounded-[5px] bg-green-100 px-[12px] py-[5px] text-[9px] font-bold text-green-700">{{ $locale === 'ar' ? 'وصول مفتوح' : 'OA' }}</span>
            @endif
        </div>
        <h2 class="mt-[14px] text-[22px] font-bold leading-tight text-spu-blue transition-colors group-hover:text-spu-red">{{ $publication['title'] ?? '' }}</h2>
        @if (! empty($publication['summary']))
            <p class="mt-[10px] max-w-[500px] text-[16px] leading-[1.75] text-[#263650]">{{ $publication['summary'] }}</p>
        @endif
        @if (! empty($publication['publisher']))
            <p class="mt-[10px] text-[12px] font-bold uppercase tracking-[0.08em] text-[#6f7280]">{{ $publication['publisher'] }}</p>
        @endif
        <div class="mt-[20px] flex flex-wrap gap-[8px]">
            <span class="inline-flex h-[27px] min-w-[87px] items-center justify-center rounded-[6px] bg-spu-blue px-4 text-[8px] font-bold text-white transition group-hover:bg-[#171d47]">{{ $locale === 'ar' ? 'عرض المنشور' : 'View Publication' }}</span>
        </div>
    </div>
</a>
