{{-- 429 Too Many Requests. Renders standalone; ErrorPageRenderer upgrades it to the full public layout when navigation and settings are reachable. --}}
@include('errors.partials.standalone', ['error' => $error])
