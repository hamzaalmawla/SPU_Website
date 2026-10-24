<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use App\Contracts\Legacy\LegacyRedirectDecisionServiceInterface;
use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyRedirectDecisionResultDTO;
use App\Models\Legacy\LegacyExactRedirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class LegacyRedirectDecisionService implements LegacyRedirectDecisionServiceInterface
{
    private const APPLY_APPROVAL = 'legacy-redirect-apply';

    private const ROLLBACK_APPROVAL = 'legacy-redirect-rollback';

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'redirect_readiness', 'evidence_status', 'approval_status', 'approval_decision', 'approved_by',
        'approval_notes', 'blockers', 'legacy_path', 'normalized_path', 'query_signature', 'target_url',
        'status_code', 'handler_key', 'subsite', 'locale', 'source_table', 'source_id',
    ];

    public function __construct(
        private readonly LegacyUrlNormalizerInterface $normalizer,
        private readonly LegacyQueryRedirectResolverInterface $queryResolver,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function decide(
        string $input,
        string $disk = 'local',
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
    ): LegacyRedirectDecisionResultDTO {
        if ($write && $approval !== self::APPLY_APPROVAL) {
            throw new InvalidArgumentException('Applying redirect decisions requires --approve='.self::APPLY_APPROVAL.'.');
        }

        $input = trim($input);

        if ($input === '' || ! Storage::disk($disk)->exists($input)) {
            throw new InvalidArgumentException('Redirect decision CSV was not found on the selected disk.');
        }

        $payload = Storage::disk($disk)->get($input);
        $checksum = hash('sha256', $payload);
        $batch = $this->batch($batch, $checksum);

        if ($write) {
            $this->validateExistingBatch($batch, $checksum);
        }

        [$rows, $malformed] = $this->csvRows($payload);
        $reasonCounts = [];
        $skipped = $malformed;

        if ($malformed > 0) {
            $reasonCounts['malformed_packet_row'] = $malformed;
        }

        $identityCounts = [];

        foreach ($rows as $row) {
            $identity = $this->identity($row);
            $identityCounts[$identity] = ($identityCounts[$identity] ?? 0) + 1;
        }

        $approvedRows = 0;
        $eligible = [];
        $idempotentRows = 0;

        foreach ($rows as $row) {
            $reason = $this->rowBlocker($row, $identityCounts);

            if (mb_strtolower($row['approval_decision']) === 'redirect') {
                $approvedRows++;
            }

            if ($reason !== null) {
                $this->skip($reasonCounts, $skipped, $reason);

                continue;
            }

            $existing = $this->existing($row['normalized_path'], $row['query_signature']);

            if ($existing instanceof LegacyExactRedirect) {
                if ((string) $existing->destination_url === $row['target_url']
                    && (int) $existing->status_code === (int) $row['status_code']
                    && (string) $existing->locale === $row['locale']) {
                    $idempotentRows++;

                    continue;
                }

                $this->skip($reasonCounts, $skipped, 'existing_redirect_conflict');

                continue;
            }

            $eligible[] = $row;
        }

        $approvers = array_values(array_unique(array_column($eligible, 'approved_by')));

        if (count($approvers) > 1) {
            throw new InvalidArgumentException('All eligible redirect decisions in one batch must use the same approved_by value.');
        }

        $createdRows = 0;

        if ($write && $eligible !== []) {
            $createdRows = DB::transaction(function () use ($batch, $checksum, $input, $eligible): int {
                $existingBatch = DB::table('legacy_redirect_decision_batches')->where('batch_id', $batch)->lockForUpdate()->first();

                if ($existingBatch !== null) {
                    if ((string) $existingBatch->evidence_sha256 !== $checksum) {
                        throw new InvalidArgumentException('Redirect batch ID is already associated with different evidence.');
                    }

                    if ((string) $existingBatch->status === 'rolled_back') {
                        throw new InvalidArgumentException('A rolled-back redirect batch cannot be reused. Choose a new batch ID.');
                    }

                    return 0;
                }

                $approvedBy = (string) $eligible[0]['approved_by'];
                $created = 0;

                foreach ($eligible as $row) {
                    $redirect = LegacyExactRedirect::query()->create([
                        'legacy_path' => $row['normalized_path'],
                        'query_signature' => $row['query_signature'] !== '' ? $row['query_signature'] : null,
                        'destination_url' => $row['target_url'],
                        'status_code' => (int) $row['status_code'],
                        'locale' => $row['locale'],
                        'is_active' => true,
                        'hit_count' => 0,
                        'notes' => $this->notes($row),
                        'decision_batch' => $batch,
                        'evidence_sha256' => $checksum,
                    ]);
                    DB::table('migration_logs')->insert([
                        'module' => 'redirect_continuity',
                        'batch_name' => $batch,
                        'source_table' => $row['source_table'],
                        'source_id' => (int) $row['source_id'],
                        'target_table' => 'legacy_exact_redirects',
                        'target_id' => (int) $redirect->getKey(),
                        'status' => 'success',
                        'message' => 'Created approved query-aware legacy redirect.',
                        'metadata' => json_encode([
                            'legacy_path' => $row['normalized_path'],
                            'query_signature' => $row['query_signature'],
                            'destination_url' => $row['target_url'],
                            'evidence_sha256' => $checksum,
                            'approved_by' => $row['approved_by'],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => now(),
                    ]);
                    $created++;
                }

                DB::table('legacy_redirect_decision_batches')->insert([
                    'batch_id' => $batch,
                    'evidence_sha256' => $checksum,
                    'packet_path' => $input,
                    'approved_by' => $approvedBy,
                    'status' => 'applied',
                    'created_redirects' => $created,
                    'applied_at' => now(),
                    'rolled_back_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $created;
            });

            $this->invalidateCache();
        }

        ksort($reasonCounts);

        return new LegacyRedirectDecisionResultDTO(
            action: 'decide',
            wroteChanges: $createdRows > 0,
            batch: $batch,
            scannedRows: count($rows) + $malformed,
            approvedRows: $approvedRows,
            eligibleRows: count($eligible),
            createdRows: $createdRows,
            idempotentRows: $idempotentRows,
            skippedRows: $skipped,
            reasonCounts: $reasonCounts,
        );
    }

    public function rollback(string $batch, bool $write = false, ?string $approval = null): LegacyRedirectDecisionResultDTO
    {
        $batch = trim($batch);

        if ($batch === '') {
            throw new InvalidArgumentException('A redirect decision batch ID is required.');
        }

        if ($write && $approval !== self::ROLLBACK_APPROVAL) {
            throw new InvalidArgumentException('Rolling back redirect decisions requires --approve='.self::ROLLBACK_APPROVAL.'.');
        }

        $record = DB::table('legacy_redirect_decision_batches')->where('batch_id', $batch)->first();

        if ($record === null) {
            throw new InvalidArgumentException('Redirect decision batch was not found.');
        }

        $count = LegacyExactRedirect::query()->where('decision_batch', $batch)->count();
        $reasonCounts = [];

        if ((string) $record->status === 'rolled_back') {
            $reasonCounts['already_rolled_back'] = 1;

            return new LegacyRedirectDecisionResultDTO('rollback', false, $batch, $count, 0, $count, 0, 0, 0, $reasonCounts);
        }

        $deleted = 0;

        if ($write) {
            $deleted = DB::transaction(function () use ($batch): int {
                $locked = DB::table('legacy_redirect_decision_batches')->where('batch_id', $batch)->lockForUpdate()->first();

                if ($locked === null || (string) $locked->status !== 'applied') {
                    return 0;
                }

                $redirects = LegacyExactRedirect::query()->where('decision_batch', $batch)->get();
                $deleted = LegacyExactRedirect::query()->where('decision_batch', $batch)->delete();

                foreach ($redirects as $redirect) {
                    DB::table('migration_logs')->insert([
                        'module' => 'redirect_continuity',
                        'batch_name' => $batch,
                        'source_table' => 'legacy_exact_redirects',
                        'source_id' => (int) $redirect->getKey(),
                        'target_table' => 'legacy_exact_redirects',
                        'target_id' => null,
                        'status' => 'rolled_back',
                        'message' => 'Rolled back approved legacy redirect decision batch.',
                        'metadata' => json_encode([
                            'legacy_path' => $redirect->legacy_path,
                            'query_signature' => $redirect->query_signature,
                            'destination_url' => $redirect->destination_url,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => now(),
                    ]);
                }

                DB::table('legacy_redirect_decision_batches')->where('batch_id', $batch)->update([
                    'status' => 'rolled_back',
                    'rolled_back_at' => now(),
                    'updated_at' => now(),
                ]);

                return $deleted;
            });

            $this->invalidateCache();
        }

        return new LegacyRedirectDecisionResultDTO('rollback', $deleted > 0, $batch, $count, 0, $count, $deleted, 0, 0, $reasonCounts);
    }

    /** @return array{0: list<array<string, string>>, 1: int} */
    private function csvRows(string $payload): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new InvalidArgumentException('Could not open redirect decision CSV.');
        }

        fwrite($stream, $payload);
        rewind($stream);
        $headers = fgetcsv($stream);

        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Redirect decision CSV is empty.');
        }

        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);

        if ($missing !== [] || count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            throw new InvalidArgumentException('Input is not a valid redirect decision CSV.'.($missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : ''));
        }

        $rows = [];
        $malformed = 0;

        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [] || $values === [null]) {
                continue;
            }

            if (count($values) !== count($headers)) {
                $malformed++;

                continue;
            }

            $rows[] = array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $values));
        }

        fclose($stream);

        return [$rows, $malformed];
    }

    /** @param array<string, string> $row @param array<string, int> $identityCounts */
    private function rowBlocker(array $row, array $identityCounts): ?string
    {
        if (mb_strtolower($row['approval_decision']) !== 'redirect') {
            return $row['approval_decision'] === '' ? 'blank_approval_decision' : 'approval_decision_not_redirect';
        }

        if ($row['approved_by'] === '') {
            return 'blank_approved_by';
        }

        if (mb_strlen($row['approved_by']) > 255 || mb_strlen($row['approval_notes']) > 2000) {
            return 'approval_metadata_too_long';
        }

        if ($row['redirect_readiness'] !== 'preview_ready' || $row['evidence_status'] !== 'resolver_ready'
            || $row['approval_status'] !== 'runtime_resolver' || $row['blockers'] !== '') {
            return 'evidence_not_ready';
        }

        if (! in_array($row['locale'], ['ar', 'en'], true)) {
            return 'unsupported_locale';
        }

        if ($row['status_code'] !== '301') {
            return 'invalid_status_code';
        }

        if (! $this->validPath($row['normalized_path']) || ! $this->validTarget($row['target_url'], $row['locale'])) {
            return 'unsafe_path_or_target';
        }

        if (mb_strlen($row['normalized_path']) > 2048 || mb_strlen($row['query_signature']) > 2048
            || mb_strlen($row['target_url']) > 2048 || $row['source_table'] === ''
            || ! ctype_digit($row['source_id']) || (int) $row['source_id'] < 1) {
            return 'invalid_evidence_metadata';
        }

        $normalized = $this->normalizer->normalize($row['legacy_path'], parse_url($row['legacy_path'], PHP_URL_QUERY) ?: null);
        $signature = $this->signature($normalized->params);

        if (mb_strtolower($normalized->path) !== mb_strtolower($row['normalized_path']) || $signature !== $row['query_signature']) {
            return 'normalized_evidence_mismatch';
        }

        if (in_array(mb_strtolower(basename($row['normalized_path'])), ['index.php', 'windex.php'], true) && $signature === '') {
            return 'router_redirect_requires_query_signature';
        }

        $currentResolution = $this->queryResolver->resolve($row['normalized_path'], $row['query_signature']);

        if ($currentResolution === null) {
            return 'target_not_currently_resolvable';
        }

        if ($currentResolution->destinationUrl !== $row['target_url']) {
            return 'target_resolution_mismatch';
        }

        if (($identityCounts[$this->identity($row)] ?? 0) > 1) {
            return 'duplicate_redirect_identity';
        }

        if (mb_strtolower($row['normalized_path']) === mb_strtolower((string) parse_url($row['target_url'], PHP_URL_PATH)) && $row['query_signature'] === '') {
            return 'self_redirect';
        }

        return null;
    }

    /** @param array<string, string> $row */
    private function identity(array $row): string
    {
        return mb_strtolower($row['normalized_path']).'?'.$row['query_signature'];
    }

    private function existing(string $path, string $querySignature): ?LegacyExactRedirect
    {
        $query = LegacyExactRedirect::query()->whereRaw('LOWER(legacy_path) = ?', [mb_strtolower($path)]);
        $querySignature === ''
            ? $query->where(fn ($inner) => $inner->whereNull('query_signature')->orWhere('query_signature', ''))
            : $query->where('query_signature', $querySignature);
        $redirect = $query->orderBy('id')->first();

        return $redirect instanceof LegacyExactRedirect ? $redirect : null;
    }

    private function validPath(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//')
            && ! str_contains($path, '\\') && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }

    private function validTarget(string $target, string $locale): bool
    {
        $path = parse_url($target, PHP_URL_PATH);

        return $this->validPath($target) && is_string($path)
            && ($path === '/'.$locale || str_starts_with($path, '/'.$locale.'/'));
    }

    /** @param array<string, string> $params */
    private function signature(array $params): string
    {
        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function batch(?string $batch, string $checksum): string
    {
        $batch = $batch !== null ? trim($batch) : '';
        $batch = $batch !== '' ? $batch : 'legacy-redirects-'.substr($checksum, 0, 12);

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}$/', $batch) !== 1) {
            throw new InvalidArgumentException('Redirect batch ID contains unsupported characters.');
        }

        return $batch;
    }

    private function validateExistingBatch(string $batch, string $checksum): void
    {
        $record = DB::table('legacy_redirect_decision_batches')->where('batch_id', $batch)->first();

        if ($record === null) {
            return;
        }

        if ((string) $record->evidence_sha256 !== $checksum) {
            throw new InvalidArgumentException('Redirect batch ID is already associated with different evidence.');
        }

        if ((string) $record->status === 'rolled_back') {
            throw new InvalidArgumentException('A rolled-back redirect batch cannot be reused. Choose a new batch ID.');
        }
    }

    /** @param array<string, string> $row */
    private function notes(array $row): string
    {
        $context = array_filter([
            'handler='.$row['handler_key'],
            'source='.$row['source_table'].':'.$row['source_id'],
            'approved_by='.$row['approved_by'],
            $row['approval_notes'] !== '' ? 'approval='.$row['approval_notes'] : null,
        ]);

        return 'Approved legacy redirect decision. '.implode('; ', $context);
    }

    /** @param array<string, int> $reasons */
    private function skip(array &$reasons, int &$skipped, string $reason): void
    {
        $skipped++;
        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
    }

    private function invalidateCache(): void
    {
        if (! $this->cacheService->flushTag('continuity')) {
            $this->cacheService->flushAll();
        }
    }
}
