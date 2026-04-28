@extends('layouts.public')

@section('content')
    <div>
        @foreach ($homepage->sections as $section)
            @continue($section->key === 'footer')
            @continue(! $section->isEnabled)
            @include('public.partials.homepage-section', ['section' => $section, 'locale' => $locale])
        @endforeach
    </div>
@endsection
