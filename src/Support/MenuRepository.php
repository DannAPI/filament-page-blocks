<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Models\Menu;

final class MenuRepository
{
    public function find(?string $handle): ?Menu
    {
        if ($handle === null || $handle === '') {
            return null;
        }

        /** @var class-string<Menu> $model */
        $model = config('filament-page-blocks.models.menu', Menu::class);

        return $model::query()
            ->where('handle', $handle)
            ->with(['items.page', 'items.children.page'])
            ->first();
    }
}
