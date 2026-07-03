<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Database\Seeders;

use DannAPI\FilamentPageBlocks\Models\GeneralInfo;
use Illuminate\Database\Seeder;

final class GeneralInfoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('filament-page-blocks.seeders.general_info.enabled', true)) {
            return;
        }

        /** @var class-string<GeneralInfo> $model */
        $model = config('filament-page-blocks.models.general_info', GeneralInfo::class);
        $model::singletonOrCreate([
            'data' => (array) config('filament-page-blocks.seeders.general_info.data', []),
            'images' => (array) config('filament-page-blocks.seeders.general_info.images', []),
            'rich_text' => (array) config('filament-page-blocks.seeders.general_info.rich_text', []),
        ]);
    }
}
