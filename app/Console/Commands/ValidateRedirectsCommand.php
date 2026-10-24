<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Shared\ContinuityServiceInterface;
use App\DTOs\Shared\ValidationMessageDTO;
use App\Models\Legacy\LegacyExactRedirect;
use App\Models\Legacy\LegacyPatternRule;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

final class ValidateRedirectsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'continuity:validate-redirects
        {--fix : Deactivate invalid or conflicting redirect rules}
        {--probe : Also request every active destination and report any that does not answer 200}';

    /**
     * @var string
     */
    protected $description = 'Detect and report invalid, duplicate, or conflicting redirect rules';

    public function __construct(
        private readonly ContinuityServiceInterface $continuityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Validating redirect rules...');

        $result = $this->continuityService->validateRedirectRules();

        if ($result->isValid) {
            $this->info('All redirect rules are valid. No issues found.');

            return $this->option('probe') ? $this->probeDestinations() : self::SUCCESS;
        }

        $this->warn('Redirect rule issues detected:');
        $this->newLine();

        $tableRows = [];

        foreach ($result->errors as $error) {
            foreach ($error->messages as $message) {
                $tableRows[] = [
                    'field' => $error->field,
                    'message' => $message,
                ];
            }
        }

        $this->table(['Source', 'Issue'], $tableRows);
        $this->newLine();
        $this->line('Total issues: '.count($tableRows));

        if ($this->option('fix')) {
            $this->newLine();
            $this->info('Applying fixes...');
            $fixed = $this->applyFixes($result->errors);
            $this->info("Deactivated {$fixed} invalid/conflicting rules.");
        }

        if ($this->option('probe')) {
            $this->probeDestinations();
        }

        return self::FAILURE;
    }

    /**
     * Request every active app-relative destination and report anything that is
     * not a direct 200.
     *
     * Rule validation alone cannot catch the failure the maintenance guide cares
     * about most: a rule that is perfectly well-formed but points at a URL that
     * no longer answers. That turns a 404 into a wrong answer, which is harder
     * to spot and worse for search than an honest 404. Running this before
     * cutover surfaces those rather than discovering them from live traffic.
     *
     * Destinations that redirect again are reported too — every rule is supposed
     * to land in one hop.
     */
    private function probeDestinations(): int
    {
        $this->newLine();
        $this->info('Probing redirect destinations...');

        /** @var array<int, string> $destinations */
        $destinations = LegacyExactRedirect::query()
            ->where('is_active', true)
            ->pluck('destination_url')
            ->map(static fn (mixed $url): string => (string) $url)
            // Absolute URLs point off this application; only in-app paths can be
            // answered by the local router.
            ->filter(static fn (string $url): bool => str_starts_with($url, '/'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($destinations === []) {
            $this->line('No app-relative destinations to probe.');

            return self::SUCCESS;
        }

        $failures = [];
        $multiHop = [];

        foreach ($destinations as $destination) {
            $status = $this->statusFor($destination);

            if ($status >= 200 && $status < 300) {
                continue;
            }

            if ($status >= 300 && $status < 400) {
                $multiHop[] = [$destination, (string) $status];

                continue;
            }

            $failures[] = [$destination, (string) $status];
        }

        if ($multiHop !== []) {
            $this->newLine();
            $this->warn('Destinations that redirect again (should land in one hop):');
            $this->table(['Destination', 'Status'], $multiHop);
        }

        if ($failures !== []) {
            $this->newLine();
            $this->error('Destinations that do not answer:');
            $this->table(['Destination', 'Status'], $failures);
            $this->line(sprintf('%d of %d destinations are broken.', count($failures), count($destinations)));

            return self::FAILURE;
        }

        $this->info(sprintf('All %d destinations answered 200.', count($destinations)));

        return self::SUCCESS;
    }

    /**
     * Resolve one destination through this application's own router.
     */
    private function statusFor(string $destination): int
    {
        try {
            $response = app()->handle(Request::create($destination, 'GET'));

            return $response->getStatusCode();
        } catch (\Throwable) {
            return 500;
        }
    }

    /**
     * Deactivate rules identified as problematic.
     *
     * @param  array<int, ValidationMessageDTO>  $errors
     */
    private function applyFixes(array $errors): int
    {
        $deactivated = 0;

        foreach ($errors as $error) {
            foreach ($error->messages as $message) {
                // Extract rule IDs from messages like "... rules: 1, 2, 3"
                if (preg_match('/rules?:\s*([\d,\s]+)/', $message, $matches)) {
                    $ids = array_map('intval', array_filter(explode(',', $matches[1])));

                    // Keep the first rule, deactivate duplicates
                    $idsToDeactivate = array_slice($ids, 1);

                    if ($error->field === 'legacy_exact_redirects') {
                        $deactivated += LegacyExactRedirect::query()
                            ->whereIn('id', $idsToDeactivate)
                            ->update(['is_active' => false]);
                    }
                }

                // Extract single rule ID from messages like "... (rule 5)"
                if (preg_match('/\(rule\s+(\d+)\)/', $message, $matches)) {
                    $ruleId = (int) $matches[1];

                    if ($error->field === 'legacy_exact_redirects') {
                        $deactivated += LegacyExactRedirect::query()
                            ->whereKey($ruleId)
                            ->update(['is_active' => false]);
                    }

                    if ($error->field === 'legacy_pattern_rules') {
                        $deactivated += LegacyPatternRule::query()
                            ->where('id', $ruleId)
                            ->update(['is_active' => false]);
                    }
                }
            }
        }

        return $deactivated;
    }
}
