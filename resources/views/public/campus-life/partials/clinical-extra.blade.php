{{-- Hospital and dental clinics: the old sites' about text, director's message, health tips and files. Each block shows only when it has content. --}}
@php
    $about = is_array($section['about'] ?? null) ? $section['about'] : [];
    $message = is_array($section['message'] ?? null) ? $section['message'] : [];
    $tips = is_array($section['tips'] ?? null) ? $section['tips'] : [];
    $tipItems = array_values(array_filter(is_array($tips['items'] ?? null) ? $tips['items'] : [], static fn (mixed $item): bool => is_array($item) && trim((string) ($item['title'] ?? '')) !== '' && trim((string) ($item['body'] ?? '')) !== ''));
    $clinicalDocuments = array_values(array_filter(is_array($section['documents'] ?? null) ? $section['documents'] : [], static fn (mixed $document): bool => is_array($document) && preg_match('#^(/|https://)#', (string) ($document['url'] ?? '')) === 1));
    $hasAbout = trim((string) ($about['body'] ?? '')) !== '';
    $hasMessage = trim((string) ($message['body'] ?? '')) !== '';
@endphp

@if ($hasAbout || $hasMessage)
    <div class="mt-24 grid gap-8 {{ $hasAbout && $hasMessage ? 'lg:grid-cols-[1.35fr_1fr]' : '' }}">
        @if ($hasAbout)
            <article class="rounded-[12px] border border-[#C3C6D180] bg-white p-8 md:p-10">
                @if (trim((string) ($about['title'] ?? '')) !== '')
                    <h2 class="mb-6 text-3xl font-bold text-spu-blue">{{ $about['title'] }}</h2>
                @endif
                <x-public.rich-text class="text-base leading-8 text-slate-600" :text="$about['body']" />
            </article>
        @endif
        @if ($hasMessage)
            <article class="flex flex-col rounded-[12px] bg-spu-blue p-8 text-white shadow-[0_16px_36px_rgba(15,23,42,0.16)] md:p-10">
                @if (trim((string) ($message['title'] ?? '')) !== '')
                    <h2 class="mb-6 text-2xl font-bold">{{ $message['title'] }}</h2>
                @endif
                <x-public.rich-text class="flex-1 text-[15px] leading-8 text-white/90" :text="$message['body']" />
                @if (trim((string) ($message['name'] ?? '')) !== '')
                    <p class="mt-8 border-t border-white/20 pt-5 text-lg font-bold">{{ $message['name'] }}</p>
                    @if (trim((string) ($message['role'] ?? '')) !== '')
                        <p class="text-sm text-white/70">{{ $message['role'] }}</p>
                    @endif
                @endif
            </article>
        @endif
    </div>
@endif

@if ($tipItems !== [])
    <div class="mt-24">
        @if (trim((string) ($tips['title'] ?? '')) !== '')
            <h2 class="mb-10 text-center text-4xl font-bold text-spu-blue">{{ $tips['title'] }}</h2>
        @endif
        <div class="mx-auto grid max-w-4xl gap-4">
            @foreach ($tipItems as $tip)
                <details class="group rounded-[12px] border border-[#C3C6D180] bg-white open:shadow-lg">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 text-lg font-bold text-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue [&::-webkit-details-marker]:hidden">
                        <span dir="auto">{{ $tip['title'] }}</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-spu-blue/5 text-xl leading-none transition group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    <div dir="auto" class="border-t border-slate-100 px-6 pb-6 pt-5">
                        <x-public.rich-text class="text-[15px] leading-8 text-slate-600" :text="$tip['body']" />
                    </div>
                </details>
            @endforeach
        </div>
    </div>
@endif

@if ($clinicalDocuments !== [])
    <div class="mt-12 flex flex-wrap justify-center gap-3">
        @foreach ($clinicalDocuments as $document)
            <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-spu-blue px-6 py-3 text-sm font-bold text-white transition hover:bg-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                <span aria-hidden="true">⤓</span>{{ trim((string) ($document['label'] ?? '')) !== '' ? $document['label'] : ($locale === 'ar' ? 'تحميل الملف' : 'Download the file') }}
            </a>
        @endforeach
    </div>
@endif
