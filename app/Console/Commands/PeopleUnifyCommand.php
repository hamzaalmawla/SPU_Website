<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Content\PeopleUnifyServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class PeopleUnifyCommand extends Command
{
    protected $signature = 'people:unify
        {--write : Write the changes (without it nothing is changed)}
        {--approve= : Required approval token for --write}';

    protected $description = 'Give every leadership row its one faculty profile; only fills the profile\'s empty fields.';

    public function __construct(
        private readonly PeopleUnifyServiceInterface $unifyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->unifyService->unify((bool) $this->option('write'), is_string($this->option('approve')) ? $this->option('approve') : null);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($result->written ? 'Unified.' : 'Dry run: nothing was changed. Re-run with --write --approve=<token>.');
        if ($result->snapshotPath !== null) {
            $this->line('Snapshot of every row touched: '.$result->snapshotPath);
        }
        foreach ($result->lines as $line) {
            $this->line('  '.$line);
        }
        foreach ($result->skipped as $line) {
            $this->line('  Left for an editor: '.$line);
        }

        $this->table(['', 'Count'], [
            ['Already tied to a profile', $result->alreadyUnified],
            ['Tied through the existing link', $result->linkedExisting],
            ['Tied to the one profile with the same name', $result->linkedByName],
            ['New profiles made from a leadership row', $result->profilesCreated],
            ['Empty profile fields filled', $result->fieldsFilled],
            ['Left for an editor', count($result->skipped)],
        ]);

        return self::SUCCESS;
    }
}
