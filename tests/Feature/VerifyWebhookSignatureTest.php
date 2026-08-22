<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class VerifyWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_SECRET = 'test-webhook-secret-key-for-hmac-verification';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        config(['services.webhook.secret' => self::TEST_SECRET]);
        Cache::store((string) config('cache.webhook_store', 'webhook'))->clear();
    }

    public function test_valid_signature_allows_request(): void
    {
        $body = json_encode(['event' => 'test', 'data' => ['id' => 1]]);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$this->signedHeaders((string) $body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
        $response->assertJson(['status' => 'received']);
    }

    public function test_missing_signature_returns_403(): void
    {
        $body = json_encode(['event' => 'test']);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertForbidden();
    }

    public function test_invalid_signature_returns_403(): void
    {
        $body = json_encode(['event' => 'test']);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => 'invalid-signature-value',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) now()->getTimestamp(),
            'HTTP_X_WEBHOOK_NONCE' => 'invalid-signature-nonce',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertForbidden();
    }

    public function test_signature_with_wrong_secret_returns_403(): void
    {
        $body = json_encode(['event' => 'test']);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$this->signedHeaders((string) $body, 'wrong-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertForbidden();
    }

    public function test_unconfigured_secret_returns_403(): void
    {
        config(['services.webhook.secret' => null]);

        $body = json_encode(['event' => 'test']);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$this->signedHeaders((string) $body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertForbidden();
    }

    public function test_empty_body_with_valid_signature_succeeds(): void
    {
        $body = '';

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$this->signedHeaders($body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
    }

    public function test_replayed_nonce_returns_403(): void
    {
        $body = json_encode(['event' => 'test']);
        $headers = $this->signedHeaders((string) $body, nonce: 'replay-nonce');

        $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$headers,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$headers,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertForbidden();
    }

    public function test_replay_nonce_survives_application_cache_flush(): void
    {
        $body = json_encode(['event' => 'test']);
        $headers = $this->signedHeaders((string) $body, nonce: 'surviving-nonce');

        $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$headers,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        Cache::store((string) config('cache.default'))->clear();

        $this->call('POST', '/webhook/incoming', [], [], [], [
            ...$headers,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $body, string $secret = self::TEST_SECRET, ?string $nonce = null, ?int $timestamp = null): array
    {
        $timestamp ??= now()->getTimestamp();
        $nonce ??= 'nonce-'.bin2hex(random_bytes(8));

        return [
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_NONCE' => $nonce,
        ];
    }
}
