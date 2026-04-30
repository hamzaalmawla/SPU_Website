<?php

/**
 * Sentry Laravel SDK configuration.
 *
 * The DSN is left empty by default — Sentry is inactive until a valid DSN is
 * provisioned via the SENTRY_LARAVEL_DSN environment variable.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // @see https://docs.sentry.io/concepts/key-terms/dsn-explainer/
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // The release version of your application
    'release' => env('SENTRY_RELEASE'),

    // When left empty or null the Laravel environment will be used (APP_ENV)
    'environment' => env('SENTRY_ENVIRONMENT'),

    // @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#sample_rate
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    // @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#traces_sample_rate
    // Default 0.2 = 20% of requests traced in production for APM
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),

    // @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#profiles_sample_rate
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    // Do not send PII (cookies, user IPs, etc.) by default
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore_transactions
    'ignore_transactions' => [
        // Ignore Laravel's default health URL
        '/up',
    ],

    // Breadcrumb specific configuration
    'breadcrumbs' => [
        'logs'                 => true,
        'cache'                => true,
        'livewire'             => true,
        'sql_queries'          => true,
        'sql_bindings'         => false,
        'queue_info'           => true,
        'command_info'         => true,
        'http_client_requests' => true,
        'notifications'        => true,
    ],

    // Performance monitoring specific configuration
    'tracing' => [
        'queue_job_transactions'  => env('SENTRY_TRACE_QUEUE_ENABLED', true),
        'queue_jobs'              => true,
        'sql_queries'             => true,
        'sql_bindings'            => false,
        'sql_origin'              => true,
        'sql_origin_threshold_ms' => 100,
        'views'                   => true,
        'livewire'                => true,
        'http_client_requests'    => true,
        'cache'                   => true,
        'redis_commands'          => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'redis_origin'            => true,
        'notifications'           => true,
        'missing_routes'          => false,
        'continue_after_response' => true,
        'default_integrations'    => true,
    ],

];
