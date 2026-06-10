@extends('layouts.public')

@section('content')
    @php
        $displayOrder = [
            'achievements_highlights' => 3,
            'academic_faculties' => 4,
        ];

        $homepageSections = collect($homepage->sections)->sortBy(
            static fn ($section): int => $displayOrder[$section->key] ?? $section->sortOrder,
        );
    @endphp

    <div>
        @foreach ($homepageSections as $section)
            @continue($section->key === 'footer')
            @continue(! $section->isEnabled)
            @include('public.partials.homepage-section', ['section' => $section, 'locale' => $locale])
        @endforeach
    </div>
@endsection
