@extends('public.staff-intake.layout')

@php($facultyName = $locale === 'ar' ? $link->facultyNameAr : $link->facultyNameEn)

@section('content')
    <section class="rounded-2xl bg-white p-6 shadow-sm sm:p-8"
        data-staff-intake-search
        data-suggest-url="{{ route('public.staff-intake.suggestions', ['locale' => $locale, 'token' => $token]) }}"
        data-new-url="{{ route('public.staff-intake.form', ['locale' => $locale, 'token' => $token]) }}"
        data-other-faculty="{{ __('staff_intake.public.other_faculty', ['faculty' => ':faculty'], $locale) }}"
        data-this-is-me="{{ __('staff_intake.public.this_is_me', [], $locale) }}"
        data-no-results="{{ __('staff_intake.public.no_results', [], $locale) }}">
        <p class="mb-6 text-slate-600">{{ __('staff_intake.public.intro', ['faculty' => $facultyName], $locale) }}</p>

        <form method="GET" action="{{ route('public.staff-intake.show', ['locale' => $locale, 'token' => $token]) }}" role="search" class="space-y-2">
            <label for="staff-intake-name" class="block text-sm font-bold text-spu-blue">{{ __('staff_intake.public.search_label', [], $locale) }}</label>
            <div class="flex gap-2">
                <input id="staff-intake-name" name="q" type="text" value="{{ $typed }}" autocomplete="off" required minlength="3" maxlength="100"
                    placeholder="{{ __('staff_intake.public.search_placeholder', [], $locale) }}"
                    role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="staff-intake-suggestions" aria-describedby="staff-intake-hint"
                    class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-lg outline-none focus:border-spu-blue focus:ring-4 focus:ring-spu-blue/10">
                <button type="submit" class="shrink-0 whitespace-nowrap rounded-lg bg-spu-blue px-5 py-3 font-bold text-white hover:bg-spu-blue/90 focus:outline-none focus:ring-4 focus:ring-spu-blue/30">{{ __('staff_intake.public.search_button', [], $locale) }}</button>
            </div>
            <p id="staff-intake-hint" class="text-xs text-slate-500">{{ __('staff_intake.public.search_hint', [], $locale) }}</p>
        </form>

        <div class="mt-6" aria-live="polite">
            <h2 class="mb-3 text-lg font-bold text-spu-blue" data-suggestions-heading @if ($typed === '') hidden @endif>{{ __('staff_intake.public.suggestions_heading', [], $locale) }}</h2>
            <ul id="staff-intake-suggestions" role="listbox" class="space-y-3" data-suggestions>
                @foreach ($suggestions as $suggestion)
                    <li role="option" aria-selected="false">
                        <a href="{{ route('public.staff-intake.form', ['locale' => $locale, 'token' => $token, 'ref' => $suggestion->ref]) }}"
                            class="flex items-center gap-4 rounded-xl border border-slate-200 p-4 hover:border-spu-blue focus:border-spu-blue focus:outline-none focus:ring-4 focus:ring-spu-blue/10">
                            @if ($suggestion->photoUrl)
                                <img src="{{ $suggestion->photoUrl }}" alt="" class="h-14 w-14 rounded-full object-cover">
                            @else
                                <span class="h-14 w-14 rounded-full bg-slate-200" aria-hidden="true"></span>
                            @endif
                            <span class="flex-1">
                                <span class="block font-bold">{{ $suggestion->nameAr }}</span>
                                <span class="block font-bold" dir="ltr">{{ $suggestion->nameEn }}</span>
                                <span class="block text-sm text-slate-500">{{ collect([$suggestion->title, $suggestion->department, $suggestion->inLinkFaculty ? null : __('staff_intake.public.other_faculty', ['faculty' => $suggestion->faculty], $locale)])->filter()->implode(' · ') }}</span>
                            </span>
                            <span class="text-sm font-bold text-spu-red">{{ __('staff_intake.public.this_is_me', [], $locale) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($typed !== '' && $suggestions->isEmpty())
                <p class="text-slate-600" data-no-results>{{ __('staff_intake.public.no_results', [], $locale) }}</p>
            @endif
        </div>

        <p class="mt-6 border-t border-slate-100 pt-6" data-new-entry @if ($typed === '') hidden @endif>
            <a href="{{ route('public.staff-intake.form', ['locale' => $locale, 'token' => $token, 'name' => $typed]) }}" data-new-link
                data-label-new="{{ __('staff_intake.public.start_new', [], $locale) }}" data-label-not-listed="{{ __('staff_intake.public.not_listed', [], $locale) }}"
                class="inline-block rounded-lg border border-spu-blue px-5 py-3 font-bold text-spu-blue hover:bg-spu-blue hover:text-white focus:outline-none focus:ring-4 focus:ring-spu-blue/20">
                {{ $suggestions->isEmpty() ? __('staff_intake.public.start_new', [], $locale) : __('staff_intake.public.not_listed', [], $locale) }}
            </a>
        </p>
    </section>
@endsection
