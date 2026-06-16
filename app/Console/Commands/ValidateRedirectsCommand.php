<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Shared\ContinuityServiceInterface;
use App\DTOs\Shared\ValidationMessageDTO;
use App\Models\Legacy\LegacyExactRedirect;
use App\Models\Legacy\LegacyPatternRule;
use Illuminate\Console\Command;

final class ValidateRedirectsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'continuity:validate-redirects
        {--fix : Deactivate invalid or conflicting redirect rules}';

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

            return self::SUCCESS;
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

        return $result->isValid ? self::SUCCESS : self::FAILURE;
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
