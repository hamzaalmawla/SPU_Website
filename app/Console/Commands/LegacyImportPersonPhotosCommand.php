<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Media\LegacyPersonPhotoLibraryServiceInterface;
use Illuminate\Console\Command;

/**
 * Puts the photos imported with honor students, alumni, faculty members and
 * persons into the legacy media library, titled with each person's name, so the
 * media picker's legacy search finds a photo by who is in it.
 *
 * Dry run by default. Safe to re-run: existing assets only get empty titles
 * and alt text filled, and nothing in the main library is touched.
 */
final class LegacyImportPersonPhotosCommand extends Command
{
    protected $signature = 'legacy-import:person-photos
        {--root=/home/spuedu/public_html : Legacy document root the photos are read from}
        {--write : Persist changes; omit for a dry run}
        {--user=  : User id recorded as uploader and in the audit log}';

    protected $description = 'Register imported person photos as legacy media assets searchable by the person\'s name.';

    public function handle(LegacyPersonPhotoLibraryServiceInterface $service): int
    {
        $root = rtrim((string) $this->option('root'), '/');
        $write = (bool) $this->option('write');
        $user = $this->option('user');

        if (! is_dir($root)) {
            $this->error(sprintf('Legacy document root not found: %s', $root));

            return self::FAILURE;
        }

        if ($user !== null && $user !== '' && ! ctype_digit((string) $user)) {
            $this->error('--user must be a numeric user id.');

            return self::FAILURE;
        }

        $report = $service->register($root, $write, is_string($user) && $user !== '' ? (int) $user : null);

        $this->info($write ? 'Person photos registered in the legacy media library.' : 'Dry run — nothing was written. Re-run with --write to save.');

        foreach ($report->referencesBySource as $source => $count) {
            $this->line(sprintf('  %-16s %d photo reference(s)', $source, $count));
        }

        $this->line(sprintf('  distinct photos found   : %d', $report->photoCount));
        $this->line(sprintf('  %s : %d', $write ? 'assets created         ' : 'assets to create       ', $report->createdCount));
        $this->line(sprintf('  %s : %d', $write ? 'names filled in        ' : 'names to fill in       ', $report->updatedCount));
        $this->line(sprintf('  already named          : %d', $report->unchangedCount));
        $this->line(sprintf('  already in main library: %d', $report->inMainLibraryCount));
        $this->line(sprintf('  not an image           : %d', $report->notImageCount));
        $this->line(sprintf('  file not found         : %d', count($report->missingFiles)));

        foreach (array_slice($report->missingFiles, 0, 10) as $value) {
            $this->line('    '.$value);
        }

        return self::SUCCESS;
    }
}
