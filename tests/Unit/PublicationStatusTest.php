<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PublicationStatus;
use Tests\TestCase;

final class PublicationStatusTest extends TestCase
{
    public function test_editable_values_include_only_draft_and_scheduled(): void
    {
        $this->assertSame(
            [PublicationStatus::Draft->value, PublicationStatus::Scheduled->value],
            PublicationStatus::editableValues(),
        );
    }
}
