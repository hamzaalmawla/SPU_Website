{{-- 404 Not Found. The highest-traffic error page after the spu.edu.sy cutover: most legacy URLs do not map yet. Renders standalone; ErrorPageRenderer upgrades it to the full public layout when navigation and settings are reachable. --}}
@include('errors.partials.standalone', ['error' => $error])
