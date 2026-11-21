<?php

declare(strict_types=1);

namespace App\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Superseded = 'superseded';

    /**
     * @return array<int, string>
     */
    public static function editableValues(): array
    {
        return [self::Draft->value, self::Scheduled->value];
    }
}
