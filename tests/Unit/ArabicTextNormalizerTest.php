<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SearchTextNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Arabic text normalization, the highest-risk correctness surface in search.
 *
 * If this folding is wrong, search is quietly wrong: a visitor types the name
 * the way it is normally written and gets nothing back, with no error to
 * explain why. Every case below is a real way Arabic gets typed or stored.
 */
final class ArabicTextNormalizerTest extends TestCase
{
    public function test_it_strips_tashkeel_so_undiacritized_queries_match_diacritized_text(): void
    {
        // "أَحْمَد" as it might be stored, versus "احمد" as it is typed.
        $this->assertSame('احمد', SearchTextNormalizer::normalize('أَحْمَد'));
        $this->assertSame('احمد', SearchTextNormalizer::normalize('احمد'));

        // A fully vocalized phrase folds to its bare consonantal skeleton.
        $this->assertSame(
            'الجامعه السوريه الخاصه',
            SearchTextNormalizer::normalize('الجَامِعَةُ السُّورِيَّةُ الخَاصَّةُ'),
        );
    }

    public function test_it_strips_every_documented_harakat_codepoint(): void
    {
        foreach (["\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}", "\u{0650}", "\u{0651}", "\u{0652}", "\u{0670}"] as $harakat) {
            $this->assertSame(
                'كتاب',
                SearchTextNormalizer::normalize('ك'.$harakat.'تاب'),
                'Failed to strip U+'.strtoupper(dechex(mb_ord($harakat))),
            );
        }
    }

    public function test_it_strips_tatweel(): void
    {
        // Kashida is decorative elongation and carries no meaning.
        $this->assertSame('محمد', SearchTextNormalizer::normalize('محـــمـــد'));
    }

    public function test_it_folds_every_hamza_and_orthographic_variant(): void
    {
        $this->assertSame('اسم', SearchTextNormalizer::normalize('أسم'));
        $this->assertSame('اسم', SearchTextNormalizer::normalize('إسم'));
        $this->assertSame('اسم', SearchTextNormalizer::normalize('آسم'));
        $this->assertSame('اسم', SearchTextNormalizer::normalize("\u{0671}سم"));

        // ى -> ي : "مستشفى" is routinely typed "مستشفي".
        $this->assertSame('مستشفي', SearchTextNormalizer::normalize('مستشفى'));

        // ة -> ه : "كلية" versus "كليه".
        $this->assertSame('كليه', SearchTextNormalizer::normalize('كلية'));

        // ؤ -> و and ئ -> ي.
        $this->assertSame('مسوول', SearchTextNormalizer::normalize('مسؤول'));
        $this->assertSame('ريis', SearchTextNormalizer::normalize('رئis'));
    }

    public function test_hamza_variants_of_the_same_word_all_fold_together(): void
    {
        $spellings = ['إدارة', 'ادارة', 'أدارة', 'إدارۃ'];
        $normalized = array_map(
            static fn (string $spelling): string => SearchTextNormalizer::normalize($spelling),
            array_slice($spellings, 0, 3),
        );

        $this->assertSame(['اداره'], array_values(array_unique($normalized)));
    }

    public function test_it_normalizes_arabic_indic_digits(): void
    {
        $this->assertSame('2026', SearchTextNormalizer::normalize('٢٠٢٦'));
        $this->assertSame('دفعه 2025', SearchTextNormalizer::normalize('دفعة ٢٠٢٥'));
    }

    public function test_it_lowercases_latin_text_so_english_search_is_case_insensitive(): void
    {
        $this->assertSame('syrian private university', SearchTextNormalizer::normalize('Syrian Private University'));
    }

    public function test_it_collapses_whitespace_and_trims(): void
    {
        $this->assertSame('احمد علي', SearchTextNormalizer::normalize("  أحمد \n\t  علي  "));
    }

    public function test_it_leaves_already_normalized_text_untouched(): void
    {
        $normalized = SearchTextNormalizer::normalize('كليه الطب البشري 2026');

        $this->assertSame($normalized, SearchTextNormalizer::normalize($normalized), 'Normalization must be idempotent');
    }

    public function test_terms_splits_a_query_longest_first(): void
    {
        $this->assertSame(['الجامعه', 'كليه', 'طب'], SearchTextNormalizer::terms('كلية طب الجامعة'));
    }

    public function test_terms_deduplicates_and_ignores_empty_input(): void
    {
        $this->assertSame(['كليه'], SearchTextNormalizer::terms('كلية كليه  كلية'));
        $this->assertSame([], SearchTextNormalizer::terms('   '));
        $this->assertSame([], SearchTextNormalizer::terms(''));
    }

    public function test_plain_text_strips_markup_entities_and_scripts(): void
    {
        $html = '<p>كلية&nbsp;الطب</p><script>alert("x")</script><style>.a{color:red}</style><a href="/x">الرابط</a>';

        $plain = SearchTextNormalizer::plainText($html);

        $this->assertStringNotContainsString('alert', $plain);
        $this->assertStringNotContainsString('color:red', $plain);
        $this->assertStringNotContainsString('<', $plain);
        $this->assertSame('كلية الطب الرابط', $plain);
    }

    public function test_plain_text_inserts_a_separator_at_block_boundaries(): void
    {
        // Without this, "<p>الطب</p><p>البشري</p>" would index as one fused word.
        $this->assertSame('الطب البشري', SearchTextNormalizer::plainText('<p>الطب</p><p>البشري</p>'));
    }

    public function test_escape_like_neutralizes_wildcards_and_the_escape_character(): void
    {
        $this->assertSame('!%', SearchTextNormalizer::escapeLike('%'));
        $this->assertSame('!_', SearchTextNormalizer::escapeLike('_'));
        $this->assertSame('!!', SearchTextNormalizer::escapeLike('!'));
        $this->assertSame('100!% !_ok!!', SearchTextNormalizer::escapeLike('100% _ok!'));
    }

    public function test_offsets_map_folded_positions_back_onto_the_original_characters(): void
    {
        // This is what lets a match found in folded space be highlighted in the
        // original text: "أَحْمَد" folds to "احمد", losing two characters.
        $original = 'أَحْمَد';
        $folded = SearchTextNormalizer::normalizeWithOffsets($original);

        $this->assertSame('احمد', $folded['normalized']);
        $this->assertCount(mb_strlen($folded['normalized']), $folded['offsets']);

        // The folded 'ح' at index 1 came from the original character at index 2.
        $this->assertSame('ح', $folded['characters'][$folded['offsets'][1]]);

        // Slicing the original by the mapped offsets returns the original
        // spelling, diacritics and all.
        $start = $folded['offsets'][0];
        $end = $folded['offsets'][mb_strlen($folded['normalized']) - 1];
        $slice = implode('', array_slice($folded['characters'], $start, $end - $start + 1));

        $this->assertSame($original, $slice);
    }

    public function test_offsets_stay_aligned_for_mixed_arabic_latin_and_digits(): void
    {
        $original = 'دفعة ٢٠٢٥ Medicine';
        $folded = SearchTextNormalizer::normalizeWithOffsets($original);

        $this->assertSame('دفعه 2025 medicine', $folded['normalized']);
        $this->assertCount(mb_strlen($folded['normalized']), $folded['offsets']);

        $index = mb_strpos($folded['normalized'], 'medicine');
        $this->assertIsInt($index);
        $this->assertSame('M', $folded['characters'][$folded['offsets'][$index]]);
    }

    public function test_normalize_with_offsets_preserves_whitespace_for_alignment(): void
    {
        // normalize() collapses whitespace; the offset-preserving variant must
        // not, or every offset after the first double space would be wrong.
        $folded = SearchTextNormalizer::normalizeWithOffsets('أ  ب');

        $this->assertSame('ا  ب', $folded['normalized']);
        $this->assertCount(4, $folded['offsets']);
    }

    public function test_replacement_table_never_maps_a_value_back_onto_a_key(): void
    {
        // The same table drives folding here and is relied on to be
        // order-independent; a value that is itself a key would break that.
        $replacements = SearchTextNormalizer::replacements();

        foreach ($replacements as $search => $replace) {
            $this->assertArrayNotHasKey(
                $replace,
                $replacements,
                'Replacement for U+'.strtoupper(dechex(mb_ord($search))).' is itself a folding key',
            );
        }
    }
}
