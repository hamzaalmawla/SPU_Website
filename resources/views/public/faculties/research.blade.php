<section class="bg-white py-[92px] font-hacen lg:py-[110px]" dir="{{ $direction }}" aria-labelledby="faculty-research-title">
    <div class="container">
        <div class="mx-auto max-w-[1168px]">
            <h1 id="faculty-research-title" class="sr-only">
                {{ $subpage['title'] ?? ($isAr ? 'أحدث الأبحاث' : 'Latest Research') }}
            </h1>

            @if (trim((string) ($subpage['body'] ?? '')) !== '')
                <x-public.rich-text class="mx-auto mb-12 max-w-[860px] text-[15px] leading-8 text-slate-600" :text="$subpage['body']" />
            @endif

            @php
                $researchYears = $filterOptions['years'] ?? [];
                $researchTotal = (int) ($filterOptions['total'] ?? 0);
                $researchFiltered = ($filters['q'] ?? '') !== '' || ($filters['year'] ?? '') !== '';
            @endphp
            @if ($researchTotal > 0)
                {{-- Search and year narrow this faculty's publications only; they work together. --}}
                <form method="GET" action="{{ $studentListUrl }}" role="search" data-faculty-list-search aria-label="{{ $isAr ? 'البحث في أبحاث الكلية' : 'Search this faculty\'s research' }}" class="mb-6 flex flex-wrap items-center gap-4">
                    <label class="relative block w-full sm:w-auto sm:min-w-[320px]">
                        <span class="sr-only">{{ $isAr ? 'ابحث في أبحاث الكلية' : 'Search this faculty\'s research' }}</span>
                        <img src="/images/icon-search-outline.svg" alt="" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 opacity-70 rtl:left-auto rtl:right-4" aria-hidden="true">
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $isAr ? 'ابحث بعنوان البحث أو الباحث أو الكلمات المفتاحية...' : 'Search by title, author or keyword...' }}" class="h-10 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white ps-11 pe-4 text-[13px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue">
                    </label>
                    @if ($researchYears !== [])
                        <label class="block w-full sm:w-auto">
                            <span class="sr-only">{{ $isAr ? 'سنة النشر' : 'Publication year' }}</span>
                            <select name="year" data-autosubmit class="h-10 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[13px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[150px]">
                                <option value="">{{ $isAr ? 'كل السنوات' : 'All years' }}</option>
                                @foreach ($researchYears as $year)
                                    <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-[6px] bg-spu-red px-5 text-[12px] font-bold text-white transition-colors hover:bg-spu-blue">{{ $isAr ? 'بحث' : 'Search' }}</button>
                    @if ($researchFiltered)
                        <a href="{{ $studentListUrl }}" class="inline-flex h-10 items-center justify-center rounded-[6px] border border-slate-200 px-5 text-[12px] font-bold text-spu-blue transition-colors hover:border-spu-blue">{{ $isAr ? 'الكل' : 'All' }}</a>
                    @endif
                </form>
                <p class="mb-8 text-[12px] font-semibold text-slate-500" aria-live="polite">
                    @if ($researchFiltered)
                        {{ $isAr ? 'النتائج: '.($pagination['total_items'] ?? 0).' من أصل '.$researchTotal : ($pagination['total_items'] ?? 0).' of '.$researchTotal.' publications' }}
                    @else
                        {{ $isAr ? 'عدد الأبحاث: '.$researchTotal : $researchTotal.' publications' }}
                    @endif
                </p>
            @endif

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
                            <h2 dir="auto" class="card-title mt-[14px] text-[22px] font-bold leading-tight text-spu-blue transition-colors group-hover:text-spu-red">{{ $publication['title'] ?? '' }}</h2>
                            @if (! empty($publication['summary']))
                                <x-public.rich-text dir="auto" class="mt-[10px] max-w-[500px] text-[16px] leading-[1.75] text-[#263650]" :text="$publication['summary']" />
                            @endif
                            <span class="mt-[20px] inline-flex h-[27px] min-w-[87px] items-center justify-center rounded-[6px] bg-spu-blue px-4 text-[8px] font-bold text-white transition group-hover:bg-[#171d47]">{{ $isAr ? 'عرض المنشور' : 'View Publication' }}</span>
                        </div>
                    </a>
                @empty
                    @if ($researchFiltered && $researchTotal > 0)
                        <div class="rounded-[10px] border border-slate-200 bg-section p-8 text-center lg:col-span-2">
                            <h2 class="text-xl font-bold text-spu-blue">{{ $isAr ? 'لا توجد أبحاث مطابقة' : 'No matching research' }}</h2>
                            <p class="mt-2 text-sm text-slate-600">{{ $isAr ? 'لا توجد أبحاث لهذه الكلية تطابق البحث. جرّب كلمات أخرى أو سنة أخرى.' : 'No research of this faculty matches. Try other words or another year.' }}</p>
                            <a href="{{ $studentListUrl }}" class="mt-5 inline-flex rounded-[6px] bg-spu-red px-5 py-2.5 text-xs font-bold text-white">{{ $isAr ? 'عرض كل الأبحاث' : 'Show all research' }}</a>
                        </div>
                    @else
                    <div class="rounded-[10px] border border-slate-200 bg-section p-8 lg:col-span-2">
                        <h2 class="text-xl font-bold text-spu-blue">{{ $subpage['emptyTitle'] ?? ($isAr ? 'لا توجد منشورات حالياً' : 'No publications available') }}</h2>
                        <x-public.rich-text class="mt-3 text-sm leading-7 text-slate-600" :text="$subpage['emptySummary'] ?? ($isAr ? 'ستظهر منشورات الكلية هنا عند نشرها.' : 'Faculty publications will appear here when published.')" />
                    </div>
                    @endif
                @endforelse
            </div>

            <x-public.pagination :current-page="$pagination['current_page'] ?? 1" :total-pages="$pagination['total_pages'] ?? 1" :page-url="$pageUrl" :locale="$locale" :label="$isAr ? 'ترقيم صفحات الأبحاث' : 'Research pagination'" />
        </div>
    </div>
</section>
