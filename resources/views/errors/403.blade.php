{{-- 403 Forbidden. Renders standalone; ErrorPageRenderer upgrades it to the full public layout when navigation and settings are reachable. --}}
@include('errors.partials.standalone', ['error' => $error])
