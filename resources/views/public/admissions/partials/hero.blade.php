<section class="bg-white">
    <div class="relative flex {{ $compactHero ? 'h-[300px] lg:h-[310px]' : 'h-[370px] lg:h-[400px]' }} items-center justify-center overflow-hidden font-hacen">
        <img src="{{ $heroImage }}" alt="{{ $title }}" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(32,39,89,0.80)_0%,rgba(32,39,89,0.50)_50%,rgba(32,39,89,0)_100%)]"></div>
        <div class="container relative z-10 mx-auto px-6 text-center text-white">
            <nav class="mb-4 flex items-center justify-center gap-2 text-xs font-black text-white" aria-label="Breadcrumb">
                <a href="{{ $homeUrl }}" class="rounded bg-spu-blue/35 px-2 py-1 transition hover:bg-spu-blue/55">{{ $homeLabel }}</a>
                <span aria-hidden="true" class="text-white/75">&gt;</span>
                <a href="{{ $parentUrl }}" class="transition hover:text-white/80">{{ $parentLabel }}</a>
                <span aria-hidden="true" class="text-white/75">&gt;</span>
                <span>{{ $currentLabel }}</span>
            </nav>
            <h1 class="text-[32px] font-black leading-tight text-white md:text-[42px]">{{ $title }}</h1>
        </div>
    </div>
</section>
