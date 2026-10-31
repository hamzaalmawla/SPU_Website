<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyCareerLinkImportServiceInterface;
use App\Contracts\Legacy\LegacyCareerLinkReviewPacketServiceInterface;
use App\Contracts\Legacy\LegacyFaqImportServiceInterface;
use App\Contracts\Legacy\LegacyFaqReviewPacketServiceInterface;
use App\DTOs\Legacy\LegacyCareerLinkImportResultDTO;
use App\DTOs\Legacy\LegacyCareerLinkReviewPacketResultDTO;
use App\DTOs\Legacy\LegacyFaqImportResultDTO;
use App\DTOs\Legacy\LegacyFaqReviewPacketResultDTO;
use App\Services\Legacy\LegacyPhaseSixRestoreService;
use Tests\TestCase;

final class LegacyFaqCareerCommandsTest extends TestCase
{
    public function test_phase_six_restore_has_no_faq_or_career_pipeline_dependency(): void
    {
        $types = collect((new \ReflectionClass(LegacyPhaseSixRestoreService::class))->getConstructor()?->getParameters() ?? [])
            ->map(static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType())->all();

        $this->assertNotContains(LegacyFaqImportServiceInterface::class, $types);
        $this->assertNotContains(LegacyCareerLinkImportServiceInterface::class, $types);
    }

    public function test_packet_commands_delegate_and_render_json(): void
    {
        $faq = $this->createMock(LegacyFaqReviewPacketServiceInterface::class);
        $faq->expects($this->once())->method('export')->with('local', 'faq-out')->willReturn(new LegacyFaqReviewPacketResultDTO('local', 8, 5, 3, [], [], ['faq.csv'], []));
        $this->app->instance(LegacyFaqReviewPacketServiceInterface::class, $faq);
        $career = $this->createMock(LegacyCareerLinkReviewPacketServiceInterface::class);
        $career->expects($this->once())->method('export')->with('local', 'career-out')->willReturn(new LegacyCareerLinkReviewPacketResultDTO('local', 3, 3, [], ['career.csv'], []));
        $this->app->instance(LegacyCareerLinkReviewPacketServiceInterface::class, $career);

        $this->artisan('legacy-import:faq-review-packets', ['--dir' => 'faq-out', '--json' => true])->expectsOutputToContain('"candidateRows": 5')->assertSuccessful();
        $this->artisan('legacy-import:career-link-review-packets', ['--dir' => 'career-out', '--json' => true])->expectsOutputToContain('"totalRows": 3')->assertSuccessful();
    }

    public function test_import_commands_delegate_all_safety_options(): void
    {
        $faq = $this->createMock(LegacyFaqImportServiceInterface::class);
        $faq->expects($this->once())->method('import')->with('faq.csv', 'local', true, 'legacy-faq-import', 'faq-batch')
            ->willReturn(new LegacyFaqImportResultDTO(true, 'faq-batch', 1, 1, 1, 1, 0, []));
        $this->app->instance(LegacyFaqImportServiceInterface::class, $faq);
        $career = $this->createMock(LegacyCareerLinkImportServiceInterface::class);
        $career->expects($this->once())->method('import')->with('career.csv', 'local', true, 'legacy-career-links-import', 'career-batch')
            ->willReturn(new LegacyCareerLinkImportResultDTO(true, 'career-batch', 1, 1, 1, 1, 0, []));
        $this->app->instance(LegacyCareerLinkImportServiceInterface::class, $career);

        $this->artisan('legacy-import:faqs', ['input' => 'faq.csv', '--write' => true, '--approve' => 'legacy-faq-import', '--batch' => 'faq-batch'])->assertSuccessful();
        $this->artisan('legacy-import:career-links', ['input' => 'career.csv', '--write' => true, '--approve' => 'legacy-career-links-import', '--batch' => 'career-batch'])->assertSuccessful();
    }
}
