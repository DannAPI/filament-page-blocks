<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(self::cases(), 'name', 'value');
    }
}
