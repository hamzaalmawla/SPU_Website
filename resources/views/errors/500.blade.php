{{-- 500 Internal Server Error.

     ALWAYS standalone. This page may be rendering precisely because the
     database, the cache, or a service binding is unavailable, so it must not
     touch any of them. Do not add @extends, @vite, __(), navigation, settings
     or analytics here — see resources/views/errors/partials/standalone.blade.php. --}}
@include('errors.partials.standalone', ['error' => $error])
