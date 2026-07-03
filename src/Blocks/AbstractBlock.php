<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Blocks;

use DannAPI\FilamentPageBlocks\Blocks\Concerns\InteractsWithBlockFields;
use DannAPI\FilamentPageBlocks\Contracts\BlockContract;
use DannAPI\FilamentPageBlocks\Models\Page;
use Filament\Forms\Components\Field;

abstract class AbstractBlock implements BlockContract
{
    use InteractsWithBlockFields;

    public static function getIcon(): string
    {
        return 'heroicon-o-square-3-stack-3d';
    }

    public static function defaults(): array
    {
        $defaults = [];
        foreach (static::form() as $component) {
            if ($component instanceof Field) {
                $defaults[$component->getName()] = $component->getDefaultState();
            }
        }

        return $defaults;
    }

    public static function normalize(array $data): array
    {
        $defaults = static::defaults();

        return $defaults === []
            ? $data
            : array_replace($defaults, array_intersect_key($data, $defaults));
    }

    public static function summary(array $data): string
    {
        return trim(class_basename(static::class)) ?: static::getLabel();
    }

    public static function authorize(?Page $page = null): bool
    {
        return true;
    }

    public static function isReusable(): bool
    {
        return false;
    }
}
