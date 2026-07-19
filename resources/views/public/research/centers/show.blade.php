@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($item = $page->item)
    @php($data = $page->data)

    @include('public.research.partials.detail-hero', [
        'title' => $item['name'] ?? '',
        'subtitle' => $item['faculty'] ?? '',
        'image' => $item['image'] ?? '/images/uni-main-place.JPG',
        'eyebrow' => $locale === 'ar' ? 'مركز بحثي' : 'Research Center',
        'parentUrl' => '/'.$locale.'/research/centers',
        'parentLabel' => $locale === 'ar' ? 'المراكز' : 'Centers',
    ])

    <section class="bg-slate-50 py-10 font-hacen md:py-14" dir="{{ $direction }}">
        <div class="container mx-auto max-w-[1120px] px-4 sm:px-6">
            <div class="research-detail-grid">
                <section class="space-y-6">
                    <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] md:p-8">
                        <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'الرسالة' : 'Mission Statement' }}</h2>
                        <p class="mt-4 text-[15px] leading-8 text-slate-700">{{ $item['mission'] ?? '' }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ([['labs', 'Labs', 'مختبر'], ['researchers', 'Researchers', 'باحث'], ['projects', 'Projects', 'مشروع'], ['publications', 'Publications', 'منشور']] as [$key, $en, $ar])
                            <div class="rounded-[8px] border border-slate-200 bg-white p-5 text-center shadow-[0_4px_12px_rgba(0,0,0,0.04)]">
                                <p class="text-[28px] font-bold text-spu-blue">{{ $item[$key] ?? 0 }}</p>
                                <p class="mt-1 text-[11px] font-medium text-slate-500">{{ $locale === 'ar' ? $ar : $en }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if (! empty($data['faculty']))
                        <section class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" aria-labelledby="affiliated-faculty-heading">
                            <h2 id="affiliated-faculty-heading" class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'الباحثون المنتسبون' : 'Affiliated Researchers' }}</h2>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($data['faculty'] as $researcher)
                                    <a href="/{{ $locale }}/research/researchers/{{ $researcher['slug'] ?? '' }}" class="group rounded-[8px] border border-slate-100 p-4 transition hover:border-spu-blue/20 hover:shadow-[0_4px_12px_rgba(32,39,89,0.06)]">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $researcher['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $researcher['name'] ?? '' }}" class="h-12 w-12 rounded-full object-cover">
                                            <div>
                                                <h3 class="text-[13px] font-bold text-spu-blue group-hover:text-spu-red">{{ $researcher['name'] ?? '' }}</h3>
                                                <p class="mt-1 text-[11px] text-slate-500">{{ $researcher['title'] ?? $researcher['role'] ?? '' }}</p>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" aria-labelledby="related-publications-heading">
                        <div class="flex items-center justify-between gap-4">
                            <h2 id="related-publications-heading" class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'المنشورات المرتبطة' : 'Related Publications' }}</h2>
                            <a href="/{{ $locale }}/research/publications" class="text-[11px] font-bold text-spu-blue transition hover:text-spu-red">{{ $locale === 'ar' ? 'عرض الكل' : 'View All' }}</a>
                        </div>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            @forelse (($data['publications'] ?? []) as $publication)
                                <a href="/{{ $locale }}/research/publications/{{ $publication['slug'] ?? '' }}" class="group block rounded-[8px] border border-slate-100 p-4 transition hover:border-spu-blue/20 hover:shadow-[0_4px_12px_rgba(32,39,89,0.06)]">
                                    <h3 class="line-clamp-2 text-[14px] font-bold leading-snug text-spu-blue group-hover:text-spu-red">{{ $publication['title'] ?? '' }}</h3>
                                    <p class="mt-2 text-[12px] text-slate-500">{{ $publication['author'] ?? '' }} · {{ $publication['year'] ?? '' }}</p>
                                    <p class="mt-2 text-[10px] font-bold uppercase tracking-wider text-spu-red">{{ $publication['type'] ?? '' }}</p>
                                </a>
                            @empty
                                <p class="text-sm text-slate-500">{{ $locale === 'ar' ? 'لا توجد منشورات مرتبطة حالياً.' : 'No related publications are available.' }}</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" aria-labelledby="related-projects-heading">
                        <div class="flex items-center justify-between gap-4">
                            <h2 id="related-projects-heading" class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'المشاريع المرتبطة' : 'Related Projects' }}</h2>
                            <a href="/{{ $locale }}/research/projects" class="text-[11px] font-bold text-spu-blue transition hover:text-spu-red">{{ $locale === 'ar' ? 'عرض الكل' : 'View All' }}</a>
                        </div>
                        <div class="mt-5 space-y-4">
                            @forelse (($data['projects'] ?? []) as $project)
                                <a href="/{{ $locale }}/research/projects/{{ $project['slug'] ?? '' }}" class="group block rounded-[8px] border border-slate-100 p-4 transition hover:border-spu-blue/20 hover:shadow-[0_4px_12px_rgba(32,39,89,0.06)]">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <h3 class="text-[14px] font-bold text-spu-blue group-hover:text-spu-red">{{ $project['title'] ?? '' }}</h3>
                                        <span class="rounded-full bg-spu-blue/5 px-2.5 py-1 text-[10px] font-semibold text-spu-blue">{{ $project['status'] ?? '' }}</span>
                                    </div>
                                    <p class="mt-2 line-clamp-2 text-[12px] leading-6 text-slate-600">{{ $project['summary'] ?? '' }}</p>
                                </a>
                            @empty
                                <p class="text-sm text-slate-500">{{ $locale === 'ar' ? 'لا توجد مشاريع مرتبطة حالياً.' : 'No related projects are available.' }}</p>
                            @endforelse
                        </div>
                    </section>
                </section>

                <aside class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] lg:sticky lg:top-28">
                    <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'معلومات المركز' : 'Center Information' }}</h2>
                    <dl class="mt-5 divide-y divide-slate-100">
                        @foreach ([[$locale === 'ar' ? 'المدير' : 'Director', $item['directorName'] ?? ''], [$locale === 'ar' ? 'الكلية' : 'Faculty', $item['faculty'] ?? '']] as [$label, $value])
                            @if ($value !== '')
                                <div class="py-3 first:pt-0"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm font-bold text-spu-blue">{{ $value }}</dd></div>
                            @endif
                        @endforeach
                        @if (! empty($item['contactEmail']))
                            <div class="py-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $locale === 'ar' ? 'البريد الإلكتروني' : 'Email' }}</dt><dd class="mt-1"><a href="mailto:{{ $item['contactEmail'] }}" class="break-all text-sm font-bold text-spu-blue hover:text-spu-red">{{ $item['contactEmail'] }}</a></dd></div>
                        @endif
                        @if (! empty($item['contactPhone']))
                            <div class="py-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $locale === 'ar' ? 'الهاتف' : 'Phone' }}</dt><dd class="mt-1"><a href="tel:{{ $item['contactPhone'] }}" class="text-sm font-bold text-spu-blue hover:text-spu-red">{{ $item['contactPhone'] }}</a></dd></div>
                        @endif
                        @if (! empty($item['externalWebsite']))
                            <div class="py-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $locale === 'ar' ? 'الموقع الإلكتروني' : 'Website' }}</dt><dd class="mt-1"><a href="{{ $item['externalWebsite'] }}" target="_blank" rel="noopener" class="text-sm font-bold text-spu-blue hover:text-spu-red">{{ $locale === 'ar' ? 'زيارة الموقع' : 'Visit website' }}</a></dd></div>
                        @endif
                    </dl>
                </aside>
            </div>
        </div>
    </section>
@endsection
