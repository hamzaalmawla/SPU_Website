<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cms\CmsTargetContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The command's whole value is telling apart three states that look identical
 * on the public site: nothing published, something published that holds no
 * items, and real content. If it cannot separate the middle case from the
 * last, it reports a site as ready that renders empty.
 *
 * Assertions read the --json output rather than matching strings, because the
 * words "published" and "empty" also appear in the per-locale detail: a naive
 * substring match passes on a row whose overall state is the opposite.
 */
final class CmsContentStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $ar
     * @param  array<string, mixed>  $en
     */
    private function publish(string $targetKey, array $ar, array $en): void
    {
        CmsTargetContent::create([
            'target_key' => $targetKey,
            'payload_json' => ['translations' => ['ar' => $ar, 'en' => $en]],
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    private function report(array $options = []): array
    {
        Artisan::call('cms:content-status', $options + ['--area' => 'news', '--json' => true]);

        $decoded = json_decode(trim(Artisan::output()), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function stateOf(array $rows, string $key): ?string
    {
        foreach ($rows as $row) {
            if (($row['key'] ?? null) === $key) {
                return $row['state'] ?? null;
            }
        }

        return null;
    }

    #[Test]
    public function a_section_with_nothing_published_is_reported_as_missing(): void
    {
        $this->assertSame('missing', $this->stateOf($this->report(), 'news.gallery'));
    }

    #[Test]
    public function a_published_but_empty_payload_is_not_reported_as_content(): void
    {
        // The shape the research section actually shipped in: a real published
        // row, structurally valid, holding nothing a visitor would see.
        $this->publish('news.gallery', ['items' => []], ['items' => []]);

        $this->assertSame('empty', $this->stateOf($this->report(), 'news.gallery'));
    }

    #[Test]
    public function a_published_payload_holding_items_counts_as_content(): void
    {
        $items = ['items' => [['id' => 'a'], ['id' => 'b'], ['id' => 'c']]];
        $this->publish('news.gallery', $items, $items);

        $this->assertSame('published', $this->stateOf($this->report(), 'news.gallery'));
    }

    #[Test]
    public function it_counts_items_nested_one_level_under_a_section_wrapper(): void
    {
        // Several payloads group their records under a wrapper rather than at
        // the top level; missing those would report populated pages as empty.
        $nested = ['gallery' => ['items' => [['id' => 'a'], ['id' => 'b']]]];
        $this->publish('news.gallery', $nested, $nested);

        $this->assertSame('published', $this->stateOf($this->report(), 'news.gallery'));
    }

    #[Test]
    public function labels_alone_do_not_make_a_section_look_populated(): void
    {
        // Every normaliser fills in titles and button labels, so a payload of
        // nothing but chrome must still read as empty - otherwise every
        // unpublished section reports healthy.
        $chrome = ['title' => 'Media Gallery', 'headline' => 'Media Gallery', 'emptyLabel' => 'Nothing here'];
        $this->publish('news.gallery', $chrome, $chrome);

        $this->assertSame('empty', $this->stateOf($this->report(), 'news.gallery'));
    }

    #[Test]
    public function a_section_published_in_only_one_locale_is_not_reported_as_complete(): void
    {
        // A half-translated section is a launch defect, not a success: the
        // other locale renders an empty page.
        $this->publish('news.gallery', ['items' => [['id' => 'a']]], ['items' => []]);

        $this->assertSame('empty', $this->stateOf($this->report(), 'news.gallery'));
    }

    #[Test]
    public function the_empty_filter_hides_sections_that_are_already_done(): void
    {
        $items = ['items' => [['id' => 'a']]];
        $this->publish('news.events', $items, $items);

        $rows = $this->report(['--empty' => true]);

        $this->assertNull($this->stateOf($rows, 'news.events'));
        $this->assertSame('missing', $this->stateOf($rows, 'news.gallery'));
    }

    #[Test]
    public function it_stays_advisory_unless_asked_to_fail(): void
    {
        // Publishing is SPU's decision, so an unpublished section must not turn
        // a deploy red by default.
        $this->artisan('cms:content-status', ['--area' => 'news'])->assertSuccessful();

        $this->artisan('cms:content-status', ['--area' => 'news', '--fail-on-empty' => true])
            ->assertFailed();
    }

    #[Test]
    public function an_unknown_area_is_an_error_rather_than_an_empty_success(): void
    {
        // Silently reporting "all clear" for a typo'd area is the one failure
        // mode that would make this command actively misleading.
        $this->artisan('cms:content-status', ['--area' => 'no-such-area'])->assertFailed();
    }
}
