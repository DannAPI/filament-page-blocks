<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;
}
