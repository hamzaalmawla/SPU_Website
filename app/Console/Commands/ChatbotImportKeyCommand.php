<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\OpenAiClientInterface;
use App\Exceptions\ChatbotProviderException;
use App\Models\User\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Stores the AI assistant's OpenAI key from a one-time file, encrypted, and
 * removes the file.
 *
 * Used by cpanel-deploy.sh when an operator has placed
 * storage/app/private/chatbot/openai-key.pending on the server, so the key
 * never travels through git, the deploy log or .env. The key is never
 * printed: only its last four characters.
 */
final class ChatbotImportKeyCommand extends Command
{
    protected $signature = 'chatbot:import-key
        {file : A file holding only the OpenAI API key; it is deleted afterwards}
        {--keep : Leave the file in place (for testing)}';

    protected $description = 'Save the AI assistant\'s OpenAI API key (encrypted) from a one-time file, then check that OpenAI accepts it.';

    public function __construct(
        private readonly ChatbotSettingsServiceInterface $settings,
        private readonly OpenAiClientInterface $openAi,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error('The key file does not exist or cannot be read.');

            return self::FAILURE;
        }

        $key = trim((string) file_get_contents($file));
        if (! (bool) $this->option('keep')) {
            // Overwrite before removing, so the key does not linger on disk.
            @file_put_contents($file, str_repeat("\0", max(1, strlen($key))));
            @unlink($file);
        }

        $actorId = (int) User::query()->where('role_slug', 'super_admin')->where('is_locked', false)->orderBy('id')->value('id');

        try {
            $this->settings->save($this->settings->get(), $key, false, $actorId);
        } catch (InvalidArgumentException $exception) {
            $this->error((string) __($exception->getMessage(), [], 'en'));

            return self::FAILURE;
        }

        $this->info('OpenAI key saved (encrypted), ending in …'.substr($key, -4).'. The key file was removed.');

        try {
            $this->openAi->moderate('Hello', $key);
            $this->info('OpenAI reachable from this server, and the key is accepted.');
        } catch (ChatbotProviderException $exception) {
            $this->warn('The key is saved, but the check failed: '.$exception->getMessage().' ('.$exception->reason.').');
        }

        return self::SUCCESS;
    }
}
