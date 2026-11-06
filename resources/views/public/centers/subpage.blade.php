@extends('layouts.public')

@section('content')
    @php
        $body = trim((string) ($page->content['body'] ?? ''));
    @endphp

    @include('public.centers.partials.hero')

    @if ($body !== '')
        <section class="bg-white pt-12 font-hacen" dir="{{ $direction }}">
            <div class="container">
                <x-public.rich-text class="mx-auto max-w-[820px] text-[17px] leading-[1.9] text-[#46464f]" :text="$body" />
            </div>
        </section>
    @endif

    @if ($page->subpageSlug === 'staff')
        <section class="bg-white py-14 font-hacen md:py-18" dir="{{ $direction }}">
            <div class="container">
                <p class="mb-8 text-center text-sm font-bold text-slate-600" role="status">{{ __('centers.staff_count', ['count' => count($page->staff)]) }}</p>
                @include('public.centers.partials.staff-grid', ['members' => $page->staff])
            </div>
        </section>
    @else
        @include('public.centers.partials.course-list')
    @endif
@endsection
