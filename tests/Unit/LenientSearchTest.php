<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LenientSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LenientSearchTest extends TestCase
{
    /** @return array<string, array{0: string, 1: list<string>, 2: list<string>}> */
    public static function matchingSearches(): array
    {
        return [
            'words in any order' => ['ali ayman', [], ['Asst. Prof. Ayman Fahd Ali']],
            'case and punctuation' => ['DR. AYMAN', [], ['Dr Ayman Ali']],
            'arabic letter variants' => ['احمد', [], ['أَحْمَد علي']],
            'arabic article' => ['الصيدلة', ['كلية صيدلة'], []],
            'article with conjunction' => ['والهندسة', ['كلية الهندسة'], []],
            'typo in a name' => ['pharmcy', [], ['Faculty of Pharmacy']],
            'typo in an arabic name' => ['الصيدلانيه', [], ['الصيدلاني']],
            'latin spelling variant' => ['mohamed ahmed', [], ['Muhammad Ahmad Khaled']],
            'unfinished word' => ['pharmac', [], ['Pharmaceutical Chemistry']],
            'either language' => ['dentistry', ['كلية طب الأسنان', 'Faculty of Dentistry'], []],
            'arabic-indic digits' => ['٢٠٢٤', ['Project 2024'], []],
            'title alone is ignored' => ['د.', ['د. ليلى'], []],
        ];
    }

    /** @param list<string> $texts @param list<string> $names */
    #[DataProvider('matchingSearches')]
    public function test_it_finds_what_a_reader_would(string $query, array $texts, array $names): void
    {
        $this->assertTrue(LenientSearch::matches($query, $texts, $names));
    }

    public function test_every_word_has_to_be_found(): void
    {
        $this->assertFalse(LenientSearch::matches('ayman zaid', [], ['Ayman Ali']));
        $this->assertFalse(LenientSearch::matches('chemistry', ['Faculty of Pharmacy'], []));
    }

    public function test_near_spellings_are_accepted_only_in_names_and_titles(): void
    {
        $this->assertFalse(LenientSearch::matches('pharmcy', ['Faculty of Pharmacy'], []));
        $this->assertTrue(LenientSearch::matches('pharmcy', [], ['Faculty of Pharmacy']));
    }

    public function test_short_words_must_match_as_typed(): void
    {
        $this->assertFalse(LenientSearch::matches('ali', [], ['Ola Saleh']));
        $this->assertFalse(LenientSearch::matches('عمر', [], ['عمار']));
        $this->assertFalse(LenientSearch::matches('البهاق', [], ['الاستخدامات غير المصرح بها للادوية']));
    }

    public function test_an_empty_query_matches_everything(): void
    {
        $this->assertTrue(LenientSearch::matches('  ', ['anything'], []));
        $this->assertSame([], LenientSearch::terms(' , '));
    }
}
