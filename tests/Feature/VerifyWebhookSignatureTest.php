<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_valid_signature_allows_request(): void
    {
        $body = json_encode(['event' => 'test', 'data' => ['id' => 1]]);
        $signature = hash_hmac('sha256', $body, self::TEST_SECRET);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            'HTTP_X_Webhook_Signature' => $signature,
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
            'HTTP_X_Webhook_Signature' => 'invalid-signature-value',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertForbidden();
    }

    public function test_signature_with_wrong_secret_returns_403(): void
    {
        $body = json_encode(['event' => 'test']);
        $wrongSignature = hash_hmac('sha256', $body, 'wrong-secret');

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            'HTTP_X_Webhook_Signature' => $wrongSignature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertForbidden();
    }

    public function test_unconfigured_secret_returns_403(): void
    {
        config(['services.webhook.secret' => null]);

        $body = json_encode(['event' => 'test']);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            'HTTP_X_Webhook_Signature' => 'some-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertForbidden();
    }

    public function test_empty_body_with_valid_signature_succeeds(): void
    {
        $body = '';
        $signature = hash_hmac('sha256', $body, self::TEST_SECRET);

        $response = $this->call('POST', '/webhook/incoming', [], [], [], [
            'HTTP_X_Webhook_Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
    }
}
