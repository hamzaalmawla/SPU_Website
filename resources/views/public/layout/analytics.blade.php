{{--
    Analytics tag.

    $analytics is an AnalyticsSnippetDTO or null, supplied by the view composer
    in AppServiceProvider::registerPublicAnalyticsComposer(). It is null unless
    a provider and measurement ID are configured AND the environment permits
    it, so this block emits nothing by default.

    The origins this markup contacts are declared in config/analytics.php and
    are added to the Content-Security-Policy by SecurityHeadersMiddleware from
    that same config, so the policy and the script cannot drift apart. The
    public policy allows no inline script, so the gtag bootstrap runs from
    app.js (initAnalytics), reading the ID and options off this tag.
--}}
@if (($analytics ?? null) !== null)
    <script async src="{{ $analytics->scriptUrl }}" data-gtag-id="{{ $analytics->measurementId }}" data-gtag-options="{{ json_encode((object) $analytics->options) }}"></script>
@endif
