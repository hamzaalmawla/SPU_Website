<?php

declare(strict_types=1);

namespace App\Services\Preview;

use App\Enums\PublicationStatus;
use App\Models\Homepage\HomepageDraft;
use App\Models\Page\PageDraft;
use App\Models\Shared\PreviewToken;
use Illuminate\Support\Str;

/**
 * Handles preview token lifecycle: creation, resolution, validation,
 * invalidation, and cryptographic hashing.
 *
 * Extracted from PreviewService to separate token CRUD concerns
 * from preview content assembly.
 */
final class PreviewTokenStore
{
    private const RAW_TOKEN_LENGTH = 64;

    /**
     * Create a new preview token record with a hashed token value.
     *
     * @return array{raw_token: string, model: PreviewToken}
     */
    public function create(
        string $targetType,
        ?int $targetId,
        string $locale,
        int $userId,
        ?string $device = null,
    ): array {
        $this->assertSupportedTargetType($targetType);
        $this->assertSupportedDevice($device);
        $this->assertSupportedLocale($locale);

        $rawToken = Str::random(self::RAW_TOKEN_LENGTH);

        $token = PreviewToken::query()->create([
            'token_hash' => $this->hashToken($rawToken),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'locale' => $locale,
            'device' => $device,
            'issued_to_user_id' => $userId,
            'payload_json' => $this->snapshotPayload($targetType, $targetId),
            'expires_at' => now()->addHours(6),
        ]);

        return ['raw_token' => $rawToken, 'model' => $token];
    }

    /**
     * Resolve a raw token string to its PreviewToken model, if valid and not expired.
     */
    public function resolve(string $rawToken): ?PreviewToken
    {
        if (! $this->isWellFormedRawToken($rawToken)) {
            return null;
        }

        $previewToken = PreviewToken::query()
            ->where('token_hash', $this->hashToken($rawToken))
            ->where('expires_at', '>', now())
            ->first();

        return $previewToken instanceof PreviewToken ? $previewToken : null;
    }

    /**
     * Check whether a raw token string maps to a valid, non-expired record.
     */
    public function validate(string $rawToken): bool
    {
        if (! $this->isWellFormedRawToken($rawToken)) {
            return false;
        }

        return PreviewToken::query()
            ->where('token_hash', $this->hashToken($rawToken))
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Delete the token record matching the given raw token.
     */
    public function invalidate(string $rawToken): bool
    {
        if (! $this->isWellFormedRawToken($rawToken)) {
            return false;
        }

        return PreviewToken::query()
            ->where('token_hash', $this->hashToken($rawToken))
            ->delete() > 0;
    }

    public function invalidateTarget(string $targetType, ?int $targetId = null): int
    {
        return PreviewToken::query()
            ->where('target_type', $targetType)
            ->when(
                $targetId === null,
                fn ($query) => $query->whereNull('target_id'),
                fn ($query) => $query->where('target_id', $targetId),
            )
            ->delete();
    }

    /**
     * Produce the HMAC-SHA256 hash used for token storage.
     */
    public function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function isWellFormedRawToken(string $rawToken): bool
    {
        return strlen($rawToken) === self::RAW_TOKEN_LENGTH
            && preg_match('/\A[A-Za-z0-9]+\z/', $rawToken) === 1;
    }

    // ------------------------------------------------------------------
    // Validation helpers
    // ------------------------------------------------------------------

    private function assertSupportedTargetType(string $targetType): void
    {
        if (! in_array($targetType, ['homepage', 'page'], true)) {
            throw new \InvalidArgumentException('Unsupported preview target type.');
        }
    }

    private function assertSupportedDevice(?string $device): void
    {
        if ($device !== null && ! in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
            throw new \InvalidArgumentException('Unsupported preview device.');
        }
    }

    private function assertSupportedLocale(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            throw new \InvalidArgumentException('Unsupported preview locale.');
        }
    }

    // ------------------------------------------------------------------
    // Snapshot helpers
    // ------------------------------------------------------------------

    /**
     * Capture the current draft payload for embedding in the token record.
     *
     * @return array<string, mixed>|null
     */
    private function snapshotPayload(string $targetType, ?int $targetId): ?array
    {
        if ($targetType === 'homepage') {
            $draft = HomepageDraft::query()
                ->where('target_type', 'homepage')
                ->whereIn('status', PublicationStatus::editableValues())
                ->latest('updated_at')
                ->first();

            return $draft instanceof HomepageDraft && is_array($draft->payload_json)
                ? $draft->payload_json
                : null;
        }

        if ($targetType === 'page' && $targetId !== null) {
            $draft = PageDraft::query()
                ->where('page_id', $targetId)
                ->whereIn('status', PublicationStatus::editableValues())
                ->latest('updated_at')
                ->first();

            return $draft instanceof PageDraft && is_array($draft->payload_json)
                ? $draft->payload_json
                : null;
        }

        return null;
    }
}
