<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Sends page views through the real collector (public/stats-hit.php) into a
 * temporary statistics directory, as a browser's POST would, then lets
 * `site-stats:collect` write them.
 */
final class SiteStatsCollector
{
    public const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    public const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    public static function boot(): string
    {
        if (! defined('SPU_STATS_NO_RUN')) {
            define('SPU_STATS_NO_RUN', true);
        }

        require_once dirname(__DIR__, 2).'/public/stats-hit.php';

        $directory = sys_get_temp_dir().'/spu-site-stats-'.bin2hex(random_bytes(4));
        mkdir($directory, 0775, true);
        config(['site_stats.directory' => $directory]);

        return $directory;
    }

    /**
     * Queues one page view in a minute that has already ended, so the next collection takes it.
     * Returns false when the collector refused the request.
     */
    public static function view(
        string $page,
        string $userAgent = self::BROWSER,
        string $ip = '10.0.0.1',
        string $referrer = '',
        ?string $origin = null,
        ?int $results = null,
        string $method = 'POST',
    ): bool {
        $host = (string) parse_url(url('/'), PHP_URL_HOST);
        $body = ['p' => $page, 'r' => $referrer];

        if ($results !== null) {
            $body['n'] = $results;
        }

        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Damascus')))->modify('-2 minutes');
        $directory = (string) config('site_stats.directory');
        $line = \spu_stats_queue_line([
            'REQUEST_METHOD' => $method,
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
            'HTTP_ORIGIN' => $origin ?? 'https://'.$host,
            'HTTP_HOST' => $host,
        ], (string) json_encode($body), $directory, $now);

        return $line !== null && \spu_stats_enqueue($directory, $line, $now);
    }

    public static function cleanUp(string $directory): void
    {
        foreach ([...(glob($directory.'/*') ?: []), ...(glob($directory.'/queue/*') ?: [])] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($directory.'/queue');
        @rmdir($directory);
    }
}
