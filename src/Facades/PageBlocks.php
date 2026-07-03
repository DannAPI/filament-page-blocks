<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Facades;

use DannAPI\FilamentPageBlocks\PageBlocksManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \DannAPI\FilamentPageBlocks\PageBlocksManager register(iterable $blocks)
 * @method static \DannAPI\FilamentPageBlocks\PageBlocksManager permissions(string $group, array $permissions)
 * @method static \DannAPI\FilamentPageBlocks\PageBlocksManager templates(iterable $templates)
 */
final class PageBlocks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PageBlocksManager::class;
    }
}
