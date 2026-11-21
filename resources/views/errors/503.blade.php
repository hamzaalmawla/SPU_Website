{{-- 503 Service Unavailable / maintenance mode.

     ALWAYS standalone, for the same reason as 500. Pre-render this view for
     maintenance windows with: php artisan down --render="errors::503" --}}
@include('errors.partials.standalone', ['error' => $error])
