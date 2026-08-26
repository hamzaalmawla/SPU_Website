<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Research\LegacyResearchFileReference;
use Illuminate\Console\Command;

/**
 * Repairs the stored path on legacy research attachment references.
 *
 * jx_member_items stored attachments as a bare filename, with the old CMS
 * prefixing the download directory at render time. legacy_path is consumed as a
 * path, and ResearchPageService::publicationDownloads() renders these references
 * directly — so a bare filename becomes a download link pointing at "/<file>" on
 * the web root, which 404s. That is a broken download on a live publication page,
 * not a missing one.
 *
 * Every path is verified against the legacy document root before it is written.
 * A reference whose file cannot be found is deleted rather than left in place:
 * offering a download that does not exist is worse than offering none.
 */
final class RepairLegacyResearchFilePathsCommand extends Command
{
    protected $signature = 'legacy-import:repair-research-file-paths
        {--root=/home/spuedu/public_html : Legacy document root to verify against}
        {--write : Persist changes; omit for a dry run}
        {--prune : Delete references whose file cannot be found}';

    protected $description = 'Resolve legacy research attachment paths against the legacy document root.';

    /** Approved legacy static trees, in search order. */
    private const DIRECTORIES = [
        'downloads/files',
        'downloads/files/thumb',
        'downloads/files2',
        'images',
    ];

    public function handle(): int
    {
        $root = rtrim((string) $this->option('root'), '/');
        $write = (bool) $this->option('write');
        $prune = (bool) $this->option('prune');

        if (! is_dir($root)) {
            $this->error(sprintf('Legacy document root not found: %s', $root));

            return self::FAILURE;
        }

        $resolved = 0;
        $already = 0;
        $missing = 0;
        $pruned = 0;
        $byDirectory = [];

        LegacyResearchFileReference::query()
            ->orderBy('id')
            ->chunkById(500, function ($references) use ($root, $write, $prune, &$resolved, &$already, &$missing, &$pruned, &$byDirectory): void {
                foreach ($references as $reference) {
                    $value = trim(str_replace('\\', '/', (string) $reference->legacy_path));

                    if ($value === '') {
                        continue;
                    }

                    if (str_contains($value, '/')) {
                        $already++;

                        continue;
                    }

                    $found = $this->locate($root, $value);

                    if ($found === null) {
                        $missing++;

                        if ($prune && $write) {
                            $reference->delete();
                            $pruned++;
                        }

                        continue;
                    }

                    if ($write) {
                        $reference->forceFill(['legacy_path' => $found, 'status' => 'resolved'])->save();
                    }

                    $byDirectory[dirname($found)] = ($byDirectory[dirname($found)] ?? 0) + 1;
                    $resolved++;
                }
            });

        $this->info($write ? 'Legacy research file paths repaired.' : 'Dry run — nothing was written.');
        $this->line(sprintf('  resolved to a real file : %d', $resolved));
        $this->line(sprintf('  already had a directory : %d', $already));
        $this->line(sprintf('  file not found          : %d%s', $missing, $prune ? sprintf(' (pruned %d)', $pruned) : ' (use --prune to remove)'));

        foreach ($byDirectory as $directory => $count) {
            $this->line(sprintf('    %s: %d', $directory, $count));
        }

        return self::SUCCESS;
    }

    /** Locate a bare filename, retrying case-insensitively for a case-sensitive filesystem. */
    private function locate(string $root, string $filename): ?string
    {
        foreach (self::DIRECTORIES as $directory) {
            if (is_file($root.'/'.$directory.'/'.$filename)) {
                return $directory.'/'.$filename;
            }
        }

        foreach (self::DIRECTORIES as $directory) {
            foreach ([mb_strtolower($filename), mb_strtoupper($filename)] as $candidate) {
                if (is_file($root.'/'.$directory.'/'.$candidate)) {
                    return $directory.'/'.$candidate;
                }
            }
        }

        return null;
    }
}
