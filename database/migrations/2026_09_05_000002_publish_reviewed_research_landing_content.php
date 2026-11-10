<?php

declare(strict_types=1);

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Models\User\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('content/research-landing.json');
        if (! is_file($path)) {
            Log::warning('Migration: research-landing.json not found at '.$path);

            return;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! is_array($payload['translations'] ?? null)) {
            Log::warning('Migration: invalid payload in research-landing.json');

            return;
        }

        // Resolve an authentic, authorized user for the publish audit trail
        $user = User::query()
            ->where('is_locked', false)
            ->where(function ($query): void {
                $query->where('email', 'admin@spu.edu.sy')
                    ->orWhere('role_slug', 'super_admin')
                    ->orWhere('role_slug', 'editor');
            })
            ->orderByRaw("CASE WHEN email = 'admin@spu.edu.sy' THEN 0 WHEN role_slug = 'super_admin' THEN 1 ELSE 2 END")
            ->first() ?? User::query()->where('is_locked', false)->first();

        if (! $user instanceof User) {
            Log::warning('Migration: No valid user found to attribute research landing publish to.');

            return;
        }

        $userId = (int) $user->getKey();
        $workflow = app(CmsWorkflowServiceInterface::class);

        $workflow->saveDraft('research.index', $payload, $userId);
        $readiness = $workflow->readiness('research.index', $payload);

        if ($readiness->isReady) {
            $workflow->publish('research.index', $userId);
            Log::info("Migration: Successfully published research.index attributed to user {$user->email} (ID: {$userId}).");
        } else {
            Log::error('Migration: Research landing payload failed CMS readiness check.', [
                'errors' => $readiness->errors,
            ]);
        }
    }

    public function down(): void
    {
        // Safe no-op: publishing status remains intact.
    }
};
