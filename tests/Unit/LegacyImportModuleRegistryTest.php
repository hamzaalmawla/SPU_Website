<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyImportModuleRegistryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyImportModuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    private LegacyImportModuleRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(LegacyImportModuleRegistryInterface::class);
    }

    public function test_links_is_registered_as_first_candidate_but_not_approved(): void
    {
        $definition = $this->registry->find('links');

        $this->assertNotNull($definition);
        $this->assertSame('links', $definition->module);
        $this->assertSame('candidate_not_approved', $definition->approvalStatus);
        $this->assertFalse($definition->approvedForRealRun);
        $this->assertFalse($this->registry->canExecute('links'));
    }

    public function test_homepage_has_no_controlled_runner_yet(): void
    {
        $this->assertNull($this->registry->find('homepage'));
        $this->assertFalse($this->registry->canExecute('homepage'));
        $this->assertSame('No controlled legacy import runner is registered for this module.', $this->registry->blockedReason('homepage'));
    }

    public function test_registry_lists_registered_candidate_definitions(): void
    {
        $definitions = $this->registry->all();

        $this->assertSame(['links'], $definitions->pluck('module')->all());
    }
}
