{{--
    Analytics tag.

    $analytics is an AnalyticsSnippetDTO or null, supplied by the view composer
    in AppServiceProvider::registerPublicAnalyticsComposer(). It is null unless
    a provider and measurement ID are configured AND the environment permits
    it, so this block emits nothing by default.

    The origins this markup contacts are declared in config/analytics.php and
    are added to the Content-Security-Policy by SecurityHeadersMiddleware from
    that same config, so the policy and the script cannot drift apart. The
    inline gtag bootstrap is permitted by the existing script-src 'unsafe-inline'.
--}}
@if (($analytics ?? null) !== null)
    <script async src="{{ $analytics->scriptUrl }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($analytics->measurementId), @json((object) $analytics->options));
    </script>
@endif
