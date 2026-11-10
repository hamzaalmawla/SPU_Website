<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AcademicRank;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AcademicRankTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string, 2: string, 3: ?string, 4: ?string}> */
    public static function typed(): array
    {
        return [
            'professor' => ['أستاذ', 'Professor', 'ar', 'أستاذ', null],
            'feminine associate' => ['أستاذة مساعدة', null, 'en', 'Associate Professor', null],
            'lecturer from English' => [null, 'Lecturer', 'ar', 'مدرس', null],
            'title before the rank' => ['د. أستاذ مساعد', null, 'ar', 'أستاذ مساعد', null],
            'dean is a position' => ['عميد كلية الطب', 'Dean', 'ar', null, 'عميد كلية الطب'],
            'head is a position' => ['رئيس قسم الجراحة', null, 'ar', null, 'رئيس قسم الجراحة'],
            'vice dean is a position' => ['نائب العميد', 'Vice Dean', 'en', null, 'نائب العميد'],
            'teaching assistant shows as typed' => ['مدرس مساعد', null, 'ar', 'مدرس مساعد', null],
            'nothing' => [null, null, 'ar', null, null],
        ];
    }

    #[DataProvider('typed')]
    public function test_a_typed_rank_is_read_apart_from_a_position(?string $typed, ?string $other, string $locale, ?string $rank, ?string $position): void
    {
        $this->assertSame(['rank' => $rank, 'position' => $position], AcademicRank::read($typed, $other, $locale));
    }

    public function test_a_profile_is_read_from_its_translations(): void
    {
        $translations = [(object) ['locale' => 'ar', 'position' => 'أستاذ'], (object) ['locale' => 'en', 'position' => null]];

        $this->assertSame('Professor', AcademicRank::ofProfile($translations, 'en')['rank']);
        $this->assertSame('أستاذ', AcademicRank::ofProfile($translations, 'ar')['rank']);
    }
}
