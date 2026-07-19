@php
    $sectionId = $sectionId ?? 'latest-research';
    $headingId = $sectionId.'-title';
    $publications = collect($latestResearch ?? [])
        ->filter(fn (mixed $publication): bool => is_array($publication)
            && preg_match('/^[A-Za-z0-9-]+$/', (string) ($publication['slug'] ?? '')) === 1
            && trim((string) ($publication['title'] ?? '')) !== '')
        ->take(3)
        ->values();
@endphp

@if ($publications->isNotEmpty())
    <section id="{{ $sectionId }}" class="bg-section py-16 font-hacen lg:py-24" aria-labelledby="{{ $headingId }}">
        <div class="container">
            <p class="text-[12px] font-bold uppercase tracking-[0.22em]" style="color: {{ $accent }}">{{ $locale === 'ar' ? 'أبحاث الكلية' : 'Faculty Research' }}</p>
            <h2 id="{{ $headingId }}" class="mt-3 text-[32px] font-bold leading-tight text-spu-blue md:text-[42px]">{{ $locale === 'ar' ? 'أحدث الأبحاث' : 'Latest Research' }}</h2>
            <div class="mt-4 h-[3px] w-16 rounded-full" style="background-color: {{ $accent }}"></div>

            <div class="mt-10 grid gap-6 lg:grid-cols-3">
                @foreach ($publications as $publication)
                    @php($publicationUrl = route('public.research.publications.show', ['locale' => $locale, 'slug' => $publication['slug']], false))
                    <article class="group flex h-full flex-col overflow-hidden rounded-[8px] border border-slate-100 bg-white shadow-[0_14px_34px_rgba(9,17,68,0.06)]">
                        @if (! empty($publication['image']))
                            <img src="{{ $publication['image'] }}" alt="" class="aspect-[16/8] w-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-105" aria-hidden="true">
                        @endif
                        <div class="flex flex-1 flex-col p-6">
                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">
                                @if (! empty($publication['type']))
                                    <span style="color: {{ $accent }}">{{ $publication['type'] }}</span>
                                @endif
                                @if (! empty($publication['year']))
                                    <span aria-hidden="true">/</span>
                                    <span>{{ $publication['year'] }}</span>
                                @endif
                            </div>
                            <h3 class="mt-3 text-xl font-bold leading-tight text-spu-blue">
                                <a href="{{ $publicationUrl }}" class="transition-colors hover:text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">{{ $publication['title'] }}</a>
                            </h3>
                            @if (! empty($publication['summary']))
                                <p class="mt-4 line-clamp-3 text-sm leading-7 text-slate-600">{{ $publication['summary'] }}</p>
                            @endif
                            <a href="{{ $publicationUrl }}" class="mt-auto inline-flex items-center gap-2 pt-6 text-sm font-bold transition-colors hover:text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue" style="color: {{ $accent }}" aria-label="{{ ($locale === 'ar' ? 'عرض المنشور: ' : 'View publication: ').$publication['title'] }}">
                                <span>{{ $locale === 'ar' ? 'عرض المنشور' : 'View Publication' }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
