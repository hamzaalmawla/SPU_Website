<div data-achievement-results>
    <p class="mb-6 text-sm font-bold text-slate-500">{{ trans_choice('public.achievements.results', $archive->results->total, ['count' => $archive->results->total]) }}</p>
    <div class="grid gap-7 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($archive->results->items as $achievement)
            <article class="group relative h-[430px] overflow-hidden rounded-[28px] bg-spu-blue shadow-card-elevated transition hover:-translate-y-1 hover:shadow-card-hover">
                <img src="{{ $achievement->image ?: '/images/slider-1.webp' }}" alt="{{ $achievement->title }}" loading="lazy" decoding="async" width="560" height="430" class="content-media-image content-media-image--dark absolute inset-0 h-full w-full transition duration-700 group-hover:scale-105">
                <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/45 to-transparent"></div>
                <div class="relative flex h-full flex-col justify-between p-7 text-white">
                    <div>@if ($achievement->typeTag)<span class="honor-panel-pill rounded-full bg-spu-red/95 px-4 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-card-elevated">{{ $achievement->typeTag }}</span>@endif</div>
                    <div>
                    @if ($achievement->meta)<p class="text-xs font-semibold text-white/75">{{ $achievement->meta }}</p>@endif
                    <h2 class="mt-2 line-clamp-3 text-2xl font-black leading-9 text-white">{{ $achievement->title }}</h2>
                    @if ($achievement->summary)<p class="mt-3 line-clamp-2 text-sm leading-7 text-white/80">{{ $achievement->summary }}</p>@endif
                    @if ($achievement->action)
                        <a href="{{ $achievement->action['url'] }}" class="honor-panel-cta relative z-10 mt-5 inline-flex border-b-2 border-spu-red pb-1 text-sm font-bold text-white">{{ $achievement->action['label'] }}</a>
                    @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center md:col-span-2 xl:col-span-3">
                <h2 class="text-xl font-black text-spu-blue">{{ __('public.achievements.empty_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('public.achievements.empty_body') }}</p>
            </div>
        @endforelse
    </div>

    @if ($archive->results->lastPage > 1)
        <nav class="mt-10 flex flex-wrap justify-center gap-2" aria-label="{{ __('public.achievements.pagination') }}" data-achievement-pagination>
            @for ($page = 1; $page <= $archive->results->lastPage; $page++)
                @php($query = http_build_query(['categories' => $archive->selectedCategories, 'page' => $page]))
                <a href="/{{ $locale }}/achievements?{{ $query }}" class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-bold {{ $page === $archive->results->currentPage ? 'border-spu-blue bg-spu-blue text-white' : 'border-slate-300 bg-white text-slate-600 hover:border-spu-red' }}" @if ($page === $archive->results->currentPage) aria-current="page" @endif>{{ $page }}</a>
            @endfor
        </nav>
    @endif
</div>
