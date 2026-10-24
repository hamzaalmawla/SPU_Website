<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Faculty\ProjectFieldBlockParserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Body shapes taken from the imported legacy student projects on v2.spu.edu.sy.
 */
final class ProjectFieldBlockParserTest extends TestCase
{
    /**
     * @param  list<string>  $body
     * @param  list<string>  $team
     * @param  list<string>  $description
     */
    #[DataProvider('bodies')]
    public function test_it_separates_the_field_block_from_the_description(array $body, array $team, ?string $supervisor, ?string $year, array $description): void
    {
        $fields = app(ProjectFieldBlockParserInterface::class)->parse($body);

        self::assertSame($team, $fields->team);
        self::assertSame($supervisor, $fields->supervisor);
        self::assertSame($year, $fields->year);
        self::assertSame($description, $fields->description);
    }

    /** @return array<string, array{0: list<string>, 1: list<string>, 2: ?string, 3: ?string, 4: list<string>}> */
    public static function bodies(): array
    {
        return [
            'two names under a bare label' => [
                ['اعداد', 'Farah fares', 'Ahmad shekha', 'تاريخ', '2025-2026'],
                ['Farah fares', 'Ahmad shekha'], null, '2025-2026', [],
            ],
            'colon labels' => [
                ['اعداد:', 'ياسمين قاسم صوان', 'صبا اياد قداح', 'تاريخ:', '2025-2026'],
                ['ياسمين قاسم صوان', 'صبا اياد قداح'], null, '2025-2026', [],
            ],
            'names on one line separated by dashes' => [
                ['اعداد', 'رغد طارق الحلبي - رزان نبيل المكاكي', 'تاريخ', '2025-2026'],
                ['رغد طارق الحلبي', 'رزان نبيل المكاكي'], null, '2025-2026', [],
            ],
            'trailing dash on a name' => [
                ['اعداد:', 'Omar Alkhateeb -', 'Anas arman', 'تاريخ:', '2025-2026'],
                ['Omar Alkhateeb', 'Anas arman'], null, '2025-2026', [],
            ],
            'misspelled date label' => [
                ['اعداد', 'تالة عدنان ضيا', 'الناريخ', '2025-2026'],
                ['تالة عدنان ضيا'], null, '2025-2026', [],
            ],
            'academic year label and student label' => [
                ['إعداد الطالبة:', 'سهام يعقوب سلبد', 'العام الدراسي:', '2023-2024'],
                ['سهام يعقوب سلبد'], null, '2023-2024', [],
            ],
            'inline year without separator' => [
                ['اعداد:', 'Owais hilal', 'تاريخ:20252026'],
                ['Owais hilal'], null, '2025-2026', [],
            ],
            'year straight after names' => [
                ['اعداد:', 'Owais hilal', 'Sara Ahmad', '2024 - 2025'],
                ['Owais hilal', 'Sara Ahmad'], null, '2024-2025', [],
            ],
            'inline value' => [
                ['إعداد الطالبة: سارة أحمد', 'أنجز المشروع بإشراف الدكتورة ليلى.'],
                ['سارة أحمد'], null, null, ['أنجز المشروع بإشراف الدكتورة ليلى.'],
            ],
            'supervisor block then prose' => [
                ['مناقشة مشروع تخرج الطالبة رنيم الحلو', 'إشراف :', 'أ.د منير عباس', 'لجنة التحكيم :', 'أ.د أديب كولو'],
                [], 'أ.د منير عباس', null, ['مناقشة مشروع تخرج الطالبة رنيم الحلو', 'لجنة التحكيم :', 'أ.د أديب كولو'],
            ],
            'description after the field block' => [
                ['اعداد', 'Owais hilal', 'تاريخ', '2025-2026', 'نظام امتحانات ذكي يعتمد على الذكاء الاصطناعي.'],
                ['Owais hilal'], null, '2025-2026', ['نظام امتحانات ذكي يعتمد على الذكاء الاصطناعي.'],
            ],
            'sentences that open with a label word stay prose' => [
                ['الفريق قام بتطوير النظام', 'تاريخ الصيدلة يعود إلى قرون مضت.'],
                [], null, null, ['الفريق قام بتطوير النظام', 'تاريخ الصيدلة يعود إلى قرون مضت.'],
            ],
            'plain description' => [
                ['Abstract', 'In water industry, the water is stored in OHT.'],
                [], null, null, ['Abstract', 'In water industry, the water is stored in OHT.'],
            ],
        ];
    }

    public function test_it_splits_stored_team_strings(): void
    {
        $parser = app(ProjectFieldBlockParserInterface::class);

        self::assertSame(['A B', 'C D', 'E F'], $parser->splitNames('A B، C D - E F'));
        self::assertSame([], $parser->splitNames(null));
        self::assertSame(['Jean-Luc Picard'], $parser->splitNames('Jean-Luc Picard'));
    }
}
