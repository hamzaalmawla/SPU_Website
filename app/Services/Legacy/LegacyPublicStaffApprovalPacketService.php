<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyPublicStaffApprovalPacketServiceInterface;
use App\DTOs\Legacy\LegacyPublicStaffApprovalPacketResultDTO;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyPublicStaffApprovalPacketService implements LegacyPublicStaffApprovalPacketServiceInterface
{
    /** @var list<string> */
    private const REQUIRED = ['source_table', 'source_id', 'service_type', 'candidate_target_module', 'candidate_faculty_slug', 'blockers', 'approval_decision', 'approved_target'];

    public function build(array $inputs, string $approvedBy, string $disk = 'local', string $directory = 'legacy-import-exports/public-staff-approval-packets', bool $central = false): LegacyPublicStaffApprovalPacketResultDTO
    {
        $approvedBy = trim($approvedBy);
        if ($approvedBy === '') {
            throw new InvalidArgumentException('An approved-by identity is required.');
        }
        $inputs = array_values(array_unique(array_filter(array_map('trim', $inputs))));
        if ($inputs === []) {
            throw new InvalidArgumentException('At least one staff review packet is required.');
        }

        $headers = null;
        $rows = [];
        $checksums = [];
        foreach ($inputs as $input) {
            if (! Storage::disk($disk)->exists($input)) {
                throw new InvalidArgumentException('Staff packet ['.$input.'] was not found.');
            }
            $payload = Storage::disk($disk)->get($input);
            [$currentHeaders, $currentRows] = $this->readCsv($payload);
            $headers ??= $currentHeaders;
            if ($headers !== $currentHeaders) {
                throw new InvalidArgumentException('Staff packets must have identical headers.');
            }
            $rows = [...$rows, ...$currentRows];
            $checksums[$input] = hash('sha256', $payload);
        }

        $idCounts = [];
        foreach ($rows as $row) {
            $idCounts[$row['source_id']] = ($idCounts[$row['source_id']] ?? 0) + 1;
        }
        $approved = [];
        $rejected = [];
        $serviceCounts = [];
        $rejectionCounts = [];
        foreach ($rows as $row) {
            $reasons = [];
            $service = ctype_digit($row['service_type']) ? (int) $row['service_type'] : 0;
            $validContext = $central
                ? $service >= 1 && $service <= 2 && $row['candidate_target_module'] === 'councils' && $row['candidate_faculty_slug'] === ''
                : $service >= 3 && $service <= 14 && $row['candidate_target_module'] === 'faculty_members' && $row['candidate_faculty_slug'] !== '';
            if ($row['source_table'] !== 'jx_councils' || ! $validContext) {
                $reasons[] = $central ? 'non_central_council_context' : 'non_faculty_context';
            }
            if ($row['blockers'] !== '') {
                $packetBlockers = array_filter(explode('|', $row['blockers']));
                if ($central) {
                    $packetBlockers = array_values(array_diff($packetBlockers, ['central_council_requires_separate_target']));
                }
                $reasons = [...$reasons, ...$packetBlockers];
            }
            if (($idCounts[$row['source_id']] ?? 0) > 1) {
                $reasons[] = 'duplicate_source_id';
            }
            $reasons = array_values(array_unique($reasons));
            if ($reasons !== []) {
                foreach ($reasons as $reason) {
                    $rejectionCounts[$reason] = ($rejectionCounts[$reason] ?? 0) + 1;
                }
                $row['safe_review_reasons'] = implode('|', $reasons);
                $rejected[] = $row;

                continue;
            }
            $row['approval_decision'] = 'import';
            $row['approved_target'] = $central ? 'council_members' : 'faculty_members';
            $row['approved_by'] = $approvedBy;
            $row['approval_basis'] = $central
                ? 'visible_unique_central_council_no_packet_blockers_disabled_review_record'
                : 'visible_unique_faculty_scoped_no_packet_blockers_disabled_draft';
            $approved[] = $row;
            $serviceCounts[$service] = ($serviceCounts[$service] ?? 0) + 1;
        }
        ksort($serviceCounts);
        ksort($rejectionCounts);
        $directory = trim($directory, '/') ?: 'legacy-import-exports/public-staff-approval-packets';
        $base = $this->uniqueBase($directory, $disk);
        $prefix = $central ? 'central_councils' : 'staff';
        $paths = [$base.'/approved_'.$prefix.'.csv', $base.'/rejected_'.$prefix.'.csv', $base.'/manifest.json'];
        Storage::disk($disk)->put($paths[0], $this->csv([...$headers, 'approved_by', 'approval_basis'], $approved));
        Storage::disk($disk)->put($paths[1], $this->csv([...$headers, 'safe_review_reasons'], $rejected));
        Storage::disk($disk)->put($paths[2], (string) json_encode([
            'generated_at' => now()->toIso8601String(), 'private_evidence' => true, 'writes_content' => false,
            'approved_by' => $approvedBy, 'source_packet_sha256' => $checksums,
            'criteria' => $central
                ? 'services 1-2; central councils target; only expected separate-target marker ignored; disabled review import only'
                : 'services 3-14; faculty target; no packet blockers; disabled draft import only',
            'summary' => ['scanned_rows' => count($rows), 'approved_rows' => count($approved), 'rejected_rows' => count($rejected), 'service_counts' => $serviceCounts, 'rejection_counts' => $rejectionCounts],
            'paths' => $paths,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return new LegacyPublicStaffApprovalPacketResultDTO(count($rows), count($approved), count($rejected), $serviceCounts, $rejectionCounts, $paths);
    }

    /** @return array{list<string>, list<array<string, string>>} */
    private function readCsv(string $payload): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $payload);
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            throw new InvalidArgumentException('Staff packet is empty.');
        }
        $headers = array_map(static fn ($value): string => trim((string) $value), $headers);
        $missing = array_diff(self::REQUIRED, $headers);
        if ($missing !== []) {
            throw new InvalidArgumentException('Invalid staff packet headers. Missing: '.implode(', ', $missing));
        }
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [] || $values === [null]) {
                continue;
            }
            if (count($values) !== count($headers)) {
                throw new InvalidArgumentException('Malformed staff packet row.');
            }
            $rows[] = array_combine($headers, array_map(static fn ($value): string => trim((string) $value), $values));
        }
        fclose($stream);

        return [$headers, $rows];
    }

    /** @param list<string> $headers @param list<array<string, string>> $rows */
    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): string => $row[$header] ?? '', $headers));
        }
        rewind($stream);
        $payload = stream_get_contents($stream);
        fclose($stream);

        return is_string($payload) ? $payload : '';
    }

    private function uniqueBase(string $directory, string $disk): string
    {
        $base = $directory.'/'.now()->format('Ymd_His').'_'.Str::lower(Str::random(8));
        $candidate = $base;
        $suffix = 1;
        while (Storage::disk($disk)->exists($candidate.'/manifest.json')) {
            $candidate = $base.'_'.sprintf('%02d', $suffix++);
        }

        return $candidate;
    }
}
