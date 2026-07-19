@php($isProjectPreview = ($isPreview ?? false) && isset($preview) && (($preview->payload->cms['target_key'] ?? null) === 'research.projects'))
<a href="{{ $isProjectPreview ? '/'.$locale.'/preview?token='.$preview->token.'&project='.urlencode((string) ($project['slug'] ?? '')) : '/'.$locale.'/research/projects/'.($project['slug'] ?? '') }}"
   class="group block overflow-hidden rounded-[10px] border border-[#d5d9e2] bg-white shadow-[0_4px_10px_rgba(0,0,0,0.16)] transition-all hover:-translate-y-1 hover:shadow-[0_8px_24px_rgba(32,39,89,0.12)]">
    <div class="relative h-[180px] overflow-hidden">
        <img src="{{ $project['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $project['title'] ?? '' }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
        <div class="absolute left-4 top-4 rtl:left-auto rtl:right-4">
            @include('public.research.partials.status', ['status' => $project['status'] ?? '', 'locale' => $locale])
        </div>
    </div>
    <div class="px-[18px] pb-[20px] pt-[20px]">
        <div class="flex flex-wrap gap-[7px]">
            <span class="rounded-[5px] border border-[#c8ceda] px-[12px] py-[5px] text-[9px] font-bold text-spu-blue">{{ $project['faculty'] ?? '' }}</span>
            <span class="rounded-[5px] border border-[#c8ceda] px-[12px] py-[5px] text-[9px] font-bold text-spu-blue">{{ $locale === 'ar' ? 'منذ ' : 'Since ' }}{{ $project['startYear'] ?? '' }}</span>
        </div>
        <h2 class="mt-[14px] text-[20px] font-bold leading-tight text-spu-blue transition-colors group-hover:text-spu-red">{{ $project['title'] ?? '' }}</h2>
        <p class="mt-[10px] max-w-[500px] text-[15px] leading-[1.75] text-[#263650]">{{ $project['summary'] ?? '' }}</p>
        <div class="mt-4 flex items-center gap-4 text-[12px] text-[#6f7280]">
            <span>{{ $project['theme'] ?? '' }}</span>
        </div>
        <div class="mt-[16px] flex items-center gap-3">
            <span class="inline-flex h-[27px] min-w-[100px] items-center justify-center rounded-[6px] bg-spu-blue px-4 text-[8px] font-bold text-white transition group-hover:bg-[#171d47]">{{ $locale === 'ar' ? 'عرض المشروع' : 'View Project' }}</span>
        </div>
    </div>
</a>
