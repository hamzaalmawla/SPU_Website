@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($data = $page->data)
    @if (! $page->isAvailable)
        @include('public.research.partials.empty-state', ['locale' => $locale, 'direction' => $direction])
    @else
    @include('public.research.partials.page-hero', ['hero' => $data['hero'] ?? [], 'locale' => $locale, 'direction' => $direction])
    <section class="bg-white pb-[80px] pt-[60px] font-hacen" dir="{{ $direction }}"><div class="container"><div class="mx-auto grid max-w-[1200px] grid-cols-1 gap-[30px] sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">@foreach (($data['items'] ?? []) as $theme)<a href="{{ ($isPreview ?? false) && isset($preview) ? '/'.$locale.'/preview?token='.$preview->token.'&theme='.urlencode((string) ($theme['slug'] ?? '')) : '/'.$locale.'/research/themes/'.($theme['slug'] ?? '') }}" class="group block overflow-hidden rounded-[12px] border border-[#d5d9e2] bg-white shadow-[0_4px_12px_rgba(0,0,0,0.08)] transition-all hover:-translate-y-2 hover:shadow-[0_12px_32px_rgba(32,39,89,0.15)]"><div class="flex h-[120px] items-center justify-center gap-4 bg-spu-blue/5 p-6">@if (! empty($theme['icon']))<img src="{{ $theme['icon'] }}" alt="" class="h-12 w-12 object-contain" aria-hidden="true">@endif<h3 class="text-center text-[16px] font-bold leading-tight text-spu-blue">{{ $theme['name'] ?? '' }}</h3></div><div class="p-5"><p class="text-[13px] leading-[1.6] text-[#50525c] line-clamp-3">{{ $theme['description'] ?? '' }}</p><div class="mt-4 flex items-center justify-between border-t border-[#e5e7eb] pt-4"><div class="text-[11px] text-[#6f7280]"><span class="font-medium text-spu-blue">{{ $theme['publicationCount'] ?? 0 }}</span> {{ $locale === 'ar' ? 'منشور' : 'Publications' }}</div><div class="text-[11px] text-[#6f7280]"><span class="font-medium text-spu-blue">{{ $theme['projectCount'] ?? 0 }}</span> {{ $locale === 'ar' ? 'مشروع' : 'Projects' }}</div></div><div class="mt-4 flex items-center gap-2 text-[12px] font-bold text-spu-red"><span>{{ $locale === 'ar' ? 'استكشف' : 'Explore' }}</span><span>→</span></div></div></a>@endforeach</div></div></section>
    @endif
@endsection
