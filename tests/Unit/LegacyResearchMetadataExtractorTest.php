<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyResearchMetadataExtractorInterface;
use Tests\TestCase;

final class LegacyResearchMetadataExtractorTest extends TestCase
{
    public function test_extracts_only_structurally_supported_publication_metadata(): void
    {
        $metadata = app(LegacyResearchMetadataExtractorInterface::class)->extract(
            '<p><strong>Authors</strong></p><p>Dr. A. Researcher and Prof. B. Scholar</p>'
            .'<p><strong>Abstract</strong></p><p>Evidence-based abstract.</p>'
            .'<p><strong>Published in</strong></p><p>SPU Medical Journal, 2021. https://doi.org/10.1234/SPU.2021.5</p>'
            .'<p><strong>Keywords</strong></p><p>medicine, evidence</p><p><strong>To Read the Article</strong></p>',
            'clinical research, university medicine',
            'A publication title',
        );

        $this->assertSame('Dr. A. Researcher and Prof. B. Scholar', $metadata->authors);
        $this->assertSame('Evidence-based abstract.', $metadata->abstract);
        $this->assertStringContainsString('SPU Medical Journal', (string) $metadata->citation);
        $this->assertSame('SPU Medical Journal', $metadata->publisher);
        $this->assertSame('10.1234/SPU.2021.5', $metadata->doi);
        $this->assertSame(2021, $metadata->publicationYear);
        $this->assertSame(['clinical research', 'university medicine'], $metadata->keywords);
    }

    public function test_does_not_invent_owner_rank_or_year_from_unstructured_abstract_prose(): void
    {
        $metadata = app(LegacyResearchMetadataExtractorInterface::class)->extract(
            '<p>This discussion cites studies from 2018 and mentions an unrelated author.</p>',
            null,
            'Unstructured paper',
        );

        $this->assertNull($metadata->authors);
        $this->assertNull($metadata->publicationYear);
        $this->assertNull($metadata->journalRank);
        $this->assertSame([], $metadata->keywords);
    }
}
