<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FacultyLogoSubtitle;
use PHPUnit\Framework\TestCase;

/**
 * The line under a faculty logo defaults to the English name until an editor
 * saves their own.
 */
final class FacultyLogoSubtitleTest extends TestCase
{
    public function test_it_prefixes_and_uppercases_the_english_name(): void
    {
        $this->assertSame('FACULTY OF CONSTRUCTION ENGINEERING', FacultyLogoSubtitle::fromEnglishName('Construction Engineering'));
        $this->assertSame('FACULTY OF PHARMACY', FacultyLogoSubtitle::fromEnglishName('Faculty of Pharmacy'));
    }

    public function test_an_empty_name_gives_no_line(): void
    {
        $this->assertSame('', FacultyLogoSubtitle::fromEnglishName('  '));
    }
}
