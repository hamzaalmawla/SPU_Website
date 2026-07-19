<section class="bg-white py-[92px] font-hacen lg:py-[110px]" dir="{{ $direction }}" aria-labelledby="faculty-research-title">
    <div class="container">
        <div class="mx-auto max-w-[1168px]">
            <h1 id="faculty-research-title" class="sr-only">
                {{ $subpage['title'] ?? ($isAr ? 'أحدث الأبحاث' : 'Latest Research') }}
            </h1>

            <div class="grid gap-[38px] lg:grid-cols-2">
                @forelse ($page->items as $publication)
                    <a href="/{{ $locale }}/research/publications/{{ $publication['slug'] ?? '' }}" class="group block overflow-hidden rounded-[10px] border border-[#d5d9e2] bg-white shadow-[0_4px_10px_rgba(0,0,0,0.16)] transition-all motion-safe:hover:-translate-y-1 hover:shadow-[0_8px_24px_rgba(32,39,89,0.12)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                        <img src="{{ $publication['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $publication['title'] ?? '' }}" class="h-[169px] w-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-105">
                        <div class="px-[18px] pb-[20px] pt-[20px]">
                            <div class="flex flex-wrap gap-[7px]">
                                @foreach ([$publication['type'] ?? null, $publication['year'] ?? null] as $tag)
                                    @if ($tag)
                                        <span class="rounded-[5px] border border-[#c8ceda] px-[12px] py-[5px] text-[9px] font-bold text-spu-blue">{{ $tag }}</span>
                                    @endif
                                @endforeach
                            </div>
                            <h2 class="mt-[14px] text-[22px] font-bold leading-tight text-spu-blue transition-colors group-hover:text-spu-red">{{ $publication['title'] ?? '' }}</h2>
                            @if (! empty($publication['summary']))
                                <p class="mt-[10px] max-w-[500px] text-[16px] leading-[1.75] text-[#263650]">{{ $publication['summary'] }}</p>
                            @endif
                            <span class="mt-[20px] inline-flex h-[27px] min-w-[87px] items-center justify-center rounded-[6px] bg-spu-blue px-4 text-[8px] font-bold text-white transition group-hover:bg-[#171d47]">{{ $isAr ? 'عرض المنشور' : 'View Publication' }}</span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-[10px] border border-slate-200 bg-section p-8 lg:col-span-2">
                        <h2 class="text-xl font-bold text-spu-blue">{{ $subpage['emptyTitle'] ?? ($isAr ? 'لا توجد منشورات حالياً' : 'No publications available') }}</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $subpage['emptySummary'] ?? ($isAr ? 'ستظهر منشورات الكلية هنا عند نشرها.' : 'Faculty publications will appear here when published.') }}</p>
                    </div>
                @endforelse
            </div>

            @if (($pagination['total_pages'] ?? 1) > 1)
                @php
                    $currentPage = (int) ($pagination['current_page'] ?? 1);
                    $totalPages = (int) ($pagination['total_pages'] ?? 1);
                    $firstPage = max(1, $currentPage - 2);
                    $lastPage = min($totalPages, $currentPage + 2);
                @endphp
                <nav class="mt-12 flex flex-wrap items-center justify-center gap-2" aria-label="{{ $isAr ? 'ترقيم صفحات الأبحاث' : 'Research pagination' }}">
                    @if ($currentPage > 1)
                        <a href="{{ $pageUrl($currentPage - 1) }}" aria-label="{{ $isAr ? 'الصفحة السابقة' : 'Previous page' }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 text-spu-blue transition hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                            <img src="/images/icon-arrow-right-outline.svg" alt="" aria-hidden="true" class="h-3 w-3 rotate-180 rtl:rotate-0">
                        </a>
                    @endif

                    @for ($pageNumber = $firstPage; $pageNumber <= $lastPage; $pageNumber++)
                        <a href="{{ $pageUrl($pageNumber) }}" @if ($pageNumber === $currentPage) aria-current="page" @endif class="inline-flex h-10 min-w-10 items-center justify-center rounded-[6px] border px-3 text-xs font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue {{ $pageNumber === $currentPage ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue hover:border-spu-blue' }}">
                            {{ $pageNumber }}
                        </a>
                    @endfor

                    @if ($currentPage < $totalPages)
                        <a href="{{ $pageUrl($currentPage + 1) }}" aria-label="{{ $isAr ? 'الصفحة التالية' : 'Next page' }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 text-spu-blue transition hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                            <img src="/images/icon-arrow-right-outline.svg" alt="" aria-hidden="true" class="h-3 w-3 rtl:rotate-180">
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </div>
</section>
