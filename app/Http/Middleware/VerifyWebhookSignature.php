<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies incoming webhook requests using HMAC-SHA256 signature.
 *
 * Expects the request to include an X-Webhook-Signature header containing
 * an HMAC-SHA256 hex digest of the raw request body, computed with the
 * shared secret from config('services.webhook.secret').
 */
final class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.webhook.secret');

        if (empty($secret)) {
            abort(403, 'Webhook secret is not configured.');
        }

        $signature = $request->header('X-Webhook-Signature');

        if (empty($signature)) {
            abort(403, 'Missing webhook signature.');
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
