<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\DTOs\Chatbot\ChatbotDocumentDTO;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeOpenAi;

trait ChatbotTestHelpers
{
    protected FakeOpenAi $openAi;

    protected function setUpChatbot(): void
    {
        Storage::fake('local');
        Cache::flush();
        $this->openAi = FakeOpenAi::install();
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $extra
     */
    protected function adminUser(array $roles = ['super_admin'], array $extra = []): User
    {
        $user = User::factory()->create([
            'role_slug' => in_array('super_admin', $roles, true) ? 'super_admin' : ($roles[0] ?? null),
            'is_locked' => false,
        ]);
        $user->roles()->sync(Role::query()->whereIn('slug', $roles)->pluck('id')->all());
        foreach ($extra as $permission) {
            $user->extraPermissions()->create(['permission' => $permission]);
        }
        app(AdminAccessServiceInterface::class)->flushPermissions();

        return $user;
    }

    protected function saveApiKey(User $user, string $key = 'sk-test-1234567890abcdefghijklmnop'): void
    {
        $service = app(ChatbotSettingsServiceInterface::class);
        $service->save($service->get(), $key, false, (int) $user->id);
    }

    protected function addKnowledge(User $user, string $title, string $text): ChatbotDocumentDTO
    {
        return app(ChatbotKnowledgeServiceInterface::class)->addText($title, $text, (int) $user->id);
    }

    protected function liveAssistant(): User
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->addKnowledge($user, 'Admission guide 2026', "# Admission office hours\nThe admission office is open Sunday to Thursday from 9 to 3.\n\n# Medicine minimum\nMedicine requires a secondary school total of at least 230.");
        $this->addKnowledge($user, 'Tuition fees', "# Pharmacy tuition\nThe annual tuition of the Faculty of Pharmacy is 4,500,000 Syrian pounds, paid per semester.");

        return $user;
    }
}
