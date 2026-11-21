@extends('layouts.public')

{{-- Most visits start here: inline the stylesheet so the first paint does not
     wait for a second connection to the server. See layouts/public. --}}
@section('inlinePublicStylesheet', '1')

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
