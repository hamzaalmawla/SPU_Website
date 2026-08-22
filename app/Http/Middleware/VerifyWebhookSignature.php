<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Verifies incoming webhook requests using HMAC-SHA256 signature.
 *
 * Expects the request to include an X-Webhook-Signature header containing
 * an HMAC-SHA256 hex digest of the raw request body, computed with the
 * shared secret from config('services.webhook.secret').
 */
final class VerifyWebhookSignature
{
    private const MAX_TIMESTAMP_SKEW_SECONDS = 300;

    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.webhook.secret');

        if (empty($secret)) {
            abort(403, 'Forbidden.');
        }

        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $nonce = $request->header('X-Webhook-Nonce');

        if (empty($signature) || empty($timestamp) || empty($nonce) || ! ctype_digit((string) $timestamp)) {
            abort(403, 'Forbidden.');
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::MAX_TIMESTAMP_SKEW_SECONDS) {
            abort(403, 'Forbidden.');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Forbidden.');
        }

        $nonceKey = 'webhook:nonce:'.hash('sha256', (string) $nonce);

        try {
            $nonceAccepted = $this->cacheFactory
                ->store((string) config('cache.webhook_store', 'webhook'))
                ->add($nonceKey, true, now()->addSeconds(self::MAX_TIMESTAMP_SKEW_SECONDS));
        } catch (Throwable $exception) {
            try {
                $this->logger->error('Webhook replay protection is unavailable.', [
                    'exception' => $exception::class,
                ]);
            } catch (Throwable) {
                // A logging failure must not expose the webhook or fail open.
            }

            abort(503, 'Webhook replay protection is unavailable.');
        }

        if (! $nonceAccepted) {
            abort(403, 'Forbidden.');
        }

        return $next($request);
    }
}
