<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\News\LegacyCalendarEventImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class NewsImportLegacyCalendarEventsCommand extends Command
{
    protected $signature = 'news:import-legacy-calendar-events
        {--file= : Path to the events JSON (default: database/seeders/Data/legacy-calendar-events.json)}
        {--user= : Id or email of the CMS user the change is saved as}
        {--write : Save and publish (without it nothing is stored)}';

    protected $description = 'Add the old homepage calendar events to the Events page as past events.';

    public function __construct(
        private readonly LegacyCalendarEventImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) ($this->option('file') ?: database_path('seeders/Data/legacy-calendar-events.json'));
        $user = $this->option('user');

        if (! is_string($user) || $user === '') {
            $this->error('Pass the CMS user with --user=<id or email>.');

            return self::INVALID;
        }

        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::INVALID;
        }

        try {
            $source = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $result = $this->importService->import(is_array($source) ? $source : [], $user, (bool) $this->option('write'));
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->line('To add: '.($result->added === [] ? 'none' : implode(', ', $result->added)));
        $this->line('Already on the Events page: '.($result->present === [] ? 'none' : implode(', ', $result->present)));

        $this->line(match (true) {
            $result->published => 'Saved and published.',
            $result->written && $result->hadPendingDraft => 'Added to the open Events draft; publish it from News > Events.',
            $result->written => 'Saved as a draft, but publishing failed; publish it from News > Events.',
            $result->added === [] => 'Nothing to do.',
            default => 'Dry run: re-run with --write to save and publish.',
        });

        return self::SUCCESS;
    }
}
