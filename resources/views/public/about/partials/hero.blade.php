<section class="about-subpage-hero relative flex items-center justify-center overflow-hidden pt-28 font-hacen">
    <img src="{{ $image ?: '/images/about-hero-3.webp' }}" alt="" class="absolute inset-0 h-full w-full object-cover">

    <div class="container relative z-10 mx-auto px-6 text-center text-white">
        <nav class="mb-6 flex items-center justify-center gap-3 text-xs font-bold text-white/85" aria-label="Breadcrumb">
            <a href="/{{ $locale }}" class="transition hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
            <span aria-hidden="true">›</span>
            <a href="/{{ $locale }}/about" class="transition hover:text-white">{{ $locale === 'ar' ? 'عن الجامعة' : 'About' }}</a>
            <span aria-hidden="true">›</span>
            <span>{{ $title }}</span>
        </nav>

        <h1 class="text-4xl font-black leading-tight text-white md:text-5xl">{{ $title }}</h1>
        @if (! empty($summary))
            <p class="mx-auto mt-5 max-w-3xl text-base font-semibold leading-8 text-white/80">{{ $summary }}</p>
        @endif
    </div>
</section>
