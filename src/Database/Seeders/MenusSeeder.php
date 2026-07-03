<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Database\Seeders;

use DannAPI\FilamentPageBlocks\Models\Menu;
use Illuminate\Database\Seeder;

final class MenusSeeder extends Seeder
{
    public function run(): void
    {
        /** @var class-string<Menu> $model */
        $model = config('filament-page-blocks.models.menu', Menu::class);

        foreach ((array) config('filament-page-blocks.seeders.menus', []) as $handle => $name) {
            if (! is_string($handle) || $handle === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $model::query()->firstOrCreate(['handle' => $handle], ['name' => $name]);
        }
    }
}
