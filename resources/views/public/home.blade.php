@extends('layouts.public')

@section('content')
    @php
        $homepageSections = collect($homepage->sections);
    @endphp

    <div data-homepage>
        @foreach ($homepageSections as $section)
            @continue($section->key === 'footer')
            @continue(! $section->isEnabled)
            @include('public.partials.homepage-section', ['section' => $section, 'locale' => $locale])
        @endforeach
    </div>
@endsection
