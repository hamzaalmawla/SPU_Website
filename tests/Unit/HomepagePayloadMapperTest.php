<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\ArticleCardDTO;
use App\DTOs\ContactLinkDTO;
use App\DTOs\EventCardDTO;
use App\DTOs\FooterColumnDTO;
use App\DTOs\HomepageFeatureItemDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageStatItemDTO;
use App\DTOs\NavigationActionDTO;
use App\DTOs\ResearchCardDTO;
use App\DTOs\SocialLinkDTO;
use App\Support\HomepagePayloadMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\PropertyTestHelpers;

/**
 * Property-based test for HomepagePayloadMapper round-trip serialization.
 *
 * **Validates: Requirements 10.1, 19.3**
 *
 * Property 8: Refactored Method Output Equivalence (Round-Trip)
 * For any valid HomepageSectionDataDTO, serializing via sectionDataToArray()
 * and then deserializing back via sectionDataFromArray() SHALL produce an
 * equivalent DTO structure.
 */
#[Group('property')]
class HomepagePayloadMapperTest extends TestCase
{
    use PropertyTestHelpers;

    // ──────────────────────────────────────────────
    //  Property 8 — Round-Trip Equivalence
    // ──────────────────────────────────────────────

    /**
     * @param HomepageSectionDataDTO $original
     */
    #[Test]
    #[DataProvider('randomSectionDataProvider')]
    public function section_data_round_trip_preserves_structure(HomepageSectionDataDTO $original): void
    {
        $serialized = HomepagePayloadMapper::sectionDataToArray($original);
        $deserialized = HomepagePayloadMapper::sectionDataFromArray($serialized);

        // Scalar string fields — semantic equivalence (null vs absent are equivalent)
        $this->assertSame($original->title, $deserialized->title, 'title mismatch');
        $this->assertSame($original->subtitle, $deserialized->subtitle, 'subtitle mismatch');
        $this->assertSame($original->videoUrl, $deserialized->videoUrl, 'videoUrl mismatch');
        $this->assertSame($original->imageUrl, $deserialized->imageUrl, 'imageUrl mismatch');
        $this->assertSame($original->backgroundImageUrl, $deserialized->backgroundImageUrl, 'backgroundImageUrl mismatch');
        $this->assertSame($original->eyebrow, $deserialized->eyebrow, 'eyebrow mismatch');
        $this->assertSame($original->badge, $deserialized->badge, 'badge mismatch');
        $this->assertSame($original->summary, $deserialized->summary, 'summary mismatch');
        $this->assertSame($original->body, $deserialized->body, 'body mismatch');

        // Collection counts
        $this->assertCount(count($original->stats), $deserialized->stats, 'stats count mismatch');
        $this->assertCount(count($original->featuredItems), $deserialized->featuredItems, 'featuredItems count mismatch');
        $this->assertCount(count($original->articles), $deserialized->articles, 'articles count mismatch');
        $this->assertCount(count($original->events), $deserialized->events, 'events count mismatch');
        $this->assertCount(count($original->researchItems), $deserialized->researchItems, 'researchItems count mismatch');
        $this->assertCount(count($original->footerColumns), $deserialized->footerColumns, 'footerColumns count mismatch');
        $this->assertCount(count($original->contactLinks), $deserialized->contactLinks, 'contactLinks count mismatch');
        $this->assertCount(count($original->socialLinks), $deserialized->socialLinks, 'socialLinks count mismatch');

        // Navigation actions — structural equivalence
        $this->assertActionEquivalent($original->primaryAction, $deserialized->primaryAction, 'primaryAction');
        $this->assertActionEquivalent($original->secondaryAction, $deserialized->secondaryAction, 'secondaryAction');
        $this->assertActionEquivalent($original->sectionAction, $deserialized->sectionAction, 'sectionAction');

        // Verify individual stat items preserve key fields
        foreach ($original->stats as $i => $stat) {
            $this->assertSame($stat->value, $deserialized->stats[$i]->value, "stats[$i].value mismatch");
            $this->assertSame($stat->label, $deserialized->stats[$i]->label, "stats[$i].label mismatch");
        }

        // Verify individual featured items preserve key fields
        foreach ($original->featuredItems as $i => $item) {
            $this->assertSame($item->title, $deserialized->featuredItems[$i]->title, "featuredItems[$i].title mismatch");
        }

        // Verify individual articles preserve key fields
        foreach ($original->articles as $i => $article) {
            $this->assertSame($article->id, $deserialized->articles[$i]->id, "articles[$i].id mismatch");
            $this->assertSame($article->title, $deserialized->articles[$i]->title, "articles[$i].title mismatch");
            $this->assertSame($article->slug, $deserialized->articles[$i]->slug, "articles[$i].slug mismatch");
        }

        // Verify individual events preserve key fields
        foreach ($original->events as $i => $event) {
            $this->assertSame($event->id, $deserialized->events[$i]->id, "events[$i].id mismatch");
            $this->assertSame($event->title, $deserialized->events[$i]->title, "events[$i].title mismatch");
            $this->assertSame($event->slug, $deserialized->events[$i]->slug, "events[$i].slug mismatch");
        }

        // Verify footer columns preserve structure
        foreach ($original->footerColumns as $i => $column) {
            $this->assertSame($column->title, $deserialized->footerColumns[$i]->title, "footerColumns[$i].title mismatch");
            $this->assertCount(count($column->links), $deserialized->footerColumns[$i]->links, "footerColumns[$i].links count mismatch");
        }
    }

    // ──────────────────────────────────────────────
    //  Data Provider — 100 random iterations
    // ──────────────────────────────────────────────

    /**
     * @return iterable<string, array{HomepageSectionDataDTO}>
     */
    public static function randomSectionDataProvider(): iterable
    {
        for ($i = 0; $i < 100; $i++) {
            yield "iteration-{$i}" => [self::generateRandomSectionData()];
        }
    }

    // ──────────────────────────────────────────────
    //  Assertion helpers
    // ──────────────────────────────────────────────

    private function assertActionEquivalent(?NavigationActionDTO $expected, ?NavigationActionDTO $actual, string $context): void
    {
        if ($expected === null) {
            $this->assertNull($actual, "{$context} should be null");

            return;
        }

        $this->assertNotNull($actual, "{$context} should not be null");
        $this->assertSame($expected->label, $actual->label, "{$context}.label mismatch");
        $this->assertSame($expected->url, $actual->url, "{$context}.url mismatch");
        $this->assertSame($expected->target, $actual->target, "{$context}.target mismatch");
    }

    // ──────────────────────────────────────────────
    //  Random generators
    // ──────────────────────────────────────────────

    private static function generateRandomSectionData(): HomepageSectionDataDTO
    {
        return new HomepageSectionDataDTO(
            eyebrow: self::nullableString(),
            subtitle: self::nullableString(),
            badge: self::nullableString(),
            title: self::nullableString(),
            summary: self::nullableString(),
            body: self::nullableString(),
            videoUrl: self::nullableUrl(),
            imageUrl: self::nullableUrl(),
            backgroundImageUrl: self::nullableUrl(),
            primaryAction: self::randomNullableAction(),
            secondaryAction: self::randomNullableAction(),
            sectionAction: self::randomNullableAction(),
            stats: self::randomStatItems(),
            featuredItems: self::randomFeatureItems(),
            articles: self::randomArticleCards(),
            researchItems: self::randomResearchCards(),
            events: self::randomEventCards(),
            footerColumns: self::randomFooterColumns(),
            contactLinks: self::randomContactLinks(),
            socialLinks: self::randomSocialLinks(),
            items: [],
            content: self::randomContentArray(),
        );
    }

    /**
     * Generate a non-empty string or null. Never returns empty string,
     * since the mapper normalises empty strings to null.
     */
    private static function nullableString(): ?string
    {
        if (random_int(0, 3) === 0) {
            return null;
        }

        return self::randomSentence();
    }

    /**
     * Generate a non-empty URL string or null.
     */
    private static function nullableUrl(): ?string
    {
        if (random_int(0, 2) === 0) {
            return null;
        }

        return 'https://example.com/' . self::randomSlugSegment() . '/' . self::randomSlugSegment();
    }

    private static function randomNullableAction(): ?NavigationActionDTO
    {
        if (random_int(0, 2) === 0) {
            return null;
        }

        return new NavigationActionDTO(
            label: self::randomSentence(),
            url: 'https://example.com/' . self::randomSlugSegment(),
            target: random_int(0, 1) === 0 ? '_blank' : null,
        );
    }

    /**
     * @return array<int, HomepageStatItemDTO>
     */
    private static function randomStatItems(): array
    {
        $count = random_int(0, 4);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = new HomepageStatItemDTO(
                value: (string) random_int(1, 9999),
                label: self::randomSentence(),
                description: self::nullableString(),
                icon: random_int(0, 2) === 0 ? 'icon-' . self::randomSlugSegment() : null,
                prefix: random_int(0, 3) === 0 ? '+' : null,
                suffix: random_int(0, 3) === 0 ? '%' : null,
                helperText: self::nullableString(),
                url: self::nullableUrl(),
                sortOrder: random_int(0, 2) === 0 ? random_int(0, 100) : null,
            );
        }

        return $items;
    }

    /**
     * @return array<int, HomepageFeatureItemDTO>
     */
    private static function randomFeatureItems(): array
    {
        $count = random_int(0, 4);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = new HomepageFeatureItemDTO(
                title: self::randomSentence(),
                summary: self::nullableString(),
                imageUrl: self::nullableUrl(),
                url: self::nullableUrl(),
                tags: self::randomTags(),
            );
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private static function randomTags(): array
    {
        $count = random_int(0, 3);
        $tags = [];

        for ($i = 0; $i < $count; $i++) {
            $tags[] = self::randomSlugSegment();
        }

        return $tags;
    }

    /**
     * @return array<int, ArticleCardDTO>
     */
    private static function randomArticleCards(): array
    {
        $count = random_int(0, 3);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = new ArticleCardDTO(
                id: random_int(1, 9999),
                locale: self::randomLocale(),
                title: self::randomSentence(),
                slug: self::randomSlugSegment(),
                excerpt: self::nullableString(),
                imageUrl: self::nullableUrl(),
                publishedAt: self::nullableDate(),
                url: self::nullableUrl(),
                categoryLabel: self::nullableString(),
                badgeTag: self::nullableString(),
            );
        }

        return $items;
    }

    /**
     * @return array<int, ResearchCardDTO>
     */
    private static function randomResearchCards(): array
    {
        $count = random_int(0, 3);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = new ResearchCardDTO(
                id: random_int(1, 9999),
                locale: self::randomLocale(),
                title: self::randomSentence(),
                slug: self::randomSlugSegment(),
                summary: self::nullableString(),
                imageUrl: self::nullableUrl(),
                publishedAt: self::nullableDate(),
                url: self::nullableUrl(),
                categoryLabel: self::nullableString(),
                authors: self::randomAuthors(),
            );
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private static function randomAuthors(): array
    {
        $count = random_int(0, 3);
        $authors = [];

        for ($i = 0; $i < $count; $i++) {
            $authors[] = 'Dr. ' . self::randomSlugSegment();
        }

        return $authors;
    }

    /**
     * @return array<int, EventCardDTO>
     */
    private static function randomEventCards(): array
    {
        $count = random_int(0, 3);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = new EventCardDTO(
                id: random_int(1, 9999),
                locale: self::randomLocale(),
                title: self::randomSentence(),
                slug: self::randomSlugSegment(),
                summary: self::nullableString(),
                startsAt: self::nullableDate(),
                endsAt: self::nullableDate(),
                location: self::nullableString(),
                url: self::nullableUrl(),
                imageUrl: self::nullableUrl(),
                timeLabel: self::nullableString(),
            );
        }

        return $items;
    }

    /**
     * @return array<int, FooterColumnDTO>
     */
    private static function randomFooterColumns(): array
    {
        $count = random_int(0, 3);
        $columns = [];

        for ($i = 0; $i < $count; $i++) {
            $linkCount = random_int(1, 4);
            $links = [];

            for ($j = 0; $j < $linkCount; $j++) {
                $links[] = new NavigationActionDTO(
                    label: self::randomSentence(),
                    url: 'https://example.com/' . self::randomSlugSegment(),
                    target: random_int(0, 2) === 0 ? '_blank' : null,
                );
            }

            $columns[] = new FooterColumnDTO(
                title: self::randomSentence(),
                links: $links,
            );
        }

        return $columns;
    }

    /**
     * @return array<int, ContactLinkDTO>
     */
    private static function randomContactLinks(): array
    {
        $count = random_int(0, 3);
        $items = [];
        $types = ['phone', 'email', 'text', 'fax'];

        for ($i = 0; $i < $count; $i++) {
            $type = $types[random_int(0, count($types) - 1)];
            $value = match ($type) {
                'phone', 'fax' => '+963-' . random_int(100000000, 999999999),
                'email' => self::randomSlugSegment() . '@example.com',
                default => self::randomSentence(),
            };

            $items[] = new ContactLinkDTO(
                type: $type,
                label: self::randomSentence(),
                value: $value,
            );
        }

        return $items;
    }

    /**
     * @return array<int, SocialLinkDTO>
     */
    private static function randomSocialLinks(): array
    {
        $count = random_int(0, 3);
        $items = [];
        $platforms = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube'];

        for ($i = 0; $i < $count; $i++) {
            $items[] = new SocialLinkDTO(
                platform: $platforms[random_int(0, count($platforms) - 1)],
                url: 'https://' . $platforms[random_int(0, count($platforms) - 1)] . '.com/' . self::randomSlugSegment(),
                isEnabled: random_int(0, 4) > 0,
            );
        }

        return $items;
    }

    private static function nullableDate(): ?string
    {
        if (random_int(0, 2) === 0) {
            return null;
        }

        $year = random_int(2020, 2025);
        $month = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);

        return "{$year}-{$month}-{$day}";
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomContentArray(): array
    {
        if (random_int(0, 2) === 0) {
            return [];
        }

        return [
            'type' => 'custom',
            'version' => (string) random_int(1, 5),
        ];
    }
}
