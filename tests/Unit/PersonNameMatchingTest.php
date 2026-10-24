<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The staff intake link suggests a teacher's profile while they type their
 * name. Every way a name is commonly typed differently must still find it.
 */
final class PersonNameMatchingTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function sameNames(): array
    {
        return [
            'hamza on alef' => ['أحمد علي', 'احمد علي'],
            'alef with madda' => ['آمنة حسن', 'امنه حسن'],
            'taa marbuta' => ['فاطمة الزهراء', 'فاطمه الزهراء'],
            'alef maqsura' => ['محمد على', 'محمد علي'],
            'diacritics' => ['مُحَمَّد عَلِي', 'محمد علي'],
            'tatweel' => ['محـمد علي', 'محمد علي'],
            'hamza on waw' => ['مؤمن حسن', 'مومن حسن'],
            'hamza on yaa' => ['هانئ سعيد', 'هاني سعيد'],
            'joined name' => ['عبد الله حسن', 'عبدالله حسن'],
            'case' => ['AHMAD ali', 'Ahmad Ali'],
            'title' => ['د. أحمد علي', 'أحمد علي'],
            'english title' => ['Dr. Ahmad Ali', 'Ahmad Ali'],
            'missing middle name' => ['Ayman Ali', 'Ayman Mohammad Ali'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function spellingVariants(): array
    {
        return [
            'ahmad ahmed' => ['Ahmad Ali', 'Ahmed Ali'],
            'mohammad muhammad' => ['Mohammad Khaled', 'Muhammad Khalid'],
            'mohamed' => ['Mohamed Hasan', 'Muhammad Hassan'],
            'abdullah abdallah' => ['Abdullah Hasan', 'Abdallah Hassan'],
            'yousef youssef' => ['Yousef Haddad', 'Youssef Hadad'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function partialTyping(): array
    {
        return [
            'first letters latin' => ['Ahm', 'Ahmad Ali'],
            'first letters arabic' => ['احم', 'أحمد علي'],
            'second word started' => ['Mohamed Kh', 'Muhammad Khaled'],
            'arabic article' => ['حسن', 'الحسن محمد'],
            'title typed' => ['Dr. Ahmed', 'Ahmad Ali'],
        ];
    }

    #[DataProvider('sameNames')]
    public function test_names_written_differently_are_the_same_person(string $typed, string $stored): void
    {
        $this->assertTrue(PersonName::same($typed, $stored));
        $this->assertTrue(PersonName::same($stored, $typed));
        $this->assertGreaterThanOrEqual(3, PersonName::matchRank($typed, $stored));
    }

    #[DataProvider('spellingVariants')]
    public function test_latin_spelling_variants_are_suggested(string $typed, string $stored): void
    {
        $this->assertSame(2, PersonName::matchRank($typed, $stored));
        $this->assertSame(2, PersonName::matchRank($stored, $typed));
    }

    #[DataProvider('partialTyping')]
    public function test_a_name_still_being_typed_is_suggested(string $typed, string $stored): void
    {
        $this->assertSame(1, PersonName::matchRank($typed, $stored));
    }

    public function test_unrelated_or_too_short_input_is_not_suggested(): void
    {
        $this->assertSame(0, PersonName::matchRank('Samer Khoury', 'Ahmad Ali'));
        $this->assertSame(0, PersonName::matchRank('xyz', 'Ahmad Ali'));
        $this->assertSame(0, PersonName::matchRank('Ah', 'Ahmad Ali'));
        $this->assertSame(0, PersonName::matchRank('Ahmad Samir', 'Ahmad Ali'));
    }

    public function test_an_exact_name_ranks_above_a_variant(): void
    {
        $this->assertSame(4, PersonName::matchRank('أحمد علي', 'احمد علي'));
        $this->assertGreaterThan(PersonName::matchRank('Ahmad Ali', 'Ahmed Ali'), PersonName::matchRank('Ahmad Ali', 'ahmad ali'));
    }
}
