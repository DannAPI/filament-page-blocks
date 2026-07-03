<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Database\Seeders;

use Illuminate\Database\Seeder;

final class FilamentPageBlocksSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GeneralInfoSeeder::class);
        $this->call(DemoUsersSeeder::class);
        $this->call(RolesSeeder::class);
        $this->call(MenusSeeder::class);
    }
}
