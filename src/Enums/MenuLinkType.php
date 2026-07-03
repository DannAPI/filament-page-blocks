<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Enums;

enum MenuLinkType: string
{
    case Page = 'page';
    case Custom = 'custom';
    case Admin = 'admin';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Page->value => 'Page',
            self::Custom->value => 'Custom URL',
            self::Admin->value => 'Admin section',
        ];
    }
}
