<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Visitor statistics collector (admin dashboard)
|--------------------------------------------------------------------------
|
| Each public page posts one small JSON body here after it loads
| (resources/js/public/siteStats.js). This file deliberately does not boot
| Laravel: the host has no OPcache, so a framework boot costs ~175 ms of a PHP
| worker, which would have been paid on every page view. Here the work is a
| few microseconds: hash the visitor with today's random key and append one
| line to this minute's queue file. `site-stats:collect` (every minute, via the
| scheduler) validates, filters bots and writes the totals
| (App\Services\SiteStats\SiteStatsRecorder), then deletes the file.
|
| No IP address is ever written: a visitor is HMAC(day|page|ip|user agent)
| keyed with a random per-day key (salt-<day>), and the keys are deleted after
| two days, so a visitor cannot be followed from one day to the next. Keep the
| key, hash and timezone rules identical to SiteStatsRecorder.
|
| Deployed to the web root by deploy/v2-staging/cpanel-deploy.sh.
|
*/

const SPU_STATS_TIMEZONE = 'Asia/Damascus';
const SPU_STATS_MAX_BODY = 2048;
// Bounds the disk a flood can use until the next collection run.
const SPU_STATS_MAX_QUEUE_BYTES = 2 * 1024 * 1024;
const SPU_STATS_TRUSTED_PROXIES = ['127.0.0.1', '::1'];

/** The application root: the parent directory, or the cPanel split layout (as public/app.php). */
function spu_stats_app_root(): string
{
    return is_file(dirname(__DIR__).'/vendor/autoload.php') ? dirname(__DIR__) : dirname(__DIR__, 3).'/spu_v2_app';
}

/** The visitor's address as Laravel sees it: X-Forwarded-For only from a trusted (local) proxy. */
function spu_stats_client_ip(array $server): string
{
    $remote = (string) ($server['REMOTE_ADDR'] ?? '');
    $forwarded = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');

    if ($forwarded === '' || ! in_array($remote, SPU_STATS_TRUSTED_PROXIES, true)) {
        return $remote;
    }

    foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $address) {
        if ($address !== '' && ! in_array($address, SPU_STATS_TRUSTED_PROXIES, true)) {
            return $address;
        }
    }

    return $remote;
}

/** Today's random key, created by whichever request needs it first (atomically). */
function spu_stats_salt(string $directory, string $day): ?string
{
    $path = $directory.'/salt-'.$day;
    $salt = is_file($path) ? @file_get_contents($path) : false;

    if (is_string($salt) && strlen($salt) === 64) {
        return $salt;
    }

    $temporary = $path.'.'.bin2hex(random_bytes(6));

    if (@file_put_contents($temporary, bin2hex(random_bytes(32))) !== 64) {
        return null;
    }

    // link() fails when another request created the key first; that key wins.
    @link($temporary, $path);
    @unlink($temporary);
    $salt = @file_get_contents($path);

    return is_string($salt) && strlen($salt) === 64 ? $salt : null;
}

/**
 * The queue line for one page view, or null when the request is not one.
 *
 * @param  array<string, mixed>  $server
 */
function spu_stats_queue_line(array $server, string $body, string $directory, ?DateTimeImmutable $now = null): ?string
{
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST' || $body === '' || strlen($body) > SPU_STATS_MAX_BODY) {
        return null;
    }

    $now ??= new DateTimeImmutable('now', new DateTimeZone(SPU_STATS_TIMEZONE));
    $day = $now->format('Y-m-d');
    $salt = spu_stats_salt($directory, $day);

    if ($salt === null) {
        return null;
    }

    $userAgent = substr((string) ($server['HTTP_USER_AGENT'] ?? ''), 0, 512);
    $origin = (string) ($server['HTTP_ORIGIN'] ?? '') !== '' ? (string) $server['HTTP_ORIGIN'] : (string) ($server['HTTP_REFERER'] ?? '');

    $line = json_encode([
        't' => $now->getTimestamp(),
        'd' => $day,
        'v' => hash_hmac('sha256', $day.'|page|'.spu_stats_client_ip($server).'|'.$userAgent, $salt),
        'ua' => $userAgent,
        'o' => (string) parse_url($origin, PHP_URL_HOST),
        'h' => strtolower((string) preg_replace('/:\d+$/', '', (string) ($server['HTTP_HOST'] ?? ''))),
        'b' => $body,
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    return is_string($line) ? $line."\n" : null;
}

/** Appends the line to this minute's queue file (minute in the statistics timezone). */
function spu_stats_enqueue(string $directory, string $line, ?DateTimeImmutable $now = null): bool
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone(SPU_STATS_TIMEZONE));
    $queue = $directory.'/queue';

    if (! is_dir($queue) && ! @mkdir($queue, 0775, true) && ! is_dir($queue)) {
        return false;
    }

    $file = $queue.'/'.$now->format('Ymd-Hi').'.log';

    if (is_file($file) && (int) @filesize($file) > SPU_STATS_MAX_QUEUE_BYTES) {
        return false;
    }

    return @file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
}

if (! defined('SPU_STATS_NO_RUN')) {
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    // The page never waits on, or learns anything from, the statistics.
    http_response_code(204);

    $directory = spu_stats_app_root().'/storage/app/site-stats';

    if (! is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $body = file_get_contents('php://input', false, null, 0, SPU_STATS_MAX_BODY + 1);
    $line = spu_stats_queue_line($_SERVER, is_string($body) ? $body : '', $directory);

    if ($line !== null) {
        spu_stats_enqueue($directory, $line);
    }
}
