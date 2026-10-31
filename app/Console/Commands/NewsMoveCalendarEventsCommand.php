<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\News\NewsCalendarEventMoveServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class NewsMoveCalendarEventsCommand extends Command
{
    protected $signature = 'news:move-calendar-events
        {--user= : Id or email of the CMS user the articles are published as}
        {--write : Publish the articles (without it nothing is stored)}';

    protected $description = 'Move the events published in the Events page list into published Events articles.';

    public function __construct(
        private readonly NewsCalendarEventMoveServiceInterface $moveService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $user = $this->option('user');

        if (! is_string($user) || $user === '') {
            $this->error('Pass the CMS user with --user=<id or email>.');

            return self::INVALID;
        }

        try {
            $result = $this->moveService->move($user, (bool) $this->option('write'));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->line($result->written ? 'Moved:' : 'Would move:');
        $this->table(['Event', 'Title', 'Article', 'Sessions', 'Registrations'], array_map(fn (array $row): array => [
            $row['id'],
            $row['title'],
            $row['articleId'] !== null ? (string) $row['articleId'] : '-',
            (string) $row['sessions'],
            (string) $row['registrations'],
        ], $result->moved));

        if ($result->alreadyMoved !== []) {
            $this->line('Already moved: '.implode(', ', array_map(fn (array $row): string => $row['id'].' → article '.$row['articleId'], $result->alreadyMoved)));
        }

        if ($result->samples !== []) {
            $this->line('Sample events left out: '.implode(', ', $result->samples));
        }

        foreach ($result->failed as $row) {
            $this->warn('Not moved: '.$row['id'].' ('.$row['title'].'): '.implode(' ', $row['errors']));
        }

        if (! $result->written) {
            $this->line('Dry run: nothing was changed. Re-run with --write to publish the articles.');
        }

        return $result->failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
