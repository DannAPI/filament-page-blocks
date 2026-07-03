<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource\Pages;

use DannAPI\FilamentPageBlocks\Filament\Resources\MenuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
