<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Adds the files waiting in a folder of the knowledge disk to the AI
 * assistant, exactly as Administration › AI assistant › Upload does
 * (ChatbotKnowledgeServiceInterface::addUpload: same reading, scans, cards,
 * duplicate check and audit log), for when no one can sign in to upload them.
 * Each file handled moves to <dir>/done; a file that failed stays for another
 * run. Without --write it only lists what it would add.
 */
final class ChatbotImportDocumentsCommand extends Command
{
    protected $signature = 'chatbot:import-documents
        {--dir=chatbot/import : Folder on the knowledge disk holding the files}
        {--user= : Id of the admin user the upload is recorded as}
        {--write : Add the files (without it nothing is stored)}';

    protected $description = 'Add the files waiting in a folder to the AI assistant\'s knowledge, as an admin upload would.';

    public function __construct(
        private readonly ChatbotKnowledgeServiceInterface $knowledge,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $user = (string) $this->option('user');
        if (! ctype_digit($user) || (int) $user < 1) {
            $this->error('Pass the admin user id with --user=<id>.');

            return self::INVALID;
        }

        $dir = trim((string) $this->option('dir'), '/');
        $disk = Storage::disk((string) config('chatbot.knowledge.disk', 'local'));
        $files = array_values(array_filter(
            $disk->files($dir),
            static fn (string $path): bool => ! str_starts_with(basename($path), '.'),
        ));

        if ($files === []) {
            $this->line('No files are waiting in '.$dir.'.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('write')) {
            foreach ($files as $path) {
                $this->line('Would add: '.basename($path).' ('.number_format((int) $disk->size($path)).' bytes)');
            }
            $this->line('Dry run: nothing was added. Re-run with --write.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($files as $path) {
            $name = basename($path);
            $staged = 'chatbot/uploads/'.Str::random(32).'.'.strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $disk->copy($path, $staged);

            try {
                $document = $this->knowledge->addUpload($staged, $name, null, (int) $user);
                $this->line(sprintf('ADDED: %s — %s, %d passage(s)%s', $document->title, $document->status, $document->chunkCount, $document->error !== null ? ' ('.$document->error.')' : ''));
            } catch (InvalidArgumentException $exception) {
                $disk->delete($staged);
                if ($exception->getMessage() !== 'admin_chatbot.errors.duplicate') {
                    $this->error('FAILED: '.$name.' — '.__($exception->getMessage()));
                    $failed++;

                    continue;
                }
                $this->line('SKIPPED: '.$name.' is already in the knowledge.');
            } catch (Throwable $exception) {
                $disk->delete($staged);
                report($exception);
                $this->error('FAILED: '.$name.' — '.$exception->getMessage());
                $failed++;

                continue;
            }

            $disk->move($path, $dir.'/done/'.$name);
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
