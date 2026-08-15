@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($item = $page->item)
    @include('public.research.partials.detail-hero', ['title' => $item['title'] ?? '', 'subtitle' => $item['faculty'] ?? '', 'image' => $item['image'] ?? '/images/uni-main-place.JPG', 'eyebrow' => $locale === 'ar' ? 'مشروع بحثي' : 'Research Project', 'parentUrl' => '/'.$locale.'/research/projects', 'parentLabel' => $locale === 'ar' ? 'المشاريع' : 'Projects'])
    <section class="bg-white pb-[80px] pt-[60px] font-hacen" dir="{{ $direction }}">
        <div class="container">
            <div class="research-project-detail-grid mx-auto max-w-[1100px]">
                <div>
                    <h2 class="text-[14px] font-bold uppercase tracking-[0.1em] text-spu-red">{{ $locale === 'ar' ? 'نظرة عامة' : 'Overview' }}</h2>
                    <p class="mt-4 text-[16px] leading-[1.8] text-[#333742]">{{ $item['summary'] ?? '' }}</p>
                    <div class="mt-10"><h2 class="text-[14px] font-bold uppercase tracking-[0.1em] text-spu-red">{{ $locale === 'ar' ? 'معلومات المشروع' : 'Project Information' }}</h2><dl class="mt-4 space-y-3">
                        @foreach ([($locale === 'ar' ? 'الكلية' : 'Faculty') => $item['faculty'] ?? '', ($locale === 'ar' ? 'المجال البحثي' : 'Research Theme') => $item['theme'] ?? '', ($locale === 'ar' ? 'سنة البدء' : 'Start Year') => $item['startYear'] ?? '', ($locale === 'ar' ? 'جهة التمويل' : 'Funding') => $item['funding'] ?? ''] as $label => $value)
                            <div class="flex gap-4"><dt class="w-[140px] text-[12px] font-medium text-[#6f7280]">{{ $label }}</dt><dd class="text-[14px] font-medium text-spu-blue">{{ $value }}</dd></div>
                        @endforeach
                    </dl></div>
                </div>
                <aside class="lg:sticky lg:top-8 lg:self-start">
                    <div class="rounded-[10px] border border-[#dde2ea] bg-white p-6 shadow-[0_4px_12px_rgba(0,0,0,0.08)]">
                        <h3 class="text-[14px] font-bold uppercase tracking-[0.1em] text-spu-blue">{{ $locale === 'ar' ? 'معلومات المشروع' : 'Project Info' }}</h3>
                        <dl class="mt-5 space-y-4">
                            <div><dt class="text-[10px] font-medium uppercase tracking-[0.1em] text-[#6f7280]">{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</dt><dd class="mt-1">@include('public.research.partials.status', ['status' => $item['status'] ?? '', 'locale' => $locale])</dd></div>
                            <div><dt class="text-[10px] font-medium uppercase tracking-[0.1em] text-[#6f7280]">{{ $locale === 'ar' ? 'الكلية' : 'Faculty' }}</dt><dd class="mt-1 text-[14px] font-medium text-spu-blue">{{ $item['faculty'] ?? '' }}</dd></div>
                            <div><dt class="text-[10px] font-medium uppercase tracking-[0.1em] text-[#6f7280]">{{ $locale === 'ar' ? 'سنة البدء' : 'Start Year' }}</dt><dd class="mt-1 text-[14px] font-medium text-spu-blue">{{ $item['startYear'] ?? '' }}</dd></div>
                        </dl>
                    </div>
                    <div class="mt-4 rounded-[10px] border border-[#dde2ea] bg-white p-6 shadow-[0_4px_12px_rgba(0,0,0,0.08)]" x-data="pageShare" data-share-url="{{ url('/'.$locale.'/research/projects/'.($item['slug'] ?? '')) }}" data-share-title="{{ $item['title'] ?? '' }}">
                        <h3 class="text-[14px] font-bold uppercase tracking-[0.1em] text-spu-blue">{{ $locale === 'ar' ? 'مشاركة المشروع' : 'Share Project' }}</h3>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button type="button" x-on:click="share" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-spu-blue transition hover:border-spu-blue">{{ $locale === 'ar' ? 'مشاركة' : 'Share' }}</button>
                            <button type="button" x-on:click="copy" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-spu-blue transition hover:border-spu-blue">
                                <span x-show="!copied">{{ $locale === 'ar' ? 'نسخ الرابط' : 'Copy link' }}</span>
                                <span x-show="copied" x-cloak>{{ $locale === 'ar' ? 'تم النسخ' : 'Copied' }}</span>
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
