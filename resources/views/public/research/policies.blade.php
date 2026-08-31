@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($data = $page->data)
    @if (! $page->isAvailable)
        @include('public.research.partials.empty-state', ['locale' => $locale, 'direction' => $direction])
    @else
    @include('public.research.partials.page-hero', ['hero' => $data['hero'] ?? [], 'locale' => $locale, 'direction' => $direction])

    <section class="bg-white py-12 font-hacen md:py-16" dir="{{ $direction }}">
        <div class="container mx-auto px-6">
            <div class="mx-auto max-w-[1200px]">
                <h2 class="mb-6 text-center text-xl font-bold text-spu-blue">{{ $locale === 'ar' ? 'السياسات والأخلاقيات البحثية' : 'Research Policies & Ethics' }}</h2>
                <div class="grid gap-8 md:grid-cols-2">
                    @foreach (($data['sections'] ?? []) as $section)
                        <div class="rounded-xl border border-spu-blue/10 bg-white p-6 shadow-sm">
                            <div class="mb-4 flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-spu-blue/[0.06] text-spu-blue">✓</div>
                                <div class="flex-1">
                                    <h3 class="mb-2 text-lg font-bold text-spu-blue">{{ $section['title'] ?? '' }}</h3>
                                    <p class="text-sm text-spu-blue/70">{{ $section['description'] ?? '' }}</p>
                                </div>
                            </div>
                            <div class="border-t border-spu-blue/10 pt-4">
                                <h4 class="mb-3 text-xs font-bold uppercase tracking-wider text-spu-blue/50">{{ $locale === 'ar' ? 'المستندات المتاحة' : 'Available Documents' }}</h4>
                                 <div class="flex flex-col gap-2">
                                     @if (($section['documentsUnavailable'] ?? false) && empty($section['documents']))
                                         <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">{{ $locale === 'ar' ? 'المستندات غير متاحة حالياً. يرجى التواصل مع مكتب البحث.' : 'Documents are currently unavailable. Please contact the Research Office.' }}</p>
                                     @endif
                                     @foreach (($section['documents'] ?? []) as $document)
                                        @if (! empty($document['url']) && $document['url'] !== '#')
                                            <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="group flex items-center justify-between rounded-lg border border-spu-blue/10 bg-spu-blue/[0.02] px-4 py-3 transition hover:border-spu-blue/30 hover:bg-spu-blue/[0.06]">
                                                <span class="text-sm font-semibold text-spu-blue">{{ $document['title'] ?? '' }}</span>
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-spu-red/70">{{ $document['fileType'] ?? '' }}</span>
                                            </a>
                                        @else
                                            <div class="flex items-center justify-between rounded-lg border border-spu-blue/10 bg-spu-blue/[0.02] px-4 py-3">
                                                <span class="text-sm font-semibold text-spu-blue">{{ $document['title'] ?? '' }}</span>
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-spu-red/70">{{ $document['fileType'] ?? '' }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mx-auto mt-12 max-w-[700px] rounded-xl border border-spu-blue/20 bg-spu-blue/[0.02] p-8">
                <h2 class="mb-2 text-center text-lg font-bold text-spu-blue">{{ $data['contactSection']['title'] ?? '' }}</h2>
                <p class="mb-6 text-center text-sm text-spu-blue/70">{{ $data['contactSection']['description'] ?? '' }}</p>
                <div class="flex flex-col items-center gap-3 text-sm text-spu-blue">
                    <a href="mailto:{{ $data['contactSection']['email'] ?? '' }}" class="font-semibold hover:text-spu-red">{{ $data['contactSection']['email'] ?? '' }}</a>
                    <a href="tel:{{ $data['contactSection']['phone'] ?? '' }}" class="font-semibold hover:text-spu-red">{{ $data['contactSection']['phone'] ?? '' }}</a>
                    <span class="text-spu-blue/60">{{ $data['contactSection']['location'] ?? '' }}</span>
                </div>
            </div>
        </div>
    </section>
    @endif
@endsection
