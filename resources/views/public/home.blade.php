@extends('layouts.public')

@section('content')
    <div class="space-y-8">
        @foreach ($homepage->sections as $section)
            @continue($section->key === 'footer')
            @include('public.partials.homepage-section', ['section' => $section, 'locale' => $locale])
        @endforeach
    </div>
@endsection
