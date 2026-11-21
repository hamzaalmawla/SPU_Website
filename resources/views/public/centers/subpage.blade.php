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
        {{-- The search narrows only this center's staff cards, already on the page. --}}
        <section class="bg-white py-14 font-hacen md:py-18" dir="{{ $direction }}"
                 @if ($page->staff !== []) x-data="facultyMembers" data-status-all="{{ __('centers.staff_count') }}" data-status-some="{{ __('centers.staff_count_filtered') }}" @endif>
            <div class="container">
                @if ($page->staff !== [])
                    <div class="fm-toolbar mx-auto mb-8 max-w-[720px]">
                        <div class="fm-toolbar__row">
                            <label class="fm-search">
                                <span class="sr-only">{{ __('centers.staff_search_label') }}</span>
                                <img src="/images/icon-search-outline.svg" alt="" class="fm-search__icon" aria-hidden="true">
                                <input type="search" x-model="query" placeholder="{{ __('centers.staff_search_placeholder') }}" autocomplete="off">
                            </label>
                        </div>
                        <div class="fm-toolbar__status">
                            <p role="status" x-text="statusText()">{{ __('centers.staff_count', ['count' => count($page->staff)]) }}</p>
                            <button type="button" class="fm-reset" x-show="hasFilters()" x-cloak x-on:click="reset">{{ __('centers.staff_search_clear') }}</button>
                        </div>
                    </div>
                @else
                    <p class="mb-8 text-center text-sm font-bold text-slate-600" role="status">{{ __('centers.staff_count', ['count' => 0]) }}</p>
                @endif
                @include('public.centers.partials.staff-grid', ['members' => $page->staff, 'searchable' => true])
                @if ($page->staff !== [])
                    <div class="fm-empty" x-show="noResults()" x-cloak>
                        <p>{{ __('centers.staff_search_empty') }}</p>
                        <button type="button" class="fm-reset" x-on:click="reset">{{ __('centers.staff_search_clear') }}</button>
                    </div>
                @endif
            </div>
        </section>
    @else
        @include('public.centers.partials.course-list')
    @endif
@endsection
