<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Models\User\User;
use Illuminate\Console\Command;

/**
 * Publishes the research landing page from a reviewed payload file.
 *
 * The landing page is editorial chrome with no database equivalent, so
 * ResearchPageService keeps /research out of the navigation and redirects it to
 * the publications archive until someone publishes reviewed content. That gate
 * is deliberate. This command does not weaken it: it fills it, by the same
 * saveDraft -> readiness -> publish path the Filament page uses, recorded
 * against the account named on the command line.
 *
 * It exists because the alternative is retyping roughly forty fields across two
 * languages into a web form, which is its own kind of unreliable. The copy
 * itself lives in database/content/research-landing.json so it is reviewable as
 * a diff rather than buried in code.
 */
final class PublishResearchLandingCommand extends Command
{
    protected $signature = 'research:publish-landing
        {--user= : Email of the account the publish is recorded against}
        {--file= : Payload to publish (default database/content/research-landing.json)}
        {--publish : Actually publish. Without it the command only saves a draft and reports readiness}
        {--force : Replace an existing draft or published payload}';

    protected $description = 'Publish the research landing page from a reviewed payload file';

    public function handle(CmsWorkflowServiceInterface $workflow): int
    {
        $path = (string) ($this->option('file') ?: database_path('content/research-landing.json'));

        if (! is_file($path)) {
            $this->error("No payload at {$path}.");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload) || ! is_array($payload['translations'] ?? null)) {
            $this->error('The payload must be JSON with a "translations" object.');

            return self::FAILURE;
        }

        foreach (['ar', 'en'] as $locale) {
            if (! is_array($payload['translations'][$locale] ?? null) || $payload['translations'][$locale] === []) {
                // Worth failing loudly on: the publish check reads both
                // translations and returns nothing unless each one holds
                // content, so a half-filled payload publishes to no visible
                // effect in either language.
                $this->error("The \"{$locale}\" translation is missing or empty. Both languages are required.");

                return self::FAILURE;
            }
        }

        // An email, not an id: the publish is an audit record, and it should
        // name whoever ran this rather than whatever account happens to be
        // first in the table.
        $email = (string) $this->option('user');

        if ($email === '') {
            $this->error('Pass --user=<email>. The publish is recorded against that account.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->error("No account with the email {$email}.");

            return self::FAILURE;
        }

        $userId = (int) $user->getKey();
        $force = (bool) $this->option('force');

        if (! $force && is_array($workflow->getPublishedPayload('research.index'))) {
            $this->warn('research.index is already published. Re-run with --force to replace it.');

            return self::FAILURE;
        }

        if (! $force && $workflow->latestEditableDraftPayload('research.index', $userId) !== null) {
            $this->warn('A draft already exists and would be overwritten. Re-run with --force to replace it.');

            return self::FAILURE;
        }

        $workflow->saveDraft('research.index', $payload, $userId);

        $readiness = $workflow->readiness('research.index', $payload);

        if (! $readiness->isReady) {
            $this->error('The CMS will not publish this payload:');

            foreach ($readiness->errors as $scope => $messages) {
                foreach ((array) $messages as $message) {
                    $this->line("  {$scope}: {$message}");
                }
            }

            return self::FAILURE;
        }

        $this->info('Draft saved for research.index, and the CMS reports it ready to publish.');

        if (! $this->option('publish')) {
            // Deliberately the default. Someone trying the command out should
            // not discover it by finding the page live.
            $this->line('Nothing was published. Re-run with --publish to go live.');

            return self::SUCCESS;
        }

        if (! $workflow->publish('research.index', $userId)) {
            $this->error('The publish call failed.');

            return self::FAILURE;
        }

        $this->info("Published research.index as {$email}.");
        $this->line('/en/research and /ar/research now render the landing page instead of redirecting,');
        $this->line('the header Research link points at it, and both enter the sitemap.');

        return self::SUCCESS;
    }
}
